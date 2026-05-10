<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class DashboardRecentEndpointsTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;
    private int $admin_id  = 0;
    private int $editor_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        DashboardPage::register();
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        self::$server   = $wp_rest_server;
        do_action( 'rest_api_init', self::$server );

        $this->admin_id  = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
        $this->editor_id = (int) $this->factory->user->create( array( 'role' => 'editor' ) );
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        wp_set_current_user( 0 );
        remove_action( 'rest_api_init', array( DashboardPage::class, 'register_rest_routes' ) );
        parent::tearDown();
    }

    /* ------- recent-bookings ------- */

    public function test_recent_bookings_unauthenticated_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-bookings' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    public function test_recent_bookings_editor_returns_403(): void
    {
        wp_set_current_user( $this->editor_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-bookings' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 403, $response->get_status() );
    }

    public function test_recent_bookings_admin_returns_200(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-bookings' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
    }

    public function test_recent_bookings_response_shape(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-bookings' );
        $response = self::$server->dispatch( $request );
        $data     = $response->get_data();

        $this->assertArrayHasKey( 'items',       $data );
        $this->assertArrayHasKey( 'total',       $data );
        $this->assertArrayHasKey( 'total_pages', $data );
        $this->assertArrayHasKey( 'page',        $data );
        $this->assertIsArray( $data['items'] );
        $this->assertSame( 1, $data['page'] );
    }

    public function test_recent_bookings_page_param_honored(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-bookings' );
        $request->set_query_params( array( 'page' => 2 ) );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 2, $response->get_data()['page'] );
    }

    public function test_recent_bookings_items_have_required_keys(): void
    {
        // Create a booking post so items is not empty.
        $this->factory->post->create( array( 'post_type' => 'vehicle_booking', 'post_status' => 'publish' ) );

        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-bookings' );
        $response = self::$server->dispatch( $request );
        $items    = $response->get_data()['items'];

        if ( empty( $items ) ) {
            $this->markTestSkipped( 'No bookings in test DB.' );
        }

        $first = $items[0];
        $this->assertArrayHasKey( 'id',            $first );
        $this->assertArrayHasKey( 'customer_name', $first );
        $this->assertArrayHasKey( 'vehicle_title', $first );
        $this->assertArrayHasKey( 'pickup_date',   $first );
        $this->assertArrayHasKey( 'status',        $first );
        $this->assertArrayHasKey( 'status_label',  $first );
        $this->assertArrayHasKey( 'display_id',    $first );
    }

    /* ------- recent-transfers ------- */

    public function test_recent_transfers_unauthenticated_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-transfers' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    public function test_recent_transfers_admin_returns_200(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-transfers' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
    }

    public function test_recent_transfers_response_shape(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-transfers' );
        $response = self::$server->dispatch( $request );
        $data     = $response->get_data();

        $this->assertArrayHasKey( 'items',       $data );
        $this->assertArrayHasKey( 'stats',       $data );
        $this->assertArrayHasKey( 'total',       $data );
        $this->assertArrayHasKey( 'total_pages', $data );
        $this->assertArrayHasKey( 'page',        $data );
        $this->assertArrayHasKey( 'total',           $data['stats'] );
        $this->assertArrayHasKey( 'this_month',      $data['stats'] );
        $this->assertArrayHasKey( 'revenue_this_month', $data['stats'] );
    }
}
