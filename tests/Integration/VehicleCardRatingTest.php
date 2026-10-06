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

    /**
     * A vehicle nobody has rated shows no rating at all: five empty stars and
     * "(0)" read as a bad score. User decision 2026-10-06 (design-plans/
     * 2026-10-06-arac-karti-kompakt-puan.md); the Broadsheet artboard only
     * draws rated vehicles.
     */
    public function test_rating_is_hidden_when_count_is_zero()
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

        $this->assertStringNotContainsString('mhm-card-rating', $output);
        $this->assertStringNotContainsString('(0)', $output);
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
        // Compact, artboard style: "★ 4,5" beside the title, no star row, no count.
        $this->assertStringContainsString('&#9733;', $output);
        $this->assertStringContainsString(number_format_i18n(4.5, 1), $output);
        $this->assertStringNotContainsString('(5)', $output);
        $this->assertStringNotContainsString('<span>*****</span>', $output);
        $this->assertMatchesRegularExpression('/mhm-card-title-row.*mhm-card-title.*mhm-card-rating/s', $output);
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
