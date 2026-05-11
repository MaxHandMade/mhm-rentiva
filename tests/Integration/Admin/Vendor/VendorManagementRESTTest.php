<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Vendor;

use MHMRentiva\Admin\Vendor\PostType\VendorApplication;
use MHMRentiva\Admin\Vendor\VendorApplicationManager;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @group vendor-management
 * @group vendor-management-rest
 */
final class VendorManagementRESTTest extends WP_UnitTestCase {

	private static WP_REST_Server $server;
	private int $admin_id       = 0;
	private int $applicant_id   = 0;
	private int $application_id = 0;
	private int $vendor_id      = 0;

	public function setUp(): void {
		parent::setUp();

		add_action( 'rest_api_init', array( \MHMRentiva\Admin\Vendor\REST\VendorManagementRestController::class, 'register_routes' ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		self::$server   = $wp_rest_server;
		do_action( 'rest_api_init', self::$server );

		$this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );

		// Applicant user — no vendor role.
		$this->applicant_id = (int) $this->factory->user->create( array(
			'role'         => 'subscriber',
			'user_email'   => 'applicant-test-98001@example.com',
			'display_name' => 'Test Applicant',
		) );

		// Seed a pending application post (vendor_id >= 98000 for cleanup isolation).
		$this->application_id = (int) $this->factory->post->create( array(
			'post_type'   => VendorApplication::POST_TYPE,
			'post_status' => VendorApplicationManager::STATUS_PENDING,
			'post_author' => $this->applicant_id,
			'post_title'  => 'Test Vendor Application',
		) );

		update_post_meta( $this->application_id, '_vendor_phone',          '+90 555 000 0001' );
		update_post_meta( $this->application_id, '_vendor_city',           'Istanbul' );
		update_post_meta( $this->application_id, '_vendor_profile_bio',    'Test bio.' );
		update_post_meta( $this->application_id, '_vendor_account_holder', 'Test Applicant' );
		update_post_meta( $this->application_id, '_vendor_iban',           VendorApplicationManager::encrypt_iban( 'TR980000000000000000012345' ) );
		update_post_meta( $this->application_id, '_vendor_tax_office',     'Kadikoy VD' );
		update_post_meta( $this->application_id, '_vendor_tax_number',     '1234567890' );

		// Vendor user with pending IBAN change request.
		$this->vendor_id = (int) $this->factory->user->create( array(
			'role'         => 'rentiva_vendor',
			'display_name' => 'Test Vendor',
			'user_email'   => 'vendor-test-98001@example.com',
		) );
		update_user_meta( $this->vendor_id, '_rentiva_vendor_iban',       VendorApplicationManager::encrypt_iban( 'TR000000000000000000000001' ) );
		update_user_meta( $this->vendor_id, '_rentiva_pending_iban',      VendorApplicationManager::encrypt_iban( 'TR980000000000000000099999' ) );
		update_user_meta( $this->vendor_id, '_rentiva_iban_change_status', 'pending' );
	}

	public function tearDown(): void {
		global $wp_rest_server;

		wp_delete_post( $this->application_id, true );
		wp_delete_user( $this->applicant_id );
		wp_delete_user( $this->vendor_id );
		wp_delete_user( $this->admin_id );

		$wp_rest_server = null;
		wp_set_current_user( 0 );
		remove_action( 'rest_api_init', array( \MHMRentiva\Admin\Vendor\REST\VendorManagementRestController::class, 'register_routes' ) );
		parent::tearDown();
	}

	// --- GET /vendors/applications ---

	public function test_unauthenticated_applications_list_returns_401(): void {
		wp_set_current_user( 0 );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/applications' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_authenticated_applications_list_returns_200_with_structure(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/applications' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'applications', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'pages', $data );
		$this->assertArrayHasKey( 'current_page', $data );
		$this->assertIsArray( $data['applications'] );
	}

	public function test_applications_list_only_returns_pending(): void {
		// Create an approved application — should not appear in list.
		$approved_id = (int) $this->factory->post->create( array(
			'post_type'   => VendorApplication::POST_TYPE,
			'post_status' => VendorApplicationManager::STATUS_APPROVED,
			'post_author' => $this->applicant_id,
		) );
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/applications' ) );
		$data = $response->get_data();
		$ids  = array_column( $data['applications'], 'id' );
		$this->assertContains( $this->application_id, $ids, 'Pending application must appear in list' );
		$this->assertNotContains( $approved_id, $ids, 'Approved application must not appear in list' );
		wp_delete_post( $approved_id, true );
	}

	// --- GET /vendors/applications/{id} ---

