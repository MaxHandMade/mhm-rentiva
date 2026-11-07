<?php
/**
 * One message for the operator after a booking Update.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A free-text notice shown once, on the edit screen of the booking it is about.
 *
 * BookingMeta::queue_admin_notice() carries four fixed keys; a refusal from
 * CancellationHandler needs its own message (PR 3b-2, Fable plan audit I1).
 * The notice lives in a per-user transient for one minute -- long enough to
 * survive the redirect after Update -- and holds the booking id, so a second
 * tab on another booking does not show it (r2-M5).
 *
 * An error also drops core's "Booking updated." (`?message=1`) from the
 * redirect, the way BookingMeta's availability refusal does: a refusal next to
 * a success line reads as a success.
 */
final class BookingEditNotice {

	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	public static function queue( int $booking_id, string $message, string $type = 'error' ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 || '' === $message ) {
			return;
		}

		set_transient(
			self::key( $user_id ),
			array(
				'booking_id' => $booking_id,
				'message'    => $message,
				'type'       => in_array( $type, array( 'error', 'warning', 'success', 'info' ), true ) ? $type : 'error',
			),
			MINUTE_IN_SECONDS
		);

		if ( 'error' === $type ) {
			add_filter( 'redirect_post_location', array( self::class, 'drop_updated_message' ), 10, 2 );
		}
	}

	/**
	 * The queued message for this booking, without consuming it.
	 */
	public static function peek( int $booking_id ): string {
		$notice = get_transient( self::key( get_current_user_id() ) );
		return is_array( $notice ) && (int) ( $notice['booking_id'] ?? 0 ) === $booking_id ? (string) ( $notice['message'] ?? '' ) : '';
	}

	/**
	 * The queued message for this booking, consumed.
	 */
	public static function take( int $booking_id ): string {
		$message = self::peek( $booking_id );
		if ( '' !== $message ) {
			delete_transient( self::key( get_current_user_id() ) );
		}
		return $message;
	}

	public static function render(): void {
		global $post;
		if ( ! $post instanceof \WP_Post || 'mhmrentiva_booking' !== $post->post_type ) {
			return;
		}

		$notice = get_transient( self::key( get_current_user_id() ) );
		if ( ! is_array( $notice ) || (int) ( $notice['booking_id'] ?? 0 ) !== (int) $post->ID ) {
			return;
		}
		delete_transient( self::key( get_current_user_id() ) );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( (string) $notice['type'] ),
			esc_html( (string) $notice['message'] )
		);
	}

	/**
	 * @param string $location Redirect target.
	 * @param int    $post_id  Booking.
	 */
	public static function drop_updated_message( string $location, int $post_id = 0 ): string {
		unset( $post_id );
		return remove_query_arg( 'message', $location );
	}

	private static function key( int $user_id ): string {
		return 'mhmrentiva_booking_edit_notice_' . $user_id;
	}
}
