<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Dashboard;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use WP_UnitTestCase;

/**
 * Task A5a seam inversion: DashboardPage's feature map (localized to the React
 * app as `caps`) no longer reads \MHMRentiva\Admin\Licensing\Mode directly.
 * Lite's own default is an empty array -- a subscriber (Pro's
 * DashboardExtensions) is the only thing that can add the transfer/reports/
 * vendors/messages/export keys.
 *
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage
 */
final class DashboardFeaturesFilterTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('mhmrentiva_dashboard_features');
    }

    protected function tearDown(): void
    {
        remove_all_filters('mhmrentiva_dashboard_features');
        wp_dequeue_script('mhm-rentiva-react-dashboard');
        wp_deregister_script('mhm-rentiva-react-dashboard');
        wp_set_current_user(0);
        set_current_screen('front');
        parent::tearDown();
    }

    public function test_features_filter_default_is_empty_without_a_subscriber(): void
    {
        $this->assertSame(array(), apply_filters('mhmrentiva_dashboard_features', array()));
    }

    public function test_a_subscriber_can_add_a_feature_key(): void
    {
        add_filter('mhmrentiva_dashboard_features', static function (array $features): array {
            $features['reports'] = true;
            return $features;
        });

        $features = apply_filters('mhmrentiva_dashboard_features', array());

        $this->assertTrue($features['reports'] ?? false);
    }

    public function test_localized_caps_map_is_empty_in_lite_without_a_subscriber(): void
    {
        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($admin_id);
        set_current_screen('toplevel_page_mhm-rentiva');

        DashboardPage::enqueue_scripts('toplevel_page_mhm-rentiva');

        $data = wp_scripts()->get_data('mhm-rentiva-react-dashboard', 'data');
        $this->assertIsString($data, 'Premise: the dashboard localization must have registered data on the handle.');
        $this->assertStringContainsString(
            '"caps":[]',
            $data,
            'Lite must localize an empty caps map to the React app when no subscriber has added feature keys.'
        );
    }

    public function test_localized_caps_map_carries_a_subscribers_key(): void
    {
        add_filter('mhmrentiva_dashboard_features', static function (array $features): array {
            $features['transfer'] = true;
            return $features;
        });

        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($admin_id);
        set_current_screen('toplevel_page_mhm-rentiva');

        DashboardPage::enqueue_scripts('toplevel_page_mhm-rentiva');

        $data = wp_scripts()->get_data('mhm-rentiva-react-dashboard', 'data');
        $this->assertIsString($data);
        $this->assertStringContainsString('"caps":{"transfer":true}', $data);
    }
}
