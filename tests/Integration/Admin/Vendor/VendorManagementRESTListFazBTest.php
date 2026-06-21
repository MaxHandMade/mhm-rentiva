<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Vendor;

use MHMRentiva\Admin\Vendor\REST\VendorManagementRestController;
use MHMRentiva\Admin\Vendor\VendorOnboardingController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Vendor Management "Active Vendors" list — status filtering.
 *
 * Regression guard: suspend() removes the rentiva_vendor role, so a role-based
 * list query hides suspended vendors entirely (even under "All"). The list must
 * enumerate by the durable _rentiva_vendor_status meta instead.
 *
 * @group vendor-management
 * @group vendor-management-faz-b
 */
final class VendorManagementRESTListFazBTest extends WP_UnitTestCase {

	private static WP_REST_Server $server;
	private int $admin_id = 0;

	public function setUp(): void {
		parent::setUp();

		add_action( 'rest_api_init', array( VendorManagementRestController::class, 'register_routes' ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		self::$server   = $wp_rest_server;
		do_action( 'rest_api_init', self::$server );

		$this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function tearDown(): void {
		global $wp_rest_server;

		wp_delete_user( $this->admin_id );
		$wp_rest_server = null;
		wp_set_current_user( 0 );
		remove_action( 'rest_api_init', array( VendorManagementRestController::class, 'register_routes' ) );
		parent::tearDown();
	}

	private function make_vendor( string $name ): int {
		$uid = (int) $this->factory->user->create( array( 'display_name' => $name, 'role' => 'rentiva_vendor' ) );
		update_user_meta( $uid, '_rentiva_vendor_status', 'active' );
		update_user_meta( $uid, '_rentiva_vendor_approved_at', current_time( 'mysql' ) );
		return $uid;
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>
	 */
	private function dispatch_list( array $params ): array {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/vendors' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		return (array) $response->get_data();
	}

	public function test_all_status_includes_suspended_vendor(): void {
		$active    = $this->make_vendor( 'Active Vendor' );
		$suspended = $this->make_vendor( 'Suspended Vendor' );
		VendorOnboardingController::suspend( $suspended ); // strips role, sets status=suspended

		$data = $this->dispatch_list( array( 'per_page' => 50, 'page' => 1, 'status' => 'all' ) );

		$ids = array_column( $data['vendors'], 'id' );
		$this->assertContains( $active, $ids, 'Active vendor must appear under All.' );
		$this->assertContains( $suspended, $ids, 'Suspended vendor must appear under All (role was removed on suspend).' );
	}

	public function test_suspended_filter_returns_only_suspended(): void {
		$active    = $this->make_vendor( 'Active Vendor' );
		$suspended = $this->make_vendor( 'Suspended Vendor' );
		VendorOnboardingController::suspend( $suspended );

		$data = $this->dispatch_list( array( 'per_page' => 50, 'page' => 1, 'status' => 'suspended' ) );

		$ids = array_column( $data['vendors'], 'id' );
		$this->assertContains( $suspended, $ids );
		$this->assertNotContains( $active, $ids );
		foreach ( $data['vendors'] as $vendor ) {
			$this->assertSame( 'suspended', $vendor['status'] );
		}
	}

	public function test_active_filter_excludes_suspended(): void {
		$active    = $this->make_vendor( 'Active Vendor' );
		$suspended = $this->make_vendor( 'Suspended Vendor' );
		VendorOnboardingController::suspend( $suspended );

		$data = $this->dispatch_list( array( 'per_page' => 50, 'page' => 1, 'status' => 'active' ) );

		$ids = array_column( $data['vendors'], 'id' );
		$this->assertContains( $active, $ids );
		$this->assertNotContains( $suspended, $ids );
	}
}
