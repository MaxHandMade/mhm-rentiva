<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Actions;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Booking\Core\Status;

/**
 * "Approve" row action on the Bookings list (Faz 2 Task 7) -- the only new
 * write endpoint this round adds. Admin-only (wp_ajax_ only, no nopriv
 * counterpart).
 *
 * Moves a `pending` booking to `confirmed` through Status::update_status(),
 * the same transition gate every other status change in this plugin uses --
 * it validates the transition itself and fires
 * `mhmrentiva_booking_status_changed`, which Task 2 subscribed the
 * occupancy-map invalidation to. This endpoint does not touch
 * `_mhmrentiva_payment_status` or any other payment meta: approving a
 * booking's schedule status is a separate concern from confirming its
 * payment, which DepositManagementAjax::approve_payment() already owns.
 *
 * Authorization is BookingActionGuard::authorize() under this endpoint's own
 * nonce action -- see that class for the four checks (nonce, id, post_type,
 * edit_post capability) it runs before this method ever sees the request.
 */
final class BookingApproveAjax {

	public static function register(): void {
		add_action( 'wp_ajax_mhmrentiva_approve_booking', array( self::class, 'approve' ) );
	}

	public static function approve(): void {
		// Authorizes: nonce mhmrentiva_approve_booking + current_user_can( 'edit_post', $id ) + post_type check -- see BookingActionGuard::authorize().
		$booking_id = BookingActionGuard::authorize( 'mhmrentiva_approve_booking' );
		if ( ! $booking_id ) {
			return;
		}

		// Defense in depth for the same trash-view gap the row action guards
		// against (BookingColumns::add_approve_row_action()): the guard
		// above only checks post_type, not post_status, so a request naming
		// a trashed booking's id still passes it. Approving one would fire
		// Status::update_status() -> mhmrentiva_booking_status_changed ->
		// Hooks::handle_status_automation(), which emails the customer a
		// confirmation for a booking that is not live. The row link is
		// already gone for this case, but the endpoint -- not the UI -- is
		// the actual door, so it re-checks rather than trusting the link's
		// absence.
		$booking = get_post( $booking_id );
		if ( ! $booking || 'publish' !== $booking->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'mhm-rentiva' ) ) );
			return;
		}

		$updated = Status::update_status( $booking_id, Status::CONFIRMED );
		if ( ! $updated ) {
			wp_send_json_error(
				array(
					'message' => __( 'This booking could not be approved. It may have changed — reload the list.', 'mhm-rentiva' ),
				),
				409
			);
			return;
		}

		wp_send_json_success(
			array(
				'status' => Status::CONFIRMED,
				'label'  => Status::get_label( Status::CONFIRMED ),
			)
		);
	}
}
