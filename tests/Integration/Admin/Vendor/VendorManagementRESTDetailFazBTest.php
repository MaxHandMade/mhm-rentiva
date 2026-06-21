<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Vendor;

use MHMRentiva\Admin\Vendor\REST\VendorManagementRestController;
use MHMRentiva\Admin\Vendor\VendorOnboardingController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Vendor Management — vendor detail endpoint (clickable vendor name target).
 *
 * @group vendor-management
 * @group vendor-management-faz-b
 */
final class VendorManagementRESTDetailFazBTest extends WP_UnitTestCase {

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
		return $uid;
	}

	public function test_detail_returns_vendor_with_vehicles_including_archived(): void {
		$vendor = $this->make_vendor( 'Detail Vendor' );

		$live = (int) $this->factory->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish', 'post_author' => $vendor ) );
		update_post_meta( $live, '_mhm_vehicle_lifecycle_status', 'active' );
		$archived = (int) $this->factory->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'draft', 'post_author' => $vendor ) );
		update_post_meta( $archived, '_mhm_vehicle_lifecycle_status', 'withdrawn' );

		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', "/mhm-rentiva/v1/vendors/vendors/{$vendor}" ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( $vendor, $data['vendor']['id'] );
		$this->assertSame( 'Detail Vendor', $data['vendor']['display_name'] );
		$vehicle_ids = array_column( $data['vendor']['vehicles'], 'id' );
		$this->assertContains( $live, $vehicle_ids );
		$this->assertContains( $archived, $vehicle_ids, 'Archived (withdrawn/draft) vehicles must appear in vendor detail.' );
	}

	public function test_detail_works_for_suspended_vendor(): void {
		$vendor = $this->make_vendor( 'Suspended Detail Vendor' );
		VendorOnboardingController::suspend( $vendor ); // role removed, status meta retained

		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', "/mhm-rentiva/v1/vendors/vendors/{$vendor}" ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'suspended', $response->get_data()['vendor']['status'] );
	}

	public function test_detail_404_for_non_vendor(): void {
		$user = (int) $this->factory->user->create( array( 'role' => 'customer' ) );

		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', "/mhm-rentiva/v1/vendors/vendors/{$user}" ) );
		$this->assertSame( 404, $response->get_status() );
	}
}
