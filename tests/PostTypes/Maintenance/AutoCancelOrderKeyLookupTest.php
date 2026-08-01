<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\PostTypes\Maintenance;

use MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel;
use WP_UnitTestCase;

/**
 * Regression test: AutoCancel must lookup the WC order ID using the same
 * fallback chain as ReportRepository / RemainingPaymentHandler.
 *
 * Bug (2026-05-15): only `_mhmrentiva_wc_order_id` was queried — every booking
 * created via the current checkout (which writes `_mhmrentiva_woocommerce_order_id`)
 * had its WC order silently skipped, leaving "pending" orders forever even
 * after the booking was cancelled.
 */
final class AutoCancelOrderKeyLookupTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        if (! class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce not loaded.');
        }
    }

    /**
     * Booking has only the new key (`_mhmrentiva_woocommerce_order_id`).
     * Backfill helper must find the order and cancel it.
     */
    public function test_sync_orphan_uses_woocommerce_order_id_meta(): void
    {
        $order              = wc_create_order(array( 'status' => 'on-hold' ));
        $order->set_total('100.00');
        $order->save();
        $order_id           = $order->get_id();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'cancelled');
        update_post_meta($booking_id, '_mhmrentiva_woocommerce_order_id', $order_id);

        $result = AutoCancel::sync_orphan_wc_orders();

        $this->assertGreaterThanOrEqual(1, $result['cancelled'], 'Backfill did not cancel any orders.');

        $refreshed = wc_get_order($order_id);
        $this->assertSame('cancelled', $refreshed->get_status(), 'WC order should be cancelled after backfill.');
    }

    /**
     * Legacy compat: booking with only the old `_mhmrentiva_wc_order_id` key
     * (pre-checkout-rewrite data) must still be found.
     */
    public function test_sync_orphan_falls_back_to_legacy_wc_order_id_meta(): void
    {
        $order              = wc_create_order(array( 'status' => 'pending' ));
        $order->set_total('50.00');
        $order->save();
        $order_id           = $order->get_id();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'cancelled');
        // Only legacy key set.
        update_post_meta($booking_id, '_mhmrentiva_wc_order_id', $order_id);

        AutoCancel::sync_orphan_wc_orders();

        $this->assertSame('cancelled', wc_get_order($order_id)->get_status());
    }

    /**
     * Idempotency: re-running on an already-cancelled order is a no-op.
     */
    public function test_sync_orphan_is_idempotent_on_cancelled_orders(): void
    {
        $order = wc_create_order(array( 'status' => 'cancelled' ));
        $order->set_total('25.00');
        $order->save();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'cancelled');
        update_post_meta($booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id());

        $result = AutoCancel::sync_orphan_wc_orders();

        $this->assertSame(0, $result['cancelled'], 'Already-cancelled order should not be touched.');
    }

    /**
     * Remaining-payment order is also cancelled when present.
     */
    public function test_sync_orphan_cancels_remaining_order_too(): void
    {
        $deposit_order = wc_create_order(array( 'status' => 'on-hold' ));
        $deposit_order->set_total('100.00');
        $deposit_order->save();

        $remaining_order = wc_create_order(array( 'status' => 'on-hold' ));
        $remaining_order->set_total('300.00');
        $remaining_order->save();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'cancelled');
        update_post_meta($booking_id, '_mhmrentiva_woocommerce_order_id', $deposit_order->get_id());
        update_post_meta($booking_id, '_mhmrentiva_remaining_order_id', $remaining_order->get_id());

        AutoCancel::sync_orphan_wc_orders();

        $this->assertSame('cancelled', wc_get_order($deposit_order->get_id())->get_status());
        $this->assertSame('cancelled', wc_get_order($remaining_order->get_id())->get_status());
    }

    /**
     * sync_stale_past_bookings: pickup_date in the past + unpaid → must cancel.
     */
    public function test_sync_stale_cancels_past_pickup_unpaid_booking(): void
    {
        $order = wc_create_order(array( 'status' => 'pending' ));
        $order->set_total('250.00');
        $order->save();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'pending');
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');
        update_post_meta($booking_id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', strtotime('-7 days')));
        update_post_meta($booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id());

        $result = AutoCancel::sync_stale_past_bookings();

        $this->assertGreaterThanOrEqual(1, $result['cancelled']);
        $this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));
        $this->assertSame('cancelled', wc_get_order($order->get_id())->get_status());
    }

    /**
     * sync_stale_past_bookings: pickup_date past but already paid → no-op.
     */
    public function test_sync_stale_skips_already_paid_booking(): void
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'completed');
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($booking_id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', strtotime('-7 days')));

        AutoCancel::sync_stale_past_bookings();

        $this->assertSame('completed', get_post_meta($booking_id, '_mhmrentiva_status', true));
    }

    /**
     * sync_stale_past_bookings: pickup_date in the future + unpaid → no-op.
     */
    public function test_sync_stale_skips_future_pickup_booking(): void
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'pending');
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');
        update_post_meta($booking_id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', strtotime('+5 days')));

        AutoCancel::sync_stale_past_bookings();

        $this->assertSame('pending', get_post_meta($booking_id, '_mhmrentiva_status', true));
    }
}
