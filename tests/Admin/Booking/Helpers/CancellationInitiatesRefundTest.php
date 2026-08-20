<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * H-01: the whole point of the slice. Measured before this task,
 * mhmrentiva_process_refund had exactly one do_action and zero listeners, so a
 * cancellation wrote 'pending' and stopped -- on both surfaces, for every
 * booking, since the feature shipped.
 *
 * The hook ORDER is asserted, not assumed: it has to fire before the balance
 * is read, or an integrator's own refund would be made twice.
 */
final class CancellationInitiatesRefundTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

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

        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', (int) self::factory()->post->create(array(
            'post_type' => 'mhmrentiva_vehicle',
        )));
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));
    }

    private function cancel(): void
    {
        // The outer ownership guard reads the CURRENT user, not the actor
        // argument; see CancellationRefundAuthorizationTest for why.
        wp_set_current_user($this->admin_id);

        CancellationHandler::cancel_booking($this->booking_id, $this->admin_id, 'customer changed plans', true);
    }

    /**
     * The only end-to-end proof of re-entrancy in the whole slice.
     *
     * settle_refund() acquires RefundLock and then calls
     * Service::processFullRefund(), which acquires the same lock again in the
     * same request. If re-entrancy were broken, that inner acquire would
     * return false, the service would answer its refusal shape, and no money
     * would move -- so a passing assertion here is proof the nested acquire
     * actually succeeded, not just that a refund happened by some other path.
     */
    public function test_cancelling_a_paid_booking_refunds_the_whole_balance(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->cancel();

        $this->assertSame(
            Money::toMinor('120'),
            Money::toMinor(wc_get_order($order->get_id())->get_total_refunded()),
            'This is the assertion that was impossible before this slice.'
        );
    }

    public function test_the_hook_fires_before_the_balance_is_read(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $seen = array();

        add_action(
            'mhmrentiva_process_refund',
            function () use (&$seen): void {
                $seen['status_at_hook_time'] = (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true);
            }
        );

        $this->cancel();

        $this->assertSame(
            'pending',
            $seen['status_at_hook_time'] ?? '',
            'The hook must see the pending marker: it fires after step 2 and before the money step.'
        );
    }

    public function test_a_listener_that_refunds_externally_prevents_a_second_refund(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        add_action(
            'mhmrentiva_process_refund',
            static function (int $booking_id) use ($order): void {
                wc_create_refund(array(
                    'order_id' => $order->get_id(),
                    'amount'   => '120',
                ));
            }
        );

        $this->cancel();

        $this->assertSame(
            Money::toMinor('120'),
            Money::toMinor(wc_get_order($order->get_id())->get_total_refunded()),
            'The integrator refunded 120; refunding again would return 240 to a customer who paid 120.'
        );
        $this->assertSame(
            'completed_externally',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true),
            'Recording not_required here would erase a real money movement from the audit trail.'
        );
    }

    public function test_the_booking_stays_cancelled(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->cancel();

        $this->assertSame(
            Status::CANCELLED,
            Status::get($this->booking_id),
            'Status::can_transition() has no cancelled -> refunded edge; the money state lives in payment_status.'
        );
    }

    public function test_the_lock_is_not_left_standing(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->cancel();

        global $wpdb;

        $this->assertNull(
            $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                'mhmrentiva_refund_lock_' . $this->booking_id
            )),
            'A lock surviving the operation blocks every later refund on this booking for the TTL.'
        );
    }
}
