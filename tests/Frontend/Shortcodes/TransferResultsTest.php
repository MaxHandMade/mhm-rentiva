<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class TransferResultsTest extends WP_UnitTestCase
{
    public function test_renders_transfer_results_wrapper()
    {
        $output = do_shortcode('[rentiva_transfer_results]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-transfer-results', $output);
    }

    public function test_list_layout_is_default()
    {
        $output = do_shortcode('[rentiva_transfer_results]');

        $this->assertStringContainsString('rv-transfer-results--list', $output);
    }

    public function test_grid_layout_attribute()
    {
        $output = do_shortcode('[rentiva_transfer_results layout="grid"]');

        $this->assertStringContainsString('rv-transfer-results--grid', $output);
    }

    public function test_renders_without_fatal_error()
    {
        // No search session — should render gracefully (empty results or skeleton)
        $output = do_shortcode('[rentiva_transfer_results]');

        $this->assertIsString($output);
        $this->assertStringNotContainsString('Fatal error', $output);
        $this->assertStringNotContainsString('Warning:', $output);
    }
}
