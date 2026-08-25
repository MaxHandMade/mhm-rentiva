<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Account;

use MHMRentiva\Admin\Frontend\Shortcodes\Account\UserDashboard;
use WP_UnitTestCase;

/**
 * The dashboard's CSS/JS and its body class were bound to is_page('panel').
 * An extension rendering the same dashboard on its own surface therefore got
 * neither, and the page arrived unstyled -- and Lite must not learn that
 * surface's name in order to fix it.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Account\UserDashboard
 */
final class DashboardSurfaceSeamTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('mhmrentiva_dashboard_surface_active');
        wp_dequeue_style('mhm-rentiva-user-dashboard');
        wp_dequeue_script('mhm-rentiva-dashboard');
        parent::tearDown();
    }

    public function test_a_subscriber_can_claim_the_dashboard_surface(): void
    {
        add_filter('mhmrentiva_dashboard_surface_active', '__return_true');

        UserDashboard::enqueue_assets();

        $this->assertTrue(wp_style_is('mhm-rentiva-user-dashboard', 'enqueued'));
        $this->assertTrue(wp_script_is('mhm-rentiva-dashboard', 'enqueued'));
    }

    public function test_the_body_class_follows_the_same_seam(): void
    {
        add_filter('mhmrentiva_dashboard_surface_active', '__return_true');

        $this->assertContains('rentiva-panel-page', UserDashboard::add_body_class(array()));
    }

    public function test_no_subscriber_leaves_both_alone(): void
    {
        UserDashboard::enqueue_assets();

        $this->assertFalse(wp_style_is('mhm-rentiva-user-dashboard', 'enqueued'));
        $this->assertNotContains('rentiva-panel-page', UserDashboard::add_body_class(array()));
    }
}
