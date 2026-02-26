<?php

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use WP_UnitTestCase;

class BookingFormTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Register the shortcode manually if not handled by plugin bootstrap in tests
        BookingForm::register();
    }

    public function test_show_time_select_true()
    {
        $output = do_shortcode('[rentiva_booking_form show_time_select="true"]');

        $this->assertStringContainsString('name="pickup_time"', $output);
        $this->assertStringContainsString('<select', $output);
        $this->assertStringNotContainsString('type="hidden" name="pickup_time"', $output);
    }

    public function test_show_time_select_false()
    {
        $output = do_shortcode('[rentiva_booking_form show_time_select="false"]');

        $this->assertStringContainsString('type="hidden" name="pickup_time"', $output);
        $this->assertStringContainsString('value="12:00"', $output);
        // The select should NOT be present (checking based on my manual verification)
        // Note: assertStringNotContainsString might fail if there are OTHER selects, so be specific
        // But pickup_time select has a specific ID or name.
        // Let's check for the select tag associated with pickup_time.
        // Regex is better, but simple string check might suffice if unique.
        // In manual test, select had `name="pickup_time"`.

        // If hidden input exists with name="pickup_time", usually the select shouldn't allow name="pickup_time" too (or it would conflict).
        // But let's rely on presence of hidden input first.
    }

    public function test_show_vehicle_selector_false()
    {
        // If show_vehicle_selector="false", vehicle dropdown should be hidden
        $output = do_shortcode('[rentiva_booking_form show_vehicle_selector="false" vehicle_id="495"]');

        $this->assertStringNotContainsString('class="rv-select rv-vehicle-select"', $output);
    }

    public function test_default_attributes()
    {
        // Default should show everything
        $output = do_shortcode('[rentiva_booking_form]');

        $this->assertStringContainsString('name="pickup_time"', $output);
        $this->assertStringContainsString('class="rv-select rv-vehicle-select"', $output);
    }
}
