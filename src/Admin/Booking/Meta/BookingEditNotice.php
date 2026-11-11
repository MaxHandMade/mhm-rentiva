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

	/** @var array<int, true> Bookings whose redirect drops core's "updated". */
	private static array $drop_for = array();

	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
		add_action( 'admin_notices', array( self::class, 'render_payment_mismatch' ) );
		add_action( 'admin_post_' . self::DISMISS_MISMATCH_ACTION, array( self::class, 'handle_dismiss_payment_mismatch' ) );
	}

	/** The admin-post action that clears a booking's payment-mismatch flag. */
	public const DISMISS_MISMATCH_ACTION = 'mhmrentiva_dismiss_payment_mismatch';

	/**
	 * A payment that arrived for an amount that was no longer current (PR-2 A3,
	 * WooCommerceBridge::settle_remaining_from_order()). Unlike the one-shot
	 * notice above it stays on the booking's edit screen until someone has
	 * looked at it: the balance was deliberately left unchanged, and only a
	 * human can decide what it should be.
	 */
	public static function render_payment_mismatch(): void {
		global $post;
		if ( ! $post instanceof \WP_Post || 'mhmrentiva_booking' !== $post->post_type || ! current_user_can( 'edit_post', (int) $post->ID ) ) {
			return;
		}

		$flag = get_post_meta( (int) $post->ID, '_mhmrentiva_payment_mismatch', true );
		if ( ! is_array( $flag ) || empty( $flag['order_id'] ) ) {
			return;
		}

		// The flag outlives WooCommerce: written while it ran, it is still on
		// the booking if WooCommerce is deactivated later. The notice must not
		// fatal the edit screen then (Fable code r1 Ş3), and it still says
		// that the balance needs a look.
		$has_wc    = function_exists( 'wc_get_order' ) && function_exists( 'wc_price' );
		$order     = $has_wc ? wc_get_order( (int) $flag['order_id'] ) : false;
		$decimals  = \MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler::debt_decimals();
		$currency  = \MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler::debt_currency();
		$due_major = (int) ( $flag['due_minor'] ?? 0 ) / ( 10 ** $decimals );
		$collected = $order instanceof \WC_Order ? wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) : '?';
		$due       = $has_wc ? wp_strip_all_tags( wc_price( $due_major, array( 'currency' => $currency ) ) ) : number_format_i18n( $due_major, $decimals ) . ' ' . $currency;

		$dismiss = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => self::DISMISS_MISMATCH_ACTION,
					'booking_id' => (int) $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::DISMISS_MISMATCH_ACTION . '_' . (int) $post->ID
		);

		printf(
			'<div class="notice notice-warning"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
			esc_html( \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::payment_mismatch_message( $flag, $collected, $due ) ),
			esc_url( $dismiss ),
			esc_html__( 'I have checked it', 'mhm-rentiva' )
		);
	}

	public static function handle_dismiss_payment_mismatch(): void {
		$booking_id = isset( $_GET['booking_id'] ) ? absint( wp_unslash( $_GET['booking_id'] ) ) : 0;
		check_admin_referer( self::DISMISS_MISMATCH_ACTION . '_' . $booking_id );
		if ( $booking_id <= 0 || 'mhmrentiva_booking' !== get_post_type( $booking_id ) || ! current_user_can( 'edit_post', $booking_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'mhm-rentiva' ), '', array( 'response' => 403 ) );
		}

		delete_post_meta( $booking_id, '_mhmrentiva_payment_mismatch' );

		wp_safe_redirect( (string) get_edit_post_link( $booking_id, 'url' ) );
		exit;
	}

	public static function queue( int $booking_id, string $message, string $type = 'error' ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 || '' === $message ) {
			return;
		}

		set_transient(
			self::key( $user_id, $booking_id ),
			array(
				'booking_id' => $booking_id,
				'message'    => $message,
				'type'       => in_array( $type, array( 'error', 'warning', 'success', 'info' ), true ) ? $type : 'error',
			),
			MINUTE_IN_SECONDS
		);

		if ( 'error' === $type ) {
			self::$drop_for[ $booking_id ] = true;
			add_filter( 'redirect_post_location', array( self::class, 'drop_updated_message' ), 10, 2 );
		}
	}

	/**
	 * The queued message for this booking, without consuming it.
	 */
	public static function peek( int $booking_id ): string {
		$notice = get_transient( self::key( get_current_user_id(), $booking_id ) );
		return is_array( $notice ) ? (string) ( $notice['message'] ?? '' ) : '';
	}

	/**
	 * The queued message for this booking, consumed.
	 */
	public static function take( int $booking_id ): string {
		$message = self::peek( $booking_id );
		if ( '' !== $message ) {
			delete_transient( self::key( get_current_user_id(), $booking_id ) );
		}
		return $message;
	}

	public static function render(): void {
		global $post;
		if ( ! $post instanceof \WP_Post || 'mhmrentiva_booking' !== $post->post_type ) {
			return;
		}

		$notice = get_transient( self::key( get_current_user_id(), (int) $post->ID ) );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( self::key( get_current_user_id(), (int) $post->ID ) );

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
		// Only the booking the error is about (Codex code audit 3b-2, Minor 5).
		return isset( self::$drop_for[ $post_id ] ) ? remove_query_arg( 'message', $location ) : $location;
	}

	/**
	 * @internal Tests only.
	 */
	public static function reset(): void {
		self::$drop_for = array();
	}

	private static function key( int $user_id, int $booking_id ): string {
		// Per user AND booking: two bookings' notices must not overwrite each
		// other (Codex code audit 3b-2, Minor 5).
		return 'mhmrentiva_booking_edit_notice_' . $user_id . '_' . $booking_id;
	}
}
