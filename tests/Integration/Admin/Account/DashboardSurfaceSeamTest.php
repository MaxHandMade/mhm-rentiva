<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Account;

use MHMRentiva\Admin\Frontend\Shortcodes\Account\UserDashboard;
use WP_UnitTestCase;

/**
 * The dashboard's CSS (and, until the KPI kit migration, its JS) and its body
 * class were bound to is_page('panel').
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
        if (function_exists('mhmuicore_kit_handle')) {
            wp_dequeue_style(mhmuicore_kit_handle('front'));
        }
        parent::tearDown();
    }

    public function test_a_subscriber_can_claim_the_dashboard_surface(): void
    {
        add_filter('mhmrentiva_dashboard_surface_active', '__return_true');

        UserDashboard::enqueue_assets();

        $this->assertTrue(wp_style_is('mhm-rentiva-user-dashboard', 'enqueued'));
        // The KPI strip's cards are ui-core kit markup (KPI kit migration,
        // Task 9); the claimed surface must load the kit's front stylesheet
        // too, or it renders unstyled cards.
        $kit_handle = function_exists('mhmuicore_kit_handle') ? mhmuicore_kit_handle('front') : '';
        $this->assertNotSame('', $kit_handle);
        $this->assertTrue(wp_style_is($kit_handle, 'enqueued'));
        // The count-up script is gone with the counter it animated.
        $this->assertFalse(wp_script_is('mhm-rentiva-dashboard', 'enqueued'));
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
