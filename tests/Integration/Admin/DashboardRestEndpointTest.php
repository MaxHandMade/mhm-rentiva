<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class DashboardRestEndpointTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;
    private int $admin_id  = 0;
    private int $editor_id = 0;

    public function setUp(): void
    {
        parent::setUp();
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
        parent::tearDown();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/upcoming' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    public function test_editor_request_returns_403(): void
    {
        wp_set_current_user( $this->editor_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/upcoming' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 403, $response->get_status() );
    }

    public function test_admin_request_returns_200(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/upcoming' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
    }

    public function test_response_has_required_keys(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/upcoming' );
        $response = self::$server->dispatch( $request );
        $data     = $response->get_data();
        $this->assertArrayHasKey( 'items',       $data );
        $this->assertArrayHasKey( 'total',       $data );
        $this->assertArrayHasKey( 'total_pages', $data );
        $this->assertArrayHasKey( 'page',        $data );
    }

    public function test_page_defaults_to_1(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/upcoming' );
        $response = self::$server->dispatch( $request );
        $data     = $response->get_data();
        $this->assertSame( 1, $data['page'] );
    }

    public function test_page_param_is_honored(): void
    {
        wp_set_current_user( $this->admin_id );
        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/upcoming' );
        $request->set_query_params( array( 'page' => 2 ) );
        $response = self::$server->dispatch( $request );
        $data     = $response->get_data();
        $this->assertSame( 2, $data['page'] );
    }
}
