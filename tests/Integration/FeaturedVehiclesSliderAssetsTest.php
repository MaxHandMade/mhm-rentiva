<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles;

final class FeaturedVehiclesSliderAssetsTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset PHP-level static caches that persist across test methods.
        AbstractShortcode::reset_enqueued_assets_for_tests();
        FeaturedVehicles::reset_layout_enqueued_for_tests();
        // Register vendor assets (Swiper) so wp_enqueue_script('mhm-swiper') has a registered src.
        AssetManager::register_vendor_assets();
    }

    public function test_slider_assets_are_enqueued_even_after_grid_variant(): void
    {
        FeaturedVehicles::render([
            'layout' => 'grid',
            'title'  => '',
            'limit'  => '1',
        ]);

        FeaturedVehicles::render([
            'layout' => 'slider',
            'title'  => '',
            'limit'  => '1',
        ]);

        $this->assertTrue(wp_script_is('mhm-swiper', 'enqueued'), 'Expected mhm-swiper to be enqueued for slider variant.');

        $has_featured_slider_script = false;
        $wp_scripts = $GLOBALS['wp_scripts'] ?? null;

        if ($wp_scripts instanceof \WP_Scripts) {
            foreach ($wp_scripts->queue as $handle) {
                $src = $wp_scripts->registered[$handle]->src ?? '';
                if (is_string($src) && strpos($src, 'featured-vehicles.js') !== false) {
                    $has_featured_slider_script = true;
                    break;
                }
            }
        }

        $this->assertTrue($has_featured_slider_script, 'Expected featured-vehicles.js to be enqueued for slider variant.');
    }

    public function test_carousel_layout_alias_is_treated_as_slider_for_assets(): void
    {
        FeaturedVehicles::render([
            'layout' => 'carousel',
            'title'  => '',
            'limit'  => '1',
        ]);

        $this->assertTrue(wp_script_is('mhm-swiper', 'enqueued'), 'Expected mhm-swiper to be enqueued for carousel alias.');

        $has_featured_slider_script = false;
        $wp_scripts = $GLOBALS['wp_scripts'] ?? null;

        if ($wp_scripts instanceof \WP_Scripts) {
            foreach ($wp_scripts->queue as $handle) {
                $src = $wp_scripts->registered[$handle]->src ?? '';
                if (is_string($src) && strpos($src, 'featured-vehicles.js') !== false) {
                    $has_featured_slider_script = true;
                    break;
                }
            }
        }

        $this->assertTrue($has_featured_slider_script, 'Expected featured-vehicles.js to be enqueued for carousel alias.');
    }

    public function test_slider_assets_are_printed_in_footer_output(): void
    {
        FeaturedVehicles::render([
            'layout' => 'slider',
            'title'  => '',
            'limit'  => '1',
        ]);

        ob_start();
        wp_print_footer_scripts();
        $footer_output = (string) ob_get_clean();

        $this->assertStringContainsString('featured-vehicles.js', $footer_output);
        $this->assertStringContainsString('swiper-bundle.min.js', $footer_output);
    }
}
