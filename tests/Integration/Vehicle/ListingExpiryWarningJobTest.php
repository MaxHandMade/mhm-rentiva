<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\PostTypes\Maintenance\ListingExpiryWarningJob;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Core\MetaKeys;

class ListingExpiryWarningJobTest extends \WP_UnitTestCase
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
     * Helper: create an active vehicle expiring in the given number of days.
     */
    private function create_expiring_vehicle(int $days_until_expiry): int
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Warning Test Vehicle',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, 'active');
        update_post_meta(
            $vehicle_id,
            MetaKeys::VEHICLE_LISTING_EXPIRES_AT,
            gmdate('Y-m-d H:i:s', strtotime('+' . $days_until_expiry . ' days'))
        );

        return $vehicle_id;
    }

    // ── First Warning (10 days) ──────────────────────────────

    public function test_first_warning_fires_for_vehicle_expiring_in_8_days(): void
    {
        $vehicle_id = $this->create_expiring_vehicle(8);

        $fired = 0;
        add_action('mhm_rentiva_vehicle_expiry_warning_first', function () use (&$fired) {
            ++$fired;
        });

        ListingExpiryWarningJob::run();

        $this->assertSame(1, $fired);
        $this->assertNotEmpty(get_post_meta($vehicle_id, '_mhm_vehicle_expiry_warning_first_sent', true));

        wp_delete_post($vehicle_id, true);
    }

    public function test_first_warning_not_sent_for_vehicle_expiring_in_20_days(): void
    {
        $vehicle_id = $this->create_expiring_vehicle(20);

        $fired = 0;
        add_action('mhm_rentiva_vehicle_expiry_warning_first', function () use (&$fired) {
            ++$fired;
        });

        ListingExpiryWarningJob::run();

        $this->assertSame(0, $fired);

        wp_delete_post($vehicle_id, true);
    }

    public function test_first_warning_not_sent_twice(): void
    {
        $vehicle_id = $this->create_expiring_vehicle(8);

        $fired = 0;
        add_action('mhm_rentiva_vehicle_expiry_warning_first', function () use (&$fired) {
            ++$fired;
        });

        ListingExpiryWarningJob::run();
        ListingExpiryWarningJob::run(); // Second run — should NOT fire again.

        $this->assertSame(1, $fired);

        wp_delete_post($vehicle_id, true);
    }

    // ── Second Warning (3 days) ──────────────────────────────

    public function test_second_warning_fires_for_vehicle_expiring_in_2_days(): void
    {
        $vehicle_id = $this->create_expiring_vehicle(2);

        $fired = 0;
        add_action('mhm_rentiva_vehicle_expiry_warning_second', function () use (&$fired) {
            ++$fired;
        });

        ListingExpiryWarningJob::run();

        $this->assertSame(1, $fired);
        $this->assertNotEmpty(get_post_meta($vehicle_id, '_mhm_vehicle_expiry_warning_second_sent', true));

        wp_delete_post($vehicle_id, true);
    }

    public function test_second_warning_not_sent_for_vehicle_expiring_in_5_days(): void
    {
        $vehicle_id = $this->create_expiring_vehicle(5);

        $fired = 0;
        add_action('mhm_rentiva_vehicle_expiry_warning_second', function () use (&$fired) {
            ++$fired;
        });

        ListingExpiryWarningJob::run();

        $this->assertSame(0, $fired);

        wp_delete_post($vehicle_id, true);
    }

    // ── Both Warnings Can Fire Together ──────────────────────

    public function test_both_warnings_fire_for_vehicle_expiring_in_2_days(): void
    {
        $vehicle_id = $this->create_expiring_vehicle(2);

        $first_fired  = 0;
        $second_fired = 0;

        add_action('mhm_rentiva_vehicle_expiry_warning_first', function () use (&$first_fired) {
            ++$first_fired;
        });
        add_action('mhm_rentiva_vehicle_expiry_warning_second', function () use (&$second_fired) {
            ++$second_fired;
        });

        ListingExpiryWarningJob::run();

        $this->assertSame(1, $first_fired);
        $this->assertSame(1, $second_fired);

        wp_delete_post($vehicle_id, true);
    }

    // ── Non-Active Vehicles Not Warned ───────────────────────

    public function test_paused_vehicle_not_warned(): void
    {
        $vehicle_id = $this->create_expiring_vehicle(5);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::PAUSED);

        $fired = 0;
        add_action('mhm_rentiva_vehicle_expiry_warning_first', function () use (&$fired) {
            ++$fired;
        });

        ListingExpiryWarningJob::run();

        $this->assertSame(0, $fired);

        wp_delete_post($vehicle_id, true);
    }

    // ── Schedule ─────────────────────────────────────────────

    public function test_maybe_schedule_creates_daily_event(): void
    {
        $next = wp_next_scheduled(ListingExpiryWarningJob::EVENT);
        if ($next) {
            wp_unschedule_event($next, ListingExpiryWarningJob::EVENT);
        }

        ListingExpiryWarningJob::maybe_schedule();

        $this->assertNotFalse(wp_next_scheduled(ListingExpiryWarningJob::EVENT));
        $this->assertSame('daily', wp_get_schedule(ListingExpiryWarningJob::EVENT));

        wp_unschedule_event(wp_next_scheduled(ListingExpiryWarningJob::EVENT), ListingExpiryWarningJob::EVENT);
    }

    protected function tearDown(): void
    {
        wp_delete_user($this->vendor_id);
        parent::tearDown();
    }
}
