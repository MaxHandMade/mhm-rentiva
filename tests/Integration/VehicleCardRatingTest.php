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

    public function test_rating_renders_even_when_count_is_zero()
    {
        $vehicle = [
            'rating' => [
                'count'   => 0,
                'average' => 0,
                'stars'   => '<span class="star empty"></span>',
            ]
        ];
        $atts = ['show_rating' => true];

        ob_start();
        $template = MHMRENTIVA_PLUGIN_DIR . 'templates/partials/vehicle-card.php';
        $vehicle = $vehicle;
        $atts = $atts;
        include $template;
        $output = ob_get_clean();

        // Rating section should always render when show_rating=true and stars are set.
        $this->assertStringContainsString('mhm-card-rating', $output);
        $this->assertStringContainsString('(0)', $output);
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
