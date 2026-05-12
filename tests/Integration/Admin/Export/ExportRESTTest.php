<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Export;

use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @group export
 * @group export-rest
 */
final class ExportRESTTest extends WP_UnitTestCase {

	private static WP_REST_Server $server;
	private int $admin_id      = 0;
	private int $subscriber_id = 0;

	public function setUp(): void {
		parent::setUp();

		add_action( 'rest_api_init', array( \MHMRentiva\Admin\Utilities\Export\REST\ExportRestController::class, 'register_routes' ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		self::$server   = $wp_rest_server;
		do_action( 'rest_api_init', self::$server );

		$this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );

		$this->subscriber_id = (int) $this->factory->user->create( array(
			'role'       => 'subscriber',
			'user_email' => 'subscriber-export-test@example.com',
		) );

		// Seed a known history entry via the transient used by Export::log_export_activity().
		$history   = get_transient( 'mhm_rentiva_export_history' ) ?: array();
		$history[] = array(
			'id'              => 'export_test_001',
			'date'            => '2026-01-15 10:00:00',
			'type'            => 'Bookings',
			'format'          => 'CSV',
			'records'         => 42,
			'status'          => 'completed',
			'user_id'         => 1,
			'filters_applied' => false,
		);
		set_transient( 'mhm_rentiva_export_history', $history, WEEK_IN_SECONDS );
	}

	public function tearDown(): void {
		global $wp_rest_server;

		delete_transient( 'mhm_rentiva_export_history' );

		wp_delete_user( $this->admin_id );
		wp_delete_user( $this->subscriber_id );

		$wp_rest_server = null;
		wp_set_current_user( 0 );
		remove_action( 'rest_api_init', array( \MHMRentiva\Admin\Utilities\Export\REST\ExportRestController::class, 'register_routes' ) );
		parent::tearDown();
	}

	// =========================================================================
	// GET /admin/export/history
	// =========================================================================

	public function test_history_returns_401_when_unauthenticated(): void {
		wp_set_current_user( 0 );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/admin/export/history' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_history_returns_403_for_non_admin(): void {
		wp_set_current_user( $this->subscriber_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/admin/export/history' ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_history_returns_200_with_correct_structure(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/admin/export/history' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'history', $data, 'Response must have "history" key' );
		$this->assertArrayHasKey( 'total', $data, 'Response must have "total" key' );
		$this->assertIsArray( $data['history'] );
		$this->assertIsInt( $data['total'] );
	}

	public function test_history_returns_seeded_entry(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/admin/export/history' ) );
		$data     = $response->get_data();
		$this->assertGreaterThanOrEqual( 1, $data['total'] );
		$ids = array_column( $data['history'], 'id' );
		$this->assertContains( 'export_test_001', $ids, 'Seeded history entry must appear in response' );
	}

	public function test_history_entry_has_required_fields(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/admin/export/history' ) );
		$data     = $response->get_data();
		$entry    = null;
		foreach ( $data['history'] as $item ) {
			if ( $item['id'] === 'export_test_001' ) {
				$entry = $item;
				break;
			}
		}
		$this->assertNotNull( $entry, 'Seeded entry must be findable' );
		$this->assertArrayHasKey( 'id',      $entry );
		$this->assertArrayHasKey( 'date',    $entry );
		$this->assertArrayHasKey( 'type',    $entry );
		$this->assertArrayHasKey( 'format',  $entry );
		$this->assertArrayHasKey( 'records', $entry );
		$this->assertArrayHasKey( 'status',  $entry );
		$this->assertSame( 42, $entry['records'] );
		$this->assertSame( 'completed', $entry['status'] );
	}

	public function test_history_empty_when_no_transient(): void {
		delete_transient( 'mhm_rentiva_export_history' );
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/admin/export/history' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 0, $data['total'] );
		$this->assertSame( array(), $data['history'] );
	}

	// =========================================================================
	// DELETE /admin/export/{id}
	// =========================================================================

	public function test_delete_returns_401_when_unauthenticated(): void {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/admin/export/export_test_001' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_delete_returns_403_for_non_admin(): void {
		wp_set_current_user( $this->subscriber_id );
		$request = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/admin/export/export_test_001' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_delete_returns_404_for_unknown_id(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/admin/export/export_nonexistent_999' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_delete_returns_200_and_removes_entry(): void {
		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/admin/export/export_test_001' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'], 'Response must contain success:true' );

		// Verify it's actually gone from the transient.
		$history = get_transient( 'mhm_rentiva_export_history' ) ?: array();
		$ids     = array_column( $history, 'id' );
		$this->assertNotContains( 'export_test_001', $ids, 'Deleted entry must be removed from transient' );
	}

