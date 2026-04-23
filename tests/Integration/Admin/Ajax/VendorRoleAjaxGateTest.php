<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Ajax;

use MHMRentiva\Admin\Vehicle\VehicleLifecycleAjaxController;
use MHMRentiva\Core\Dashboard\AnalyticsController;
use MHMRentiva\Core\Financial\PayoutAjaxController;
use WP_Ajax_UnitTestCase;

class VendorRoleAjaxGateTest extends WP_Ajax_UnitTestCase
{
    private int $vendor_user_id;

    public function set_up()
    {
        parent::set_up();

        if (! get_role('rentiva_vendor')) {
            add_role('rentiva_vendor', 'Rentiva Vendor', array('read' => true));
        }

        $this->vendor_user_id = $this->factory->user->create(array('role' => 'subscriber'));
        $vendor_user          = new \WP_User($this->vendor_user_id);
        $vendor_user->add_role('rentiva_vendor');

        if (! has_action('wp_ajax_mhm_request_payout', array(PayoutAjaxController::class, 'handle_request_payout'))) {
            PayoutAjaxController::register();
        }

        if (! has_action('wp_ajax_mhm_fetch_vendor_stats', array(AnalyticsController::class, 'fetch_vendor_stats'))) {
            AnalyticsController::register();
        }

        if (! has_action('wp_ajax_mhm_vehicle_lifecycle_pause', array(VehicleLifecycleAjaxController::class, 'handle_pause'))) {
            VehicleLifecycleAjaxController::register();
        }

        $_POST    = array();
        $_REQUEST = array();
    }

    public function tear_down()
    {
        $_POST    = array();
        $_REQUEST = array();
        wp_set_current_user(0);

        parent::tear_down();
    }

    /** @test */
    public function vendor_role_can_reach_payout_validation()
    {
        wp_set_current_user($this->vendor_user_id);

        $_POST['action']        = 'mhm_request_payout';
        $_REQUEST['action']     = 'mhm_request_payout';
        $_POST['nonce']         = wp_create_nonce('mhm_rentiva_vendor_nonce');
        $_REQUEST['nonce']      = $_POST['nonce'];
        $_POST['payout_amount'] = '0';
        $_REQUEST['payout_amount'] = '0';

        try {
            $this->_handleAjax('mhm_request_payout');
            $this->fail('Expected AJAX handler to terminate.');
        } catch (\WPAjaxDieContinueException $e) {
        } catch (\WPAjaxDieStopException $e) {
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'] ?? true);
        $this->assertSame('Invalid payout amount requested.', $response['data']['message'] ?? '');
    }

    /** @test */
    public function vendor_role_can_reach_analytics_validation()
    {
        wp_set_current_user($this->vendor_user_id);

        $_POST['action']     = 'mhm_fetch_vendor_stats';
        $_REQUEST['action']  = 'mhm_fetch_vendor_stats';
        $_POST['nonce']      = wp_create_nonce('mhm_rentiva_vendor_nonce');
        $_REQUEST['nonce']   = $_POST['nonce'];
        $_POST['start_date'] = '';
        $_REQUEST['start_date'] = '';
        $_POST['end_date']   = '';
        $_REQUEST['end_date'] = '';

        try {
            $this->_handleAjax('mhm_fetch_vendor_stats');
            $this->fail('Expected AJAX handler to terminate.');
        } catch (\WPAjaxDieContinueException $e) {
        } catch (\WPAjaxDieStopException $e) {
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'] ?? true);
        $this->assertSame('Invalid date range', $response['data']['message'] ?? '');
    }

    /** @test */
    public function vendor_role_can_reach_vehicle_lifecycle_validation()
    {
        wp_set_current_user($this->vendor_user_id);

        $_POST['action']      = 'mhm_vehicle_lifecycle_pause';
        $_REQUEST['action']   = 'mhm_vehicle_lifecycle_pause';
        $_POST['nonce']       = wp_create_nonce('mhm_rentiva_vehicle_lifecycle');
        $_REQUEST['nonce']    = $_POST['nonce'];
        $_POST['vehicle_id']  = '0';
        $_REQUEST['vehicle_id'] = '0';

        try {
            $this->_handleAjax('mhm_vehicle_lifecycle_pause');
            $this->fail('Expected AJAX handler to terminate.');
        } catch (\WPAjaxDieContinueException $e) {
        } catch (\WPAjaxDieStopException $e) {
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'] ?? true);
        $this->assertSame('Invalid vehicle ID.', $response['data']['message'] ?? '');
    }
}
