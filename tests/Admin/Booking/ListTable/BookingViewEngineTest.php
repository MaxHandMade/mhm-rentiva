<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use WP_UnitTestCase;

/**
 * Task 3 of the Faz 2 view-engine plan — the mhmrentiva_view query var, its
 * whitelisted getter, the mhm-view-* body class, the segmented toggle
 * markup, and the guard that stops the old below-table calendar rendering
 * on non-list faces. Bookings only ever offers list|calendar (no cards
 * face — that is Vehicles-only).
 */
final class BookingViewEngineTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        BookingColumns::register();
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
    private function goToBookingScreen( string $query = '' ): void
    {
        $this->go_to( admin_url( 'edit.php' . ( '' !== $query ? '?' . $query : '' ) ) );
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_booking';
    }

    public function test_getter_accepts_calendar(): void
    {
        $this->goToBookingScreen( 'mhmrentiva_view=calendar' );
        $this->assertSame( 'calendar', BookingColumns::get_current_view() );
    }

    public function test_getter_rejects_cards_not_offered_on_bookings(): void
    {
        // 'cards' is a valid face on Vehicles but not Bookings — the
        // whitelist is per-screen, not shared.
        $this->goToBookingScreen( 'mhmrentiva_view=cards' );
        $this->assertSame( 'list', BookingColumns::get_current_view() );
    }

    public function test_getter_falls_back_to_list_for_bogus_values(): void
    {
        $this->goToBookingScreen( 'mhmrentiva_view=bogus' );
        $this->assertSame( 'list', BookingColumns::get_current_view() );
    }

    public function test_getter_defaults_to_list_when_param_absent(): void
    {
        $this->goToBookingScreen();
        $this->assertSame( 'list', BookingColumns::get_current_view() );
    }

    public function test_body_class_carries_the_calendar_face(): void
    {
        $this->goToBookingScreen( 'mhmrentiva_view=calendar' );
        $this->assertStringContainsString( 'mhm-view-calendar', BookingColumns::add_body_class( '' ) );
    }

    public function test_body_class_carries_no_face_class_on_list(): void
    {
        $this->goToBookingScreen();
        $classes = BookingColumns::add_body_class( '' );
        $this->assertStringNotContainsString( 'mhm-view-calendar', $classes );
        $this->assertStringNotContainsString( 'mhm-view-cards', $classes );
    }

    public function test_old_calendar_renderer_is_silent_off_the_list_face(): void
    {
        $this->goToBookingScreen( 'mhmrentiva_view=calendar' );

        ob_start();
        BookingColumns::add_booking_calendar();
        $this->assertSame( '', ob_get_clean() );
    }

    public function test_old_calendar_renderer_still_renders_on_the_list_face(): void
    {
        $this->goToBookingScreen();

        ob_start();
        BookingColumns::add_booking_calendar();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'mhm-calendars', $html );
    }

    public function test_toggle_renders_list_and_calendar_only_with_the_active_face_marked(): void
    {
        $this->goToBookingScreen( 'mhmrentiva_view=calendar' );

        ob_start();
        BookingColumns::render_view_toggle();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'rv-view-toggle', $html );
        $this->assertStringContainsString( 'rv-view-toggle__btn', $html );
        $this->assertStringNotContainsString( '>Cards<', $html );
        // Active segment carries is-active; the inactive List link does not.
        $this->assertMatchesRegularExpression( '/rv-view-toggle__btn is-active"[^>]*>Calendar</', $html );
        $this->assertDoesNotMatchRegularExpression( '/rv-view-toggle__btn is-active"[^>]*>List</', $html );
    }

    public function test_toggle_list_link_drops_the_view_param(): void
    {
        $this->goToBookingScreen( 'mhmrentiva_view=calendar' );

        ob_start();
        BookingColumns::render_view_toggle();
        $html = ob_get_clean();

        $this->assertMatchesRegularExpression( '/href="[^"]*"[^>]*>List</', $html );
        $this->assertStringNotContainsString( 'mhmrentiva_view=list', $html );
    }
}
