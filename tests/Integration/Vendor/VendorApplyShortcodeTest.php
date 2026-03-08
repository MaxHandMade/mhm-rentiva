<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor;

use MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorApply;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

/**
 * Integration tests for the [rentiva_vendor_apply] shortcode.
 */
class VendorApplyShortcodeTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset shortcode cache so register() runs fresh each test.
        AbstractShortcode::reset_enqueued_assets_for_tests();
    }

    public function test_shortcode_tag_is_correct(): void
    {
        // Access the tag via the public render path (tag returned via static method).
        // We verify by registering the shortcode and confirming it appears in the WP registry.
        VendorApply::register();
        $this->assertTrue(shortcode_exists('rentiva_vendor_apply'));
    }

    public function test_ajax_action_is_registered_after_register(): void
    {
        VendorApply::register();
        $this->assertGreaterThan(
            0,
            has_action('wp_ajax_mhm_vendor_apply', array(VendorApply::class, 'handle_ajax')),
            'wp_ajax_mhm_vendor_apply action should be registered after VendorApply::register()'
        );
    }

    public function test_prepare_template_data_returns_pro_required_in_lite_mode(): void
    {
        // In the test environment Mode::canUseVendorMarketplace() returns false (no active Pro license).
        $data = VendorApply::get_data(array());
        $this->assertArrayHasKey('pro_required', $data);
        $this->assertTrue($data['pro_required'], 'pro_required should be true when vendor marketplace is not available');
    }

    public function test_render_returns_pro_required_notice_in_lite_mode(): void
    {
        $html = VendorApply::render();
        $this->assertStringContainsString('pro', strtolower($html));
    }
}
