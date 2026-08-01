<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Reports;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Locks the two branches of `DashboardService::get_recent_bookings_paginated()`
 * against each other.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * This method has the same two-literal-branch shape as
 * `ReportRepository::get_upcoming_operations_paginated()`: one statement carries
 * a `%i` JOIN against the Transfer add-on's locations table, the other does not,
 * and only one of the two therefore has a different placeholder order. In that
 * sibling, the WP.org T7 Task 9.5 rewrite transposed the argument list and the
 * panel silently returned nothing on every site that had the add-on -- swallowed
 * by `suppress_errors()` and a try/catch.
 *
 * This method's argument order is correct today. It was also correct in the
 * sibling's *other* branch, which is precisely why the bug survived: correctness
 * here proves nothing about tomorrow. Nothing in `tests/` covered these two
 * branches, so the exact class of bug just fixed next door was unprotected here.
 *
 * The assertions are deliberately not count-based. Both branches return the same
 * number of rows when the result set is empty, which is how a 0-against-0
 * comparison passed a broken query once already.
 *
 * MUTATION-PROVEN (WP.org T7 Task 9.5, fix round 2): moving `$locations_table`
 * ahead of the meta-key arguments -- the same transposition that broke the
 * sibling -- turns both tests red.
 *
 * @package MHMRentiva\Tests\Integration\Admin\Reports
 */
class RecentBookingsLocationBranchParityTest extends \WP_UnitTestCase
{
    private int $booking_id = 0;

    /**
     * The name the method probes for first; created and dropped per test.
     */
    private function locations_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'rentiva_transfer_locations';
    }

    /**
     * Only the columns the query reads: `id` for the JOIN, `name` for the SELECT.
     */
    private function create_locations_table(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test fixture: the add-on table this test exists to switch on and off.
        $wpdb->query(
            $wpdb->prepare(
                'CREATE TABLE IF NOT EXISTS %i (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    name varchar(191) NOT NULL DEFAULT %s,
                    city varchar(191) NOT NULL DEFAULT %s,
                    PRIMARY KEY (id)
                )',
                $this->locations_table(),
                '',
                ''
            )
        );
    }

    private function drop_locations_table(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test fixture teardown.
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $this->locations_table()));
    }

    /**
     * Run a callback with every executed statement captured, and return the one
     * containing $needle.
     *
     * A transposition is only visible in the SQL the server was actually given.
     * `$wpdb->last_error` cannot do this job: wpdb clears it at the start of
     * every query and this method runs several more after its main SELECT.
     */
    private function capture_query(callable $run, string $needle): string
    {
        $captured = array();
        $capture  = static function ($query) use (&$captured) {
            $captured[] = $query;
            return $query;
        };

        add_filter('query', $capture);
        $run();
        remove_filter('query', $capture);

        foreach ($captured as $query) {
            if (strpos($query, $needle) !== false) {
                return $query;
            }
        }

        return '';
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->drop_locations_table();

        $vehicle_id = self::factory()->post->create(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Recent Parity Vehicle',
        ));
        update_post_meta($vehicle_id, '_mhm_rentiva_license_plate', '34REC200');

        // This query has no date window and accepts any of publish/private/pending,
        // so a plain published booking is enough to guarantee a non-empty result.
        $this->booking_id = self::factory()->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
            'post_title'  => 'Recent Parity Booking',
        ));

        update_post_meta($this->booking_id, '_mhm_vehicle_id', $vehicle_id);
        update_post_meta($this->booking_id, '_mhm_status', 'confirmed');
        update_post_meta($this->booking_id, '_mhm_pickup_date', gmdate('Y-m-d'));
        update_post_meta($this->booking_id, '_mhm_customer_name', 'Recent Parity Customer');
        update_post_meta($this->booking_id, '_mhm_total_price', '1250');
    }

    public function tearDown(): void
    {
        $this->drop_locations_table();

        parent::tearDown();
    }

    /**
     * @return list<int> Booking IDs the dashboard returned, sorted.
     */
    private function booking_ids(): array
    {
        $items = DashboardService::get_recent_bookings_paginated(1, 50)['items'];
        $ids   = array_map(static fn (array $row): int => (int) $row['id'], $items);
        sort($ids);

        return $ids;
    }

    /**
     * Both branches must return the same bookings, and both must return some.
     */
    public function test_both_location_branches_return_the_same_bookings(): void
    {
        $without_table = $this->booking_ids();

        $this->assertContains(
            $this->booking_id,
            $without_table,
            'Fixture sanity: the seeded booking must be returned when the locations table is absent.'
        );

        $this->create_locations_table();
        $with_table = $this->booking_ids();

        $this->assertContains(
            $this->booking_id,
            $with_table,
            'The seeded booking disappeared once the locations table existed -- the locations branch is broken.'
        );

        $this->assertSame(
            $without_table,
            $with_table,
            'The two location branches must return identical booking IDs.'
        );
    }

    /**
     * The bound values must land in the slots their placeholders occupy.
     */
    public function test_locations_table_is_bound_as_a_table_not_as_a_meta_key(): void
    {
        $this->create_locations_table();

        $recent = $this->capture_query(fn () => $this->booking_ids(), 'loc_veh');

        $this->assertNotSame('', $recent, 'Fixture sanity: the locations-branch statement was never issued.');

        $table = $this->locations_table();

        $this->assertStringContainsString(
            'LEFT JOIN `' . $table . '` loc_veh',
            $recent,
            'The locations table must be bound into the JOIN slot.'
        );
        $this->assertStringNotContainsString(
            "meta_key = '" . $table . "'",
            $recent,
            'The locations table name was bound into a meta_key slot -- the arguments are transposed.'
        );
        $this->assertStringContainsString(
            "pm_pickup.meta_key = '_mhm_pickup_date'",
            $recent,
            'The pickup-date meta key must be bound into the pm_pickup slot.'
        );
    }
}
