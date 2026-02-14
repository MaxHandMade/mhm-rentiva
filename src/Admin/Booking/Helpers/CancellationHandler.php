<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cancellation workflows validate booking/payment state through controlled direct queries.

namespace MHMRentiva\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking Cancellation Handler
 *
 * Handles booking cancellations with deadline validation,
 * availability management, email notifications, and refunds.
 *
 * @since 4.0.0
 */
final class CancellationHandler {


	/**
	 * Cancel a booking
	 *
	 * @param int    $booking_id Booking ID
	 * @param int    $user_id User ID who is cancelling (0 for admin)
	 * @param string $reason Cancellation reason (optional)
	 * @param bool   $force Force cancellation even if deadline passed (admin only)
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure
	 */
	public static function cancel_booking( int $booking_id, int $user_id = 0, string $reason = '', bool $force = false ) {
		global $wpdb;

		// Validate booking exists
		$booking = get_post( $booking_id );
		if ( ! $booking || $booking->post_type !== 'vehicle_booking' ) {
			return new \WP_Error(
				'invalid_booking',
				__( 'Invalid booking ID.', 'mhm-rentiva' )
			);
		}

		// Get current status
		$current_status = Status::get( $booking_id );

		// Check if booking can be cancelled
		if ( Status::CANCELLED === $current_status ) {
			return new \WP_Error(
				'already_cancelled',
				__( 'This booking is already cancelled.', 'mhm-rentiva' )
			);
		}

		if ( Status::COMPLETED === $current_status ) {
			return new \WP_Error(
				'booking_completed',
				__( 'Cannot cancel a completed booking.', 'mhm-rentiva' )
			);
		}

		if ( Status::REFUNDED === $current_status ) {
			return new \WP_Error(
				'booking_refunded',
				__( 'Cannot cancel a refunded booking.', 'mhm-rentiva' )
			);
		}

		// Check cancellation deadline (skip for admin with force flag)
		if ( ! $force ) {
			$deadline_check = self::check_cancellation_deadline( $booking_id );
			if ( is_wp_error( $deadline_check ) ) {
				return $deadline_check;
			}
		}

		// Check user permission (user can only cancel their own bookings)
		if ( $user_id > 0 ) {
			$booking_customer_id = (int) get_post_meta( $booking_id, '_mhm_customer_id', true );
			if ( $user_id !== $booking_customer_id && ! current_user_can( 'manage_options' ) ) {
				return new \WP_Error(
					'permission_denied',
					__( 'You do not have permission to cancel this booking.', 'mhm-rentiva' )
				);
			}
		}

		// Begin transaction-like operation
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Update booking status to cancelled
			$status_updated = Status::update_status( $booking_id, Status::CANCELLED, $user_id );

			if ( ! $status_updated ) {
				throw new \Exception( __( 'Failed to update booking status.', 'mhm-rentiva' ) );
			}

			// Save cancellation metadata
			$cancellation_data = array(
				'cancelled_at'        => current_time( 'mysql' ),
				'cancelled_by'        => $user_id > 0 ? $user_id : 'admin',
				'cancellation_reason' => $reason,
			);

			update_post_meta( $booking_id, '_mhm_cancellation_data', $cancellation_data );

			// Free up vehicle availability
			$availability_freed = self::free_vehicle_availability( $booking_id );
			if ( is_wp_error( $availability_freed ) ) {
				throw new \Exception( $availability_freed->get_error_message() );
			}

			// Clear cache
			if ( class_exists( 'MHMRentiva\Admin\Booking\Helpers\Cache' ) ) {
				$vehicle_id = (int) get_post_meta( $booking_id, '_mhm_vehicle_id', true );
				if ( $vehicle_id > 0 ) {
					Cache::invalidateVehicle( $vehicle_id );
				}
			}

			// Commit changes
			$wpdb->query( 'COMMIT' );

			// Send cancellation email (after commit to ensure data consistency)
			self::send_cancellation_email( $booking_id, $reason );

			// Process refund if payment was made
			self::process_refund( $booking_id, $user_id );

			// Trigger action for other plugins/integrations
			do_action( 'mhm_rentiva_booking_cancelled', $booking_id, $user_id, $reason );

			return true;
		} catch ( \Exception $e ) {
			// Rollback on error
			$wpdb->query( 'ROLLBACK' );

			return new \WP_Error(
				'cancellation_failed',
				sprintf(
					/* translators: %s placeholder. */
					__( 'Cancellation failed: %s', 'mhm-rentiva' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Check if booking can be cancelled based on deadline
	 *
	 * @param int $booking_id Booking ID
	 * @return true|\WP_Error True if can cancel, WP_Error if deadline passed
	 */
	public static function check_cancellation_deadline( int $booking_id ) {
		// Get cancellation deadline setting (in hours)
		$deadline_hours = (int) SettingsCore::get( 'mhm_rentiva_booking_cancellation_deadline_hours', 24 );

		// Get booking pickup date/time
		$pickup_date = get_post_meta( $booking_id, '_mhm_pickup_date', true );
		$pickup_time = get_post_meta( $booking_id, '_mhm_pickup_time', true );

		if ( empty( $pickup_date ) ) {
			return new \WP_Error(
				'invalid_booking_data',
				__( 'Booking pickup date is missing.', 'mhm-rentiva' )
			);
		}

		// Use default time if not set
		if ( empty( $pickup_time ) ) {
			$pickup_time = '10:00';
		}

		try {
			// Create pickup datetime
			$timezone        = wp_timezone();
			$pickup_datetime = \DateTime::createFromFormat( 'Y-m-d H:i', $pickup_date . ' ' . $pickup_time, $timezone );

			if ( ! $pickup_datetime ) {
				return new \WP_Error(
					'invalid_datetime',
					__( 'Invalid pickup date/time format.', 'mhm-rentiva' )
				);
			}

			// Calculate cancellation deadline
			$cancellation_deadline = clone $pickup_datetime;
			$cancellation_deadline->modify( '-' . $deadline_hours . ' hours' );

			// Get current time
			$current_time = new \DateTime( 'now', $timezone );

			// Check if deadline has passed
			if ( $current_time > $cancellation_deadline ) {
				$hours_until_pickup = round( ( $pickup_datetime->getTimestamp() - $current_time->getTimestamp() ) / 3600, 1 );

				return new \WP_Error(
					'deadline_passed',
					sprintf(
						/* translators: 1: required hours before pickup, 2: hours remaining until pickup. */
						__( 'Cancellation deadline has passed. Bookings must be cancelled at least %1$d hours before pickup. Only %2$.1f hours remaining.', 'mhm-rentiva' ),
						$deadline_hours,
						max( 0, $hours_until_pickup )
					)
				);
			}

			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'deadline_check_failed',
				__( 'Failed to check cancellation deadline.', 'mhm-rentiva' )
			);
		}
	}

	/**
	 * Free vehicle availability after cancellation
	 *
	 * @param int $booking_id Booking ID
	 * @return true|\WP_Error True on success, WP_Error on failure
	 */
	private static function free_vehicle_availability( int $booking_id ) {
		$vehicle_id   = (int) get_post_meta( $booking_id, '_mhm_vehicle_id', true );
		$pickup_date  = get_post_meta( $booking_id, '_mhm_pickup_date', true );
		$dropoff_date = get_post_meta( $booking_id, '_mhm_dropoff_date', true );

		if ( $vehicle_id === 0 || empty( $pickup_date ) || empty( $dropoff_date ) ) {
			return new \WP_Error(
				'invalid_booking_data',
				__( 'Invalid booking data for availability update.', 'mhm-rentiva' )
			);
		}

		// Get blocked dates
		$blocked_dates = get_post_meta( $vehicle_id, '_mhm_rentiva_blocked_dates', true );
		if ( ! is_array( $blocked_dates ) ) {
			$blocked_dates = array();
		}

		// Generate date range for this booking
		$start    = new \DateTime( $pickup_date );
		$end      = new \DateTime( $dropoff_date );
		$interval = new \DateInterval( 'P1D' );
		$period   = new \DatePeriod( $start, $interval, $end );

		// Remove dates from blocked list
		foreach ( $period as $date ) {
			$date_str = $date->format( 'Y-m-d' );
			$key      = array_search( $date_str, $blocked_dates );
			if ( $key !== false ) {
				unset( $blocked_dates[ $key ] );
			}
		}

		// Update vehicle meta
		update_post_meta( $vehicle_id, '_mhm_rentiva_blocked_dates', array_values( $blocked_dates ) );

		return true;
	}

	/**
	 * Send cancellation email
	 *
	 * @param int    $booking_id Booking ID
	 * @param string $reason Cancellation reason
	 * @return bool True if email sent, false otherwise
	 */
	private static function send_cancellation_email( int $booking_id, string $reason = '' ): bool {
		// Check if cancellation emails are enabled
		if ( ! SettingsCore::get( 'mhm_rentiva_booking_send_confirmation_emails', '1' ) ) {
			return false;
		}

		// Get customer email
		$customer_email = get_post_meta( $booking_id, '_mhm_customer_email', true );
		if ( empty( $customer_email ) ) {
			return false;
		}

		// Get booking details
		$vehicle_id    = (int) get_post_meta( $booking_id, '_mhm_vehicle_id', true );
		$vehicle_name  = get_the_title( $vehicle_id );
		$pickup_date   = get_post_meta( $booking_id, '_mhm_pickup_date', true );
		$dropoff_date  = get_post_meta( $booking_id, '_mhm_dropoff_date', true );
		$customer_name = get_post_meta( $booking_id, '_mhm_customer_name', true );

		// Email subject
		$subject = sprintf(
			/* translators: %s placeholder. */
			__( 'Booking Cancellation Confirmation - %s', 'mhm-rentiva' ),
			get_bloginfo( 'name' )
		);

		// Email template
		$template_path = MHM_RENTIVA_PLUGIN_DIR . 'templates/emails/booking-cancelled.html.php';

		// Fallback to simple HTML if template doesn't exist
		if ( file_exists( $template_path ) ) {
			ob_start();
			include $template_path;
			$message = ob_get_clean();
		} else {
			$message = self::get_default_cancellation_email_content(
				$customer_name,
				$booking_id,
				$vehicle_name,
				$pickup_date,
				$dropoff_date,
				$reason
			);
		}

		// Email headers
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		// Send email
		$sent = wp_mail( $customer_email, $subject, $message, $headers );

		// Send copy to admin if admin notifications are enabled
		$admin_notifications = SettingsCore::get( 'mhm_rentiva_booking_admin_notifications', '1' );
		if ( '1' === $admin_notifications ) {
			$admin_email   = get_option( 'admin_email' );
			$admin_subject = sprintf(
				/* translators: %d placeholder. */
				__( '[ADMIN] Booking Cancelled - #%d', 'mhm-rentiva' ),
				$booking_id
			);
			wp_mail( $admin_email, $admin_subject, $message, $headers );
		}

		return $sent;
	}

	/**
	 * Get default cancellation email content
	 *
	 * @param string $customer_name Customer name
	 * @param int    $booking_id Booking ID
	 * @param string $vehicle_name Vehicle name
	 * @param string $pickup_date Pickup date
	 * @param string $dropoff_date Dropoff date
	 * @param string $reason Cancellation reason
	 * @return string Email HTML content
	 */
	private static function get_default_cancellation_email_content(
		string $customer_name,
		int $booking_id,
		string $vehicle_name,
		string $pickup_date,
		string $dropoff_date,
		string $reason
	): string {
		$html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$html .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;">';
		$html .= '<h2 style="color: #dc3545;">' . esc_html__( 'Booking Cancelled', 'mhm-rentiva' ) . '</h2>';
		/* translators: %s placeholder. */
		$html .= '<p>' . sprintf( esc_html__( 'Hello %s,', 'mhm-rentiva' ), esc_html( $customer_name ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Your booking has been cancelled successfully.', 'mhm-rentiva' ) . '</p>';

		$html .= '<div style="background: white; padding: 15px; margin: 20px 0; border-left: 4px solid #dc3545;">';
		$html .= '<p><strong>' . esc_html__( 'Booking Details:', 'mhm-rentiva' ) . '</strong></p>';
		$html .= '<p><strong>' . esc_html__( 'Booking ID:', 'mhm-rentiva' ) . '</strong> #' . esc_html( $booking_id ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Vehicle:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $vehicle_name ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Pickup Date:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $pickup_date ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Dropoff Date:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $dropoff_date ) . '</p>';

		if ( ! empty( $reason ) ) {
			$html .= '<p><strong>' . esc_html__( 'Reason:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $reason ) . '</p>';
		}
		$html .= '</div>';

		$html .= '<p>' . esc_html__( 'If a refund is applicable, it will be processed within 5-7 business days.', 'mhm-rentiva' ) . '</p>';
		$html .= '<p>' . esc_html__( 'Thank you for choosing our service.', 'mhm-rentiva' ) . '</p>';
		$html .= '<p style="margin-top: 30px; font-size: 12px; color: #666;">' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		$html .= '</div></body></html>';

		return $html;
	}

	/**
	 * Process refund if payment was made
	 *
	 * @param int $booking_id Booking ID
	 * @param int $user_id User ID
	 * @return bool True if refund initiated, false otherwise
	 */
	private static function process_refund( int $booking_id, int $user_id ): bool {
		// Get payment status
		$payment_status = get_post_meta( $booking_id, '_mhm_payment_status', true );

		if ( 'paid' !== $payment_status ) {
			// No payment to refund
			return false;
		}

		// Get payment gateway
		$payment_gateway = get_post_meta( $booking_id, '_mhm_payment_gateway', true );

		// Mark as pending refund
		update_post_meta( $booking_id, '_mhm_refund_status', 'pending' );
		update_post_meta( $booking_id, '_mhm_refund_requested_at', current_time( 'mysql' ) );
		update_post_meta( $booking_id, '_mhm_refund_requested_by', $user_id );

		// Trigger refund action for payment gateway handlers
		do_action( 'mhm_rentiva_process_refund', $booking_id, $payment_gateway, $user_id );

		// Note: Actual refund processing depends on payment gateway implementation
		// For now, we just mark it as pending refund and trigger the action
		// Payment gateway classes should listen to this action and process refunds

		return true;
	}

	/**
	 * Check if user can cancel booking
	 *
	 * @param int $booking_id Booking ID
	 * @param int $user_id User ID (0 for current user)
	 * @return bool True if can cancel, false otherwise
	 */
	public static function user_can_cancel( int $booking_id, int $user_id = 0 ): bool {
		if ( $user_id === 0 ) {
			$user_id = get_current_user_id();
		}

		// Admins can always cancel
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Check if user owns the booking
		$booking_customer_id = (int) get_post_meta( $booking_id, '_mhm_customer_id', true );
		if ( $booking_customer_id !== $user_id ) {
			return false;
		}

		// Check status
		$current_status = Status::get( $booking_id );
		if ( in_array( $current_status, array( Status::CANCELLED, Status::COMPLETED, Status::REFUNDED ), true ) ) {
			return false;
		}

		// Check deadline
		$deadline_check = self::check_cancellation_deadline( $booking_id );

		return ! is_wp_error( $deadline_check );
	}

	/**
	 * Get cancellation deadline info for display
	 *
	 * @param int $booking_id Booking ID
	 * @return array{can_cancel: bool, deadline: string, hours_remaining: float, message: string}
	 */
	public static function get_cancellation_info( int $booking_id ): array {
		$deadline_hours = (int) SettingsCore::get( 'mhm_rentiva_booking_cancellation_deadline_hours', 24 );

		$pickup_date = get_post_meta( $booking_id, '_mhm_pickup_date', true );
		$pickup_time = get_post_meta( $booking_id, '_mhm_pickup_time', true );

		if ( empty( $pickup_date ) ) {
			return array(
				'can_cancel'      => false,
				'deadline'        => '',
				'hours_remaining' => 0,
				'message'         => __( 'Invalid booking data.', 'mhm-rentiva' ),
			);
		}

		if ( empty( $pickup_time ) ) {
			$pickup_time = '10:00';
		}

		try {
			$timezone        = wp_timezone();
			$pickup_datetime = \DateTime::createFromFormat( 'Y-m-d H:i', $pickup_date . ' ' . $pickup_time, $timezone );

			$cancellation_deadline = clone $pickup_datetime;
			$cancellation_deadline->modify( '-' . $deadline_hours . ' hours' );

			$current_time    = new \DateTime( 'now', $timezone );
			$hours_remaining = ( $cancellation_deadline->getTimestamp() - $current_time->getTimestamp() ) / 3600;

			$can_cancel = $hours_remaining > 0;

			if ( $can_cancel ) {
				$message = sprintf(
					/* translators: 1: %s; 2: %d. */
					__( 'You can cancel until %1$s (%2$d hours before pickup)', 'mhm-rentiva' ),
					$cancellation_deadline->format( 'Y-m-d H:i' ),
					$deadline_hours
				);
			} else {
				$message = sprintf(
					/* translators: %d placeholder. */
					__( 'Cancellation deadline has passed. Bookings must be cancelled at least %d hours before pickup.', 'mhm-rentiva' ),
					$deadline_hours
				);
			}

			return array(
				'can_cancel'      => $can_cancel,
				'deadline'        => $cancellation_deadline->format( 'Y-m-d H:i' ),
				'hours_remaining' => round( $hours_remaining, 1 ),
				'message'         => $message,
			);
		} catch ( \Exception $e ) {
			return array(
				'can_cancel'      => false,
				'deadline'        => '',
				'hours_remaining' => 0,
				'message'         => __( 'Error calculating cancellation deadline.', 'mhm-rentiva' ),
			);
		}
	}
}
