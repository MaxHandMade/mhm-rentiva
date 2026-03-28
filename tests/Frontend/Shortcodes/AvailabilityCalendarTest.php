<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class AvailabilityCalendarTest extends WP_UnitTestCase
{
    public function test_renders_error_when_no_vehicle_id()
    {
        $output = do_shortcode('[rentiva_availability_calendar]');

        $this->assertNotEmpty($output, 'Shortcode must return non-empty output');
        $this->assertStringContainsString('rv-availability-error', $output);
    }

    public function test_renders_error_for_invalid_vehicle_id()
    {
        $output = do_shortcode('[rentiva_availability_calendar vehicle_id="99999"]');

        $this->assertStringContainsString('rv-availability-error', $output);
    }

    public function test_renders_calendar_wrapper_with_valid_vehicle()
    {
        $vehicle_id = $this->factory->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Calendar Test Vehicle',
        ]);

        $output = do_shortcode('[rentiva_availability_calendar vehicle_id="' . $vehicle_id . '"]');

        $this->assertStringContainsString('rv-availability-calendar', $output);
    }

    public function test_show_pricing_attribute_is_accepted()
    {
        $output = do_shortcode('[rentiva_availability_calendar show_pricing="0"]');

        // Without vehicle, error is returned — but shortcode must not fatal
        $this->assertNotEmpty($output);
    }
}
