<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\PostTypes\Maintenance\ListingExpiryJob;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleManager;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Core\MetaKeys;

class ListingExpiryJobTest extends \WP_UnitTestCase
{
    private int $vendor_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($this->vendor_id);
        $user->add_role('rentiva_vendor');
    }

    /**
     * Helper: create a vehicle in active lifecycle state with a specific expiry date.
     */
    private function create_active_vehicle(string $expires_at): int
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Expiry Test Vehicle',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, 'active');
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_STARTED_AT, gmdate('Y-m-d H:i:s', strtotime('-90 days')));
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, $expires_at);

        return $vehicle_id;
    }

    // ── Expiry ───────────────────────────────────────────────

    public function test_run_expires_overdue_vehicles(): void
    {
        $expired_vehicle = $this->create_active_vehicle(gmdate('Y-m-d H:i:s', strtotime('-1 hour')));
        $active_vehicle  = $this->create_active_vehicle(gmdate('Y-m-d H:i:s', strtotime('+30 days')));

        ListingExpiryJob::run();

        $this->assertSame(VehicleLifecycleStatus::EXPIRED, VehicleLifecycleStatus::get($expired_vehicle));
        $this->assertSame(VehicleLifecycleStatus::ACTIVE, VehicleLifecycleStatus::get($active_vehicle));

        wp_delete_post($expired_vehicle, true);
        wp_delete_post($active_vehicle, true);
    }

    public function test_run_does_not_expire_non_active_vehicles(): void
    {
        $vehicle_id = $this->create_active_vehicle(gmdate('Y-m-d H:i:s', strtotime('-1 hour')));

        // Manually set to paused — should NOT be expired by the cron.
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::PAUSED);

        ListingExpiryJob::run();

        $this->assertSame(VehicleLifecycleStatus::PAUSED, VehicleLifecycleStatus::get($vehicle_id));

        wp_delete_post($vehicle_id, true);
    }

    public function test_expiry_fires_lifecycle_changed_hook(): void
    {
        $this->create_active_vehicle(gmdate('Y-m-d H:i:s', strtotime('-1 hour')));

        $fired = 0;
        add_action('mhm_rentiva_vehicle_expired', function () use (&$fired) {
            ++$fired;
        });

        ListingExpiryJob::run();

        $this->assertSame(1, $fired);
    }

    // ── Auto-Withdraw Past Grace ─────────────────────────────

    public function test_auto_withdraw_past_grace_period(): void
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Grace Period Vehicle',
        ));

        // Expired vehicle with expires_at well beyond grace period.
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::EXPIRED);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('-15 days')));

        ListingExpiryJob::run();

        $this->assertSame(VehicleLifecycleStatus::WITHDRAWN, VehicleLifecycleStatus::get($vehicle_id));
        $this->assertSame('draft', get_post_status($vehicle_id));
        $this->assertNotEmpty(get_post_meta($vehicle_id, MetaKeys::VEHICLE_COOLDOWN_ENDS_AT, true));

        wp_delete_post($vehicle_id, true);
    }

    public function test_auto_withdraw_fires_hook(): void
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Grace Period Vehicle',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::EXPIRED);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('-15 days')));

        $fired = 0;
        add_action('mhm_rentiva_vehicle_auto_withdrawn', function () use (&$fired) {
            ++$fired;
        });

        ListingExpiryJob::run();

        $this->assertSame(1, $fired);

        wp_delete_post($vehicle_id, true);
    }

    public function test_no_auto_withdraw_within_grace_period(): void
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Grace Period Vehicle',
        ));

        // Expired 2 days ago — still within 7-day grace.
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::EXPIRED);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('-2 days')));

        ListingExpiryJob::run();

        $this->assertSame(VehicleLifecycleStatus::EXPIRED, VehicleLifecycleStatus::get($vehicle_id));

        wp_delete_post($vehicle_id, true);
    }

    // ── Schedule ─────────────────────────────────────────────

    public function test_maybe_schedule_creates_event(): void
    {
        // Unschedule first if already present.
        $next = wp_next_scheduled(ListingExpiryJob::EVENT);
        if ($next) {
            wp_unschedule_event($next, ListingExpiryJob::EVENT);
        }

        ListingExpiryJob::maybe_schedule();

        $this->assertNotFalse(wp_next_scheduled(ListingExpiryJob::EVENT));
        $this->assertSame('twicedaily', wp_get_schedule(ListingExpiryJob::EVENT));

        // Cleanup.
        wp_unschedule_event(wp_next_scheduled(ListingExpiryJob::EVENT), ListingExpiryJob::EVENT);
    }

    protected function tearDown(): void
    {
        wp_delete_user($this->vendor_id);
        parent::tearDown();
    }
}
