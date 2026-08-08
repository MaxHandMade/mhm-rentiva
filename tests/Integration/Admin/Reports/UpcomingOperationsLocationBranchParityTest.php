<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Reports;

use MHMRentiva\Admin\Reports\Repository\ReportRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Locks the two branches of `get_upcoming_operations_paginated()` against each other.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * The rentals query exists as two near-identical literal statements, chosen by
 * whether the Transfer add-on's locations table is present. Only one of the two
 * carries the three `%i` JOINs, so only one has a different placeholder order --
 * and in the first cut of WP.org T7 Task 9.5 that one had its `prepare()`
 * arguments transposed: the three meta keys landed in the `%i` slots and the
 * table name landed in three `meta_key = %s` slots. MySQL raised 1146 (unknown
 * table `_mhmrentiva_pickup_date`), `$wpdb->suppress_errors()` at the top of the method
 * swallowed it, the surrounding try/catch swallowed what was left, and the
 * Upcoming Operations panel silently returned nothing on every site that HAS the
 * add-on.
 *
 * Nothing caught it: the false branch is correct, the dev database's newest
 * pickup date is in the past so both branches legitimately returned zero rows,
 * and a row-count comparison of 0 against 0 passes.
 *
 * So this test does two things a count assertion cannot:
 *   1. it seeds a booking that MUST come back, so an empty result is a failure
 *      rather than a vacuous pass;
 *   2. it compares the two branches' row IDS, not their sizes.
 *
 * MUTATION-PROVEN (WP.org T7 Task 9.5 fix round): re-transposing the argument
 * list turns this test red.
 *
 * @package MHMRentiva\Tests\Integration\Admin\Reports
 */
class UpcomingOperationsLocationBranchParityTest extends \WP_UnitTestCase
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
    /**
     * The suite rewrites CREATE TABLE / DROP TABLE to their TEMPORARY forms,
     * and `SHOW TABLES LIKE` — the exact probe the method under test uses —
     * does not list temporary tables. The fixture therefore has to be a REAL
     * table, or the locations branch never runs (fresh-database CI failure;
     * locally a leftover table masked it).
     */
    private function use_real_tables(): void
    {
        remove_filter('query', array( $this, '_create_temporary_tables' ));
        remove_filter('query', array( $this, '_drop_temporary_tables' ));
    }

    private function create_locations_table(): void
    {
        global $wpdb;

        $this->use_real_tables();

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

        $this->use_real_tables();

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
            'post_type'   => 'mhmrentiva_vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Parity Test Vehicle',
        ));
        update_post_meta($vehicle_id, '_mhmrentiva_license_plate', '34PAR100');

        // A booking inside the window the method asks for: today or later, and
        // in one of the three statuses the WHERE clause accepts.
        $this->booking_id = self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
            'post_title'  => 'Parity Test Booking',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', $vehicle_id);
        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+2 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_return_date', gmdate('Y-m-d', strtotime('+4 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_customer_name', 'Parity Customer');
    }

    public function tearDown(): void
    {
        $this->drop_locations_table();

        parent::tearDown();
    }

    /**
     * @return list<int> Booking IDs the report returned, sorted.
     */
    private function operation_ids(): array
    {
        $items = ReportRepository::get_upcoming_operations_paginated(1, 50, 30)['items'];
        $ids   = array_map(static fn (array $row): int => (int) $row['id'], $items);
        sort($ids);

        return $ids;
    }

    /**
     * Both branches must return the same bookings, and both must return some.
     */
    public function test_both_location_branches_return_the_same_bookings(): void
    {
        $without_table = $this->operation_ids();

        $this->assertContains(
            $this->booking_id,
            $without_table,
            'Fixture sanity: the seeded booking must be returned when the locations table is absent.'
        );

        $this->create_locations_table();
        $with_table = $this->operation_ids();

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
     *
     * This reads the statement the server was actually given, because that is
     * the only place a transposition is visible. `$wpdb->last_error` cannot do
     * this job: wpdb clears it at the start of every query, and this method
     * runs several more (the transfers probe, BookingEnricher) after the
     * rentals SELECT, so the error is gone before a test can read it. That is
     * exactly how the original bug survived a `last_error` assertion.
     */
    public function test_locations_table_is_bound_as_a_table_not_as_a_meta_key(): void
    {
        global $wpdb;

        $this->create_locations_table();

        $rentals = $this->capture_query(fn () => $this->operation_ids(), 'loc_origin');

        $this->assertNotSame('', $rentals, 'Fixture sanity: the locations-branch statement was never issued.');

        $table = $this->locations_table();

        $this->assertStringContainsString(
            'LEFT JOIN `' . $table . '` loc_origin',
            $rentals,
            'The locations table must be bound into the JOIN slot.'
        );
        $this->assertStringNotContainsString(
            "meta_key = '" . $table . "'",
            $rentals,
            'The locations table name was bound into a meta_key slot -- the arguments are transposed.'
        );
        $this->assertStringContainsString(
            "pm_pickup.post_id AND pm_pickup.meta_key = '_mhmrentiva_pickup_date'",
            $rentals,
            'The pickup-date meta key must be bound into the pm_pickup slot.'
        );
    }
}
