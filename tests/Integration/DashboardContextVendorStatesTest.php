<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Core\Dashboard\DashboardContext;

/**
 * DashboardContext::resolve() routes every logged-in user, so it stays in Lite.
 *
 * The 'vendor_application_pending' outcome is unreachable here: it depends on
 * the carved-out VendorApplication post type, and resolve() short-circuits on
 * class_exists() before ever querying for it. That case is therefore covered in
 * the Pro build, not this one. The role/meta-driven outcomes below need no Pro
 * class and are exercised as usual.
 */
class DashboardContextVendorStatesTest extends \WP_UnitTestCase
{
    protected function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
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
