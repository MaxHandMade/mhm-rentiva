<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\VehicleLifecycleManager;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Vendor\VendorVehicleReviewManager;
use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Tests that the vehicle approval flow triggers lifecycle activation.
 */
class VehicleLifecycleActivationTest extends \WP_UnitTestCase
{
    private int $vendor_id;
    private int $vehicle_id;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure hooks are registered.
        VehicleLifecycleManager::register();

        $this->vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($this->vendor_id);
        $user->add_role('rentiva_vendor');

        // Create a pending vehicle (simulates vendor submission).
        $this->vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'pending',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Activation Test Vehicle',
        ));
        update_post_meta($this->vehicle_id, '_vehicle_review_status', 'pending_review');
        update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::PENDING_REVIEW);
    }

    public function test_approve_triggers_lifecycle_activation(): void
    {
        VendorVehicleReviewManager::approve($this->vehicle_id);

        $this->assertSame(VehicleLifecycleStatus::ACTIVE, VehicleLifecycleStatus::get($this->vehicle_id));
        $this->assertSame('publish', get_post_status($this->vehicle_id));
    }

    public function test_approve_starts_listing_timer(): void
    {
        VendorVehicleReviewManager::approve($this->vehicle_id);

        $started = get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_STARTED_AT, true);
        $expires = get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, true);

        $this->assertNotEmpty($started);
        $this->assertNotEmpty($expires);

        $diff_days = (int) round((strtotime($expires) - strtotime($started)) / DAY_IN_SECONDS);
        $this->assertSame(VehicleLifecycleStatus::LISTING_DURATION_DAYS, $diff_days);
    }

    public function test_approve_already_active_vehicle_no_timer_reset(): void
    {
        // First approval: sets timer.
        VendorVehicleReviewManager::approve($this->vehicle_id);
        $original_started = get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_STARTED_AT, true);

        // Simulate a second approval (e.g. admin re-publishes) — should NOT reset timer.
        do_action('mhm_rentiva_vehicle_approved', $this->vehicle_id, $this->vendor_id);
        $after_started = get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_STARTED_AT, true);

        $this->assertSame($original_started, $after_started);
    }

    public function test_direct_publish_triggers_lifecycle_activation(): void
    {
        // Simulate admin publishing directly (transition_post_status fires sync_review_on_publish).
        wp_update_post(array(
            'ID'          => $this->vehicle_id,
            'post_status' => 'publish',
        ));

        $this->assertSame(VehicleLifecycleStatus::ACTIVE, VehicleLifecycleStatus::get($this->vehicle_id));
        $this->assertNotEmpty(get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_STARTED_AT, true));
    }

    public function test_lifecycle_changed_action_fires_on_activation(): void
    {
        $fired = 0;
        add_action('mhm_rentiva_vehicle_lifecycle_changed', function () use (&$fired) {
            ++$fired;
        });

        VendorVehicleReviewManager::approve($this->vehicle_id);

        $this->assertGreaterThan(0, $fired);
    }

    public function test_is_vendor_listing_true_for_vendor_false_for_operator(): void
    {
        $this->assertTrue(
            VehicleLifecycleStatus::is_vendor_listing($this->vehicle_id),
            'vendor-authored vehicle is a vendor listing'
        );

        $admin_id      = $this->factory()->user->create(array('role' => 'administrator'));
        $admin_vehicle = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'pending',
            'post_author' => $admin_id,
            'post_title'  => 'Operator Vehicle',
        ));

        $this->assertFalse(
            VehicleLifecycleStatus::is_vendor_listing($admin_vehicle),
            'admin-authored vehicle is not a vendor listing'
        );

        wp_delete_post($admin_vehicle, true);
        wp_delete_user($admin_id);
    }

    public function test_operator_owned_vehicle_publish_does_not_start_lifecycle_timer(): void
    {
        $admin_id      = $this->factory()->user->create(array('role' => 'administrator'));
        $admin_vehicle = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'pending',
            'post_author' => $admin_id,
            'post_title'  => 'Operator Vehicle',
        ));
        update_post_meta($admin_vehicle, '_vehicle_review_status', 'pending_review');

        // Admin publishes directly — fires the approval flow, but lifecycle must be skipped.
        wp_update_post(array('ID' => $admin_vehicle, 'post_status' => 'publish'));

        $this->assertEmpty(
            get_post_meta($admin_vehicle, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, true),
            'operator vehicle must not get an expiry timer'
        );
        $this->assertEmpty(
            get_post_meta($admin_vehicle, MetaKeys::VEHICLE_LISTING_STARTED_AT, true),
            'operator vehicle must not get a listing start'
        );

        wp_delete_post($admin_vehicle, true);
        wp_delete_user($admin_id);
    }

    public function test_listing_expiry_job_skips_operator_owned_vehicles(): void
    {
        $admin_id      = $this->factory()->user->create(array('role' => 'administrator'));
        $admin_vehicle = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $admin_id,
            'post_title'  => 'Operator Vehicle (stale lifecycle)',
        ));
        // Simulate stale lifecycle meta predating the fix: active + already past expiry.
        update_post_meta($admin_vehicle, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($admin_vehicle, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('-1 day')));

        \MHMRentiva\Admin\PostTypes\Maintenance\ListingExpiryJob::run();

        $this->assertSame(
            VehicleLifecycleStatus::ACTIVE,
            VehicleLifecycleStatus::get($admin_vehicle),
            'operator-owned vehicle must not be expired by the cron'
        );
        $this->assertSame('publish', get_post_status($admin_vehicle));

        wp_delete_post($admin_vehicle, true);
        wp_delete_user($admin_id);
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->vehicle_id, true);
        wp_delete_user($this->vendor_id);
        parent::tearDown();
    }
}
