<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class TransferSearchTest extends WP_UnitTestCase
{
    public function test_renders_transfer_search_wrapper()
    {
        $output = do_shortcode('[rentiva_transfer_search]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-transfer-search', $output);
    }

    public function test_renders_form_element()
    {
        $output = do_shortcode('[rentiva_transfer_search]');

        $this->assertStringContainsString('data-testid="transfer-search-form"', $output);
    }

    public function test_horizontal_layout_is_default()
    {
        $output = do_shortcode('[rentiva_transfer_search]');

        $this->assertStringContainsString('rv-layout-horizontal', $output);
    }

    public function test_show_pickup_true_renders_origin_select()
    {
        $output = do_shortcode('[rentiva_transfer_search show_pickup="1"]');

        $this->assertStringContainsString('data-testid="transfer-origin"', $output);
    }
}
