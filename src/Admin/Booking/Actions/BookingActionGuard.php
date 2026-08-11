<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Actions;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Security\VerifiedRequest;

/**
 * Single entry guard shared by every booking-scoped AJAX write endpoint.
 *
 * Extracted verbatim from DepositManagementAjax::authorize_booking_action()
 * (Faz 2 Task 7), whose logic hard-coded a single nonce action. The nonce
 * action now travels in as a parameter so a second, unrelated endpoint (the
 * bookings-list "Approve" row action) can reuse the exact same four checks
 * under its own nonce action instead of re-implementing them -- re-
 * implementing them is how the wrong-post-type gap this class closes would
 * have gotten a second chance to be forgotten.
 *
 * Four steps, in order, each one terminating the request via
 * wp_send_json_error() on failure:
 *   1. nonce  -- wp_verify_nonce() against the caller's own action string.
 *   2. id     -- the booking id the request names, via VerifiedRequest so
 *                the superglobal read stays in the same scope as the nonce
 *                check above it.
 *   3. type   -- the resolved post actually IS a mhmrentiva_booking. Without
 *                this step, a valid nonce plus edit_post capability on SOME
 *                post the caller owns (a regular `post`, for instance) would
 *                pass every other check.
 *   4. cap    -- current_user_can('edit_post', $id) against THAT booking,
 *                not a blanket edit_posts check, so a role with edit_posts
 *                cannot act on a booking belonging to someone else.
 *
 * Returns the booking id (a genuine positive int) once every step passes,
 * or 0 after already sending the JSON error response.
 */
final class BookingActionGuard {

	public static function authorize( string $nonce_action ): int {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			return 0;
		}

		$booking_id = VerifiedRequest::from( $_POST )->int( 'booking_id' );
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking ID.', 'mhm-rentiva' ) ) );
			return 0;
		}

		$booking = get_post( $booking_id );
		if ( ! $booking || $booking->post_type !== 'mhmrentiva_booking' ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'mhm-rentiva' ) ) );
			return 0;
		}

		if ( ! current_user_can( 'edit_post', $booking_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return 0;
		}

		return $booking_id;
	}
}
