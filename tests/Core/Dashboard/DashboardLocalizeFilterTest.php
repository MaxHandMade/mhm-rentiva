<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Dashboard;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use WP_UnitTestCase;

/**
 * Task A5b seam inversion: the dashboard's transfer data (`transfer_stats`,
 * `recent_transfers`, `recent_transfers_total_pages`) no longer ships from
 * Lite at all. Lite's own localized array carries none of those keys -- a
 * subscriber (Pro's DashboardExtensions, gated on Edition::isPro()) is the
 * only thing that can add them back, via the `mhm_rentiva_dashboard_localize`
 * filter applied just before `wp_localize_script()`.
 *
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage
 */
final class DashboardLocalizeFilterTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('mhm_rentiva_dashboard_localize');
    }

    protected function tearDown(): void
    {
        remove_all_filters('mhm_rentiva_dashboard_localize');
        wp_dequeue_script('mhm-rentiva-react-dashboard');
        wp_deregister_script('mhm-rentiva-react-dashboard');
        wp_set_current_user(0);
        set_current_screen('front');
        parent::tearDown();
    }

    public function test_localized_data_has_no_transfer_keys_without_a_subscriber(): void
    {
        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($admin_id);
        set_current_screen('toplevel_page_mhm-rentiva');

        DashboardPage::enqueue_scripts('toplevel_page_mhm-rentiva');

        $data = wp_scripts()->get_data('mhm-rentiva-react-dashboard', 'data');
        $this->assertIsString($data, 'Premise: the dashboard localization must have registered data on the handle.');
        $this->assertStringNotContainsString('transfer_stats', $data, 'Lite must not localize transfer_stats without a subscriber.');
        $this->assertStringNotContainsString('recent_transfers', $data, 'Lite must not localize recent_transfers without a subscriber.');
    }

    public function test_mhm_rentiva_dashboard_localize_filter_is_applied(): void
    {
        add_filter('mhm_rentiva_dashboard_localize', static function (array $data): array {
            $data['transfer_stats'] = array( 'total' => 42 );
            return $data;
        });

        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($admin_id);
        set_current_screen('toplevel_page_mhm-rentiva');

        DashboardPage::enqueue_scripts('toplevel_page_mhm-rentiva');

        $data = wp_scripts()->get_data('mhm-rentiva-react-dashboard', 'data');
        $this->assertIsString($data);
        $this->assertStringContainsString('"transfer_stats":{"total":42}', $data, 'A subscriber must be able to add transfer_stats back via the localize filter.');
    }
}
