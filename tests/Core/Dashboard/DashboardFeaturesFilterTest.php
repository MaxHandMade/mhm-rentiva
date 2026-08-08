<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Dashboard;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use WP_UnitTestCase;

/**
 * WP.org T1/T4 trialware regression: Lite must not ship or localize a hidden
 * paid-feature capability map. Extensions can add their own UI through their
 * own assets; Lite's dashboard contract contains only Lite data.
 *
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage
 */
final class DashboardFeaturesFilterTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        wp_dequeue_script('mhm-rentiva-react-dashboard');
        wp_deregister_script('mhm-rentiva-react-dashboard');
        wp_set_current_user(0);
        set_current_screen('front');
        parent::tearDown();
    }

    public function test_lite_dashboard_does_not_localize_a_paid_feature_caps_map(): void
    {
        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($admin_id);
        set_current_screen('toplevel_page_mhm-rentiva');

        DashboardPage::enqueue_scripts('toplevel_page_mhm-rentiva');

        $data = wp_scripts()->get_data('mhm-rentiva-react-dashboard', 'data');
        $this->assertIsString($data, 'Premise: the dashboard localization must have registered data on the handle.');
        $this->assertStringNotContainsString(
            '"caps":',
            $data,
            'Lite must not expose a hidden paid-feature capability contract to its React dashboard.'
        );
    }
}
