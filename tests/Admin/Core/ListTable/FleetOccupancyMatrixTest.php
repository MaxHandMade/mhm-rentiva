<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\ListTable;

use MHMRentiva\Admin\Core\ListTable\FleetOccupancyMatrix;
use MHMRentiva\Admin\Core\Utilities\OccupancyMapService;
use WP_UnitTestCase;

/**
 * Faz 2 Task 4 — the shared FleetOccupancyMatrix renderer both the Vehicles
 * Calendar face (this task) and, from Task 5, the Bookings Calendar face
 * paint through.
 *
 * @covers \MHMRentiva\Admin\Core\ListTable\FleetOccupancyMatrix
 */
final class FleetOccupancyMatrixTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        OccupancyMapService::reset_memo();
    }

    public function tearDown(): void
    {
        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        parent::tearDown();
    }

    private function makeVehicle(): \WP_Post
    {
        $id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
        return get_post( $id );
    }

    private function makeBooking( int $vehicle_id, string $status, string $pickup, string $dropoff ): int
    {
        $booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking, '_mhmrentiva_status', $status );
        update_post_meta( $booking, '_mhmrentiva_vehicle_id', $vehicle_id );
        update_post_meta( $booking, '_mhmrentiva_pickup_date', $pickup );
        update_post_meta( $booking, '_mhmrentiva_dropoff_date', $dropoff );
        // Non-WC customer fields set directly so BookingQueryHelper never
        // reaches its WooCommerce fallback branch (declared query-budget
        // exception; these tests deliberately avoid it).
        update_post_meta( $booking, '_mhmrentiva_customer_first_name', 'Ada' );
        update_post_meta( $booking, '_mhmrentiva_customer_last_name', 'Lovelace' );
        update_post_meta( $booking, '_mhmrentiva_customer_email', 'ada@example.test' );
        update_post_meta( $booking, '_mhmrentiva_customer_phone', '+90 555 000 0000' );
        return $booking;
    }

    private function render( array $vehicles, int $month, int $year, array $opts = array() ): string
    {
        ob_start();
        FleetOccupancyMatrix::render( $vehicles, $month, $year, $opts );
        return (string) ob_get_clean();
    }

    // --- Test 1: filter-then-reduce (the spec-audit BLOCKER fix) ----------

    public function test_no_filter_the_stronger_status_wins_the_cell(): void
    {
        $vehicle = $this->makeVehicle();
        $month   = (int) gmdate( 'n' );
        $year    = (int) gmdate( 'Y' );
        $day     = sprintf( '%04d-%02d-15', $year, $month );

        $this->makeBooking( $vehicle->ID, 'pending', $day, $day );
        $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );

        $html = $this->render( array( $vehicle ), $month, $year );

        $this->assertMatchesRegularExpression( '/class="day-cell booked status-confirmed"/', $html );
    }

    public function test_filter_statuses_preserves_raw_data_pending_survives_the_filter(): void
    {
        $vehicle = $this->makeVehicle();
        $month   = (int) gmdate( 'n' );
        $year    = (int) gmdate( 'Y' );
        $day     = sprintf( '%04d-%02d-15', $year, $month );

        $this->makeBooking( $vehicle->ID, 'pending', $day, $day );
        $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );

        $html = $this->render( array( $vehicle ), $month, $year, array( 'filter_statuses' => array( 'pending' ) ) );

        // The raw pending entry survives the filter even though it lost the
        // unfiltered precedence race against 'confirmed' above — filtering
        // happens BEFORE reduce(), not the other way round.
        $this->assertMatchesRegularExpression( '/class="day-cell booked status-pending"/', $html );
        // The legend always lists every status (including 'confirmed'); the
        // assertion that matters is that no CELL is painted confirmed.
        $this->assertDoesNotMatchRegularExpression( '/class="day-cell booked status-confirmed"/', $html );
    }

    // --- Fix round 1, Critical #1: multi-booking popup payload --------------
    // Binding acceptance criterion: "on a day with multiple bookings, the
    // popup lists ALL of them." The cell still PAINTS the reduce() winner's
    // color, but the popup payload (the 'bookings' JSON attribute
    // booking-popup.js's showSingleBooking()/showMultiBooking() read) must
    // carry every booking touching that cell, post-filter.

    public function test_multi_booking_cell_payload_contains_every_booking_id(): void
    {
        $vehicle = $this->makeVehicle();
        $month   = (int) gmdate( 'n' );
        $year    = (int) gmdate( 'Y' );
        $day     = sprintf( '%04d-%02d-15', $year, $month );

        $pending_id   = $this->makeBooking( $vehicle->ID, 'pending', $day, $day );
        $confirmed_id = $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );

        $html = $this->render( array( $vehicle ), $month, $year );

        $this->assertStringContainsString( '&quot;booking_id&quot;:' . $pending_id, $html );
        $this->assertStringContainsString( '&quot;booking_id&quot;:' . $confirmed_id, $html );
    }

    public function test_multi_booking_cell_payload_is_filtered_by_filter_statuses(): void
    {
        $vehicle = $this->makeVehicle();
        $month   = (int) gmdate( 'n' );
        $year    = (int) gmdate( 'Y' );
        $day     = sprintf( '%04d-%02d-15', $year, $month );

        $pending_id   = $this->makeBooking( $vehicle->ID, 'pending', $day, $day );
        $confirmed_id = $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );

        $html = $this->render( array( $vehicle ), $month, $year, array( 'filter_statuses' => array( 'pending' ) ) );

        $this->assertStringContainsString( '&quot;booking_id&quot;:' . $pending_id, $html );
        $this->assertStringNotContainsString( '&quot;booking_id&quot;:' . $confirmed_id, $html );
    }

    // --- Test 2: PII gate ---------------------------------------------------

    public function test_customer_fields_are_absent_for_a_user_without_edit_others_posts(): void
    {
        $vehicle = $this->makeVehicle();
        $month   = (int) gmdate( 'n' );
        $year    = (int) gmdate( 'Y' );
        $day     = sprintf( '%04d-%02d-15', $year, $month );
        $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );

        wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
        $html = $this->render( array( $vehicle ), $month, $year );

        $this->assertStringNotContainsString( 'data-customer-name', $html );
        $this->assertStringNotContainsString( 'data-customer-email', $html );
        $this->assertStringNotContainsString( 'data-customer-phone', $html );
        $this->assertStringNotContainsString( 'Ada', $html );
        $this->assertStringNotContainsString( 'ada@example.test', $html );
        // The badge/dates payload is still there.
        $this->assertStringContainsString( 'data-booking-id', $html );
        $this->assertStringContainsString( 'data-status=', $html );
        $this->assertStringContainsString( 'data-start-date', $html );
        $this->assertStringContainsString( 'data-end-date', $html );
    }

    public function test_customer_fields_are_present_for_a_user_with_edit_others_posts(): void
    {
        $vehicle = $this->makeVehicle();
        $month   = (int) gmdate( 'n' );
        $year    = (int) gmdate( 'Y' );
        $day     = sprintf( '%04d-%02d-15', $year, $month );
        $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );

        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        $html = $this->render( array( $vehicle ), $month, $year );

        $this->assertStringContainsString( 'data-customer-name="Ada Lovelace"', $html );
        $this->assertStringContainsString( 'data-customer-email="ada@example.test"', $html );
    }

    // --- Test 3: blocked beats booking --------------------------------------

    public function test_blocked_day_wins_over_a_booking_on_the_same_cell(): void
    {
        $vehicle = $this->makeVehicle();
        $month   = (int) gmdate( 'n' );
        $year    = (int) gmdate( 'Y' );
        $day     = sprintf( '%04d-%02d-15', $year, $month );

        $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );
        update_post_meta( $vehicle->ID, '_mhmrentiva_blocked_dates', wp_json_encode( array( $day ) ) );

        $html = $this->render( array( $vehicle ), $month, $year );

        $this->assertMatchesRegularExpression( '/class="day-cell blocked-day"[^>]*>/', $html );
        $this->assertDoesNotMatchRegularExpression( '/class="day-cell booked status-confirmed"/', $html );
    }

    // --- in_progress gets its own class, not a fallback to pending ---------

    public function test_in_progress_gets_its_own_status_class(): void
    {
        $vehicle = $this->makeVehicle();
        $month   = (int) gmdate( 'n' );
        $year    = (int) gmdate( 'Y' );
        $day     = sprintf( '%04d-%02d-15', $year, $month );
        $this->makeBooking( $vehicle->ID, 'in_progress', $day, $day );

        $html = $this->render( array( $vehicle ), $month, $year );

        $this->assertStringContainsString( 'status-in_progress', $html );
    }

    // --- Legend vocabulary --------------------------------------------------

    public function test_legend_carries_the_canonical_vocabulary(): void
    {
        $vehicle = $this->makeVehicle();
        $html    = $this->render( array( $vehicle ), (int) gmdate( 'n' ), (int) gmdate( 'Y' ) );

        $this->assertStringContainsString( '>Available<', $html );
        $this->assertStringContainsString( '>Pending<', $html );
        $this->assertStringContainsString( '>Confirmed<', $html );
        $this->assertStringContainsString( '>In Progress<', $html );
        $this->assertStringContainsString( '>Completed<', $html );
        $this->assertStringContainsString( '>Blocked Day<', $html );
    }

    // --- Test 4: month nav preserves view + filters -------------------------

    public function test_month_nav_links_preserve_the_calendar_view_and_current_filters(): void
    {
        $this->go_to( admin_url( 'edit.php?post_type=mhmrentiva_vehicle&mhmrentiva_view=calendar&mhmrentiva_lifecycle_filter=active' ) );

        $vehicle = $this->makeVehicle();
        $html    = $this->render( array( $vehicle ), 6, 2026 );

        $this->assertMatchesRegularExpression( '/href="[^"]*mhmrentiva_view=calendar[^"]*"[^>]*prev-btn/', $html );
        $this->assertMatchesRegularExpression( '/href="[^"]*mhmrentiva_lifecycle_filter=active[^"]*"[^>]*prev-btn/', $html );
        $this->assertMatchesRegularExpression( '/href="[^"]*mhmrentiva_view=calendar[^"]*"[^>]*next-btn/', $html );
        $this->assertStringContainsString( 'mhmrentiva_month=7', $html );
        $this->assertStringContainsString( 'mhmrentiva_month=5', $html );
    }

    public function test_month_nav_rolls_over_the_year_at_january_and_december(): void
    {
        $vehicle = $this->makeVehicle();
        $html    = $this->render( array( $vehicle ), 1, 2026 );

        $this->assertStringContainsString( 'mhmrentiva_month=12', $html );
        $this->assertStringContainsString( 'mhmrentiva_year=2025', $html );
    }

    // --- Test 5: query budget is constant, not O(n) -------------------------
    //
    // Fix round 1, Critical #2: the fixture factory's wp_insert_post() calls
    // incidentally warm the post OBJECT cache (as opposed to the meta cache
    // this class explicitly primes), which used to make this test pass even
    // though get_post_field('post_date', ...) in production hits a cold
    // cache -- one query per distinct booking. clean_post_cache() on every
    // booking id before measuring removes that false-green: the assertion
    // now actually exercises the cold-cache path _prime_post_caches() fixes.

    public function test_query_budget_does_not_scale_with_vehicle_count(): void
    {
        global $wpdb;

        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );
        $day   = sprintf( '%04d-%02d-15', $year, $month );

        $small           = array( $this->makeVehicle(), $this->makeVehicle() );
        $small_bookings  = array();
        foreach ( $small as $v ) {
            $small_bookings[] = $this->makeBooking( $v->ID, 'confirmed', $day, $day );
        }
        foreach ( $small_bookings as $bid ) {
            clean_post_cache( $bid );
        }

        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        // invalidate() is a raw SQL DELETE on wp_options (documented limit
        // in its own docblock) -- it does not evict the in-memory
        // 'options' object-cache group get_transient()/get_option() read
        // from. Without a full flush, a second get_map() call for this
        // SAME date window (both scenarios share one calendar month) would
        // read the first scenario's now-stale cached transient instead of
        // re-querying, making the two measurements incomparable.
        wp_cache_flush();
        $before_small = $wpdb->num_queries;
        $this->render( $small, $month, $year );
        $queries_small = $wpdb->num_queries - $before_small;

        $large          = array();
        $large_bookings = array();
        for ( $i = 0; $i < 5; $i++ ) {
            $v                = $this->makeVehicle();
            $large[]          = $v;
            $large_bookings[] = $this->makeBooking( $v->ID, 'confirmed', $day, $day );
        }
        foreach ( $large_bookings as $bid ) {
            clean_post_cache( $bid );
        }

        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        wp_cache_flush();
        $before_large = $wpdb->num_queries;
        $this->render( $large, $month, $year );
        $queries_large = $wpdb->num_queries - $before_large;

        $this->assertLessThanOrEqual(
            $queries_small + 1,
            $queries_large,
            "Query count must not scale with vehicle/booking count (small={$queries_small}, large={$queries_large})."
        );
    }

    // --- enable_block_toggle / show_plate opts -------------------------------

    public function test_block_toggle_data_attributes_are_withheld_when_disabled(): void
    {
        $vehicle = $this->makeVehicle();
        $html    = $this->render( array( $vehicle ), (int) gmdate( 'n' ), (int) gmdate( 'Y' ), array( 'enable_block_toggle' => false ) );

        $this->assertStringNotContainsString( 'data-vehicle-id', $html );
    }

    public function test_block_toggle_data_attributes_are_present_when_enabled(): void
    {
        $vehicle = $this->makeVehicle();
        $html    = $this->render( array( $vehicle ), (int) gmdate( 'n' ), (int) gmdate( 'Y' ), array( 'enable_block_toggle' => true ) );

        $this->assertStringContainsString( 'data-vehicle-id="' . $vehicle->ID . '"', $html );
    }

    public function test_plate_is_withheld_when_show_plate_is_false(): void
    {
        $vehicle = $this->makeVehicle();
        update_post_meta( $vehicle->ID, '_mhmrentiva_license_plate', '34 ZZ 999' );

        $html = $this->render( array( $vehicle ), (int) gmdate( 'n' ), (int) gmdate( 'Y' ), array( 'show_plate' => false ) );

        $this->assertStringNotContainsString( '34 ZZ 999', $html );
    }

    // --- Table marker class used by the negative-control test ---------------

    public function test_output_carries_the_matrix_table_marker_class(): void
    {
        $vehicle = $this->makeVehicle();
        $html    = $this->render( array( $vehicle ), (int) gmdate( 'n' ), (int) gmdate( 'Y' ) );

        $this->assertStringContainsString( 'mhm-occupancy-matrix', $html );
    }
}
