<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Core\Dashboard\DashboardContext;

/**
 * DashboardContext::resolve() routes every logged-in user, so it stays in Lite.
 *
 * The 'vendor_application_pending' outcome is now reached through
 * `apply_filters('mhmrentiva_dashboard_vendor_application_pending', false, $user_id)`
 * (Task A5a seam inversion) rather than a direct VendorApplication class_exists()
 * + get_posts() read. Lite's own default is `false`; a subscriber (Pro) is the
 * only thing that can route to 'vendor_application_pending'. The role/meta-driven
 * outcomes below need no subscriber and are exercised as usual.
 */
class DashboardContextVendorStatesTest extends \WP_UnitTestCase
{
    protected function tearDown(): void
    {
        wp_set_current_user(0);
        remove_all_filters('mhmrentiva_dashboard_vendor_application_pending');
        parent::tearDown();
    }

    public function test_resolve_returns_customer_by_default_without_a_vendor_application_subscriber(): void
    {
        remove_all_filters('mhmrentiva_dashboard_vendor_application_pending');
        $user_id = $this->factory()->user->create();
        wp_set_current_user($user_id);

        $this->assertSame('customer', DashboardContext::resolve());
    }

    public function test_a_subscriber_can_route_to_vendor_application_pending(): void
    {
        remove_all_filters('mhmrentiva_dashboard_vendor_application_pending');
        add_filter('mhmrentiva_dashboard_vendor_application_pending', '__return_true');

        $user_id = $this->factory()->user->create();
        wp_set_current_user($user_id);

        $this->assertSame('vendor_application_pending', DashboardContext::resolve());
    }

    public function test_resolve_returns_vendor_suspended_for_suspended_vendor(): void
    {
        $user_id = $this->factory()->user->create();
        $user = new \WP_User($user_id);
        $user->add_role('rentiva_vendor');
        update_user_meta($user_id, '_rentiva_vendor_status', 'suspended');
        wp_set_current_user($user_id);

        $this->assertSame('vendor_suspended', DashboardContext::resolve());
    }

    public function test_resolve_returns_vendor_for_active_vendor(): void
    {
        $user_id = $this->factory()->user->create();
        $user = new \WP_User($user_id);
        $user->add_role('rentiva_vendor');
        update_user_meta($user_id, '_rentiva_vendor_status', 'active');
        wp_set_current_user($user_id);

        $this->assertSame('vendor', DashboardContext::resolve());
    }
}
