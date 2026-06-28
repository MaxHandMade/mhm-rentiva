<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor;

use MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VehicleSubmit;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

/**
 * Integration tests for the [rentiva_vehicle_submit] shortcode.
 */
class VehicleSubmitShortcodeTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AbstractShortcode::reset_enqueued_assets_for_tests();
    }

    public function test_shortcode_tag_is_correct(): void
    {
        VehicleSubmit::register();
        $this->assertTrue(shortcode_exists('rentiva_vehicle_submit'));
    }

    public function test_ajax_action_is_registered_after_register(): void
    {
        VehicleSubmit::register();
        $this->assertGreaterThan(
            0,
            has_action('wp_ajax_mhm_vehicle_submit', array(VehicleSubmit::class, 'handle_ajax')),
            'wp_ajax_mhm_vehicle_submit action should be registered after VehicleSubmit::register()'
        );
    }

    public function test_prepare_template_data_returns_pro_required_in_lite_mode(): void
    {
        // In the test environment Mode::canUseVendorMarketplace() returns false.
        $data = VehicleSubmit::get_data(array());
        $this->assertArrayHasKey('pro_required', $data);
        $this->assertTrue($data['pro_required'], 'pro_required should be true when vendor marketplace is not available');
    }

    public function test_render_returns_pro_required_notice_in_lite_mode(): void
    {
        $html = VehicleSubmit::render();
        $this->assertStringContainsString('pro', strtolower($html));
    }

    /**
     * Regression: the vendor vehicle form must read the SAME user-meta key that
     * onboarding/profile-settings write (_rentiva_vendor_city). It previously read an
     * orphan key (_mhm_rentiva_vendor_city) that was never written, so the city was
     * always empty — which hid the transfer locations, routes, and the whole VIP
     * transfer section on the vendor form.
     */
    public function test_get_vendor_city_reads_canonical_meta_key(): void
    {
        $user_id = $this->factory()->user->create();
        update_user_meta($user_id, \MHMRentiva\Admin\Core\MetaKeys::VENDOR_CITY, 'İstanbul');

        $this->assertSame(
            'İstanbul',
            VehicleSubmit::get_vendor_city($user_id),
            'get_vendor_city must read the canonical _rentiva_vendor_city meta key'
        );

        wp_delete_user($user_id);
    }

    public function test_get_vendor_city_returns_empty_for_orphan_key(): void
    {
        $user_id = $this->factory()->user->create();
        // Simulate the historical orphan key: it must NOT be picked up.
        update_user_meta($user_id, '_mhm_rentiva_vendor_city', 'Ankara');

        $this->assertSame(
            '',
            VehicleSubmit::get_vendor_city($user_id),
            'The orphan _mhm_rentiva_vendor_city key must not be read'
        );

        wp_delete_user($user_id);
    }

    public function test_prepare_template_data_returns_vendor_only_false_for_vendor_user_when_pro_enabled(): void
    {
        // This test only runs when Mode::canUseVendorMarketplace() is true.
        // In Lite/test mode it is skipped because pro_required gates first.
        $data = VehicleSubmit::get_data(array());
        if (! empty($data['pro_required'])) {
            $this->markTestSkipped('Vendor marketplace not available in this environment (Lite/test mode).');
        }

        // Create a vendor user and log in.
        $user_id = $this->factory()->user->create();
        $user    = new \WP_User($user_id);
        $user->add_role('rentiva_vendor');
        wp_set_current_user($user_id);

        $data = VehicleSubmit::get_data(array());
        $this->assertArrayHasKey('vendor_only', $data);
        $this->assertFalse($data['vendor_only'], 'vendor_only should be false for a user with rentiva_vendor role');

        wp_set_current_user(0);
        wp_delete_user($user_id);
    }
}
