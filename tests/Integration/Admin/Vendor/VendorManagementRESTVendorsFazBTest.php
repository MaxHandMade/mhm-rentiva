<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Vendor;

use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @group vendor-management
 * @group vendor-management-faz-b
 */
final class VendorManagementRESTVendorsFazBTest extends WP_UnitTestCase {

	private static WP_REST_Server $server;
	private int $admin_id            = 0;
	private int $vendor_active_id    = 0;
	private int $vendor_suspended_id = 0;

	public function setUp(): void {
		parent::setUp();

		add_action( 'rest_api_init', array( \MHMRentiva\Admin\Vendor\REST\VendorManagementRestController::class, 'register_routes' ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		self::$server   = $wp_rest_server;
		do_action( 'rest_api_init', self::$server );

		$this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );

		$this->vendor_active_id = (int) $this->factory->user->create( array(
			'role'         => 'rentiva_vendor',
			'display_name' => 'Active Vendor Test',
			'user_email'   => 'vendor-fazb-active@example.com',
		) );
		update_user_meta( $this->vendor_active_id, '_rentiva_vendor_status',           'active' );
		update_user_meta( $this->vendor_active_id, '_rentiva_vendor_city',             'Istanbul' );
		update_user_meta( $this->vendor_active_id, '_rentiva_vendor_approved_at',      '2026-01-15 10:00:00' );
		update_user_meta( $this->vendor_active_id, '_rentiva_vendor_reliability_score', 85 );

		$this->vendor_suspended_id = (int) $this->factory->user->create( array(
			'role'         => 'rentiva_vendor',
			'display_name' => 'Suspended Vendor Test',
			'user_email'   => 'vendor-fazb-suspended@example.com',
		) );
		update_user_meta( $this->vendor_suspended_id, '_rentiva_vendor_status',           'suspended' );
		update_user_meta( $this->vendor_suspended_id, '_rentiva_vendor_city',             'Ankara' );
		update_user_meta( $this->vendor_suspended_id, '_rentiva_vendor_approved_at',      '2026-02-01 10:00:00' );
		update_user_meta( $this->vendor_suspended_id, '_rentiva_vendor_reliability_score', 40 );
	}

	public function tearDown(): void {
		global $wp_rest_server;

		wp_delete_user( $this->vendor_active_id );
		wp_delete_user( $this->vendor_suspended_id );
		wp_delete_user( $this->admin_id );

		$wp_rest_server = null;
		wp_set_current_user( 0 );
		remove_action( 'rest_api_init', array( \MHMRentiva\Admin\Vendor\REST\VendorManagementRestController::class, 'register_routes' ) );
		parent::tearDown();
	}

	public function test_unauthenticated_vendors_list_returns_401(): void {
		wp_set_current_user( 0 );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/vendors' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_authenticated_vendors_list_returns_200_with_structure(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/vendors' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'vendors', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'pages', $data );
		$this->assertArrayHasKey( 'current_page', $data );
		$this->assertIsArray( $data['vendors'] );
		$this->assertIsInt( $data['total'] );
		$this->assertIsInt( $data['pages'] );
		$this->assertIsInt( $data['current_page'] );
	}

	public function test_vendors_list_includes_active_and_suspended_by_default(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/vendors' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$ids  = array_column( $data['vendors'], 'id' );
		$this->assertContains( $this->vendor_active_id,    $ids, 'Active vendor must appear in unfiltered list' );
		$this->assertContains( $this->vendor_suspended_id, $ids, 'Suspended vendor must appear in unfiltered list' );
	}

	public function test_vendors_list_filter_by_status_active_excludes_suspended(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/vendors' );
		$request->set_query_params( array( 'status' => 'active' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$ids = array_column( $response->get_data()['vendors'], 'id' );
		$this->assertContains(    $this->vendor_active_id,    $ids, 'Active vendor must appear with status=active filter' );
		$this->assertNotContains( $this->vendor_suspended_id, $ids, 'Suspended vendor must NOT appear with status=active filter' );
	}

	public function test_vendors_list_filter_by_status_suspended(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/vendors' );
		$request->set_query_params( array( 'status' => 'suspended' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$ids = array_column( $response->get_data()['vendors'], 'id' );
		$this->assertContains(    $this->vendor_suspended_id, $ids, 'Suspended vendor must appear with status=suspended filter' );
		$this->assertNotContains( $this->vendor_active_id,    $ids, 'Active vendor must NOT appear with status=suspended filter' );
	}

	public function test_vendors_list_search_filters_by_name(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/vendors' );
		$request->set_query_params( array( 'search' => 'Active Vendor' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$ids = array_column( $response->get_data()['vendors'], 'id' );
		$this->assertContains(    $this->vendor_active_id,    $ids, 'Active vendor must appear in search results' );
		$this->assertNotContains( $this->vendor_suspended_id, $ids, 'Suspended vendor must NOT appear when searching for active vendor name' );
	}

	public function test_suspend_vendor_sets_status_meta_to_suspended(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/vendors/{$this->vendor_active_id}/suspend" ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( 'suspended', get_user_meta( $this->vendor_active_id, '_rentiva_vendor_status', true ) );
	}

	public function test_unsuspend_vendor_sets_status_meta_to_active(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/vendors/{$this->vendor_suspended_id}/unsuspend" ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$status = get_user_meta( $this->vendor_suspended_id, '_rentiva_vendor_status', true );
		$this->assertSame( 'active', $status, 'Vendor status must be "active" after unsuspend' );
	}

	public function test_update_vendor_city_with_valid_city_updates_canonical_meta(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/vendors/{$this->vendor_active_id}/city" );
		$request->set_body_params( array( 'city' => 'Ankara' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( 'Ankara', $response->get_data()['city'] );
		$this->assertSame(
			'Ankara',
			get_user_meta( $this->vendor_active_id, \MHMRentiva\Admin\Core\MetaKeys::VENDOR_CITY, true ),
			'Admin city edit must write the canonical _rentiva_vendor_city user meta'
		);
	}

	public function test_update_vendor_city_rejects_city_outside_the_known_list(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/vendors/{$this->vendor_active_id}/city" );
		$request->set_body_params( array( 'city' => 'Gotham City' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame(
			'Istanbul',
			get_user_meta( $this->vendor_active_id, \MHMRentiva\Admin\Core\MetaKeys::VENDOR_CITY, true ),
			'An invalid city must not overwrite the existing value'
		);
	}

	public function test_update_vendor_city_on_non_vendor_returns_404(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/vendors/{$this->admin_id}/city" );
		$request->set_body_params( array( 'city' => 'Ankara' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_update_vendor_city_unauthenticated_returns_401(): void {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/vendors/{$this->vendor_active_id}/city" );
		$request->set_body_params( array( 'city' => 'Ankara' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}
}
