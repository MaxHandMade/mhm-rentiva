<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\VendorReport;

use MHMRentiva\Admin\VendorReport\Core\VendorReportContext;
use MHMRentiva\Admin\VendorReport\Core\VendorReportRepository;
use MHMRentiva\Admin\VendorReport\Core\VendorReportStatus;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @group vendor-report
 * @group vendor-report-rest
 */
final class VendorReportsRESTTest extends WP_UnitTestCase {

	private static WP_REST_Server $server;
	private int $admin_id  = 0;
	private string $table  = '';

	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->table = $wpdb->prefix . 'mhm_rentiva_vendor_reports';

		// Register routes directly (bypasses Mode::canUseVendorMarketplace gate).
		add_action( 'rest_api_init', array( \MHMRentiva\Admin\VendorReport\REST\VendorReportsController::class, 'register_routes' ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		self::$server   = $wp_rest_server;
		do_action( 'rest_api_init', self::$server );

		$this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );

		// Seed deterministic test rows with vendor_id >= 98000 for cleanup isolation.
		$wpdb->insert( $this->table, array(
			'vendor_id'    => 98001,
			'context_type' => VendorReportContext::BOOKING,
			'context_id'   => '501',
			'title'        => 'Test booking report',
			'description'  => 'Booking issue desc.',
			'status'       => VendorReportStatus::OPEN,
			'created_at'   => '2026-05-01 10:00:00',
			'updated_at'   => '2026-05-01 10:00:00',
		) );

		$wpdb->insert( $this->table, array(
			'vendor_id'    => 98001,
			'context_type' => VendorReportContext::VEHICLE,
			'context_id'   => '301',
			'title'        => 'Test vehicle report',
			'description'  => 'Vehicle issue desc.',
			'status'       => VendorReportStatus::RESOLVED,
			'created_at'   => '2026-05-02 10:00:00',
			'updated_at'   => '2026-05-02 11:00:00',
		) );
	}

	public function tearDown(): void {
		global $wpdb, $wp_rest_server;

		$wpdb->query( "DELETE FROM {$this->table} WHERE vendor_id >= 98000" );
		$wp_rest_server = null;
		wp_set_current_user( 0 );
		remove_action( 'rest_api_init', array( \MHMRentiva\Admin\VendorReport\REST\VendorReportsController::class, 'register_routes' ) );
		parent::tearDown();
	}

	// --- List endpoint ---

	public function test_unauthenticated_list_returns_401(): void {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendor-reports' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_authenticated_list_returns_200_with_structure(): void {
		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendor-reports' );
		$request->set_query_params( array( 'status' => 'all' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'reports', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'pages', $data );
		$this->assertArrayHasKey( 'current_page', $data );
		$this->assertIsArray( $data['reports'] );
	}

	public function test_list_default_status_filter_returns_only_open(): void {
		wp_set_current_user( $this->admin_id );
		// Default status = 'open' — should not include the resolved row.
		$request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendor-reports' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		foreach ( $data['reports'] as $r ) {
			$this->assertNotSame( VendorReportStatus::RESOLVED, $r['status'], 'Default filter must exclude resolved' );
		}
	}

	public function test_list_status_all_returns_all_rows(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendor-reports' );
		$request->set_query_params( array( 'status' => 'all' ) );
		$response = self::$server->dispatch( $request );
		$data     = $response->get_data();
		$statuses = array_column( $data['reports'], 'status' );
		$this->assertContains( VendorReportStatus::OPEN, $statuses );
		$this->assertContains( VendorReportStatus::RESOLVED, $statuses );
	}

	public function test_list_context_filter_returns_only_matching_context(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendor-reports' );
		$request->set_query_params( array( 'status' => 'all', 'context_type' => VendorReportContext::BOOKING ) );
		$response = self::$server->dispatch( $request );
		$data     = $response->get_data();
		foreach ( $data['reports'] as $r ) {
			$this->assertSame( VendorReportContext::BOOKING, $r['context_type'] );
		}
	}

	// --- Detail endpoint ---

	public function test_unauthenticated_detail_returns_401(): void {
		wp_set_current_user( 0 );
		global $wpdb;
		$id       = (int) $wpdb->get_var( "SELECT id FROM {$this->table} WHERE vendor_id = 98001 LIMIT 1" );
		$request  = new WP_REST_Request( 'GET', "/mhm-rentiva/v1/vendor-reports/{$id}" );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_authenticated_detail_returns_200_with_full_structure(): void {
		wp_set_current_user( $this->admin_id );
		global $wpdb;
		$id       = (int) $wpdb->get_var( "SELECT id FROM {$this->table} WHERE vendor_id = 98001 AND context_type = 'booking' LIMIT 1" );
		$request  = new WP_REST_Request( 'GET', "/mhm-rentiva/v1/vendor-reports/{$id}" );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data   = $response->get_data();
		$report = $data['report'];
		$this->assertSame( $id, $report['id'] );
		$this->assertArrayHasKey( 'vendor_name', $report );
		$this->assertArrayHasKey( 'vendor_email', $report );
		$this->assertArrayHasKey( 'description', $report );
		$this->assertArrayHasKey( 'is_terminal', $report );
		$this->assertArrayHasKey( 'created_human', $report );
		$this->assertFalse( $report['is_terminal'], 'Open report must not be terminal' );
	}

	public function test_nonexistent_detail_returns_404(): void {
		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendor-reports/9999999' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'report_not_found', $data['code'] );
	}
}
