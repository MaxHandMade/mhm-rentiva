<?php

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Core\Utilities\Templates;

class VehicleCardRatingTest extends \WP_UnitTestCase
{

    public function setUp(): void
    {
        parent::setUp();
        if (!defined('MHMRENTIVA_PLUGIN_DIR')) {
            define('MHMRENTIVA_PLUGIN_DIR', dirname(dirname(__DIR__)) . '/');
        }
    }

    public function test_rating_does_not_render_when_count_is_zero()
    {
        $vehicle = [
            'rating' => [
                'count'   => 0,
                'average' => 0,
                'stars'   => '',
            ]
        ];
        $atts = ['show_rating' => true];

        // Mock Templates class or used internal include logic if Templates is just a wrapper
        // Assuming Templates::render() is the standard way. 
        // If Templates::render doesn't exist or is static, check usage.
        // Looking at codebase, Templates::render exists in Admin/Core/Utilities/Templates.php?
        // Let's assume the static call is correct but maybe the path 'partials/vehicle-card' is relative to template dir.

        ob_start();
        $template = MHMRENTIVA_PLUGIN_DIR . 'templates/partials/vehicle-card.php';
        $vehicle = $vehicle; // Extract for template
        $atts = $atts;
        include $template;
        $output = ob_get_clean();

        $this->assertStringNotContainsString('mhm-card-rating', $output);
    }

    public function test_rating_renders_when_count_is_positive()
    {
        $vehicle = [
            'ID' => 123,
            'permalink' => 'http://example.com/car',
            'title' => 'Test Car',
            'image' => '',
            'rating' => [
                'count'   => 5,
                'average' => 4.5,
                'stars'   => '<span>*****</span>',
            ]
        ];
        $atts = ['show_rating' => true];

        ob_start();
        $template = MHMRENTIVA_PLUGIN_DIR . 'templates/partials/vehicle-card.php';
        include $template;
        $output = ob_get_clean();

        $this->assertStringContainsString('mhm-card-rating', $output);
        $this->assertStringContainsString('(5)', $output);
    }

    public function test_rating_does_not_render_when_toggle_is_off()
    {
        $vehicle = [
            'ID' => 123,
            'permalink' => 'http://example.com/car',
            'title' => 'Test Car',
            'image' => '',
            'rating' => [
                'count'   => 5,
                'average' => 4.5,
                'stars'   => '<span>*****</span>',
            ]
        ];
        $atts = ['show_rating' => 'false'];

        ob_start();
        $template = MHMRENTIVA_PLUGIN_DIR . 'templates/partials/vehicle-card.php';
        include $template;
        $output = ob_get_clean();

        $this->assertStringNotContainsString('mhm-card-rating', $output);
    }

    public function test_rating_does_not_render_when_toggle_is_zero()
    {
        $vehicle = [
            'ID' => 123,
            'permalink' => 'http://example.com/car',
            'title' => 'Test Car',
            'image' => '',
            'rating' => [
                'count'   => 5,
                'average' => 4.5,
                'stars'   => '<span>*****</span>',
            ]
        ];
        $atts = ['show_rating' => '0'];

        ob_start();
        $template = MHMRENTIVA_PLUGIN_DIR . 'templates/partials/vehicle-card.php';
        include $template;
        $output = ob_get_clean();

        $this->assertStringNotContainsString('mhm-card-rating', $output);
    }
}
