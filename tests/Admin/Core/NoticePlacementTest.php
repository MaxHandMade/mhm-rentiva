<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\NoticePlacement;
use WP_UnitTestCase;

/**
 * Which screens get the pre-paint notice placement script, and that the
 * Notification Templates tab no longer prints a second header marker.
 *
 * The placement itself is browser behaviour (a MutationObserver during
 * parsing) and was measured per screen on 2026-09-17: on every Rentiva screen
 * with a `.wrap`, the licence notice's first visible frame was already its
 * final position, one copy, no hidden interval. This test pins the PHP side
 * that decides where that script is printed.
 */
final class NoticePlacementTest extends WP_UnitTestCase
{
    public function tearDown(): void
    {
        global $pagenow, $post_type;
        $pagenow   = 'index.php';
        $post_type = null;
        set_current_screen('front');
        parent::tearDown();
    }

    private function printed_on(string $screen_id): string
    {
        set_current_screen($screen_id);
        ob_start();
        NoticePlacement::print_script();
        return (string) ob_get_clean();
    }

    public function test_script_is_printed_on_rentiva_custom_and_core_screens(): void
    {
        foreach (array(
            'mhm-rentiva_page_mhm-rentiva-settings',
            'mhm-rentiva_page_vehicle-settings',
            'mhmrentiva_vehicle',
            'edit-mhmrentiva_contact',
            'edit-mhmrentiva_vehicle_category',
        ) as $screen_id) {
            $html = $this->printed_on($screen_id);
            $this->assertStringContainsString('MutationObserver', $html, "Missing on $screen_id");
            $this->assertStringContainsString('below-h2', $html, "Missing on $screen_id");
        }
    }

    public function test_script_is_not_printed_on_other_plugins_screens(): void
    {
        foreach (array( 'dashboard', 'plugins', 'edit-post', 'woocommerce_page_wc-settings' ) as $screen_id) {
            $this->assertSame('', $this->printed_on($screen_id), "Printed on $screen_id");
        }
    }

    public function test_script_is_not_printed_on_the_transformed_list_screens(): void
    {
        // ListScreenLayout places notices below the KPI band there.
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_booking';

        $this->assertSame('', $this->printed_on('edit-mhmrentiva_booking'));
    }

    public function test_notification_templates_tab_prints_no_header_marker_of_its_own(): void
    {
        // The Settings page header already prints one; common.js clones a
        // notice once per marker, so a second one showed the licence warning
        // twice on this tab.
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/src/Admin/Emails/Core/EmailTemplates.php'
        );

        // Guard against a vacuous pass on a wrong path or an unreadable file.
        $this->assertStringContainsString('function render_content_only', $source);
        $this->assertStringNotContainsString('<hr class="wp-header-end"', $source);
    }
}
