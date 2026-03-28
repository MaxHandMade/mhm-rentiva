<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\VendorCancellationDateBlocker;
use MHMRentiva\Admin\Core\MetaKeys;

class VendorCancellationDateBlockerTest extends \WP_UnitTestCase
{
    private int $vendor_id;
    private int $customer_id;
    private int $vehicle_id;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure hook is registered.
        VendorCancellationDateBlocker::register();

        $this->vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($this->vendor_id);
        $user->add_role('rentiva_vendor');

        $this->customer_id = $this->factory()->user->create(array('role' => 'subscriber'));

        $this->vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Date Blocker Test Vehicle',
        ));
    }

    /**
     * Helper: create a booking for the test vehicle.
     */
    private function create_booking(string $pickup, string $dropoff): int
    {
        $booking_id = wp_insert_post(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
            'post_title'  => 'Test Booking',
        ));

        update_post_meta($booking_id, '_mhm_vehicle_id', $this->vehicle_id);
        update_post_meta($booking_id, '_mhm_customer_id', $this->customer_id);
        update_post_meta($booking_id, '_mhm_pickup_date', $pickup);
        update_post_meta($booking_id, '_mhm_dropoff_date', $dropoff);
        update_post_meta($booking_id, '_mhm_status', 'confirmed');

        return $booking_id;
    }

    // ── Vendor Cancellation → Dates Blocked ──────────────────

    public function test_vendor_cancellation_blocks_dates(): void
    {
        $booking_id = $this->create_booking('2026-05-01', '2026-05-04');

        // Simulate: vendor cancels the booking.
        do_action('mhm_rentiva_booking_cancelled', $booking_id, $this->vendor_id, 'Testing');

        $blocked = get_post_meta($this->vehicle_id, '_mhm_blocked_dates', true);
        $this->assertIsArray($blocked);
        $this->assertContains('2026-05-01', $blocked);
        $this->assertContains('2026-05-02', $blocked);
        $this->assertContains('2026-05-03', $blocked);
        // 2026-05-04 is the dropoff date (exclusive).
        $this->assertNotContains('2026-05-04', $blocked);

        wp_delete_post($booking_id, true);
    }

    public function test_customer_cancellation_does_not_block_dates(): void
    {
        $booking_id = $this->create_booking('2026-06-01', '2026-06-03');

        // Simulate: customer cancels.
        do_action('mhm_rentiva_booking_cancelled', $booking_id, $this->customer_id, 'Changed plans');

        $blocked = get_post_meta($this->vehicle_id, '_mhm_blocked_dates', true);
        $this->assertEmpty($blocked);

        wp_delete_post($booking_id, true);
    }

    public function test_admin_cancellation_does_not_block_dates(): void
    {
        $booking_id = $this->create_booking('2026-07-01', '2026-07-03');

        // Simulate: admin cancels (user_id = 0).
        do_action('mhm_rentiva_booking_cancelled', $booking_id, 0, 'Admin action');

        $blocked = get_post_meta($this->vehicle_id, '_mhm_blocked_dates', true);
        $this->assertEmpty($blocked);

        wp_delete_post($booking_id, true);
    }

    // ── Penalty Block Tracking ───────────────────────────────

    public function test_penalty_block_recorded_with_expiry(): void
    {
        $booking_id = $this->create_booking('2026-05-10', '2026-05-13');

        do_action('mhm_rentiva_booking_cancelled', $booking_id, $this->vendor_id, 'Testing');

        $blocks = VendorCancellationDateBlocker::get_active_blocks($this->vehicle_id);
        $this->assertCount(1, $blocks);
        $this->assertSame($booking_id, $blocks[0]['booking_id']);
        $this->assertCount(3, $blocks[0]['dates']); // 10, 11, 12

        wp_delete_post($booking_id, true);
    }

    public function test_expired_blocks_not_returned(): void
    {
        $booking_id = $this->create_booking('2026-01-01', '2026-01-03');

        do_action('mhm_rentiva_booking_cancelled', $booking_id, $this->vendor_id, 'Testing');

        // Manually expire the block.
        $blocks = get_post_meta($this->vehicle_id, '_mhm_vehicle_penalty_blocked_dates', true);
        $blocks[0]['expires_at'] = '2025-01-01';
        update_post_meta($this->vehicle_id, '_mhm_vehicle_penalty_blocked_dates', $blocks);

        $active = VendorCancellationDateBlocker::get_active_blocks($this->vehicle_id);
        $this->assertCount(0, $active);

        wp_delete_post($booking_id, true);
    }

    // ── Hook Fires ───────────────────────────────────────────

    public function test_penalty_blocked_hook_fires(): void
    {
        $booking_id = $this->create_booking('2026-08-01', '2026-08-03');

        $fired = 0;
        add_action('mhm_rentiva_vehicle_dates_penalty_blocked', function () use (&$fired) {
            ++$fired;
        });

        do_action('mhm_rentiva_booking_cancelled', $booking_id, $this->vendor_id, 'Testing');

        $this->assertSame(1, $fired);

        wp_delete_post($booking_id, true);
    }

    // ── Merge With Existing Blocked Dates ────────────────────

    public function test_dates_merged_with_existing_blocked_dates(): void
    {
        // Pre-existing blocked dates.
        update_post_meta($this->vehicle_id, '_mhm_blocked_dates', array('2026-05-01', '2026-05-15'));

        $booking_id = $this->create_booking('2026-05-01', '2026-05-03');

        do_action('mhm_rentiva_booking_cancelled', $booking_id, $this->vendor_id, 'Testing');

        $blocked = get_post_meta($this->vehicle_id, '_mhm_blocked_dates', true);
        // Should have: 01, 02, 15 (01 deduplicated, 02 new, 15 existing).
        $this->assertContains('2026-05-01', $blocked);
        $this->assertContains('2026-05-02', $blocked);
        $this->assertContains('2026-05-15', $blocked);
        $this->assertCount(3, $blocked);

        wp_delete_post($booking_id, true);
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->vehicle_id, true);
        wp_delete_user($this->vendor_id);
        wp_delete_user($this->customer_id);
        parent::tearDown();
    }
}