	public function test_delete_does_not_affect_other_entries(): void {
		// Seed a second entry.
		$history   = get_transient( 'mhm_rentiva_export_history' ) ?: array();
		$history[] = array(
			'id'              => 'export_test_002',
			'date'            => '2026-01-16 12:00:00',
			'type'            => 'Vehicles',
			'format'          => 'CSV',
			'records'         => 10,
			'status'          => 'completed',
			'user_id'         => 1,
			'filters_applied' => false,
		);
		set_transient( 'mhm_rentiva_export_history', $history, WEEK_IN_SECONDS );

		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/admin/export/export_test_001' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$remaining = get_transient( 'mhm_rentiva_export_history' ) ?: array();
		$ids       = array_column( $remaining, 'id' );
		$this->assertNotContains( 'export_test_001', $ids );
		$this->assertContains( 'export_test_002', $ids, 'Second entry must survive deletion of first' );
	}

	// =========================================================================
	// POST /admin/export/preview
	// =========================================================================

	public function test_preview_returns_401_when_unauthenticated(): void {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array( 'post_type' => 'vehicle_booking' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_preview_returns_403_for_non_admin(): void {
		wp_set_current_user( $this->subscriber_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array( 'post_type' => 'vehicle_booking' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_preview_returns_400_without_post_type(): void {
		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_preview_returns_400_for_invalid_post_type(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array( 'post_type' => 'invalid_type' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_preview_returns_200_with_required_structure(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array( 'post_type' => 'vehicle_booking' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'count',  $data, 'Response must have "count" key' );
		$this->assertArrayHasKey( 'sample', $data, 'Response must have "sample" key' );
		$this->assertIsInt( $data['count'] );
		$this->assertIsArray( $data['sample'] );
	}

	public function test_preview_accepts_vehicle_post_type(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array( 'post_type' => 'vehicle' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_preview_accepts_log_post_type(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array( 'post_type' => 'mhm_app_log' ) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_preview_count_reflects_actual_bookings(): void {
		// Create 3 bookings.
		$ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = (int) $this->factory->post->create( array(
				'post_type'   => 'vehicle_booking',
				'post_status' => 'publish',
			) );
		}

		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array( 'post_type' => 'vehicle_booking' ) );
		$response = self::$server->dispatch( $request );
		$data     = $response->get_data();
		$this->assertGreaterThanOrEqual( 3, $data['count'], 'Count must include the 3 seeded bookings' );

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function test_preview_sample_max_5_records(): void {
		// Create 7 bookings so sample cap can be tested.
		$ids = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$ids[] = (int) $this->factory->post->create( array(
				'post_type'   => 'vehicle_booking',
				'post_status' => 'publish',
			) );
		}

		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array( 'post_type' => 'vehicle_booking' ) );
		$response = self::$server->dispatch( $request );
		$data     = $response->get_data();
		$this->assertLessThanOrEqual( 5, count( $data['sample'] ), 'Sample must be capped at 5 records' );

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function test_preview_sample_record_has_required_fields(): void {
		$booking_id = (int) $this->factory->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
		) );
		update_post_meta( $booking_id, '_mhm_status', 'confirmed' );

		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array( 'post_type' => 'vehicle_booking' ) );
		$response = self::$server->dispatch( $request );
		$data     = $response->get_data();

		if ( ! empty( $data['sample'] ) ) {
			$record = $data['sample'][0];
			$this->assertArrayHasKey( 'id',     $record );
			$this->assertArrayHasKey( 'date',   $record );
			$this->assertArrayHasKey( 'status', $record );
		}

		wp_delete_post( $booking_id, true );
	}

	public function test_preview_filters_by_date_range(): void {
		// Old booking — should be excluded.
		$old_id = (int) $this->factory->post->create( array(
			'post_type'     => 'vehicle_booking',
			'post_status'   => 'publish',
			'post_date_gmt' => '2020-01-01 00:00:00',
		) );
		// Recent booking — should be included.
		$new_id = (int) $this->factory->post->create( array(
			'post_type'     => 'vehicle_booking',
			'post_status'   => 'publish',
			'post_date_gmt' => '2026-06-01 00:00:00',
		) );

		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/admin/export/preview' );
		$request->set_body_params( array(
			'post_type' => 'vehicle_booking',
			'date_from' => '2026-01-01',
			'date_to'   => '2026-12-31',
		) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$sample_ids = array_column( $data['sample'], 'id' );
		$this->assertContains( $new_id, $sample_ids, 'Recent booking must appear in filtered preview' );
		$this->assertNotContains( $old_id, $sample_ids, 'Old booking must not appear in filtered preview' );

		wp_delete_post( $old_id, true );
		wp_delete_post( $new_id, true );
	}
}
