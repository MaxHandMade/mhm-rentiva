<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class UnifiedSearchTest extends WP_UnitTestCase
{
    public function test_renders_search_widget_wrapper()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-unified-search', $output);
    }

    public function test_renders_with_testid_attribute()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertStringContainsString('data-testid="unified-search"', $output);
    }

    public function test_horizontal_layout_is_default()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertStringContainsString('rv-unified-search--horizontal', $output);
    }

    public function test_vertical_layout_attribute()
    {
        $output = do_shortcode('[rentiva_unified_search layout="vertical"]');

        $this->assertStringContainsString('rv-unified-search--vertical', $output);
        $this->assertStringNotContainsString('rv-unified-search--horizontal', $output);
    }

    public function test_renders_rental_tab()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertStringContainsString('data-testid="tab-rental"', $output);
    }

    public function test_renders_transfer_tab()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertStringContainsString('data-testid="tab-transfer"', $output);
    }
}