	public function test_unauthenticated_application_detail_returns_401(): void {
		wp_set_current_user( 0 );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', "/mhm-rentiva/v1/vendors/applications/{$this->application_id}" ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_application_detail_returns_200_with_full_structure(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', "/mhm-rentiva/v1/vendors/applications/{$this->application_id}" ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'application', $data );
		$app = $data['application'];
		$this->assertSame( $this->application_id, $app['id'] );
		$this->assertArrayHasKey( 'applicant_name', $app );
		$this->assertArrayHasKey( 'applicant_email', $app );
		$this->assertArrayHasKey( 'phone', $app );
		$this->assertArrayHasKey( 'city', $app );
		$this->assertArrayHasKey( 'bio', $app );
		$this->assertArrayHasKey( 'iban_masked', $app );
		$this->assertArrayHasKey( 'tax_office', $app );
		$this->assertArrayHasKey( 'tax_number', $app );
		$this->assertArrayHasKey( 'documents', $app );
		$this->assertArrayHasKey( 'status', $app );
	}

	public function test_application_detail_iban_is_masked_not_raw(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', "/mhm-rentiva/v1/vendors/applications/{$this->application_id}" ) );
		$app      = $response->get_data()['application'];
		$iban     = $app['iban_masked'];
		// Must not contain the full IBAN substring.
		$this->assertStringNotContainsString( '00000000000000012345', $iban, 'Raw IBAN digits must not appear in response' );
		// Must be masked (contains * or spaces suggesting masking).
		$this->assertStringContainsString( '*', $iban );
	}

	public function test_nonexistent_application_detail_returns_404(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/applications/9999999' ) );
		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'application_not_found', $response->get_data()['code'] );
	}

	// --- POST /vendors/applications/{id}/approve ---

	public function test_unauthenticated_approve_returns_401(): void {
		wp_set_current_user( 0 );
		$response = self::$server->dispatch( new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/applications/{$this->application_id}/approve" ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_approve_nonexistent_application_returns_404(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'POST', '/mhm-rentiva/v1/vendors/applications/9999999/approve' ) );
		$this->assertSame( 404, $response->get_status() );
	}

	// --- POST /vendors/applications/{id}/reject ---

	public function test_reject_without_reason_returns_400(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/applications/{$this->application_id}/reject" ) );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'reason_required', $response->get_data()['code'] );
	}

	public function test_reject_with_reason_returns_200(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/applications/{$this->application_id}/reject" );
		$request->set_body_params( array( 'reason' => 'Incomplete documentation.' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	// --- GET /vendors/iban-requests ---

	public function test_unauthenticated_iban_list_returns_401(): void {
		wp_set_current_user( 0 );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/iban-requests' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_iban_list_returns_pending_requests_with_masked_ibans(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/iban-requests' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'requests', $data );
		$this->assertArrayHasKey( 'total', $data );

		$vendor_ids = array_column( $data['requests'], 'vendor_id' );
		$this->assertContains( $this->vendor_id, $vendor_ids, 'Vendor with pending IBAN must appear in list' );

		// Verify masking on the found row.
		foreach ( $data['requests'] as $row ) {
			if ( $row['vendor_id'] === $this->vendor_id ) {
				$this->assertStringContainsString( '*', $row['current_iban_masked'] );
				$this->assertStringContainsString( '*', $row['pending_iban_masked'] );
				$this->assertStringNotContainsString( '000000000000000000000001', $row['current_iban_masked'] );
			}
		}
	}

	// --- POST /vendors/iban-requests/{vendor_id}/approve ---

	public function test_iban_approve_swaps_iban_and_clears_pending(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/iban-requests/{$this->vendor_id}/approve" ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		// Pending meta must be cleared.
		$this->assertEmpty( get_user_meta( $this->vendor_id, '_rentiva_pending_iban', true ) );
		$this->assertEmpty( get_user_meta( $this->vendor_id, '_rentiva_iban_change_status', true ) );

		// Active IBAN must be updated to the pending value.
		$active_raw = VendorApplicationManager::decrypt_iban(
			(string) get_user_meta( $this->vendor_id, '_rentiva_vendor_iban', true )
		);
		$this->assertSame( 'TR980000000000000000099999', $active_raw );
	}

	// --- POST /vendors/iban-requests/{vendor_id}/reject ---

	public function test_iban_reject_clears_pending_meta(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'POST', "/mhm-rentiva/v1/vendors/iban-requests/{$this->vendor_id}/reject" ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		$this->assertEmpty( get_user_meta( $this->vendor_id, '_rentiva_pending_iban', true ) );
		$this->assertEmpty( get_user_meta( $this->vendor_id, '_rentiva_iban_change_status', true ) );

		// Active IBAN must be unchanged.
		$active_raw = VendorApplicationManager::decrypt_iban(
			(string) get_user_meta( $this->vendor_id, '_rentiva_vendor_iban', true )
		);
		$this->assertSame( 'TR000000000000000000000001', $active_raw );
	}
}
