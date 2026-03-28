<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\VehicleLifecycleManager;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Core\MetaKeys;

class VehicleLifecycleManagerTest extends \WP_UnitTestCase
{
    private int $vendor_id;
    private int $vehicle_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($this->vendor_id);
        $user->add_role('rentiva_vendor');

        $this->vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'pending',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Lifecycle Test Vehicle',
        ));
        update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::PENDING_REVIEW);
    }

    // ── Activate ──────────────────────────────────────────────

    public function test_activate_from_pending_review(): void
    {
        $result = VehicleLifecycleManager::activate($this->vehicle_id);
        $this->assertTrue($result);
        $this->assertSame(VehicleLifecycleStatus::ACTIVE, VehicleLifecycleStatus::get($this->vehicle_id));
        $this->assertSame('publish', get_post_status($this->vehicle_id));
        $this->assertNotEmpty(get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, true));
    }

    public function test_activate_sets_listing_timer(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        $started = get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_STARTED_AT, true);
        $expires = get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, true);

        $this->assertNotEmpty($started);
        $this->assertNotEmpty($expires);

        $diff_days = (int) round((strtotime($expires) - strtotime($started)) / DAY_IN_SECONDS);
        $this->assertSame(VehicleLifecycleStatus::LISTING_DURATION_DAYS, $diff_days);
    }

    public function test_activate_already_active_returns_error(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        $result = VehicleLifecycleManager::activate($this->vehicle_id);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('already_active', $result->get_error_code());
    }

    public function test_activate_fires_lifecycle_changed_action(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vehicle_lifecycle_changed'));
    }

    // ── Pause ─────────────────────────────────────────────────

    public function test_pause_from_active(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        $result = VehicleLifecycleManager::pause($this->vehicle_id, $this->vendor_id);
        $this->assertTrue($result);
        $this->assertSame(VehicleLifecycleStatus::PAUSED, VehicleLifecycleStatus::get($this->vehicle_id));
        $this->assertNotEmpty(get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_PAUSED_AT, true));
    }

    public function test_pause_fires_hook(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::pause($this->vehicle_id, $this->vendor_id);
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vehicle_paused'));
    }

    public function test_pause_wrong_owner_fails(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        $other_user = $this->factory()->user->create();
        $result = VehicleLifecycleManager::pause($this->vehicle_id, $other_user);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('not_owner', $result->get_error_code());
        wp_delete_user($other_user);
    }

    public function test_pause_from_pending_review_fails(): void
    {
        $result = VehicleLifecycleManager::pause($this->vehicle_id, $this->vendor_id);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_transition', $result->get_error_code());
    }

    public function test_pause_limit_enforced(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);

        // Pause and resume twice (max per month).
        VehicleLifecycleManager::pause($this->vehicle_id, $this->vendor_id);
        VehicleLifecycleManager::resume($this->vehicle_id, $this->vendor_id);
        VehicleLifecycleManager::pause($this->vehicle_id, $this->vendor_id);
        VehicleLifecycleManager::resume($this->vehicle_id, $this->vendor_id);

        // Third pause should fail.
        $result = VehicleLifecycleManager::pause($this->vehicle_id, $this->vendor_id);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('pause_limit_reached', $result->get_error_code());
    }

    // ── Resume ────────────────────────────────────────────────

    public function test_resume_from_paused(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::pause($this->vehicle_id, $this->vendor_id);

        $result = VehicleLifecycleManager::resume($this->vehicle_id, $this->vendor_id);
        $this->assertTrue($result);
        $this->assertSame(VehicleLifecycleStatus::ACTIVE, VehicleLifecycleStatus::get($this->vehicle_id));
    }

    public function test_resume_clears_paused_at(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::pause($this->vehicle_id, $this->vendor_id);
        VehicleLifecycleManager::resume($this->vehicle_id, $this->vendor_id);

        $this->assertEmpty(get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_PAUSED_AT, true));
    }

    public function test_resume_from_active_fails(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        $result = VehicleLifecycleManager::resume($this->vehicle_id, $this->vendor_id);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('not_paused', $result->get_error_code());
    }

    // ── Withdraw ──────────────────────────────────────────────

    public function test_withdraw_from_active(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        $result = VehicleLifecycleManager::withdraw($this->vehicle_id, $this->vendor_id);
        $this->assertTrue($result);
        $this->assertSame(VehicleLifecycleStatus::WITHDRAWN, VehicleLifecycleStatus::get($this->vehicle_id));
        $this->assertSame('draft', get_post_status($this->vehicle_id));
        $this->assertNotEmpty(get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_COOLDOWN_ENDS_AT, true));
    }

    public function test_withdraw_fires_hook(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::withdraw($this->vehicle_id, $this->vendor_id);
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vehicle_withdrawn'));
    }

    public function test_withdraw_from_paused(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::pause($this->vehicle_id, $this->vendor_id);
        $result = VehicleLifecycleManager::withdraw($this->vehicle_id, $this->vendor_id);
        $this->assertTrue($result);
        $this->assertSame(VehicleLifecycleStatus::WITHDRAWN, VehicleLifecycleStatus::get($this->vehicle_id));
    }

    // ── Expire ────────────────────────────────────────────────

    public function test_expire_from_active(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        $result = VehicleLifecycleManager::expire($this->vehicle_id);
        $this->assertTrue($result);
        $this->assertSame(VehicleLifecycleStatus::EXPIRED, VehicleLifecycleStatus::get($this->vehicle_id));
    }

    public function test_expire_fires_hook(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::expire($this->vehicle_id);
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vehicle_expired'));
    }

    // ── Renew ─────────────────────────────────────────────────

    public function test_renew_from_expired(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::expire($this->vehicle_id);

        // Set a recent expires_at so grace period is valid.
        update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('-1 day')));

        $result = VehicleLifecycleManager::renew($this->vehicle_id, $this->vendor_id);
        $this->assertTrue($result);
        $this->assertSame(VehicleLifecycleStatus::ACTIVE, VehicleLifecycleStatus::get($this->vehicle_id));
        $this->assertSame(1, (int) get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_RENEWAL_CNT, true));
    }

    public function test_renew_from_active_fails(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        $result = VehicleLifecycleManager::renew($this->vehicle_id, $this->vendor_id);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('not_expired', $result->get_error_code());
    }

    // ── Relist ────────────────────────────────────────────────

    public function test_relist_from_withdrawn_after_cooldown(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::withdraw($this->vehicle_id, $this->vendor_id);

        // Simulate cooldown expiry.
        update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_COOLDOWN_ENDS_AT, gmdate('Y-m-d H:i:s', strtotime('-1 day')));

        $result = VehicleLifecycleManager::relist($this->vehicle_id, $this->vendor_id);
        $this->assertTrue($result);
        $this->assertSame(VehicleLifecycleStatus::PENDING_REVIEW, VehicleLifecycleStatus::get($this->vehicle_id));
        $this->assertSame('pending', get_post_status($this->vehicle_id));
    }

    public function test_relist_during_cooldown_fails(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::withdraw($this->vehicle_id, $this->vendor_id);

        // Cooldown is still active (set by withdraw).
        $result = VehicleLifecycleManager::relist($this->vehicle_id, $this->vendor_id);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('cooldown_active', $result->get_error_code());
    }

    public function test_relist_clears_withdrawal_meta(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);
        VehicleLifecycleManager::withdraw($this->vehicle_id, $this->vendor_id);
        update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_COOLDOWN_ENDS_AT, gmdate('Y-m-d H:i:s', strtotime('-1 day')));

        VehicleLifecycleManager::relist($this->vehicle_id, $this->vendor_id);
        $this->assertEmpty(get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_WITHDRAWN_AT, true));
        $this->assertEmpty(get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_COOLDOWN_ENDS_AT, true));
    }

    // ── Transition Validation ─────────────────────────────────

    public function test_status_can_transition_valid(): void
    {
        $this->assertTrue(VehicleLifecycleStatus::can_transition('active', 'paused'));
        $this->assertTrue(VehicleLifecycleStatus::can_transition('active', 'withdrawn'));
        $this->assertTrue(VehicleLifecycleStatus::can_transition('paused', 'active'));
        $this->assertTrue(VehicleLifecycleStatus::can_transition('expired', 'active'));
        $this->assertTrue(VehicleLifecycleStatus::can_transition('withdrawn', 'pending_review'));
    }

    public function test_status_can_transition_invalid(): void
    {
        $this->assertFalse(VehicleLifecycleStatus::can_transition('active', 'active'));
        $this->assertFalse(VehicleLifecycleStatus::can_transition('withdrawn', 'active'));
        $this->assertFalse(VehicleLifecycleStatus::can_transition('expired', 'paused'));
        $this->assertFalse(VehicleLifecycleStatus::can_transition('pending_review', 'paused'));
    }

    public function test_status_get_defaults_to_active(): void
    {
        $no_meta_vehicle = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'No Meta Vehicle',
        ));

        $this->assertSame(VehicleLifecycleStatus::ACTIVE, VehicleLifecycleStatus::get($no_meta_vehicle));

        wp_delete_post($no_meta_vehicle, true);
    }

    public function test_status_labels_and_colors(): void
    {
        $this->assertNotEmpty(VehicleLifecycleStatus::get_label('active'));
        $this->assertNotEmpty(VehicleLifecycleStatus::get_color('active'));
        $this->assertSame('#28a745', VehicleLifecycleStatus::get_color('active'));
        $this->assertSame('#dc3545', VehicleLifecycleStatus::get_color('withdrawn'));
    }

    // ── Active Bookings Block ─────────────────────────────────

    public function test_withdraw_blocked_by_active_booking(): void
    {
        VehicleLifecycleManager::activate($this->vehicle_id);

        // Create a confirmed booking for this vehicle.
        $booking_id = wp_insert_post(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
            'post_title'  => 'Test Booking',
        ));
        update_post_meta($booking_id, '_mhm_vehicle_id', $this->vehicle_id);
        update_post_meta($booking_id, '_mhm_status', 'confirmed');

        $result = VehicleLifecycleManager::withdraw($this->vehicle_id, $this->vendor_id);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('active_bookings_exist', $result->get_error_code());

        wp_delete_post($booking_id, true);
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->vehicle_id, true);
        wp_delete_user($this->vendor_id);
        parent::tearDown();
    }
}
