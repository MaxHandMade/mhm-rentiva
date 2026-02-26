<?php

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\SettingsView;
use MHMRentiva\Admin\Settings\View\TabRendererRegistry;
use WP_UnitTestCase;

/**
 * Test class for SettingsView
 */
class SettingsViewTest extends WP_UnitTestCase
{
    /**
     * Helper to get common test arguments
     */
    private function get_test_args(): array
    {
        $registry = new TabRendererRegistry();
        $tabs = [];
        foreach ($registry->get_all() as $slug => $tab_renderer) {
            $tabs[$slug] = $tab_renderer->get_label();
        }

        return [
            'current_tab' => 'general',
            'tabs'        => $tabs,
            'renderer'    => $registry->get('general')
        ];
    }

    /**
     * Test authorized access
     * 
     * @test
     */
    public function it_renders_when_authorized_user_accesses_settings()
    {
        // Create an administrator
        $admin_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_id);

        $args = $this->get_test_args();

        // Capture output
        ob_start();
        SettingsView::render_settings_page($args['current_tab'], $args['tabs'], $args['renderer']);
        $output = ob_get_clean();

        // Check if it contains basic wrap class
        $this->assertStringContainsString('mhm-settings-page', $output);
    }

    /**
     * Test renderer logic
     * 
     * @test
     */
    public function it_renders_specific_tab_metadata()
    {
        $admin_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_id);

        $registry = new TabRendererRegistry();
        $tabs = ['general' => 'General', 'vehicle' => 'Vehicles'];
        $renderer = $registry->get('vehicle');

        ob_start();
        SettingsView::render_settings_page('vehicle', $tabs, $renderer);
        $output = ob_get_clean();

        // Tab should be marked as active
        $this->assertStringContainsString('class="mhm-settings-nav-item active"', $output);
        $this->assertStringContainsString('Vehicles', $output);
    }
}
