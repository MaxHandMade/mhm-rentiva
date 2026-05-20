<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use WP_Scripts;
use WP_UnitTestCase;

final class DashboardScriptDepsTest extends WP_UnitTestCase
{
    private int $admin_user_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin_user_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $this->admin_user_id );
        set_current_screen( 'toplevel_page_mhm-rentiva' );
        $this->reset_react_handle();
    }

    public function tearDown(): void
    {
        $this->reset_react_handle();
        wp_set_current_user( 0 );
        set_current_screen( 'front' );
        parent::tearDown();
    }

    public function test_react_dashboard_script_is_registered_after_enqueue(): void
    {
        DashboardPage::enqueue_scripts( 'toplevel_page_mhm-rentiva' );

        $scripts = wp_scripts();
        $this->assertInstanceOf( WP_Scripts::class, $scripts );
        $this->assertArrayHasKey(
            'mhm-rentiva-react-dashboard',
            $scripts->registered,
            'mhm-rentiva-react-dashboard must be registered after enqueue_scripts().'
        );
    }

    public function test_react_dashboard_script_no_longer_depends_on_chart_js(): void
    {
        DashboardPage::enqueue_scripts( 'toplevel_page_mhm-rentiva' );

        $scripts = wp_scripts();
        $this->assertNotContains(
            'chart-js',
            $scripts->registered['mhm-rentiva-react-dashboard']->deps,
            'mhm-rentiva-react-dashboard must NOT declare chart-js as a dependency — Chart.js is now bundled inside dashboard.js via webpack import (Task 5.0k carved the broken in-tree assets/js/vendor/chart.min.js enqueue; the file was never git-tracked + .distignore-excluded, causing a 404 on the shipped ZIP since baseline).'
        );
    }

    private function reset_react_handle(): void
    {
        wp_dequeue_script( 'mhm-rentiva-react-dashboard' );
        wp_deregister_script( 'mhm-rentiva-react-dashboard' );
        wp_dequeue_script( 'chart-js' );
        wp_deregister_script( 'chart-js' );
    }
}
