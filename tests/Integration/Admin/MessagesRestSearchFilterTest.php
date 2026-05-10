<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Messages\REST\Messages as MessagesREST;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class MessagesRestSearchFilterTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;
    private int $admin_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        // Call register_routes() directly to bypass the Mode::canUseMessages() license gate.
        add_action( 'rest_api_init', array( MessagesREST::class, 'register_routes' ) );
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
        remove_action( 'rest_api_init', array( MessagesREST::class, 'register_routes' ) );
        parent::tearDown();
    }

    private function make_message( string $customer_name, string $status = 'pending', string $priority = 'normal' ): int
    {
        $id = (int) $this->factory->post->create( array(
            'post_type'    => 'mhm_message',
            'post_status'  => 'publish',
            'post_title'   => 'Subject from ' . $customer_name,
        ) );
        update_post_meta( $id, '_mhm_customer_name',  $customer_name );
        update_post_meta( $id, '_mhm_message_status', $status );
        update_post_meta( $id, '_mhm_message_priority', $priority );
        update_post_meta( $id, '_mhm_customer_email', strtolower( $customer_name ) . '@example.com' );
        update_post_meta( $id, '_mhm_message_category', 'general' );
        return $id;
    }

    // Test 5
    public function test_unauthenticated_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/messages' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    // Test 6
    public function test_authenticated_list_returns_200_with_structure(): void
    {
        wp_set_current_user( $this->admin_id );
        $this->make_message( 'Ali Veli' );

        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/messages' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'messages', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'pages', $data );
    }

    // Test 7
    public function test_search_filters_by_customer_name(): void
    {
        wp_set_current_user( $this->admin_id );
        $this->make_message( 'Findable Person' );
        $this->make_message( 'Other Person' );

        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/messages' );
        $request->set_query_params( array( 'search' => 'Findable' ) );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( 1, $data['total'] );
        $this->assertSame( 'Findable Person', $data['messages'][0]->customer_name );
    }

    // Test 8
    public function test_priority_filter_returns_only_matching(): void
    {
        wp_set_current_user( $this->admin_id );
        $this->make_message( 'Normal User',  'pending', 'normal' );
        $this->make_message( 'Urgent User',  'pending', 'urgent' );

        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/messages' );
        $request->set_query_params( array( 'priority' => 'urgent' ) );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( 1, $data['total'] );
        $this->assertSame( 'Urgent User', $data['messages'][0]->customer_name );
    }

    // Test 9
    public function test_status_and_category_filters_combine(): void
    {
        wp_set_current_user( $this->admin_id );
        $id1 = $this->make_message( 'Alice', 'pending' );
        update_post_meta( $id1, '_mhm_message_category', 'booking' );
        $id2 = $this->make_message( 'Bob', 'answered' );
        update_post_meta( $id2, '_mhm_message_category', 'booking' );

        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/messages' );
        $request->set_query_params( array( 'status' => 'pending', 'category' => 'booking' ) );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( 1, $data['total'] );
    }

    // Test 10
    public function test_unauthenticated_thread_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/messages/999' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    // Test 11
    public function test_reply_with_close_thread_true_updates_status(): void
    {
        wp_set_current_user( $this->admin_id );
        $id = $this->make_message( 'Customer A', 'pending' );

        $request = new WP_REST_Request( 'POST', "/mhm-rentiva/v1/messages/{$id}/reply" );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( json_encode( array(
            'message'      => 'Yanıt metni',
            'close_thread' => true,
        ) ) );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $status = get_post_meta( $id, '_mhm_message_status', true );
        $this->assertSame( 'closed', $status );
    }

    // Test 12
    public function test_status_update_with_invalid_value_returns_400(): void
    {
        wp_set_current_user( $this->admin_id );
        $id = $this->make_message( 'Customer B' );

        $request = new WP_REST_Request( 'POST', "/mhm-rentiva/v1/messages/{$id}/status" );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( json_encode( array( 'status' => 'nonexistent_status' ) ) );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 400, $response->get_status() );
    }
}
