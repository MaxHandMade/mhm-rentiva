<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Payment\Refunds\RefundValidator;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayDouble;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayRegistration;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Slice 3 task 6, fix round 1 (I-2, I-4).
 *
 * RefundOperationMultiOrderTest's fixture (WooCommerceFixtures::create_paid_order_for_booking())
 * never sets a payment method on the orders it creates, so
 * RefundValidator::modeForOrder() answers MODE_MANUAL for every leg those
 * tests ever exercise and Service::runOperation()'s
 * `refund_payment => RefundValidator::MODE_AUTO === $mode` branch never runs
 * with a true operand there. The old hardcoded `refund_payment => true` even
 * failed for exactly that reason -- "The payment gateway for this order does
 * not exist" -- proof that the fixture alone cannot accidentally exercise the
 * auto path.
 *
 * These tests register a real WC_Payment_Gateway double
 * (WooCommerceRefundGatewayDouble) that supports refunds, wire it onto an
 * order's payment_method, and observe wc_create_refund()'s actual $args via
 * the woocommerce_create_refund action -- the same hook wc_create_refund()
 * itself fires with the unmodified $args before the refund_payment branch
 * runs (wc-order-functions.php).
 *
 * runOperation() is exercised directly via reflection in the first two tests
 * because its `mode` field -- the operation-level auto/manual verdict -- is
 * not part of Service::process()'s public return shape; Tasks 7-10 read it
 * from the array this method returns, and that array is the contract under
 * test. The third test goes through the public Service::processFullRefund()
 * instead, because what it proves (I-4: a partial failure must name the
 * amount already refunded, not just say "failed") is a property of the
 * public-facing message, not of runOperation()'s internal shape.
 */
final class RefundOperationGatewayModeTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;
    use WooCommerceRefundGatewayRegistration;

    /** @var int */
    private $booking_id;

    /** @var int */
    private $admin_id;

    /** @var array<int, bool> */
    private $captured_refund_payment_flags = array();

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->admin_id   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');

        $this->captured_refund_payment_flags = array();
        add_action('woocommerce_create_refund', array( $this, 'capture_refund_payment_flag' ), 10, 2);
        $this->register_refund_gateway_double();
    }

    public function tearDown(): void
    {
        remove_action('woocommerce_create_refund', array( $this, 'capture_refund_payment_flag' ), 10);
        $this->unregister_refund_gateway_double();

        parent::tearDown();
    }

    public function capture_refund_payment_flag(\WC_Order_Refund $refund, array $args): void
    {
        $this->captured_refund_payment_flags[] = (bool) $args['refund_payment'];
    }

    /**
     * @return array{ok: bool, refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string}
     */
    private function run_operation(int $amount_kurus, string $reason): array
    {
        $method = new ReflectionMethod(Service::class, 'runOperation');
        $method->setAccessible(true);

        /** @var array{ok: bool, refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string} $operation */
        $operation = $method->invoke(null, $this->booking_id, $amount_kurus, $reason);

        return $operation;
    }

    public function test_an_auto_capable_gateway_leg_sends_refund_payment_true(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');
        $order->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $order->save();

        $operation = $this->run_operation(Money::toMinor('120'), 'auto leg check');

        $this->assertTrue($operation['ok'], $operation['message']);
        $this->assertSame(RefundValidator::MODE_AUTO, $operation['mode']);
        $this->assertSame(
            array( true ),
            $this->captured_refund_payment_flags,
            'wc_create_refund() must receive refund_payment => true for a gateway that supports and can refund.'
        );
    }

    public function test_a_mixed_auto_and_manual_operation_collapses_to_manual(): void
    {
        $auto_order = $this->create_paid_order_for_booking($this->booking_id, '30');
        $auto_order->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $auto_order->save();

        // No payment method: wc_get_payment_gateway_by_order() resolves to
        // false and modeForOrder() falls back to MODE_MANUAL, same as every
        // order WooCommerceFixtures creates on its own.
        $manual_order = $this->create_paid_order_for_booking($this->booking_id, '70');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $manual_order->get_id());

        $operation = $this->run_operation(Money::toMinor('100'), 'mixed legs');

        $this->assertTrue($operation['ok'], $operation['message']);
        $this->assertSame(
            RefundValidator::MODE_MANUAL,
            $operation['mode'],
            'One manual leg must collapse the whole operation to manual -- recording a refund for '
                . 'a card leg without moving the money is the failure this guards against.'
        );
        $this->assertSame(
            array( true, false ),
            $this->captured_refund_payment_flags,
            'The auto leg (the deposit, walked first) still sends true; only the operation-level mode collapses.'
        );
    }

    public function test_a_partial_failure_names_the_amount_already_refunded(): void
    {
        $first = $this->create_paid_order_for_booking($this->booking_id, '30');
        $first->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $first->save();

        $second = $this->create_paid_order_for_booking($this->booking_id, '70');
        $second->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $second->save();
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $second->get_id());

        // Leg 1 (the 30 deposit) succeeds; leg 2 (the 70 remaining order)
        // fails at the gateway -- money already left the account for leg 1
        // by the time leg 2's failure reaches Service::finish().
        WooCommerceRefundGatewayDouble::$results = array( true, false );

        $result = Service::processFullRefund($this->booking_id, 'i4 partial failure check', $this->admin_id);

        $this->assertSame('0', $result['mhmrentiva_refund']);

        $expected_amount = CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('30')),
            Money::decimals(),
            PaymentState::forBooking($this->booking_id)->currency()
        );

        $this->assertStringContainsString(
            $expected_amount,
            (string) $result['mhmrentiva_refund_msg'],
            'A partial failure must name the amount that already moved, not just say the refund failed.'
        );
        $this->assertStringContainsString(
            'An error occurred while attempting to create the refund using the payment gateway API.',
            (string) $result['mhmrentiva_refund_msg'],
            'The underlying WooCommerce error must survive alongside the amount, not be replaced by it.'
        );
    }
}
