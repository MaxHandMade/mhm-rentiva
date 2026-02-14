<?php declare(strict_types=1);

namespace MHMRentiva\Admin\REST\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central validation helper class for REST API
 *
 * This class meets the common validation needs of all REST endpoints
 * and prevents code duplication.
 */
final class ValidationHelper {

	/**
	 * Safely get client IP address
	 *
	 * @return string Client IP address
	 */
	public static function getClientIp(): string {
		$ipHeaders = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ipHeaders as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$headerValue = sanitize_text_field( wp_unslash( (string) $_SERVER[ $header ] ) );
				$ipList      = explode( ',', $headerValue );
				foreach ( $ipList as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						return $ip;
					}
				}
			}
		}

		$remoteAddr = sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		return '' !== $remoteAddr ? $remoteAddr : '0.0.0.0';
	}

	/**
	 * Check if booking is suitable for payment
	 *
	 * @param int    $booking_id Booking ID
	 * @param string $post_type Post type (default: 'vehicle_booking')
	 * @param string $payment_meta_key Payment status meta key (default: '_mhm_payment_status')
	 * @param array  $allowed_statuses Allowed payment statuses
	 * @return bool Is booking suitable for payment?
	 */
	public static function validateBookingForPayment(
		int $booking_id,
		string $post_type = 'vehicle_booking',
		string $payment_meta_key = '_mhm_payment_status',
		array $allowed_statuses = array( 'unpaid', 'failed', '' )
	): bool {
		if ( $booking_id <= 0 ) {
			return false;
		}

		$post = get_post( $booking_id );
		if ( ! $post || $post->post_type !== $post_type ) {
			return false;
		}

		$paymentStatus = (string) get_post_meta( $booking_id, $payment_meta_key, true );

		return in_array( $paymentStatus, $allowed_statuses, true );
	}

	/**
	 * Booking validation (with detailed result)
	 *
	 * @param int    $booking_id Booking ID
	 * @param string $post_type Post type (default: 'vehicle_booking')
	 * @param string $status_meta_key Status meta key (default: '_mhm_status')
	 * @param string $payment_meta_key Payment status meta key (default: '_mhm_payment_status')
	 * @param array  $allowed_statuses Allowed statuses
	 * @param array  $blocked_payment_statuses Blocked payment statuses
	 * @return array Validation result
	 */
	public static function validateBookingDetailed(
		int $booking_id,
		string $post_type = 'vehicle_booking',
		string $status_meta_key = '_mhm_status',
		string $payment_meta_key = '_mhm_payment_status',
		array $allowed_statuses = array( 'pending', 'confirmed' ),
		array $blocked_payment_statuses = array( 'paid', 'refunded' )
	): array {
		if ( $booking_id <= 0 ) {
			return array(
				'valid' => false,
				'error' => 'invalid_booking_id',
			);
		}

		$post = get_post( $booking_id );
		if ( ! $post || $post->post_type !== $post_type ) {
			return array(
				'valid' => false,
				'error' => 'booking_not_found',
			);
		}

		$status        = (string) get_post_meta( $booking_id, $status_meta_key, true );
		$paymentStatus = (string) get_post_meta( $booking_id, $payment_meta_key, true );

		if ( in_array( $paymentStatus, $blocked_payment_statuses, true ) ) {
			return array(
				'valid' => false,
				'error' => 'already_paid',
			);
		}

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return array(
				'valid' => false,
				'error' => 'invalid_status',
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Check if booking is suitable for refund
	 *
	 * @param int    $booking_id Booking ID
	 * @param string $post_type Post type (default: 'vehicle_booking')
	 * @param string $payment_meta_key Payment status meta key (default: '_mhm_payment_status')
	 * @return array Validation result
	 */
	public static function validateBookingForRefund(
		int $booking_id,
		string $post_type = 'vehicle_booking',
		string $payment_meta_key = '_mhm_payment_status'
	): array {
		if ( $booking_id <= 0 ) {
			return array(
				'valid' => false,
				'error' => 'invalid_booking_id',
			);
		}

		$post = get_post( $booking_id );
		if ( ! $post || $post->post_type !== $post_type ) {
			return array(
				'valid' => false,
				'error' => 'booking_not_found',
			);
		}

		$paymentStatus = (string) get_post_meta( $booking_id, $payment_meta_key, true );

		if ( $paymentStatus !== 'paid' ) {
			return array(
				'valid' => false,
				'error' => 'not_paid',
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Email address validation
	 *
	 * @param string $email Email address
	 * @return bool Is email valid?
	 */
	public static function isValidEmail( string $email ): bool {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
	}

	/**
	 * Phone number validation
	 *
	 * @param string $phone Phone number
	 * @return bool Is phone valid?
	 */
	public static function isValidPhone( string $phone ): bool {
		// Only allow digits, +, -, ( and ) characters
		$cleaned = preg_replace( '/[^\d\+\-\(\)\s]/', '', $phone );
		return strlen( $cleaned ) >= 10 && strlen( $cleaned ) <= 20;
	}

	/**
	 * Money amount validation
	 *
	 * @param mixed $amount Money amount
	 * @param float $min Minimum amount
	 * @param float $max Maximum amount
	 * @return bool Is amount valid?
	 */
	public static function isValidAmount( $amount, float $min = 0.01, float $max = 999999.99 ): bool {
		if ( ! is_numeric( $amount ) ) {
			return false;
		}

		$amount = (float) $amount;
		return $amount >= $min && $amount <= $max;
	}

	/**
	 * Date validation
	 *
	 * @param string $date Date string
	 * @param string $format Date format (default: 'Y-m-d')
	 * @return bool Is date valid?
	 */
	public static function isValidDate( string $date, string $format = 'Y-m-d' ): bool {
		$dateTime = \DateTime::createFromFormat( $format, $date );
		return $dateTime && $dateTime->format( $format ) === $date;
	}

	/**
	 * Future date check
	 *
	 * @param string $date Date string
	 * @param string $format Date format (default: 'Y-m-d')
	 * @return bool Is date in the future?
	 */
	public static function isFutureDate( string $date, string $format = 'Y-m-d' ): bool {
		if ( ! self::isValidDate( $date, $format ) ) {
			return false;
		}

		$dateTime = \DateTime::createFromFormat( $format, $date );
		return $dateTime > new \DateTime();
	}

	/**
	 * URL validation
	 *
	 * @param string $url URL
	 * @return bool Is URL valid?
	 */
	public static function isValidUrl( string $url ): bool {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Nonce validation
	 *
	 * @param string $nonce Nonce value
	 * @param string $action Action
	 * @return bool Is nonce valid?
	 */
	public static function isValidNonce( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Post ID validation
	 *
	 * @param mixed  $post_id Post ID
	 * @param string $post_type Post type
	 * @return bool Is Post ID valid?
	 */
	public static function isValidPostId( $post_id, string $post_type = '' ): bool {
		if ( ! is_numeric( $post_id ) || (int) $post_id <= 0 ) {
			return false;
		}

		$post = get_post( (int) $post_id );
		if ( ! $post || $post->post_status === 'trash' ) {
			return false;
		}

		if ( ! empty( $post_type ) && $post->post_type !== $post_type ) {
			return false;
		}

		return true;
	}

	/**
	 * Meta value validation
	 *
	 * @param mixed  $value Meta value
	 * @param string $type Expected type ('string', 'int', 'float', 'bool', 'array')
	 * @return bool Is value valid?
	 */
	public static function isValidMetaValue( $value, string $type ): bool {
		switch ( $type ) {
			case 'string':
				return is_string( $value );
			case 'int':
				return is_numeric( $value ) && is_int( (int) $value );
			case 'float':
				return is_numeric( $value );
			case 'bool':
				return is_bool( $value ) || in_array( $value, array( '0', '1', 'true', 'false' ), true );
			case 'array':
				return is_array( $value );
			default:
				return true;
		}
	}
}
