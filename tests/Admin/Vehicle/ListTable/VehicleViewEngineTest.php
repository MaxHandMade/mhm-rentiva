<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\ListTable;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * Task 3 of the Faz 2 view-engine plan — the mhmrentiva_view query var, its
 * whitelisted getter, the mhm-view-* body class, the segmented toggle
 * markup, and the guard that stops the old below-table calendar rendering
 * on non-list faces. Vehicles offers all three faces: list|cards|calendar.
 */
final class VehicleViewEngineTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        VehicleColumns::register();
    }

    public function tearDown(): void
    {
        global $pagenow, $post_type;
        $pagenow   = 'index.php';
        $post_type = null;
        parent::tearDown();
    }

    /**
     * go_to() resets `$pagenow`/`current_screen` as part of simulating a
     * fresh request (see WP_UnitTestCase_Base::go_to()), so the screen
     * globals the render methods gate on must be (re)applied AFTER it, not
     * before — setting them first only means go_to() immediately wipes them.
     */
    private function goToVehicleScreen( string $query = '' ): void
    {
        $this->go_to( admin_url( 'edit.php' . ( '' !== $query ? '?' . $query : '' ) ) );
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_vehicle';
    }

    public function test_getter_accepts_cards(): void
    {
        $this->goToVehicleScreen( 'mhmrentiva_view=cards' );
        $this->assertSame( 'cards', VehicleColumns::get_current_view() );
    }

    public function test_getter_accepts_calendar(): void
    {
        $this->goToVehicleScreen( 'mhmrentiva_view=calendar' );
        $this->assertSame( 'calendar', VehicleColumns::get_current_view() );
    }

    public function test_getter_falls_back_to_list_for_bogus_values(): void
    {
        $this->goToVehicleScreen( 'mhmrentiva_view=bogus' );
        $this->assertSame( 'list', VehicleColumns::get_current_view() );
    }

    public function test_getter_defaults_to_list_when_param_absent(): void
    {
        $this->goToVehicleScreen();
        $this->assertSame( 'list', VehicleColumns::get_current_view() );
    }

    public function test_body_class_carries_the_calendar_face(): void
    {
        $this->goToVehicleScreen( 'mhmrentiva_view=calendar' );
        $this->assertStringContainsString( 'mhm-view-calendar', VehicleColumns::add_body_class( '' ) );
    }

    public function test_body_class_carries_the_cards_face(): void
    {
        $this->goToVehicleScreen( 'mhmrentiva_view=cards' );
        $this->assertStringContainsString( 'mhm-view-cards', VehicleColumns::add_body_class( '' ) );
    }

    public function test_body_class_carries_no_face_class_on_list(): void
    {
        $this->goToVehicleScreen();
        $classes = VehicleColumns::add_body_class( '' );
        $this->assertStringNotContainsString( 'mhm-view-calendar', $classes );
        $this->assertStringNotContainsString( 'mhm-view-cards', $classes );
    }

    /**
     * Task 4 replaced add_monthly_calendar() with render_calendar_view()
     * (shared FleetOccupancyMatrix renderer) — the guard direction flips:
     * the OLD renderer only rendered on 'list'; the NEW one only renders on
     * 'calendar'.
     */
    public function test_calendar_face_renderer_is_silent_on_the_list_face(): void
    {
        $this->goToVehicleScreen();

        ob_start();
        VehicleColumns::render_calendar_view();
        $this->assertSame( '', ob_get_clean() );
    }

    public function test_calendar_face_renderer_renders_the_matrix_on_the_calendar_face(): void
    {
        $this->goToVehicleScreen( 'mhmrentiva_view=calendar' );

        ob_start();
        VehicleColumns::render_calendar_view();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'mhm-calendars', $html );
        $this->assertStringContainsString( 'mhm-occupancy-matrix', $html );
    }

    /**
     * Negative controls (Task 4 retirement, spec-required, same commit
     * series): the old monthly-calendar trio and its date-format helper
     * are gone — normalize_date()'s only caller was get_monthly_bookings(),
     * which dies with it, and grep confirmed no other production caller.
     */
    public function test_the_old_monthly_calendar_trio_is_gone(): void
    {
        $this->assertFalse( method_exists( VehicleColumns::class, 'add_monthly_calendar' ) );
        $this->assertFalse( method_exists( VehicleColumns::class, 'get_monthly_bookings' ) );
        $this->assertFalse( method_exists( VehicleColumns::class, 'get_calendar_vehicles' ) );
    }

    public function test_normalize_date_helper_died_with_its_only_caller(): void
    {
        $this->assertFalse( method_exists( VehicleColumns::class, 'normalize_date' ) );
    }

    /**
     * Task 4 left Bookings' own old calendar renderer and day-map builder
     * untouched (Task 5 owned their retirement). Task 5 has since landed —
     * both are gone now; the negative control lives with the rest of
     * Task 5's retirement pins in BookingViewEngineTest.
     */
    public function test_the_bookings_calendar_renderer_was_retired_by_task_5(): void
    {
        $this->assertFalse( method_exists( \MHMRentiva\Admin\Booking\ListTable\BookingColumns::class, 'add_booking_calendar' ) );
        $this->assertFalse( method_exists( \MHMRentiva\Admin\Booking\ListTable\BookingColumns::class, 'get_booking_calendar_days' ) );
    }

    /**
     * AutoCancel relocation: the old add_monthly_calendar() ran this
     * throttled fallback only on the list face (by accident of where the
     * calendar itself was gated); render_calendar_view() now runs it
     * BEFORE the face branch, so it fires on every face including list.
     */
    public function test_autocancel_throttle_fires_on_the_list_face_load(): void
    {
        delete_transient( 'mhmrentiva_autocancel_ran' );
        $this->goToVehicleScreen();

        $this->assertFalse( get_transient( 'mhmrentiva_autocancel_ran' ) );

        ob_start();
        VehicleColumns::render_calendar_view();
        ob_get_clean();

        $this->assertNotFalse( get_transient( 'mhmrentiva_autocancel_ran' ), 'AutoCancel throttle must be set on every face load, including list.' );
    }

    public function test_autocancel_throttle_also_fires_on_the_calendar_face_load(): void
    {
        delete_transient( 'mhmrentiva_autocancel_ran' );
        $this->goToVehicleScreen( 'mhmrentiva_view=calendar' );

        ob_start();
        VehicleColumns::render_calendar_view();
        ob_get_clean();

        $this->assertNotFalse( get_transient( 'mhmrentiva_autocancel_ran' ) );
    }

    /**
     * Bounds rule convergence: current year ± 10 (BookingColumns' rule),
     * replacing the old vehicles-only hardcoded 2020-2030 clamp. An
     * out-of-range year in the query string must not leak into the
     * rendered month label.
     */
    public function test_out_of_range_year_clamps_to_the_current_year(): void
    {
        $bogus_year = (int) gmdate( 'Y' ) + 50;
        $this->goToVehicleScreen( 'mhmrentiva_view=calendar&mhmrentiva_year=' . $bogus_year );

        ob_start();
        VehicleColumns::render_calendar_view();
        $html = ob_get_clean();

        $this->assertStringNotContainsString( (string) $bogus_year, $html );
        $this->assertStringContainsString( (string) gmdate( 'Y' ), $html );
    }

    public function test_toggle_renders_all_three_faces_with_the_active_one_marked(): void
    {
        $this->goToVehicleScreen( 'mhmrentiva_view=cards' );

        ob_start();
        VehicleColumns::render_view_toggle();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'rv-view-toggle', $html );
        $this->assertStringContainsString( '>List<', $html );
        $this->assertStringContainsString( '>Cards<', $html );
        $this->assertStringContainsString( '>Calendar<', $html );
        $this->assertMatchesRegularExpression( '/rv-view-toggle__btn is-active"[^>]*>Cards</', $html );
        $this->assertDoesNotMatchRegularExpression( '/rv-view-toggle__btn is-active"[^>]*>List</', $html );
    }

    public function test_toggle_list_link_drops_the_view_param(): void
    {
        $this->goToVehicleScreen( 'mhmrentiva_view=cards' );

        ob_start();
        VehicleColumns::render_view_toggle();
        $html = ob_get_clean();

        $this->assertMatchesRegularExpression( '/href="[^"]*"[^>]*>List</', $html );
        $this->assertStringNotContainsString( 'mhmrentiva_view=list', $html );
    }
}
