<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayDouble;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayRegistration;
use WP_UnitTestCase;

/**
 * Spec §5.2 item 4: when one order in the operation fails, the flow stops and
 * the refunds already made are NOT rolled back -- WooCommerce has no such
 * operation. The booking must therefore carry a record saying so, or the
 * operator sees "refund failed" over money that already left.
 *
 * _mhmrentiva_refund_status had zero readers when this was written (spec
 * §5.7 / N-05). The display and the retry affordance are step 7; this task
 * guarantees the value is there to display. Named here so a later reader does
 * not mistake the silence for a defect.
 */
final class RefundPartialFailureTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;
    use WooCommerceRefundGatewayRegistration;

    /** @var int */
    private $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');

        $this->register_refund_gateway_double();
    }

    public function tearDown(): void
    {
        $this->unregister_refund_gateway_double();

        parent::tearDown();
    }

    /**
     * create_paid_order_for_booking() never sets a payment method, so
     * wc_get_payment_gateway_by_order() returns false and modeForOrder()
     * answers MODE_MANUAL. This test was asserting 'completed' for an
     * operation in which no gateway moved a lira -- true of the write, false
     * of the world. wc-refunds.md fact 2: a manual refund is a bookkeeping
     * record, not a payment, and the operator still has to send the money.
     */
    public function test_a_manual_operation_records_manual_pending(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::processFullRefund($this->booking_id, 'manual');

        $this->assertSame(
            'manual_pending',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true)
        );
    }

    public function test_an_automatic_operation_records_completed(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        // Opt this order into the auto path deliberately; see
        // WooCommerceRefundGatewayDouble's docblock. The gateway itself is
        // registered by the trait in setUp().
        $order->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $order->save();

        Service::processFullRefund($this->booking_id, 'auto');

        $this->assertSame(
            'completed',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true),
            'Only the gateway path may claim the money has already gone back.'
        );
    }

    public function test_the_operation_announces_its_end_once(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $fired = 0;
        add_action(
            'mhmrentiva_refund_completed',
            static function () use (&$fired): void {
                ++$fired;
            },
            10,
            2
        );

        Service::processFullRefund($this->booking_id, 'announce');

        $this->assertSame(1, $fired, 'Spec §5.3 step 9: one operation, one completion signal.');
    }

    /**
     * Finding A: before this slice, mhmrentiva_refund_completed had zero
     * listeners, so a broken one could never be reached. Now that it can fire
     * real integrator code, a throw from it must not unwind the terminal
     * status finish() already wrote, nor the record of what actually
     * happened -- only the completion signal itself is allowed to fail.
     */
    public function test_a_throwing_refund_completed_listener_does_not_undo_the_terminal_status(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');
        $order->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $order->save();

        add_action(
            'mhmrentiva_refund_completed',
            static function (): void {
                throw new \RuntimeException('listener exploded');
            },
            10,
            2
        );

        $result = Service::processFullRefund($this->booking_id, 'completed listener throws');

        $this->assertSame(
            '1',
            $result['mhmrentiva_refund'],
            'A broken mhmrentiva_refund_completed listener must not turn a completed refund into a failure return.'
        );

        $this->assertSame(
            'completed',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true),
            'finish() already wrote the terminal status before the hook fired; a listener throw must not erase it.'
        );

        $this->assertSame(
            Money::toMinor('120'),
            Money::toMinor((string) wc_get_order($order->get_id())->get_total_refunded()),
            'The refund itself already happened before the hook fired.'
        );
    }

    public function test_a_leg_that_fails_after_a_successful_one_records_partial_failure(): void
    {
        $first = $this->create_paid_order_for_booking($this->booking_id, '30');

        $second = $this->create_paid_order_for_booking($this->booking_id, '70');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $second->get_id());

        // Refuse only the second order's refund.
        //
        // There is no filter that can make wc_create_refund() return a
        // WP_Error directly. What it does have (wc-order-functions.php
        // :559-753, WC 11.0.1) is one big try/catch: any exception thrown from
        // inside it -- including from the woocommerce_refund_created action at
        // :745 -- is caught at :747, the refund object is force-deleted, and
        // the function returns new WP_Error('error', $message). Throwing at
        // priority 1 also lands ahead of WooCommerceBridge's own priority-10
        // handler, so the failed leg leaves no meta behind either.
        $fired = 0;

        add_action(
            'woocommerce_refund_created',
            static function (int $refund_id, array $args) use ($second, &$fired): void {
                if ((int) $args['order_id'] === $second->get_id()) {
                    ++$fired;
                    throw new \RuntimeException('refused on purpose');
                }
            },
            1,
            2
        );

        $result = Service::processFullRefund($this->booking_id, 'partial failure');

        $this->assertSame(1, $fired, 'Negative control: the refusal hook must actually have run.');
        $this->assertSame('0', $result['mhmrentiva_refund']);
        $this->assertSame(
            'partial_failure',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true),
            'Money left the first order; reporting a flat failure would hide that.'
        );
        $this->assertSame(
            '30',
            wc_format_decimal(wc_get_order($first->get_id())->get_total_refunded(), 0),
            'The successful leg is not rolled back -- WooCommerce cannot undo a refund.'
        );
    }

    public function test_a_first_leg_failure_records_failed(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $fired = 0;

        add_action(
            'woocommerce_refund_created',
            static function () use (&$fired): void {
                ++$fired;
                throw new \RuntimeException('refused on purpose');
            },
            1,
            2
        );

        Service::processFullRefund($this->booking_id, 'total failure');

        $this->assertSame(1, $fired, 'Negative control: the refusal hook must actually have run.');
        $this->assertSame(
            'failed',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true),
            'Nothing moved, so this is a plain failure, not a partial one.'
        );
    }
}
