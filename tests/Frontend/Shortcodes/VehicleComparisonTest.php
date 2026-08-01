<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class VehicleComparisonTest extends WP_UnitTestCase
{
    public function test_renders_comparison_wrapper_without_vehicles()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-vehicle-comparison', $output);
    }

    /**
     * The Add Vehicle control is gone, and must not come back by accident.
     *
     * It was wired to nothing in three layers at once: the button had no JS
     * handler, the two AJAX methods behind it were registered on no hook, and
     * the Elementor switcher that claimed to toggle it was overwritten before it
     * reached the template. A visitor picked a vehicle, pressed the button and
     * nothing happened -- no request, no error, ever.
     */
    public function test_the_dead_add_vehicle_control_is_not_rendered()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison manual_add="1"]');

        $this->assertStringNotContainsString('rv-add-vehicle-section', $output);
        $this->assertStringNotContainsString('rv-add-vehicle-btn', $output);
    }

    public function test_renders_with_vehicle_ids()
    {
        if (!post_type_exists('mhmrentiva_vehicle')) {
            register_post_type('mhmrentiva_vehicle', [
                'public'     => true,
                'supports'   => ['title', 'thumbnail'],
            ]);
        }

        $v1 = $this->factory->post->create(['post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish']);
        $v2 = $this->factory->post->create(['post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish']);

        $output = do_shortcode('[rentiva_vehicle_comparison vehicle_ids="' . $v1 . ',' . $v2 . '"]');

        $this->assertStringContainsString('rv-vehicle-comparison', $output);
    }

    public function test_max_vehicles_attribute_is_accepted()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison max_vehicles="3"]');

        $this->assertStringContainsString('data-max-vehicles="3"', $output);
    }

    public function test_table_layout_is_default()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison]');

        // rv-layout-table is hardcoded on the wrapper div (template line 42)
        $this->assertStringContainsString('rv-layout-table', $output);
    }
}
