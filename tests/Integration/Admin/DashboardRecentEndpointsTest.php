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
        $this->factory->post->create( array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ) );

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
        $this->assertArrayHasKey( 'total_price',   $first );
    }

    public function test_recent_bookings_ordered_by_pickup_date_desc(): void
    {
        $mk = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_PICKUP_DATE;

        $mid = $this->factory->post->create( array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ) );
        update_post_meta( $mid, $mk, '2026-05-20 10:00:00' );

        $new = $this->factory->post->create( array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ) );
        update_post_meta( $new, $mk, '2026-07-05 10:00:00' );

        $old = $this->factory->post->create( array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ) );
        update_post_meta( $old, $mk, '2026-01-10 10:00:00' );

        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-bookings' );
        $response = self::$server->dispatch( $request );
        $items    = $response->get_data()['items'];

        $dates    = array_map( static fn( $i ) => $i['pickup_date'], $items );
        $non_null = array_values( array_filter( $dates, static fn( $d ) => $d !== null && $d !== '' ) );

        // Only assert on our three known pickup dates relative to each other --
        // the DB may already contain other bookings from earlier tests/fixtures.
        $idx_new = array_search( '2026-07-05 10:00:00', $non_null, true );
        $idx_mid = array_search( '2026-05-20 10:00:00', $non_null, true );
        $idx_old = array_search( '2026-01-10 10:00:00', $non_null, true );

        $this->assertNotFalse( $idx_new );
        $this->assertNotFalse( $idx_mid );
        $this->assertNotFalse( $idx_old );
        $this->assertTrue( $idx_new < $idx_mid && $idx_mid < $idx_old, 'pickup dates should be descending' );
    }

    /* ------- recent-transfers -------
     * Task A5b seam inversion: /dashboard/recent-transfers moved to Pro's
     * DashboardExtensions (Edition::isPro()-gated). Lite no longer registers
     * this route at all, so it must 404 rather than return 401/403/200.
     */

    public function test_recent_transfers_route_is_not_registered_in_lite(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/dashboard/recent-transfers' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 404, $response->get_status(), 'Lite must not register /dashboard/recent-transfers -- it is a Pro-only surface (Task A5b).' );
    }
}
