<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Core\Dashboard\DashboardContext;
use MHMRentiva\Admin\Vendor\PostType\VendorApplication;

class DashboardContextVendorStatesTest extends \WP_UnitTestCase
{
    protected function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_resolve_returns_vendor_application_pending_for_pending_applicant(): void
    {
        $user_id = $this->factory()->user->create();
        wp_set_current_user($user_id);

        wp_insert_post(array(
            'post_type'   => VendorApplication::POST_TYPE,
            'post_author' => $user_id,
            'post_status' => 'pending',
            'post_title'  => 'Test Application',
        ));

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
