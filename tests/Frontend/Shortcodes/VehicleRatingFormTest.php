<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm;
use WP_UnitTestCase;

class VehicleRatingFormTest extends WP_UnitTestCase
{
    private int $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();
        VehicleRatingForm::register();
        VehicleRatingForm::reset_enqueued_assets_for_tests();

        $this->vehicle_id = $this->factory->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Rating Test Vehicle',
        ]);
    }

    public function test_renders_error_without_vehicle_id()
    {
        $output = do_shortcode('[rentiva_vehicle_rating_form]');

        $this->assertNotEmpty($output);
        // Template's `return` inside ob_start is discarded (include does not capture return value),
        // so empty buffer triggers get_fallback_html() which emits the shortcode-error wrapper.
        $this->assertStringContainsString('mhm-rentiva-shortcode-error', $output);
    }

    public function test_renders_rating_form_with_valid_vehicle()
    {
        $output = do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $this->vehicle_id . '"]');

        $this->assertStringContainsString('rv-rating-form', $output);
        $this->assertStringContainsString('data-vehicle-id="' . $this->vehicle_id . '"', $output);
    }

    public function test_renders_rating_display_section()
    {
        $output = do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $this->vehicle_id . '"]');

        $this->assertStringContainsString('rv-rating-display', $output);
    }
}
