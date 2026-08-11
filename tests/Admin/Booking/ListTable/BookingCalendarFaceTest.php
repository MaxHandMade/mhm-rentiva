<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use MHMRentiva\Admin\Core\Utilities\OccupancyMapService;
use WP_UnitTestCase;

/**
 * Faz 2 Task 5 — the Bookings Calendar face (`BookingColumns::render_calendar_view()`
 * + its row-source query `get_calendar_row_source()`), replacing the retired
 * below-table aggregate grid. Negative controls for the retirement itself
 * live in BookingViewEngineTest (Task 3's file); this file covers the new
 * face's own behavior: row source, status-chip filtering, the row cap, the
 * vehicle-less note, and the query budget.
 *
 * @covers \MHMRentiva\Admin\Booking\ListTable\BookingColumns
 */
final class BookingCalendarFaceTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        BookingColumns::register();
        OccupancyMapService::reset_memo();
    }

    public function tearDown(): void
    {
        global $pagenow, $post_type;
        $pagenow   = 'index.php';
        $post_type = null;
        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        remove_all_filters( 'mhmrentiva_occupancy_matrix_row_cap' );
        parent::tearDown();
    }

    /**
     * go_to() resets `$pagenow`/`current_screen` (see WP_UnitTestCase_Base::go_to()),
     * so the screen globals render_calendar_view() gates on must be
     * (re)applied AFTER it, not before.
     */
    private function goToCalendarFace( string $extraQuery = '' ): void
    {
        $query = 'mhmrentiva_view=calendar' . ( '' !== $extraQuery ? '&' . $extraQuery : '' );
        $this->go_to( admin_url( 'edit.php?' . $query ) );
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_booking';
    }

    private function makeVehicle( string $title ): \WP_Post
    {
        $id = self::factory()->post->create(
            array(
                'post_type'  => 'mhmrentiva_vehicle',
                'post_title' => $title,
            )
        );
        return get_post( $id );
    }

    private function makeBooking( int $vehicle_id, string $status, string $pickup, string $dropoff ): int
    {
        $booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking, '_mhmrentiva_status', $status );
        update_post_meta( $booking, '_mhmrentiva_vehicle_id', $vehicle_id );
        update_post_meta( $booking, '_mhmrentiva_pickup_date', $pickup );
        update_post_meta( $booking, '_mhmrentiva_dropoff_date', $dropoff );
        // Non-WC customer fields so BookingQueryHelper never reaches its
        // WooCommerce fallback branch (declared query-budget exception,
        // same as FleetOccupancyMatrixTest's fixtures).
        update_post_meta( $booking, '_mhmrentiva_customer_first_name', 'Ada' );
        update_post_meta( $booking, '_mhmrentiva_customer_last_name', 'Lovelace' );
        update_post_meta( $booking, '_mhmrentiva_customer_email', 'ada@example.test' );
        update_post_meta( $booking, '_mhmrentiva_customer_phone', '+90 555 000 0000' );
        return $booking;
    }

    private function render(): string
    {
        ob_start();
        BookingColumns::render_calendar_view();
        return (string) ob_get_clean();
    }

    // --- Negative control: matrix marker present, old grid class absent ----

    public function test_calendar_face_renders_the_shared_matrix_not_the_old_grid(): void
    {
        $this->goToCalendarFace();

        $html = $this->render();

        $this->assertStringContainsString( 'mhm-occupancy-matrix', $html );
        $this->assertStringNotContainsString( 'booking-calendar-page', $html );
    }

    public function test_calendar_face_is_silent_on_the_list_face(): void
    {
        $this->go_to( admin_url( 'edit.php' ) );
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_booking';

        $this->assertSame( '', $this->render() );
    }

    // --- Test 1: row source -------------------------------------------------

    public function test_rows_are_only_the_in_month_booked_vehicles_title_asc(): void
    {
        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );
        $day   = sprintf( '%04d-%02d-15', $year, $month );

        $vehicleZebra = $this->makeVehicle( 'Zebra' );
        $vehicleAlpha = $this->makeVehicle( 'Alpha' );
        $vehicleIdle  = $this->makeVehicle( 'Idle' ); // no in-month booking

        $this->makeBooking( $vehicleZebra->ID, 'pending', $day, $day );
        $this->makeBooking( $vehicleAlpha->ID, 'confirmed', $day, $day );
        // Out-of-month booking for the "idle" vehicle -- must not produce a row.
        $outOfMonthMonth = 1 === $month ? 12 : $month - 1;
        $outOfMonthYear  = 1 === $month ? $year - 1 : $year;
        $outOfMonth      = sprintf( '%04d-%02d-01', $outOfMonthYear, $outOfMonthMonth );
        $this->makeBooking( $vehicleIdle->ID, 'confirmed', $outOfMonth, $outOfMonth );

        $this->goToCalendarFace();
        $html = $this->render();

        $alphaPos = strpos( $html, 'Alpha' );
        $zebraPos = strpos( $html, 'Zebra' );

        $this->assertNotFalse( $alphaPos, 'Alpha (in-month) must have a row.' );
        $this->assertNotFalse( $zebraPos, 'Zebra (in-month) must have a row.' );
        $this->assertLessThan( $zebraPos, $alphaPos, 'Rows must be title ASC (Alpha before Zebra).' );
        $this->assertStringNotContainsString( 'Idle', $html );
    }

    // --- Test 2: status-chip filter narrows the ROW query, not just cells --

    public function test_status_chip_excludes_the_non_matching_vehicles_row_entirely(): void
    {
        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );
        $day   = sprintf( '%04d-%02d-15', $year, $month );

        $pendingVehicle   = $this->makeVehicle( 'Pending Car' );
        $confirmedVehicle = $this->makeVehicle( 'Confirmed Car' );

        $this->makeBooking( $pendingVehicle->ID, 'pending', $day, $day );
        $this->makeBooking( $confirmedVehicle->ID, 'confirmed', $day, $day );

        $this->goToCalendarFace( 'mhmrentiva_booking_status=pending' );
        $html = $this->render();

        $this->assertStringContainsString( 'Pending Car', $html );
        $this->assertStringNotContainsString( 'Confirmed Car', $html );
    }

    // --- Test 3: cap, via the filterable knob -------------------------------

    public function test_cap_trims_rows_and_prints_the_notice(): void
    {
        add_filter(
            'mhmrentiva_occupancy_matrix_row_cap',
            static function () {
                return 2;
            }
        );

        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );
        $day   = sprintf( '%04d-%02d-15', $year, $month );

        foreach ( array( 'V1', 'V2', 'V3' ) as $title ) {
            $vehicle = $this->makeVehicle( $title );
            $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );
        }

        $this->goToCalendarFace();
        $html = $this->render();

        $this->assertStringContainsString( 'Showing first 2 vehicles', $html );
    }

    public function test_no_cap_notice_when_the_fleet_fits_under_the_cap(): void
    {
        add_filter(
            'mhmrentiva_occupancy_matrix_row_cap',
            static function () {
                return 2;
            }
        );

        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );
        $day   = sprintf( '%04d-%02d-15', $year, $month );

        $vehicle = $this->makeVehicle( 'Solo' );
        $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );

        $this->goToCalendarFace();
        $html = $this->render();

        $this->assertStringNotContainsString( 'Showing first', $html );
    }

    // --- Test 4: vehicle-less note ------------------------------------------

    public function test_vehicleless_note_is_absent_when_every_booking_has_a_vehicle(): void
    {
        $month   = (int) gmdate( 'n' );
        $year    = (int) gmdate( 'Y' );
        $day     = sprintf( '%04d-%02d-15', $year, $month );
        $vehicle = $this->makeVehicle( 'Has Vehicle' );
        $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );

        $this->goToCalendarFace();
        $html = $this->render();

        $this->assertStringNotContainsString( 'has no vehicle assigned', $html );
        $this->assertStringNotContainsString( 'bookings have no vehicle assigned', $html );
    }

    public function test_vehicleless_note_singular_for_one_unassigned_booking(): void
    {
        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );
        $day   = sprintf( '%04d-%02d-15', $year, $month );

        $booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking, '_mhmrentiva_status', 'confirmed' );
        update_post_meta( $booking, '_mhmrentiva_pickup_date', $day );
        update_post_meta( $booking, '_mhmrentiva_dropoff_date', $day );
        // Deliberately no vehicle id meta -- transfer-style booking.

        $this->goToCalendarFace();
        $html = $this->render();

        $this->assertStringContainsString( '1 booking has no vehicle assigned', $html );
    }

    public function test_vehicleless_note_plural_for_two_unassigned_bookings(): void
    {
        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );
        $day   = sprintf( '%04d-%02d-15', $year, $month );

        foreach ( range( 1, 2 ) as $i ) {
            $booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
            update_post_meta( $booking, '_mhmrentiva_status', 'confirmed' );
            update_post_meta( $booking, '_mhmrentiva_pickup_date', $day );
            update_post_meta( $booking, '_mhmrentiva_dropoff_date', $day );
        }

        $this->goToCalendarFace();
        $html = $this->render();

        $this->assertStringContainsString( '2 bookings have no vehicle assigned', $html );
    }

    // --- Test 5: query budget is constant -----------------------------------

    public function test_query_budget_does_not_scale_with_vehicle_count(): void
    {
        global $wpdb;

        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );
        $day   = sprintf( '%04d-%02d-15', $year, $month );

        $smallBookings = array();
        for ( $i = 0; $i < 2; $i++ ) {
            $vehicle         = $this->makeVehicle( 'Small ' . $i );
            $smallBookings[] = $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );
        }
        foreach ( $smallBookings as $bid ) {
            clean_post_cache( $bid );
        }

        $this->goToCalendarFace();
        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        wp_cache_flush();
        $beforeSmall  = $wpdb->num_queries;
        $this->render();
        $queriesSmall = $wpdb->num_queries - $beforeSmall;

        $largeBookings = array();
        for ( $i = 0; $i < 5; $i++ ) {
            $vehicle         = $this->makeVehicle( 'Large ' . $i );
            $largeBookings[] = $this->makeBooking( $vehicle->ID, 'confirmed', $day, $day );
        }
        foreach ( $largeBookings as $bid ) {
            clean_post_cache( $bid );
        }

        $this->goToCalendarFace();
        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        wp_cache_flush();
        $beforeLarge  = $wpdb->num_queries;
        $this->render();
        $queriesLarge = $wpdb->num_queries - $beforeLarge;

        $this->assertLessThanOrEqual(
            $queriesSmall + 1,
            $queriesLarge,
            "Query count must not scale with vehicle/booking count (small={$queriesSmall}, large={$queriesLarge})."
        );
    }
}
