<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vendor\REST;

use MHMRentiva\Admin\Vendor\REST\VendorManagementRestController;
use WP_REST_Request;
use WP_UnitTestCase;

class VendorManagementRestVendorCommissionRateTest extends WP_UnitTestCase
{
    private int $vendor_id;
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin_id  = self::factory()->user->create(array('role' => 'administrator'));
        $this->vendor_id = self::factory()->user->create(array('role' => 'rentiva_vendor'));
        wp_set_current_user($this->admin_id);
    }

    public function test_setting_a_valid_rate_updates_the_user_meta(): void
    {
        $request = new WP_REST_Request('POST', '/mhm-rentiva/v1/vendors/vendors/' . $this->vendor_id . '/commission-rate');
        $request->set_param('id', $this->vendor_id);
        $request->set_param('rate', 12.5);

        $response = VendorManagementRestController::update_vendor_commission_rate($request);
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertSame(12.5, $data['rate']);
        $this->assertSame('12.5', get_user_meta($this->vendor_id, '_mhm_vendor_commission_rate', true));
    }

    public function test_omitting_the_rate_clears_the_override(): void
    {
        update_user_meta($this->vendor_id, '_mhm_vendor_commission_rate', '20.0');

        $request = new WP_REST_Request('POST', '/mhm-rentiva/v1/vendors/vendors/' . $this->vendor_id . '/commission-rate');
        $request->set_param('id', $this->vendor_id);
        // No 'rate' param set — signals "clear the override".

        $response = VendorManagementRestController::update_vendor_commission_rate($request);
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertNull($data['rate']);
        $this->assertSame('', get_user_meta($this->vendor_id, '_mhm_vendor_commission_rate', true));
    }

    public function test_rate_outside_zero_to_hundred_is_rejected(): void
    {
        $request = new WP_REST_Request('POST', '/mhm-rentiva/v1/vendors/vendors/' . $this->vendor_id . '/commission-rate');
        $request->set_param('id', $this->vendor_id);
        $request->set_param('rate', 150.0);

        $response = VendorManagementRestController::update_vendor_commission_rate($request);

        $this->assertSame(400, $response->get_status());
    }

    public function test_unknown_vendor_returns_404(): void
    {
        $request = new WP_REST_Request('POST', '/mhm-rentiva/v1/vendors/vendors/999999/commission-rate');
        $request->set_param('id', 999999);
        $request->set_param('rate', 10.0);

        $response = VendorManagementRestController::update_vendor_commission_rate($request);

        $this->assertSame(404, $response->get_status());
    }

    public function test_vendor_detail_includes_the_commission_rate(): void
    {
        update_user_meta($this->vendor_id, '_mhm_vendor_commission_rate', '8.0');

        $request = new WP_REST_Request('GET', '/mhm-rentiva/v1/vendors/vendors/' . $this->vendor_id);
        $request->set_param('id', $this->vendor_id);

        $response = VendorManagementRestController::get_vendor_detail($request);
        $data     = $response->get_data();

        $this->assertSame(8.0, $data['vendor']['commission_rate']);
    }
}
