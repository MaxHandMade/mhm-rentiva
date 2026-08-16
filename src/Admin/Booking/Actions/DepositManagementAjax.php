<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Actions;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Core\Security\VerifiedRequest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DepositManagementAjax {

	/**
	 * Single entry guard for every deposit action.
	 *
	 * Verifies the nonce, resolves the booking the request names, and checks the
	 * caller against THAT booking. The handlers used to check the blanket
	 * edit_posts and then act on whichever booking_id arrived, so any role with
	 * edit_posts (a contributor, for instance) could approve payments, cancel
	 * bookings or issue refunds on bookings belonging to anyone. edit_post on the
	 * resolved booking is the capability that matches the object being acted on.
	 *
	 * Terminates the request via wp_send_json_error() when any check fails.
	 */
	private static function authorize_booking_action(): int {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_deposit_management_action' ) ) {
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

	public static function register(): void {
		add_action( 'wp_ajax_mhmrentiva_process_remaining_payment', array( self::class, 'process_remaining_payment' ) );
		add_action( 'wp_ajax_mhmrentiva_send_remaining_payment_link', array( self::class, 'send_remaining_payment_link' ) );
		add_action( 'wp_ajax_mhmrentiva_approve_payment', array( self::class, 'approve_payment' ) );
		add_action( 'wp_ajax_mhmrentiva_deposit_cancel_booking', array( self::class, 'cancel_booking' ) );
		add_action( 'wp_ajax_mhmrentiva_deposit_process_refund', array( self::class, 'process_refund' ) );
	}

	public static function process_remaining_payment(): void {
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

		// If the rental period has actually ended, mark as completed; otherwise
		// confirmed. Uses the drop-off time, not just the date -- see
		// Util::rental_has_ended().
		$target_status = \MHMRentiva\Admin\Booking\Helpers\Util::rental_has_ended( $booking_id ) ? 'completed' : 'confirmed';
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

		// Policy says nothing is owed back: nothing to attempt, nothing to change.
		if ( $refund_amount <= 0 ) {
			self::add_booking_log(
				$booking_id,
				'refund_processed',
				array(
					'refund_amount' => 0,
					'processed_by'  => get_current_user_id(),
				)
			);

			wp_send_json_success(
				array(
					'message' => __( 'Refund not processed due to cancellation policy.', 'mhm-rentiva' ),
				)
			);
			return;
		}

		// Hand the actual refund to the refund service.
		//
		// Until 6.0.4 this method wrote _mhmrentiva_payment_status = refunded and
		// answered "Refund completed successfully" WITHOUT ever calling a gateway:
		// no wc_create_refund(), no API call, nothing checking that money moved.
		// The booking said refunded, the operator saw success, and the customer was
		// never paid back.
		//
		// Payment\Refunds\Service does it properly -- validates, calls the gateway,
		// inspects the result and only writes meta when the refund actually
		// happened -- and had no caller anywhere in either edition. Rather than
		// grow a second refund implementation, the live path now goes through it.
		//
		// It works in kuruş (minor units), which is what every consumer of
		// _mhmrentiva_refunded_amount already assumes (BookingRefundMetaBox,
		// RefundCalculator); the float this method used to write was itself a unit
		// mismatch with those readers.
		$result = \MHMRentiva\Admin\Payment\Refunds\Service::process(
			$booking_id,
			(int) round( $refund_amount * 100 ),
			__( 'Refund issued from the deposit management screen.', 'mhm-rentiva' )
		);

		if ( '1' !== ( $result['mhmrentiva_refund'] ?? '0' ) ) {
			$message = (string) ( $result['mhmrentiva_refund_msg'] ?? '' );

			self::add_booking_log(
				$booking_id,
				'refund_failed',
				array(
					'refund_amount' => $refund_amount,
					'processed_by'  => get_current_user_id(),
					'reason'        => $message,
				)
			);

			wp_send_json_error(
				array(
					'message' => '' !== $message ? $message : __( 'Refund failed.', 'mhm-rentiva' ),
				)
			);
			return;
		}

		// The service has written payment status, refunded amount and the gateway
		// transaction id. These two are this screen's own audit trail.
		update_post_meta( $booking_id, '_mhmrentiva_refund_date', gmdate( 'Y-m-d H:i:s' ) );
		update_post_meta( $booking_id, '_mhmrentiva_refund_processed_by', get_current_user_id() );

		self::add_booking_log(
			$booking_id,
			'refund_processed',
			array(
				'refund_amount' => $refund_amount,
				'processed_by'  => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				/* translators: %s placeholder. */
				'message' => sprintf( __( 'Refund completed successfully. Refund amount: %s', 'mhm-rentiva' ), self::format_price( $refund_amount ) ),
			)
		);
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
		$symbol   = get_woocommerce_currency_symbol();
		$position = Settings::get( 'mhmrentiva_currency_position', 'right_space' );
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
