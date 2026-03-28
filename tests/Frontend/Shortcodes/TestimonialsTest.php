<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class TestimonialsTest extends WP_UnitTestCase
{
    public function test_renders_testimonials_wrapper()
    {
        $output = do_shortcode('[rentiva_testimonials]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-testimonials', $output);
    }

    public function test_grid_layout_is_default()
    {
        $output = do_shortcode('[rentiva_testimonials]');

        $this->assertStringContainsString('rv-layout-grid', $output);
    }

    public function test_carousel_layout_attribute()
    {
        $output = do_shortcode('[rentiva_testimonials layout="carousel"]');

        $this->assertStringContainsString('rv-layout-carousel', $output);
    }

    public function test_columns_attribute_applied()
    {
        $output = do_shortcode('[rentiva_testimonials columns="2"]');

        $this->assertStringContainsString('rv-columns-2', $output);
    }

    public function test_default_columns_is_three()
    {
        $output = do_shortcode('[rentiva_testimonials]');

        $this->assertStringContainsString('rv-columns-3', $output);
    }

    public function test_class_attribute_applied()
    {
        $output = do_shortcode('[rentiva_testimonials class="my-custom-class"]');

        $this->assertStringContainsString('my-custom-class', $output);
    }
}
