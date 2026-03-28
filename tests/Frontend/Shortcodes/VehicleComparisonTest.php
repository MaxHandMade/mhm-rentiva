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

    public function test_renders_add_vehicle_section()
    {
        // show_add_vehicle requires manual_add="1" and fewer vehicles than max_vehicles
        $output = do_shortcode('[rentiva_vehicle_comparison manual_add="1"]');

        $this->assertStringContainsString('rv-add-vehicle-section', $output);
    }

    public function test_renders_with_vehicle_ids()
    {
        if (!post_type_exists('vehicle')) {
            register_post_type('vehicle', [
                'public'     => true,
                'supports'   => ['title', 'thumbnail'],
            ]);
        }

        $v1 = $this->factory->post->create(['post_type' => 'vehicle', 'post_status' => 'publish']);
        $v2 = $this->factory->post->create(['post_type' => 'vehicle', 'post_status' => 'publish']);

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
