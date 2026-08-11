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

    public function test_old_calendar_renderer_is_silent_off_the_list_face(): void
    {
        $this->goToVehicleScreen( 'mhmrentiva_view=calendar' );

        ob_start();
        VehicleColumns::add_monthly_calendar();
        $this->assertSame( '', ob_get_clean() );
    }

    public function test_old_calendar_renderer_still_renders_on_the_list_face(): void
    {
        $this->goToVehicleScreen();

        ob_start();
        VehicleColumns::add_monthly_calendar();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'mhm-calendars', $html );
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
