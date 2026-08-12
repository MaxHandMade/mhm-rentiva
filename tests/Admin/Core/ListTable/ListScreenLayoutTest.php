<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use MHMRentiva\Admin\Core\ListTable\ListScreenLayout;
use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * The server-side layout seam that replaced the relocation scripts.
 *
 * The regression this pins is a first-paint one: the blocks used to print
 * from `admin_notices` (above `.wrap`, above the page <h1>) and jQuery dragged
 * them into place at DOMContentLoaded, so the whole screen visibly jumped on
 * every load. What the unit level can hold is the wiring that makes the
 * server-rendered order possible — that the blocks hang off the two slots
 * inside `.wrap` and NOT off `admin_notices` any more, and that each slot
 * fires only where it belongs.
 */
final class ListScreenLayoutTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        ListScreenLayout::register();
    }

    public function tearDown(): void
    {
        global $pagenow, $post_type;
        $pagenow   = 'index.php';
        $post_type = null;
        parent::tearDown();
    }

    private function on_screen(string $type): void
    {
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = $type;
    }

    /**
     * `do_action()` spells the hook names out as literals — WPCS cannot check
     * the plugin prefix on a constant and warns on every such call. That is
     * only safe while the constants and the literals agree, so this pins them.
     */
    public function test_the_public_constants_match_the_hook_names_fired(): void
    {
        $this->assertSame('mhmrentiva_list_screen_header', ListScreenLayout::HEADER_ACTION);
        $this->assertSame('mhmrentiva_list_screen_face', ListScreenLayout::FACE_ACTION);
    }

    public function test_header_slot_fires_on_a_transformed_screen(): void
    {
        $this->on_screen('mhmrentiva_vehicle');

        $fired = 0;
        add_action(ListScreenLayout::HEADER_ACTION, static function () use (&$fired): void {
            ++$fired;
        });

        ob_start();
        ListScreenLayout::render_header(array());
        ob_get_clean();

        $this->assertSame(1, $fired);
    }

    public function test_header_slot_returns_the_status_links_untouched(): void
    {
        $this->on_screen('mhmrentiva_booking');

        $views = array( 'all' => '<a href="#">All</a>', 'publish' => '<a href="#">Published</a>' );

        ob_start();
        $returned = ListScreenLayout::render_header($views);
        ob_get_clean();

        $this->assertSame($views, $returned);
    }

    /**
     * `views_edit-{$post_type}` is filterable by any plugin hooked ahead of
     * ours; nothing obliges an earlier subscriber to hand back an array. A
     * typed `array $views` parameter would let a misbehaving plugin throw a
     * TypeError here and white-screen the whole list screen. This pins the
     * defensive contract: a non-array input is returned unchanged, without a
     * fatal, and without our blocks rendering.
     */
    public function test_non_array_views_input_passes_through_without_a_fatal(): void
    {
        $this->on_screen('mhmrentiva_vehicle');

        $fired = 0;
        add_action(ListScreenLayout::HEADER_ACTION, static function () use (&$fired): void {
            ++$fired;
        });

        ob_start();
        $returned = ListScreenLayout::render_header('not-an-array');
        $html     = (string) ob_get_clean();

        $this->assertSame('not-an-array', $returned);
        $this->assertSame(0, $fired, 'A malformed upstream filter must not trigger our header blocks.');
        $this->assertSame('', $html);
    }

    /**
     * Same contract for `null` and an object, the two other shapes a badly
     * behaved subscriber is likely to hand back.
     */
    public function test_null_and_object_views_input_also_pass_through_unchanged(): void
    {
        $this->on_screen('mhmrentiva_vehicle');

        $this->assertNull(ListScreenLayout::render_header(null));

        $object = (object) array( 'not' => 'an array' );
        $this->assertSame($object, ListScreenLayout::render_header($object));
    }

    public function test_header_slot_stays_silent_off_the_transformed_screens(): void
    {
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'post';

        $fired = 0;
        add_action(ListScreenLayout::HEADER_ACTION, static function () use (&$fired): void {
            ++$fired;
        });

        ob_start();
        ListScreenLayout::render_header(array());
        $html = (string) ob_get_clean();

        $this->assertSame(0, $fired);
        $this->assertSame('', $html);
    }

    public function test_face_slot_fires_only_for_the_bottom_tablenav(): void
    {
        $this->on_screen('mhmrentiva_vehicle');

        $fired = 0;
        add_action(ListScreenLayout::FACE_ACTION, static function () use (&$fired): void {
            ++$fired;
        });

        ob_start();
        ListScreenLayout::render_face('top');
        ob_get_clean();

        $this->assertSame(0, $fired, 'The face belongs below the table, not above it.');

        ob_start();
        ListScreenLayout::render_face('bottom');
        ob_get_clean();

        $this->assertSame(1, $fired);
    }

    /**
     * The notice placement script is what keeps core's DOMContentLoaded
     * relocation from moving anything: it does the same work early and stamps
     * each notice `below-h2`, the class core's own pass excludes.
     */
    public function test_header_slot_prints_the_notice_placement_script(): void
    {
        $this->on_screen('mhmrentiva_vehicle');

        ob_start();
        ListScreenLayout::render_header(array());
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('mhmRentivaPlaceAdminNotices', $html);
        $this->assertStringContainsString('below-h2', $html);
        $this->assertStringContainsString('div.updated, div.error, div.notice', $html);
    }

    public function test_face_slot_replays_the_notice_placement(): void
    {
        $this->on_screen('mhmrentiva_booking');

        ob_start();
        ListScreenLayout::render_face('bottom');
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('mhmRentivaPlaceAdminNotices', $html);
    }

    /**
     * The wiring itself. `admin_notices` fires above `.wrap` — a block
     * registered there cannot render in its final position, which is the whole
     * bug. Each of these blocks must hang off a seam instead.
     */
    public function test_vehicle_blocks_hang_off_the_seams_and_not_admin_notices(): void
    {
        VehicleColumns::register();

        $header = array( 'render_view_toggle', 'add_vehicle_stats_cards', 'category_chips' );
        foreach ($header as $method) {
            $callback = array( VehicleColumns::class, $method );
            $this->assertNotFalse(
                has_action(ListScreenLayout::HEADER_ACTION, $callback),
                "VehicleColumns::{$method}() must render from the header slot."
            );
            $this->assertFalse(
                has_action('admin_notices', $callback),
                "VehicleColumns::{$method}() must not render from admin_notices."
            );
        }

        foreach (array( 'render_calendar_view', 'render_cards_view' ) as $method) {
            $callback = array( VehicleColumns::class, $method );
            $this->assertNotFalse(has_action(ListScreenLayout::FACE_ACTION, $callback));
            $this->assertFalse(has_action('admin_notices', $callback));
        }
    }

    public function test_booking_blocks_hang_off_the_seams_and_not_admin_notices(): void
    {
        BookingColumns::register();

        $header = array( 'render_toolbar_row', 'add_booking_stats_cards', 'status_chips' );
        foreach ($header as $method) {
            $callback = array( BookingColumns::class, $method );
            $this->assertNotFalse(
                has_action(ListScreenLayout::HEADER_ACTION, $callback),
                "BookingColumns::{$method}() must render from the header slot."
            );
            $this->assertFalse(
                has_action('admin_notices', $callback),
                "BookingColumns::{$method}() must not render from admin_notices."
            );
        }

        $calendar = array( BookingColumns::class, 'render_calendar_view' );
        $this->assertNotFalse(has_action(ListScreenLayout::FACE_ACTION, $calendar));
        $this->assertFalse(has_action('admin_notices', $calendar));
    }

    /**
     * The toolbar row is the flex wrapper jQuery's wrapAll() used to build.
     * Without a Pro subscriber there is no wrapper and no toolbar container —
     * the neutral-seam contract BookingToolbarSeamTest pins, now also true of
     * the row that would have held it.
     */
    public function test_toolbar_row_is_just_the_toggle_without_a_subscriber(): void
    {
        $this->on_screen('mhmrentiva_booking');

        ob_start();
        BookingColumns::render_toolbar_row();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('rv-view-toggle', $html);
        $this->assertStringNotContainsString('rv-bkl-toolbar-row', $html);
        $this->assertStringNotContainsString('rv-bkl-toolbar"', $html);
    }

    public function test_toolbar_row_wraps_the_toggle_and_the_subscriber_actions(): void
    {
        $this->on_screen('mhmrentiva_booking');

        add_filter('mhmrentiva_booking_list_toolbar_actions', static function (array $actions): array {
            $actions[] = array(
                'label' => 'Export',
                'url'   => admin_url('admin.php?page=mhm-rentiva-export'),
            );
            return $actions;
        });

        ob_start();
        BookingColumns::render_toolbar_row();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('rv-bkl-toolbar-row', $html);
        $this->assertStringContainsString('rv-view-toggle', $html);
        $this->assertStringContainsString('rv-bkl-toolbar', $html);

        // Toggle first, seam actions second -- the order jQuery's
        // `$toggle.add($toolbar).wrapAll()` produced.
        $this->assertLessThan(
            (int) strpos($html, 'rv-bkl-toolbar"'),
            (int) strpos($html, 'rv-view-toggle"')
        );
    }
}
