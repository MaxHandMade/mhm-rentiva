<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Reports\Reports;
use MHMRentiva\Admin\Reports\REST\ReportsRestController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class ReportsRestEndpointTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;
    private int $admin_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        Reports::register();
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        self::$server   = $wp_rest_server;
        do_action( 'rest_api_init', self::$server );
        $this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        wp_set_current_user( 0 );
        remove_action( 'rest_api_init', array( ReportsRestController::class, 'register_routes' ) );
        parent::tearDown();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/reports' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    public function test_invalid_date_format_returns_400(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/reports' );
        $request->set_query_params( array( 'start_date' => 'not-a-date', 'end_date' => gmdate( 'Y-m-d' ) ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 400, $response->get_status() );
    }

    public function test_start_after_end_returns_400(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/reports' );
        $request->set_query_params( array(
            'start_date' => gmdate( 'Y-m-d' ),
            'end_date'   => gmdate( 'Y-m-d', strtotime( '-10 days' ) ),
        ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 400, $response->get_status() );
    }

    public function test_lite_user_exceeding_range_returns_403(): void
    {
        wp_set_current_user( $this->admin_id );
        // Default test env has no feature token → Lite mode, max 30 days.
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/reports' );
        $request->set_query_params( array(
            'start_date' => gmdate( 'Y-m-d', strtotime( '-60 days' ) ),
            'end_date'   => gmdate( 'Y-m-d' ),
        ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 403, $response->get_status() );
    }

    public function test_revenue_tab_returns_200_with_data_key(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/reports' );
        $request->set_query_params( array(
            'tab'        => 'revenue',
            'start_date' => gmdate( 'Y-m-d', strtotime( '-14 days' ) ),
            'end_date'   => gmdate( 'Y-m-d' ),
        ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
        $this->assertArrayHasKey( 'data', $response->get_data() );
    }

    public function test_bookings_tab_returns_200(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/reports' );
        $request->set_query_params( array(
            'tab'        => 'bookings',
            'start_date' => gmdate( 'Y-m-d', strtotime( '-14 days' ) ),
            'end_date'   => gmdate( 'Y-m-d' ),
        ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
        $this->assertArrayHasKey( 'data', $response->get_data() );
    }

    public function test_overview_tab_returns_all_four_keys(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/reports' );
        $request->set_query_params( array(
            'tab'        => 'overview',
            'start_date' => gmdate( 'Y-m-d', strtotime( '-14 days' ) ),
            'end_date'   => gmdate( 'Y-m-d' ),
        ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data()['data'];
        $this->assertArrayHasKey( 'revenue',   $data );
        $this->assertArrayHasKey( 'bookings',  $data );
        $this->assertArrayHasKey( 'vehicles',  $data );
        $this->assertArrayHasKey( 'customers', $data );
    }

    public function test_unknown_tab_returns_400(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/reports' );
        $request->set_query_params( array(
            'tab'        => 'xyz',
            'start_date' => gmdate( 'Y-m-d', strtotime( '-14 days' ) ),
            'end_date'   => gmdate( 'Y-m-d' ),
        ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 400, $response->get_status() );
    }
}
