<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Actions;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Admin\Emails\Core\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deposit-management write endpoints (payment approval, remaining-payment
 * processing/link, cancellation, refund).
 *
 * Every handler opens with a line-local `check_ajax_referer( ..., false )`
 * that is REDUNDANT with authorize_booking_action() immediately below it.
 * That is deliberate: this file used to contain its own nonce and capability
 * checks inline, and the Faz 2 Task 7 guard extraction moved both one file
 * away -- leaving five registered `wp_ajax_*` money endpoints in which
 * grepping for a nonce check finds nothing. The authoritative check (and the
 * failure response) still belongs to BookingActionGuard; these lines only
 * make the protection visible where the endpoint is.
 */
final class DepositManagementAjax {

	/**
	 * Single entry guard for every deposit action.
	 *
	 * Delegates to BookingActionGuard::authorize() (Faz 2 Task 7 extraction)
	 * under this class's own nonce action -- byte-for-byte the same four
	 * checks (nonce, id, post_type, edit_post on the resolved booking) this
	 * method used to run inline. See BookingActionGuard's docblock for why
	 * edit_post on the resolved booking, not a blanket edit_posts check, is
	 * the capability that belongs here: the handlers used to check the
	 * blanket capability and then act on whichever booking_id arrived, so
	 * any role with edit_posts (a contributor, for instance) could approve
	 * payments, cancel bookings or issue refunds on bookings belonging to
	 * anyone.
	 */
	private static function authorize_booking_action(): int {
		$booking_id = BookingActionGuard::authorize( 'mhmrentiva_deposit_management_action' );
		if ( ! $booking_id ) {
			return 0;
		}

		// Redundant by design: keeps the capability check greppable in this
		// file (WP.org review lens). BookingActionGuard::authorize() already
		// ran this exact current_user_can('edit_post', ...) check against
		// this exact booking id one line above -- nothing runs in between
		// that could change the outcome, so this branch can never reject a
		// caller the guard just approved. Message matches the guard's own
		// rejection payload so behaviour is identical either way.
		if ( ! current_user_can( 'edit_post', $booking_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return 0;
		}

		return $booking_id;
	}

	public static function register(): void {
		add_action( 'wp_ajax_mhmrentiva_process_remaining_payment', array( self::class, 'process_remaining_payment' ) );
		add_action( 'wp_ajax_mhmrentiva_send_remaining_payment_link', array( self::class, 'send_remaining_payment_link' ) );
		add_action( 'wp_ajax_mhmrentiva_approve_payment', array( self::class, 'approve_payment' ) );
		add_action( 'wp_ajax_mhmrentiva_deposit_cancel_booking', array( self::class, 'cancel_booking' ) );
		add_action( 'wp_ajax_mhmrentiva_deposit_process_refund', array( self::class, 'process_refund' ) );
	}

	public static function process_remaining_payment(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		// Deposit system check
		$payment_type = get_post_meta( $booking_id, '_mhmrentiva_payment_type', true );
		if ( $payment_type !== 'deposit' ) {
			wp_send_json_error( array( 'message' => __( 'This booking does not use deposit system.', 'mhm-rentiva' ) ) );
			return;
		}

		$remaining_amount = floatval( get_post_meta( $booking_id, '_mhmrentiva_remaining_amount', true ) );
		if ( $remaining_amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'No remaining amount found.', 'mhm-rentiva' ) ) );
			return;
		}

		// Reset remaining amount
		update_post_meta( $booking_id, '_mhmrentiva_remaining_amount', 0 );

		// Update payment status
		update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'paid' );

		// If rental end date has already passed, mark as completed; otherwise confirmed
		$dropoff       = get_post_meta( $booking_id, '_mhmrentiva_dropoff_date', true )
			?: get_post_meta( $booking_id, '_mhmrentiva_end_date', true );
		$target_status = ( $dropoff && strtotime( $dropoff ) < time() ) ? 'completed' : 'confirmed';
		Status::update_status( $booking_id, $target_status, get_current_user_id() );

		// Add log
		self::add_booking_log(
			$booking_id,
			'remaining_payment_processed',
			array(
				'amount'       => $remaining_amount,
				'processed_by' => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Remaining amount processed successfully.', 'mhm-rentiva' ),
			)
		);
	}

	public static function send_remaining_payment_link(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		// Deposit system check (same guard as process_remaining_payment())
		$payment_type = get_post_meta( $booking_id, '_mhmrentiva_payment_type', true );
		if ( $payment_type !== 'deposit' ) {
			wp_send_json_error( array( 'message' => __( 'This booking does not use deposit system.', 'mhm-rentiva' ) ) );
			return;
		}

		$remaining_amount = floatval( get_post_meta( $booking_id, '_mhmrentiva_remaining_amount', true ) );
		if ( $remaining_amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'No remaining amount found.', 'mhm-rentiva' ) ) );
			return;
		}

		$order = RemainingPaymentHandler::get_or_create_remaining_order( $booking_id );
		if ( is_wp_error( $order ) ) {
			wp_send_json_error( array( 'message' => $order->get_error_message() ) );
			return;
		}

		$payment_url = $order->get_checkout_payment_url();

		Mailer::sendBookingEmail(
			'remaining_payment_link_customer',
			$booking_id,
			'customer',
			array(
				'payment' => array( 'url' => $payment_url ),
			)
		);

		// Add log
		self::add_booking_log(
			$booking_id,
			'remaining_payment_link_sent',
			array(
				'order_id' => $order->get_id(),
				'sent_by'  => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message'     => __( 'Payment link generated and emailed to the customer.', 'mhm-rentiva' ),
				'payment_url' => $payment_url,
			)
		);
	}

	public static function approve_payment(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		$payment_status = get_post_meta( $booking_id, '_mhmrentiva_payment_status', true );
		if ( ! in_array( $payment_status, array( 'pending', 'unpaid', 'pending_verification', '' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This booking is not awaiting payment.', 'mhm-rentiva' ) ) );
			return;
		}

		// Update payment status to confirmed
		update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'paid' );

		// Update booking status to confirmed
		Status::update_status( $booking_id, 'confirmed', get_current_user_id() );

		// Add log
		self::add_booking_log(
			$booking_id,
			'payment_approved',
			array(
				'approved_by' => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Payment confirmed successfully.', 'mhm-rentiva' ),
			)
		);
	}

	public static function cancel_booking(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		$current_status = Status::get( $booking_id );
		if ( ! in_array( $current_status, array( 'pending', 'confirmed' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This booking cannot be cancelled.', 'mhm-rentiva' ) ) );
			return;
		}

		// Update booking status to cancelled
		Status::update_status( $booking_id, 'cancelled', get_current_user_id() );

		// Add log
		self::add_booking_log(
			$booking_id,
			'booking_cancelled',
			array(
				'cancelled_by' => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Booking cancelled successfully.', 'mhm-rentiva' ),
			)
		);
	}

	public static function process_refund(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		$payment_status = get_post_meta( $booking_id, '_mhmrentiva_payment_status', true );
		$booking_status = Status::get( $booking_id );

		if ( $payment_status !== 'paid' || $booking_status !== 'cancelled' ) {
			wp_send_json_error( array( 'message' => __( 'Refund cannot be processed for this booking.', 'mhm-rentiva' ) ) );
			return;
		}

		// Calculate refund amount
		$deposit_amount   = floatval( get_post_meta( $booking_id, '_mhmrentiva_deposit_amount', true ) );
		$total_amount     = floatval( get_post_meta( $booking_id, '_mhmrentiva_total_price', true ) );
		$remaining_amount = floatval( get_post_meta( $booking_id, '_mhmrentiva_remaining_amount', true ) );

		// Cancellation policy check
		$cancellation_deadline = get_post_meta( $booking_id, '_mhmrentiva_cancellation_deadline', true );
		$refund_amount         = 0;

		if ( $cancellation_deadline ) {
			$now      = time();
			$deadline = strtotime( $cancellation_deadline . ' UTC' );

			if ( $now <= $deadline ) {
				// Cancellation within 24 hours - full refund
				$refund_amount = $deposit_amount;
			} else {
				// Cancellation after 24 hours - no refund
				$refund_amount = 0;
			}
		} else {
			// No cancellation policy - full refund
			$refund_amount = $deposit_amount;
		}

		// Update refund status
		if ( $refund_amount > 0 ) {
			update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'refunded' );
			update_post_meta( $booking_id, '_mhmrentiva_refunded_amount', $refund_amount );
			update_post_meta( $booking_id, '_mhmrentiva_refund_date', gmdate( 'Y-m-d H:i:s' ) );
			update_post_meta( $booking_id, '_mhmrentiva_refund_processed_by', get_current_user_id() );
		}

		// Add log
		self::add_booking_log(
			$booking_id,
			'refund_processed',
			array(
				'refund_amount' => $refund_amount,
				'processed_by'  => get_current_user_id(),
			)
		);

		if ( $refund_amount > 0 ) {
			wp_send_json_success(
				array(
					/* translators: %s placeholder. */
					'message' => sprintf( __( 'Refund completed successfully. Refund amount: %s', 'mhm-rentiva' ), self::format_price( $refund_amount ) ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message' => __( 'Refund not processed due to cancellation policy.', 'mhm-rentiva' ),
				)
			);
		}
	}

	private static function add_booking_log( int $booking_id, string $action, array $data = array() ): void {
		$logs_meta = get_post_meta( $booking_id, '_mhmrentiva_booking_logs', true );
		$logs      = is_array( $logs_meta ) ? $logs_meta : array();

		$logs[] = array(
			'action'    => $action,
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
			'data'      => $data,
		);

		update_post_meta( $booking_id, '_mhmrentiva_booking_logs', $logs );
	}

	private static function format_price( float $price ): string {
		// Canonical currency formatting (WC-aware symbol/position/separators).
		// Reading mhmrentiva_currency_position here pinned this to `right_space`
		// whenever that option was unset, which is its normal state.
		return \MHMRentiva\Admin\Core\CurrencyHelper::format_price( $price, 2 );
	}
}
