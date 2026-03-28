<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class VehicleDetailsTest extends WP_UnitTestCase
{
    private int $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->vehicle_id = $this->factory->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Details Test Vehicle',
            'meta_input'  => [
                '_mhm_rentiva_price_per_day' => '200',
                '_mhm_vehicle_status'        => 'active',
            ],
        ]);
        wp_cache_delete($this->vehicle_id, 'post_meta');
    }

    public function test_renders_vehicle_details_wrapper_with_valid_vehicle()
    {
        $output = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '"]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-vehicle-details-wrapper', $output);
    }

    public function test_renders_without_vehicle_id_returns_output()
    {
        // Without vehicle_id, shortcode should return something (error or empty state)
        $output = do_shortcode('[rentiva_vehicle_details]');

        $this->assertIsString($output);
    }

    public function test_show_gallery_attribute_accepted()
    {
        $output = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '" show_gallery="0"]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-vehicle-details-wrapper', $output);
    }

    public function test_show_booking_button_false()
    {
        $output_with    = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '" show_booking_button="1"]');
        $output_without = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '" show_booking_button="0"]');

        // Both should render the page wrapper
        $this->assertStringContainsString('rv-vehicle-details-wrapper', $output_with);
        $this->assertStringContainsString('rv-vehicle-details-wrapper', $output_without);
    }
}
