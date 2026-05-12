<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Vendor;

use MHMRentiva\Core\Financial\PolicyRepository;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @group vendor-management
 * @group vendor-management-faz-b
 */
final class VendorManagementRESTCommissionFazBTest extends WP_UnitTestCase {

	private static WP_REST_Server $server;
	private int $admin_id = 0;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}mhm_rentiva_commission_policy" );

		add_action( 'rest_api_init', array( \MHMRentiva\Admin\Vendor\REST\VendorManagementRestController::class, 'register_routes' ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		self::$server   = $wp_rest_server;
		do_action( 'rest_api_init', self::$server );

		$this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function tearDown(): void {
		global $wp_rest_server, $wpdb;

		wp_delete_user( $this->admin_id );

		$wp_rest_server = null;
		wp_set_current_user( 0 );
		remove_action( 'rest_api_init', array( \MHMRentiva\Admin\Vendor\REST\VendorManagementRestController::class, 'register_routes' ) );
		parent::tearDown();

		$wpdb->query( "DELETE FROM {$wpdb->prefix}mhm_rentiva_commission_policy" );
	}

	public function test_unauthenticated_commission_returns_401(): void {
		wp_set_current_user( 0 );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/commission' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_get_commission_no_policy_returns_null_rate(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/commission' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertNull( $data['current_rate'], 'current_rate must be null when no policy exists' );
		$this->assertSame( array(), $data['history'], 'history must be empty array when no policy exists' );
	}

	public function test_get_commission_with_policy_returns_rate_and_history(): void {
		PolicyRepository::insert_global_policy( 15.0, 'Test Rate' );

		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/commission' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 15.0, $data['current_rate'], 'current_rate must be 15.0 after inserting policy' );
		$this->assertCount( 1, $data['history'], 'history must have exactly 1 entry' );
		$entry = $data['history'][0];
		$this->assertArrayHasKey( 'rate',           $entry );
		$this->assertArrayHasKey( 'label',          $entry );
		$this->assertArrayHasKey( 'effective_from', $entry );
	}

	public function test_save_commission_creates_new_policy(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/vendors/commission' );
		$request->set_body_params( array(
			'global_rate'  => 20.0,
			'policy_label' => 'New Rate',
		) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( 20.0, $data['new_rate'] );
		$this->assertSame( 20.0, PolicyRepository::get_current_global_rate() );
	}

	public function test_save_commission_rejects_rate_above_100(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/vendors/commission' );
		$request->set_body_params( array( 'global_rate' => 150 ) );
		$response = self::$server->dispatch( $request );
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
		$this->assertLessThan( 500, $response->get_status() );
	}

	public function test_save_commission_rejects_missing_global_rate(): void {
		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/vendors/commission' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}
}
