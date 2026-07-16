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

    /**
     * Inverted for the Lite carve (was: asserts the transfer tab renders).
     *
     * Transfer is a Pro surface. Its tab posts to the `rentiva_transfer_results`
     * page, whose shortcode Lite carves out, so offering the tab sent the visitor
     * to a page printing the tag's own literal text. The tab is therefore absent
     * by design in this build -- the monolith-era expectation is now the bug.
     *
     * The rental tab above still asserts the positive case, so this pair pins both
     * halves: the search widget degrades to rental-only rather than disappearing.
     * Fuller coverage (every attribute path that forces transfer on, plus the
     * panel and submit button) lives in
     * tests/Frontend/UnifiedSearchTransferSeamTest.php.
     */
    public function test_does_not_render_transfer_tab_in_lite()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertStringNotContainsString('data-testid="tab-transfer"', $output);
    }
}
