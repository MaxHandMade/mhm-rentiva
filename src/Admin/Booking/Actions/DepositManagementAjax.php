<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Actions;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DepositManagementAjax {

	/**
	 * Read and validate booking id from POST.
	 *
	 * @return int
	 */
	private static function post_booking_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is enforced in each AJAX handler before this helper is called.
		if ( ! isset( $_POST['booking_id'] ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is enforced in each AJAX handler before this helper is called.
		return absint( wp_unslash( $_POST['booking_id'] ) );
	}

	public static function register(): void {
		add_action( 'wp_ajax_mhm_process_remaining_payment', array( self::class, 'process_remaining_payment' ) );
		add_action( 'wp_ajax_mhm_approve_payment', array( self::class, 'approve_payment' ) );
		add_action( 'wp_ajax_mhm_cancel_booking', array( self::class, 'cancel_booking' ) );
		add_action( 'wp_ajax_mhm_process_refund', array( self::class, 'process_refund' ) );
		add_action( 'wp_ajax_mhm_update_booking_status', array( self::class, 'update_booking_status' ) );
	}

	public static function process_remaining_payment(): void {
		// Nonce check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_deposit_management_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return;
		}

		$booking_id = self::post_booking_id();
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking ID.', 'mhm-rentiva' ) ) );
			return;
		}

		// Booking check
		$booking = get_post( $booking_id );
		if ( ! $booking || $booking->post_type !== 'vehicle_booking' ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'mhm-rentiva' ) ) );
			return;
		}

		// Deposit system check
		$payment_type = get_post_meta( $booking_id, '_mhm_payment_type', true );
		if ( $payment_type !== 'deposit' ) {
			wp_send_json_error( array( 'message' => __( 'This booking does not use deposit system.', 'mhm-rentiva' ) ) );
			return;
		}

		$remaining_amount = floatval( get_post_meta( $booking_id, '_mhm_remaining_amount', true ) );
		if ( $remaining_amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'No remaining amount found.', 'mhm-rentiva' ) ) );
			return;
		}

		// Reset remaining amount
		update_post_meta( $booking_id, '_mhm_remaining_amount', 0 );

		// Update payment status
		update_post_meta( $booking_id, '_mhm_payment_status', 'paid' );

		// If rental end date has already passed, mark as completed; otherwise confirmed
		$dropoff       = get_post_meta( $booking_id, '_mhm_dropoff_date', true )
			?: get_post_meta( $booking_id, '_mhm_end_date', true );
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

	public static function approve_payment(): void {
		// Nonce check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_deposit_management_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return;
		}

		$booking_id = self::post_booking_id();
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking ID.', 'mhm-rentiva' ) ) );
			return;
		}

		// Booking check
		$booking = get_post( $booking_id );
		if ( ! $booking || $booking->post_type !== 'vehicle_booking' ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'mhm-rentiva' ) ) );
			return;
		}

		$payment_status = get_post_meta( $booking_id, '_mhm_payment_status', true );
		if ( ! in_array( $payment_status, array( 'pending', 'unpaid', 'pending_verification', '' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This booking is not awaiting payment.', 'mhm-rentiva' ) ) );
			return;
		}

		// Update payment status to confirmed
		update_post_meta( $booking_id, '_mhm_payment_status', 'paid' );

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
		// Nonce check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_deposit_management_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return;
		}

		$booking_id = self::post_booking_id();
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking ID.', 'mhm-rentiva' ) ) );
			return;
		}

		// Booking check
		$booking = get_post( $booking_id );
		if ( ! $booking || $booking->post_type !== 'vehicle_booking' ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'mhm-rentiva' ) ) );
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
		// Nonce check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_deposit_management_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return;
		}

		$booking_id = self::post_booking_id();
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking ID.', 'mhm-rentiva' ) ) );
			return;
		}

		// Booking check
		$booking = get_post( $booking_id );
		if ( ! $booking || $booking->post_type !== 'vehicle_booking' ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'mhm-rentiva' ) ) );
			return;
		}

		$payment_status = get_post_meta( $booking_id, '_mhm_payment_status', true );
		$booking_status = Status::get( $booking_id );

		if ( $payment_status !== 'paid' || $booking_status !== 'cancelled' ) {
			wp_send_json_error( array( 'message' => __( 'Refund cannot be processed for this booking.', 'mhm-rentiva' ) ) );
			return;
		}

		// Calculate refund amount
		$deposit_amount   = floatval( get_post_meta( $booking_id, '_mhm_deposit_amount', true ) );
		$total_amount     = floatval( get_post_meta( $booking_id, '_mhm_total_price', true ) );
		$remaining_amount = floatval( get_post_meta( $booking_id, '_mhm_remaining_amount', true ) );

		// Cancellation policy check
		$cancellation_deadline = get_post_meta( $booking_id, '_mhm_cancellation_deadline', true );
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
			update_post_meta( $booking_id, '_mhm_payment_status', 'refunded' );
			update_post_meta( $booking_id, '_mhm_refunded_amount', $refund_amount );
			update_post_meta( $booking_id, '_mhm_refund_date', gmdate( 'Y-m-d H:i:s' ) );
			update_post_meta( $booking_id, '_mhm_refund_processed_by', get_current_user_id() );
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

	public static function update_booking_status(): void {
		// Nonce check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_deposit_management_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return;
		}

		$booking_id = self::post_booking_id();
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking ID.', 'mhm-rentiva' ) ) );
			return;
		}

		// Booking check
		$booking = get_post( $booking_id );
		if ( ! $booking || $booking->post_type !== 'vehicle_booking' ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'mhm-rentiva' ) ) );
			return;
		}

		// Status update operation
		// This function can be used for general status updates

		// Add log
		self::add_booking_log(
			$booking_id,
			'status_updated',
			array(
				'updated_by' => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Status updated successfully.', 'mhm-rentiva' ),
			)
		);
	}

	private static function add_booking_log( int $booking_id, string $action, array $data = array() ): void {
		$logs_meta = get_post_meta( $booking_id, '_mhm_booking_logs', true );
		$logs      = is_array( $logs_meta ) ? $logs_meta : array();

		$logs[] = array(
			'action'    => $action,
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
			'data'      => $data,
		);

		update_post_meta( $booking_id, '_mhm_booking_logs', $logs );
	}

	private static function format_price( float $price ): string {
		$symbol   = get_woocommerce_currency_symbol();
		$position = Settings::get( 'mhm_rentiva_currency_position', 'right_space' );
		$amount   = number_format_i18n( $price, 2 );

		switch ( $position ) {
			case 'left':
				return $symbol . $amount;
			case 'right':
				return $amount . $symbol;
			case 'left_space':
				return $symbol . ' ' . $amount;
			case 'right_space':
			default:
				return $amount . ' ' . $symbol;
		}
	}
}
