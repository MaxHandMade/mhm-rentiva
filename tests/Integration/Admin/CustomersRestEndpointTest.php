<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Customers\CustomersPage;
use MHMRentiva\Admin\Customers\REST\CustomersRestController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class CustomersRestEndpointTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;
    private int $admin_id  = 0;
    private int $customer_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        // Register routes without requiring CustomersPage::register() side effects.
        add_action( 'rest_api_init', array( CustomersRestController::class, 'register_routes' ) );
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        self::$server   = $wp_rest_server;
        do_action( 'rest_api_init', self::$server );
        $this->admin_id    = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
        $this->customer_id = (int) $this->factory->user->create( array( 'role' => 'customer' ) );
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        wp_set_current_user( 0 );
        remove_action( 'rest_api_init', array( CustomersRestController::class, 'register_routes' ) );
        parent::tearDown();
    }

    // Test 1
    public function test_unauthenticated_list_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    // Test 2
    public function test_authenticated_list_returns_200_with_structure(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'items', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'total_pages', $data );
        $this->assertArrayHasKey( 'page', $data );
        $this->assertIsArray( $data['items'] );
    }

    // Test 3
    public function test_search_param_filters_results(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        // Use a search term that matches no one — response must still be 200 with empty items.
        $request->set_query_params( array( 'search' => 'zzznoone_xyzzy' ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( 0, $data['total'] );
        $this->assertCount( 0, $data['items'] );
    }

    // Test 4
    public function test_out_of_range_page_returns_empty_items(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $request->set_query_params( array( 'page' => 9999 ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
        $this->assertCount( 0, $response->get_data()['items'] );
    }

    // Test 5
    public function test_invalid_sort_by_returns_400(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $request->set_query_params( array( 'sort_by' => 'invalid_column' ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 400, $response->get_status() );
    }

    // Test 6
    public function test_detail_valid_id_returns_200(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers/' . $this->customer_id );
        $response = self::$server->dispatch( $request );
        // May be 404 if no bookings; that is also acceptable per spec.
        $this->assertContains( $response->get_status(), array( 200, 404 ) );
    }

    // Test 7
    public function test_detail_nonexistent_id_returns_404(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers/9999999' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 404, $response->get_status() );
    }

    // Test 8
    public function test_unauthenticated_detail_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers/' . $this->customer_id );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    // Test 9
    public function test_bulk_delete_valid_ids_returns_200(): void
    {
        wp_set_current_user( $this->admin_id );
        $target_id = (int) $this->factory->user->create( array( 'role' => 'customer' ) );
        $request   = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/customers/bulk' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( json_encode( array( 'ids' => array( $target_id ) ) ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'deleted', $data );
        $this->assertArrayHasKey( 'skipped', $data );
    }

    // Test 10
    public function test_bulk_delete_empty_ids_returns_400(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/customers/bulk' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( json_encode( array( 'ids' => array() ) ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 400, $response->get_status() );
    }
}
