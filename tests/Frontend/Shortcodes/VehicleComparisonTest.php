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
        $this->assertStringContainsString('rvs-compare', $output);
        $this->assertStringContainsString('rvs-shell', $output);
    }

    /**
     * The old Add Vehicle control was wired to nothing and must not come back; the
     * drawing's "+ Add vehicle" is a link to the search results (PR E, R19, a
     * deliberate change of this test). It takes no attribute, and a fixed list
     * (vehicle_ids) has no such link.
     */
    public function test_the_add_vehicle_control_is_a_link_on_the_visitors_list_only()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison manual_add="1"]');

        $this->assertStringNotContainsString('rv-add-vehicle-section', $output);
        $this->assertStringNotContainsString('rv-add-vehicle-btn', $output);

        $v1 = \MHMRentiva\Tests\Support\ShortcodeFixtures::vehicle();
        $v2 = \MHMRentiva\Tests\Support\ShortcodeFixtures::vehicle();
        $fixed = do_shortcode('[rentiva_vehicle_comparison vehicle_ids="' . $v1 . ',' . $v2 . '"]');

        $this->assertStringContainsString('rvs-compare__table', $fixed);
        $this->assertStringNotContainsString('data-rvs-cmp-add', $fixed);
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

        $this->assertStringContainsString('rvs-compare', $output);
    }

    public function test_max_vehicles_attribute_is_accepted()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison max_vehicles="3"]');

        $this->assertStringContainsString('data-max-vehicles="3"', $output);
    }

    public function test_table_layout_is_default()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison]');

        $this->assertStringContainsString('rvs-compare--table', $output);
    }
}
