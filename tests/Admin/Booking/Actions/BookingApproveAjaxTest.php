<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Actions;

use MHMRentiva\Admin\Booking\Actions\BookingApproveAjax;
use MHMRentiva\Admin\Booking\Core\Hooks;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\Utilities\OccupancyMapService;
use WP_Ajax_UnitTestCase;

/**
 * "Approve" row action endpoint (Faz 2 Task 7) -- the only new write
 * endpoint in this round. Guard behavior is exercised through the real
 * registered endpoint (same convention RemainingPaymentLinkTest.php uses for
 * DepositManagementAjax), which is also what proves BookingActionGuard --
 * the 4-step guard extracted FROM DepositManagementAjax for this endpoint to
 * reuse -- actually wired correctly under the new nonce action.
 *
 * The wrong-post-type case is the spec-audit finding this endpoint exists to
 * avoid repeating: a valid nonce plus current_user_can('edit_post', $id)
 * alone is not enough, because an editor can edit_post on a REGULAR `post`
 * they own. The guard must reject that ID specifically for its post_type,
 * not merely because the capability check would eventually fail on some
 * other object.
 */
final class BookingApproveAjaxTest extends WP_Ajax_UnitTestCase {

	/** @var string */
	protected $_last_response;

	private int $booking_id;

	public function setUp(): void {
		parent::setUp();

		BookingApproveAjax::register();

		$this->booking_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->booking_id, '_mhmrentiva_status', Status::PENDING );

		OccupancyMapService::reset_memo();
	}

	private function post_approve( int $booking_id, string $nonce ): void {
		$_POST['action']     = 'mhmrentiva_approve_booking';
		$_POST['booking_id'] = $booking_id;
		$_POST['nonce']      = $nonce;

		try {
			$this->_handleAjax( 'mhmrentiva_approve_booking' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected -- wp_send_json_*() always wp_die()s.
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function response(): array {
		return json_decode( $this->_last_response, true );
	}

	public function test_rejects_missing_nonce(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->post_approve( $this->booking_id, '' );

		$this->assertFalse( $this->response()['success'] );
		$this->assertSame( Status::PENDING, Status::get( $this->booking_id ) );
	}

	public function test_rejects_wrong_nonce(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->post_approve( $this->booking_id, 'not-a-real-nonce' );

		$this->assertFalse( $this->response()['success'] );
		$this->assertSame( Status::PENDING, Status::get( $this->booking_id ) );
	}

	public function test_rejects_valid_nonce_from_user_without_edit_rights(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->post_approve( $this->booking_id, wp_create_nonce( 'mhmrentiva_approve_booking' ) );

		$this->assertFalse( $this->response()['success'] );
		$this->assertSame( Status::PENDING, Status::get( $this->booking_id ) );
	}

	public function test_rejects_wrong_post_type_even_for_a_user_who_can_edit_it(): void {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$regular_post_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $editor_id,
			)
		);

		$this->post_approve( $regular_post_id, wp_create_nonce( 'mhmrentiva_approve_booking' ) );

		$response = $this->response();
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'not found', strtolower( (string) $response['data']['message'] ) );
	}

	public function test_approves_pending_booking_and_clears_occupancy_cache(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		Hooks::register();

		// Seed an occupancy transient the way OccupancyMapService actually
		// writes it, so this test proves the Task 2 invalidation wiring
		// fires end-to-end from this endpoint, not merely that Status
		// returns true.
		OccupancyMapService::get_map( '2026-01-01', '2026-01-31' );

		$fired = false;
		add_action(
			'mhmrentiva_booking_status_changed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->post_approve( $this->booking_id, wp_create_nonce( 'mhmrentiva_approve_booking' ) );

		$response = $this->response();
		$this->assertTrue( $response['success'] );
		$this->assertSame( 'confirmed', $response['data']['status'] );
		$this->assertSame( Status::get_label( Status::CONFIRMED ), $response['data']['label'] );
		$this->assertSame( Status::CONFIRMED, Status::get( $this->booking_id ) );
		$this->assertTrue( $fired, 'mhmrentiva_booking_status_changed must fire.' );

		global $wpdb;
		$remaining = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_mhmrentiva_vehicle_stats_%'
			)
		);
		$this->assertSame( '0', (string) $remaining, 'The seeded occupancy transient must be gone after approval.' );
	}

	/**
	 * Fix round 1 (reviewer Finding 1, Important, defense in depth): the row
	 * link is already hidden for a trashed booking (BookingApproveRowActionTest),
	 * but the endpoint is the actual door -- a crafted/stale request naming
	 * a trashed booking's id must still be rejected, and MUST NOT reach
	 * Status::update_status() at all (which would fire
	 * mhmrentiva_booking_status_changed -> the confirmation-email
	 * automation for a booking sitting in the trash).
	 */
	public function test_rejects_trashed_booking_and_never_fires_the_status_changed_hook(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		wp_trash_post( $this->booking_id );

		$fired = false;
		add_action(
			'mhmrentiva_booking_status_changed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->post_approve( $this->booking_id, wp_create_nonce( 'mhmrentiva_approve_booking' ) );

		$response = $this->response();
		$this->assertFalse( $response['success'] );
		$this->assertFalse( $fired, 'A trashed booking must never reach Status::update_status().' );
		$this->assertSame( Status::PENDING, Status::get( $this->booking_id ) );
	}

	public function test_rejects_already_confirmed_booking_and_leaves_status_unchanged(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		update_post_meta( $this->booking_id, '_mhmrentiva_status', Status::CONFIRMED );

		$this->post_approve( $this->booking_id, wp_create_nonce( 'mhmrentiva_approve_booking' ) );

		$response = $this->response();
		$this->assertFalse( $response['success'] );
		$this->assertSame( Status::CONFIRMED, Status::get( $this->booking_id ) );
	}

	public function test_does_not_touch_payment_status(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		update_post_meta( $this->booking_id, '_mhmrentiva_payment_status', 'unpaid' );

		$this->post_approve( $this->booking_id, wp_create_nonce( 'mhmrentiva_approve_booking' ) );

		$this->assertTrue( $this->response()['success'] );
		$this->assertSame( 'unpaid', get_post_meta( $this->booking_id, '_mhmrentiva_payment_status', true ) );
	}
}
