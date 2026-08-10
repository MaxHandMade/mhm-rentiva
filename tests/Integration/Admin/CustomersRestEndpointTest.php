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
        // The optimizer caches list/stats payloads by parameter hash; a warm
        // object cache from an earlier test in the same run would leak stale
        // rows into the assertions below.
        \MHMRentiva\Admin\Customers\CustomersOptimizer::clear_cache();
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

    /**
     * Create a booking post keyed to a customer e-mail, the way the optimizer
     * queries expect them (email + total price meta, optional vehicle link).
     */
    private function create_booking( string $email, string $price, int $vehicle_id = 0 ): int
    {
        $booking_id = (int) $this->factory->post->create(
            array(
                'post_type'   => 'mhmrentiva_booking',
                'post_status' => 'publish',
                'post_title'  => 'Booking for ' . $email,
            )
        );
        update_post_meta( $booking_id, '_mhmrentiva_customer_email', $email );
        update_post_meta( $booking_id, '_mhmrentiva_total_price', $price );
        if ( $vehicle_id > 0 ) {
            update_post_meta( $booking_id, '_mhmrentiva_vehicle_id', $vehicle_id );
        }
        return $booking_id;
    }

    // Test 11 — the status arg is schema-validated against its enum.
    public function test_invalid_status_returns_400(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $request->set_query_params( array( 'status' => 'bogus' ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 400, $response->get_status() );
    }

    // Test 12 — list rows carry the derived status tag.
    public function test_list_rows_include_status_key(): void
    {
        wp_set_current_user( $this->admin_id );
        $this->create_booking( get_userdata( $this->customer_id )->user_email, '100.00' );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
        $items = $response->get_data()['items'];
        $this->assertNotEmpty( $items );
        foreach ( $items as $item ) {
            $this->assertArrayHasKey( 'status', $item );
            $this->assertContains( $item['status'], array( 'vip', 'new', 'active', 'none' ) );
        }
    }

    // Test 13 — status=vip returns only customers at or above the booking floor.
    public function test_status_vip_filters_to_vip_customers(): void
    {
        wp_set_current_user( $this->admin_id );

        $vip_email    = get_userdata( $this->customer_id )->user_email;
        $casual_id    = (int) $this->factory->user->create( array( 'role' => 'customer' ) );
        $casual_email = get_userdata( $casual_id )->user_email;

        for ( $i = 0; $i < 5; $i++ ) {
            $this->create_booking( $vip_email, '100.00' );
        }
        $this->create_booking( $casual_email, '50.00' );

        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $request->set_query_params( array( 'status' => 'vip' ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );

        $items = $response->get_data()['items'];
        $ids   = array_column( $items, 'id' );
        $this->assertContains( $this->customer_id, $ids );
        $this->assertNotContains( $casual_id, $ids );
        foreach ( $items as $item ) {
            if ( $item['id'] === $this->customer_id ) {
                $this->assertSame( 'vip', $item['status'] );
            }
        }
    }

    // Test 14 — detail payload carries the panel's new fields.
    public function test_detail_includes_recent_bookings_and_favorites(): void
    {
        wp_set_current_user( $this->admin_id );

        $email      = get_userdata( $this->customer_id )->user_email;
        $vehicle_id = (int) $this->factory->post->create(
            array(
                'post_type'   => 'mhmrentiva_vehicle',
                'post_status' => 'publish',
                'post_title'  => 'Panel Test Car',
            )
        );
        $this->create_booking( $email, '250.00', $vehicle_id );
        $this->create_booking( $email, '400.00' );
        update_user_meta( $this->customer_id, 'mhmrentiva_favorites', array( $vehicle_id ) );

        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers/' . $this->customer_id );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'recent_bookings', $data );
        $this->assertArrayHasKey( 'favorites_count', $data );
        $this->assertArrayHasKey( 'status', $data );
        $this->assertSame( 1, $data['favorites_count'] );
        $this->assertCount( 2, $data['recent_bookings'] );
        foreach ( $data['recent_bookings'] as $booking ) {
            $this->assertArrayHasKey( 'vehicle', $booking );
            $this->assertArrayHasKey( 'date', $booking );
            $this->assertArrayHasKey( 'amount', $booking );
        }
        $vehicles = array_column( $data['recent_bookings'], 'vehicle' );
        $this->assertContains( 'Panel Test Car', $vehicles );
    }
}
