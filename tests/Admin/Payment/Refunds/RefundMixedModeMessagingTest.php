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
 * Fable audit, H-2: "a mixed-mode refund tells the operator to hand-transfer
 * money the gateway already returned."
 *
 * Service::runOperation() decides refund_payment PER ORDER -- its own comment
 * explains why: a deposit paid by card and a remainder paid by transfer are
 * two different answers, and collapsing them to "manual" would record a
 * refund for the card without moving the money. One layer up, exactly that
 * collapse used to happen anyway: $allAuto flattened the whole operation to a
 * single mode, and RefundNotifications::notify() built its sentences from
 * that collapsed mode with the OPERATION TOTAL as the amount. The admin mail
 * said "the amount above must be transferred to the customer manually" naming
 * the total -- an operator who followed it over-refunded the customer by
 * whatever the gateway leg already returned.
 *
 * The fixture mirrors RefundOperationGatewayModeTest's idiom exactly: a
 * WooCommerceRefundGatewayDouble-backed order (auto-capable) plus a
 * payment-method-less order (falls back to MODE_MANUAL), wired as a
 * deposit-plus-remaining pair.
 */
final class RefundMixedModeMessagingTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;
    use WooCommerceRefundGatewayRegistration;

    private int $booking_id;
    private int $admin_id;

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
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');

        $this->register_refund_gateway_double();
    }

    public function tearDown(): void
    {
        $this->unregister_refund_gateway_double();

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

        parent::tearDown();
    }

    /**
     * The deposit (30) walked first, auto-capable; the remainder (70) walked
     * second, no payment method so modeForOrder() falls back to MODE_MANUAL --
     * same setup as RefundOperationGatewayModeTest::
     * test_a_mixed_auto_and_manual_operation_collapses_to_manual().
     */
    private function wire_mixed_legs(): void
    {
        $auto_order = $this->create_paid_order_for_booking($this->booking_id, '30');
        $auto_order->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $auto_order->save();

        $manual_order = $this->create_paid_order_for_booking($this->booking_id, '70');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $manual_order->get_id());
    }

    /**
     * @return array{ok: bool, refunded: int, auto_refunded: int, manual_refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string}
     */
    private function run_operation(int $amount_kurus, string $reason): array
    {
        $method = new ReflectionMethod(Service::class, 'runOperation');
        $method->setAccessible(true);

        /** @var array{ok: bool, refunded: int, auto_refunded: int, manual_refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string} $operation */
        $operation = $method->invoke(null, $this->booking_id, $amount_kurus, $reason);

        return $operation;
    }

    /**
     * runOperation() already knows each leg's mode; the defect was that only
     * the collapsed operation-level 'mode' ever left this method. This pins
     * the subtotals at the source, independent of the messaging layer that
     * reads them.
     */
    public function test_runOperation_splits_the_subtotals_by_mode(): void
    {
        $this->wire_mixed_legs();

        $operation = $this->run_operation(Money::toMinor('100'), 'mixed subtotal check');

        $this->assertTrue($operation['ok'], $operation['message']);
        $this->assertSame(
            RefundValidator::MODE_MANUAL,
            $operation['mode'],
            'The operation-level mode still collapses to manual for status purposes -- unchanged logic.'
        );
        $this->assertSame(
            Money::toMinor('30'),
            $operation['auto_refunded'],
            'The card leg (30, walked first) is the auto subtotal.'
        );
        $this->assertSame(
            Money::toMinor('70'),
            $operation['manual_refunded'],
            'The transfer leg (70) is the manual subtotal.'
        );
        $this->assertSame(
            $operation['refunded'],
            $operation['auto_refunded'] + $operation['manual_refunded'],
            'The two subtotals must sum to the total refunded, never a hard-coded scale.'
        );
    }

    /**
     * @return array<string, mixed>|null the captured context for the given Mailer key
     */
    private function capture_email_context(callable $operation, string $wantedKey): ?array
    {
        $captured = null;

        $listener = static function (string $key, string $to, bool $ok, string $subject, array $context) use ($wantedKey, &$captured): void {
            if ($key === $wantedKey) {
                $captured = $context;
            }
        };

        add_action('mhmrentiva_email_sent', $listener, 10, 5);
        $operation();
        remove_action('mhmrentiva_email_sent', $listener, 10);

        return $captured;
    }

    /**
     * The exact failure the audit describes: the OLD admin mail said "the
     * amount above must be transferred to the customer manually", where "the
     * amount above" was the operation TOTAL (100) regardless of how much of
     * it the gateway already returned. An operator who followed it would
     * over-refund the customer by the 30 that already moved through the
     * gateway. The new sentence must name only the 70 still owed by hand.
     */
    public function test_the_admin_mail_names_only_the_manual_portion_in_mixed_mode(): void
    {
        $this->wire_mixed_legs();

        $currency = PaymentState::forBooking($this->booking_id)->currency() ?: 'TRY';

        $thirty = CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('30')),
            CurrencyHelper::get_price_decimals(),
            $currency
        );
        $seventy = CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('70')),
            CurrencyHelper::get_price_decimals(),
            $currency
        );

        $adminContext = $this->capture_email_context(function (): void {
            $result = Service::processFullRefund($this->booking_id, 'mixed admin mail check', $this->admin_id);
            $this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);
        }, 'refund_admin');

        $this->assertNotNull($adminContext, 'The admin mail must have been sent for a successful mixed operation.');

        $expectedAdminSentence = sprintf(
            __(
                'The payment gateway already returned %1$s of this refund automatically; only the remaining %2$s must be transferred to the customer manually.',
                'mhm-rentiva'
            ),
            $thirty,
            $seventy
        );

        $this->assertSame(
            $expectedAdminSentence,
            $adminContext['admin_mode_text'],
            'The admin sentence must name only the manual portion (70), not instruct transferring the whole 100.'
        );
        $this->assertStringNotContainsString(
            'the amount above must be transferred to the customer manually',
            (string) $adminContext['admin_mode_text'],
            'The old collapsed sentence -- which pointed at the operation TOTAL via "the amount above" --'
                . ' must not survive for a mixed operation.'
        );
    }

    /**
     * The customer's mirror of the same fix: which part came back to their
     * original payment method automatically, and which part is still coming
     * by hand.
     */
    public function test_the_customer_mail_names_both_amounts_in_mixed_mode(): void
    {
        $this->wire_mixed_legs();

        $currency = PaymentState::forBooking($this->booking_id)->currency() ?: 'TRY';

        $thirty = CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('30')),
            CurrencyHelper::get_price_decimals(),
            $currency
        );
        $seventy = CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('70')),
            CurrencyHelper::get_price_decimals(),
            $currency
        );

        $customerContext = $this->capture_email_context(function (): void {
            $result = Service::processFullRefund($this->booking_id, 'mixed customer mail check', $this->admin_id);
            $this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);
        }, 'refund_customer');

        $this->assertNotNull($customerContext, 'The customer mail must have been sent for a successful mixed operation.');

        $expectedCustomerSentence = sprintf(
            __(
                '%1$s was returned to your original payment method automatically; the remaining %2$s will be transferred to you manually.',
                'mhm-rentiva'
            ),
            $thirty,
            $seventy
        );

        $this->assertSame(
            $expectedCustomerSentence,
            $customerContext['mode_text'],
            'The customer sentence must name both amounts -- what came back automatically and what is still coming by hand.'
        );
        $this->assertStringNotContainsString(
            'it will not appear on your original payment method automatically',
            (string) $customerContext['mode_text'],
            'The old pure-manual sentence -- which denied ANY automatic return -- must not survive when part of the refund genuinely did return automatically.'
        );
    }

    /**
     * Pure cases must keep their existing sentences and existing strings,
     * byte-for-byte, so no translator sees a diff for them. A single
     * auto-capable order producing auto_refunded > 0 and manual_refunded === 0
     * must NOT trip the mixed-mode branch.
     */
    public function test_a_pure_auto_operation_keeps_the_original_sentence(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');
        $order->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $order->save();

        $adminContext = $this->capture_email_context(function (): void {
            $result = Service::processFullRefund($this->booking_id, 'pure auto check', $this->admin_id);
            $this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);
        }, 'refund_admin');

        $this->assertNotNull($adminContext);
        $this->assertSame(
            __('The payment gateway processed this refund automatically; no manual transfer is required.', 'mhm-rentiva'),
            $adminContext['admin_mode_text'],
            'A pure-auto operation must keep the original, unchanged sentence.'
        );
    }
}
