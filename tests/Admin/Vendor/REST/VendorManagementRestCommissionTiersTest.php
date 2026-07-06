<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vendor\REST;

use MHMRentiva\Admin\Vendor\REST\VendorManagementRestController;
use WP_REST_Request;
use WP_UnitTestCase;

class VendorManagementRestCommissionTiersTest extends WP_UnitTestCase
{
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
        wp_set_current_user($this->admin_id);
        delete_option('mhm_rentiva_commission_tiers');
    }

    public function test_get_returns_the_default_tiers_when_no_option_set(): void
    {
        $response = VendorManagementRestController::get_commission_tiers();
        $data     = $response->get_data();

        $this->assertCount(3, $data['tiers']);
        $this->assertSame(30000.0, $data['tiers'][0]['threshold']);
        $this->assertSame(6.0, $data['tiers'][0]['discount']);
    }

    public function test_saving_new_tiers_updates_the_option(): void
    {
        $request = new WP_REST_Request('POST', '/mhm-rentiva/v1/vendors/commission-tiers');
        $request->set_param('tiers', array(
            array('threshold' => 50000.0, 'discount' => 8.0),
            array('threshold' => 20000.0, 'discount' => 5.0),
            array('threshold' => 8000.0, 'discount' => 2.0),
        ));

        $response = VendorManagementRestController::save_commission_tiers($request);
        $data     = $response->get_data();

        $this->assertTrue($data['success']);

        $saved = get_option('mhm_rentiva_commission_tiers');
        $this->assertSame(50000.0, $saved[0]['threshold']);
        $this->assertSame(8.0, $saved[0]['discount']);
    }

    public function test_saving_wrong_number_of_tiers_is_rejected(): void
    {
        $request = new WP_REST_Request('POST', '/mhm-rentiva/v1/vendors/commission-tiers');
        $request->set_param('tiers', array(
            array('threshold' => 50000.0, 'discount' => 8.0),
        ));

        $response = VendorManagementRestController::save_commission_tiers($request);

        $this->assertSame(400, $response->get_status());
    }

    public function test_saving_a_tier_with_an_out_of_range_discount_is_rejected(): void
    {
        $request = new WP_REST_Request('POST', '/mhm-rentiva/v1/vendors/commission-tiers');
        $request->set_param('tiers', array(
            array('threshold' => 50000.0, 'discount' => 8.0),
            array('threshold' => 20000.0, 'discount' => 150.0),
            array('threshold' => 8000.0, 'discount' => 2.0),
        ));

        $response = VendorManagementRestController::save_commission_tiers($request);
        $data     = $response->get_data();

        $this->assertSame(400, $response->get_status());
        $this->assertSame('invalid_tier_value', $data['code']);
    }
}
