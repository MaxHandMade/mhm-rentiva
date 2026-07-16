<?php

namespace MHMRentiva\Tests\Core\Dashboard;

use MHMRentiva\Admin\Frontend\Shortcodes\Account\UserDashboard;
use MHMRentiva\Core\Dashboard\DashboardContext;
use MHMRentiva\Core\Dashboard\DashboardNavigation;
use WP_UnitTestCase;

/**
 * The customer dashboard must not offer a Messages tab Lite cannot render.
 *
 * The Messages panel is produced by `[rentiva_messages hide_nav="1"]`, whose
 * class (AccountMessages) is carved out of Lite. The shortcode registry drops
 * the tag to match -- but an unregistered shortcode degrades to its own literal
 * source text, so an unfiltered tab printed the raw string
 * `[rentiva_messages hide_nav="1"]` to every Lite customer who clicked it.
 *
 * @package MHMRentiva\Tests\Core\Dashboard
 */
class DashboardNavigationLiteSeamTest extends WP_UnitTestCase
{

    /**
     * Guards the premise: if AccountMessages ever ships in Lite, the expectations
     * below would be asserting the wrong thing.
     */
    public function test_messages_shortcode_class_is_absent_from_lite(): void
    {
        $this->assertFalse(class_exists('MHMRentiva\Admin\Frontend\Shortcodes\Account\AccountMessages'));
    }

    public function test_customer_nav_has_no_messages_item_in_lite(): void
    {
        $items = DashboardNavigation::get_items('customer');

        $this->assertArrayNotHasKey('messages', $items, 'Lite customers must not see a Messages tab.');
    }

    public function test_vendor_nav_has_no_messages_item_in_lite(): void
    {
        $items = DashboardNavigation::get_items('vendor');

        $this->assertArrayNotHasKey('messages', $items);
    }

    /**
     * The filter must be surgical: dropping every item would satisfy the tests
     * above while destroying the dashboard.
     */
    public function test_core_customer_nav_items_survive_in_lite(): void
    {
        $items = DashboardNavigation::get_items('customer');

        $this->assertSame(array( 'overview', 'bookings', 'favorites' ), array_keys($items));
    }

    /**
     * End-to-end: the panel never renders either. `resolve_tab()` validates `?tab=`
     * against these same nav keys, so a hand-typed `?tab=messages` must fall back
     * to Overview rather than emit the shortcode's literal text.
     */
    public function test_customer_dashboard_never_renders_the_messages_shortcode_literal(): void
    {
        wp_set_current_user(self::factory()->user->create(array( 'role' => 'subscriber' )));

        $this->assertSame('customer', DashboardContext::resolve(), 'Premise: a plain logged-in user is a customer in Lite.');

        $original_tab  = $_GET['tab'] ?? null;
        $_GET['tab']   = 'messages';

        $html = UserDashboard::render();

        if (null === $original_tab) {
            unset($_GET['tab']);
        } else {
            $_GET['tab'] = $original_tab;
        }

        $this->assertNotSame('', $html, 'Premise: the dashboard rendered nothing, so the assertions below would be vacuous.');
        $this->assertStringNotContainsString('[rentiva_messages', $html, 'The Messages shortcode leaked to the page as literal text.');
        $this->assertStringNotContainsString('rentiva_messages', $html);
    }
}
