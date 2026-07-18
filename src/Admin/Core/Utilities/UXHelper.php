<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UX Helper
 *
 * Central class for user experience and error handling
 */
final class UXHelper {



	/**
	 * Error message types
	 */
	public const ERROR_TYPE_BOOKING    = 'booking';
	public const ERROR_TYPE_PAYMENT    = 'payment';
	public const ERROR_TYPE_VEHICLE    = 'vehicle';
	public const ERROR_TYPE_CUSTOMER   = 'customer';
	public const ERROR_TYPE_SYSTEM     = 'system';
	public const ERROR_TYPE_VALIDATION = 'validation';
	public const ERROR_TYPE_PERMISSION = 'permission';
	public const ERROR_TYPE_NETWORK    = 'network';

	/**
	 * Error severity levels
	 */
	public const SEVERITY_LOW      = 'low';
	public const SEVERITY_MEDIUM   = 'medium';
	public const SEVERITY_HIGH     = 'high';
	public const SEVERITY_CRITICAL = 'critical';

	/**
	 * User-friendly error messages
	 */
	public static function get_user_friendly_error( string $error_type, string $error_code, array $context = array() ): string {
		$messages = self::get_error_messages();

		if ( ! isset( $messages[ $error_type ][ $error_code ] ) ) {
			return __( 'An unexpected error occurred. Please try again later.', 'mhm-rentiva' );
		}

		$message = $messages[ $error_type ][ $error_code ];

		// Replace context variables
		if ( ! empty( $context ) ) {
			$message = self::replace_context_variables( $message, $context );
		}

		return $message;
	}

	/**
	 * Error messages with context
	 */
	private static function get_error_messages(): array {
		return array(
			self::ERROR_TYPE_BOOKING    => array(
				/* translators: %date% is replaced with a runtime value. */
				'vehicle_not_available' => __( 'The selected vehicle is not available on %date%. Please select a different date.', 'mhm-rentiva' ),
				'booking_failed'        => __( 'An error occurred while creating reservation. Please check your information and try again.', 'mhm-rentiva' ),
				'invalid_dates'         => __( 'Invalid date range. Start date must be before end date.', 'mhm-rentiva' ),
				'past_date'             => __( 'You cannot select a past date. Please select a date after today.', 'mhm-rentiva' ),
				/* translators: %days% is replaced with a runtime value. */
				'too_far_future'        => __( 'Reservation date is too far in the future. You can make reservations up to %days% days in advance.', 'mhm-rentiva' ),
				/* translators: %hours% is replaced with a runtime value. */
				'minimum_duration'      => __( 'Minimum reservation duration is %hours% hours.', 'mhm-rentiva' ),
				/* translators: %days% is replaced with a runtime value. */
				'maximum_duration'      => __( 'Maximum reservation duration is %days% days.', 'mhm-rentiva' ),
				'booking_limit_reached' => __( 'You can make a maximum of %limit% reservations at once.', 'mhm-rentiva' ),
				'customer_not_found'    => __( 'Customer information not found. Please log in or register.', 'mhm-rentiva' ),
				'payment_required'      => __( 'Payment is required for reservation. Please select your payment method.', 'mhm-rentiva' ),
			),
			self::ERROR_TYPE_PAYMENT    => array(
				'payment_failed'               => __( 'Payment transaction failed. Please check your card information and try again.', 'mhm-rentiva' ),
				'insufficient_funds'           => __( 'Insufficient balance. Please try a different payment method.', 'mhm-rentiva' ),
				'card_expired'                 => __( 'Your card has expired. Please use a current card.', 'mhm-rentiva' ),
				'invalid_card'                 => __( 'Invalid card information. Please check your card number and security code.', 'mhm-rentiva' ),
				'payment_timeout'              => __( 'Payment transaction timed out. Please try again.', 'mhm-rentiva' ),
				'refund_failed'                => __( 'Refund process failed. Please contact customer service.', 'mhm-rentiva' ),
				'partial_refund'               => __( 'Partial refund completed. %amount% has been refunded to your account.', 'mhm-rentiva' ),
				'payment_method_not_supported' => __( 'Selected payment method is not supported. Please try a different method.', 'mhm-rentiva' ),
			),
			self::ERROR_TYPE_VEHICLE    => array(
				'vehicle_not_found'        => __( 'Vehicle not found. Please refresh the page and try again.', 'mhm-rentiva' ),
				'vehicle_unavailable'      => __( 'This vehicle is currently in use. Please select a different vehicle.', 'mhm-rentiva' ),
				/* translators: %duration% is replaced with a runtime value. */
				'vehicle_maintenance'      => __( 'Vehicle is under maintenance. Estimated duration: %duration%.', 'mhm-rentiva' ),
				'vehicle_damaged'          => __( 'Vehicle is damaged. Booking not available.', 'mhm-rentiva' ),
				'vehicle_location_changed' => __( 'Vehicle location changed. New location: %location%.', 'mhm-rentiva' ),
				'vehicle_price_changed'    => __( 'Vehicle price updated. New price: %price%.', 'mhm-rentiva' ),
			),
			self::ERROR_TYPE_CUSTOMER   => array(
				'customer_not_found'             => __( 'Customer information not found. Please log in.', 'mhm-rentiva' ),
				'customer_blocked'               => __( 'Your account has been temporarily restricted. Please contact customer service.', 'mhm-rentiva' ),
				'customer_verification_required' => __( 'You need to verify your account. Please check your email address.', 'mhm-rentiva' ),
				'customer_profile_incomplete'    => __( 'Your profile information is incomplete. Please complete your information from your profile page.', 'mhm-rentiva' ),
				'customer_license_expired'       => __( 'Your license has expired. Please upload a current license.', 'mhm-rentiva' ),
				'customer_age_restriction'       => __( 'You cannot make a reservation due to age restriction. Minimum age: %age%.', 'mhm-rentiva' ),
			),
			self::ERROR_TYPE_SYSTEM     => array(
				'database_error'      => __( 'System is temporarily unavailable. Please try again in a few minutes.', 'mhm-rentiva' ),
				'server_error'        => __( 'Server error occurred. Please try again later.', 'mhm-rentiva' ),
				/* translators: %duration% is replaced with a runtime value. */
				'maintenance_mode'    => __( 'System is under maintenance. Estimated duration: %duration%.', 'mhm-rentiva' ),
				'feature_disabled'    => __( 'This feature is temporarily disabled.', 'mhm-rentiva' ),
				/* translators: %seconds% is replaced with a runtime value. */
				'rate_limit_exceeded' => __( 'Too many requests. Please wait %seconds% seconds.', 'mhm-rentiva' ),
				'session_expired'     => __( 'Your session has expired. Please log in again.', 'mhm-rentiva' ),
			),
			self::ERROR_TYPE_VALIDATION => array(
				'required_field'     => __( 'This field is required.', 'mhm-rentiva' ),
				'invalid_email'      => __( 'Please enter a valid email address.', 'mhm-rentiva' ),
				'invalid_phone'      => __( 'Please enter a valid phone number.', 'mhm-rentiva' ),
				'invalid_date'       => __( 'Please enter a valid date.', 'mhm-rentiva' ),
				'invalid_time'       => __( 'Please enter a valid time.', 'mhm-rentiva' ),
				'password_too_short' => __( 'Password must be at least %length% characters.', 'mhm-rentiva' ),
				'password_mismatch'  => __( 'Passwords do not match.', 'mhm-rentiva' ),
				/* translators: %size% is replaced with a runtime value. */
				'file_too_large'     => __( 'File size is too large. Maximum size: %size%.', 'mhm-rentiva' ),
				'invalid_file_type'  => __( 'Invalid file type. Allowed types: %types%.', 'mhm-rentiva' ),
			),
			self::ERROR_TYPE_PERMISSION => array(
				'access_denied'         => __( 'You do not have access to this page.', 'mhm-rentiva' ),
				'action_not_allowed'    => __( 'You do not have permission to perform this action.', 'mhm-rentiva' ),
				'admin_required'        => __( 'Administrator privileges required for this action.', 'mhm-rentiva' ),
				'login_required'        => __( 'You need to log in for this action.', 'mhm-rentiva' ),
				'verification_required' => __( 'You need to verify your account for this action.', 'mhm-rentiva' ),
			),
			self::ERROR_TYPE_NETWORK    => array(
				'connection_failed' => __( 'Connection failed. Please check your internet connection.', 'mhm-rentiva' ),
				'timeout'           => __( 'Connection timed out. Please try again.', 'mhm-rentiva' ),
				'dns_error'         => __( 'DNS error occurred. Please try again later.', 'mhm-rentiva' ),
				'ssl_error'         => __( 'Secure connection error. Please try again later.', 'mhm-rentiva' ),
			),
		);
	}

	/**
	 * Replace context variables
	 */
	private static function replace_context_variables( string $message, array $context ): string {
		foreach ( $context as $key => $value ) {
			$message = str_replace( '%' . $key . '%', $value, $message );
		}
		return $message;
	}

	/**
	 * Success messages
	 */
	public static function get_success_message( string $action_type, array $context = array() ): string {
		$messages = array(
			/* translators: %booking_id% is replaced with a runtime value. */
			'booking_created'   => __( 'Your reservation has been successfully created. Your reservation number: %booking_id%', 'mhm-rentiva' ),
			'booking_updated'   => __( 'Your reservation has been successfully updated.', 'mhm-rentiva' ),
			'booking_cancelled' => __( 'Your reservation has been successfully cancelled.', 'mhm-rentiva' ),
			'payment_completed' => __( 'Your payment has been successfully completed.', 'mhm-rentiva' ),
			'profile_updated'   => __( 'Your profile information has been successfully updated.', 'mhm-rentiva' ),
			'password_changed'  => __( 'Your password has been successfully changed.', 'mhm-rentiva' ),
			'email_sent'        => __( 'Email sent successfully.', 'mhm-rentiva' ),
			'file_uploaded'     => __( 'File uploaded successfully.', 'mhm-rentiva' ),
			'settings_saved'    => __( 'Settings saved successfully.', 'mhm-rentiva' ),
		);

		if ( ! isset( $messages[ $action_type ] ) ) {
			return __( 'Operation completed successfully.', 'mhm-rentiva' );
		}

		$message = $messages[ $action_type ];

		if ( ! empty( $context ) ) {
			$message = self::replace_context_variables( $message, $context );
		}

		return $message;
	}

	/**
	 * Warning messages
	 */
	public static function get_warning_message( string $warning_type, array $context = array() ): string {
		$messages = array(
			'booking_ending_soon'   => __( 'Your reservation will end in %time%.', 'mhm-rentiva' ),
			/* translators: %date% is replaced with a runtime value. */
			'payment_due'           => __( 'Your payment must be made on %date%.', 'mhm-rentiva' ),
			/* translators: %date% is replaced with a runtime value. */
			'vehicle_return_due'    => __( 'You must return the vehicle on %date%.', 'mhm-rentiva' ),
			/* translators: %days% is replaced with a runtime value. */
			'license_expiring'      => __( 'Your license will expire in %days% days.', 'mhm-rentiva' ),
			/* translators: %date% is replaced with a runtime value. */
			'maintenance_scheduled' => __( 'Vehicle maintenance is scheduled for %date%.', 'mhm-rentiva' ),
			/* translators: %date% is replaced with a runtime value. */
			'price_increase'        => __( 'Vehicle price will increase from %date%.', 'mhm-rentiva' ),
		);

		if ( ! isset( $messages[ $warning_type ] ) ) {
			return __( 'Warning: %message%', 'mhm-rentiva' );
		}

		$message = $messages[ $warning_type ];

		if ( ! empty( $context ) ) {
			$message = self::replace_context_variables( $message, $context );
		}

		return $message;
	}

	/**
	 * Info messages
	 */
	public static function get_info_message( string $info_type, array $context = array() ): string {
		$messages = array(
			'booking_confirmed'     => __( 'Your reservation has been confirmed. Details have been sent via email.', 'mhm-rentiva' ),
			'payment_processing'    => __( 'Your payment is being processed. Please wait...', 'mhm-rentiva' ),
			'vehicle_ready'         => __( 'Your vehicle is ready. You can pick it up at the reservation time.', 'mhm-rentiva' ),
			'maintenance_completed' => __( 'Vehicle maintenance completed. You can make a reservation.', 'mhm-rentiva' ),
			/* translators: %feature% is replaced with a runtime value. */
			'new_feature'           => __( 'New feature: %feature%. Click for details.', 'mhm-rentiva' ),
			'system_update'         => __( 'System updated. New features available.', 'mhm-rentiva' ),
		);

		if ( ! isset( $messages[ $info_type ] ) ) {
			return __( 'Info: %message%', 'mhm-rentiva' );
		}

		$message = $messages[ $info_type ];

		if ( ! empty( $context ) ) {
			$message = self::replace_context_variables( $message, $context );
		}

		return $message;
	}

	/**
	 * Error recovery suggestions
	 */
	public static function get_recovery_suggestions( string $error_type, string $error_code ): array {
		$suggestions = array(
			self::ERROR_TYPE_BOOKING => array(
				'vehicle_not_available' => array(
					__( 'Select a different date', 'mhm-rentiva' ),
					__( 'Select a different vehicle', 'mhm-rentiva' ),
					__( 'Contact customer service', 'mhm-rentiva' ),
				),
				'booking_failed'        => array(
					__( 'Check your information', 'mhm-rentiva' ),
					__( 'Refresh the page', 'mhm-rentiva' ),
					__( 'Try a different browser', 'mhm-rentiva' ),
				),
			),
			self::ERROR_TYPE_PAYMENT => array(
				'payment_failed'     => array(
					__( 'Check your card information', 'mhm-rentiva' ),
					__( 'Try a different card', 'mhm-rentiva' ),
					__( 'Contact your bank', 'mhm-rentiva' ),
				),
				'insufficient_funds' => array(
					__( 'Check your account balance', 'mhm-rentiva' ),
					__( 'Try a different payment method', 'mhm-rentiva' ),
					__( 'Try partial payment option', 'mhm-rentiva' ),
				),
			),
			self::ERROR_TYPE_SYSTEM  => array(
				'database_error' => array(
					__( 'Wait a few minutes', 'mhm-rentiva' ),
					__( 'Refresh the page', 'mhm-rentiva' ),
					__( 'Contact customer service', 'mhm-rentiva' ),
				),
				'server_error'   => array(
					__( 'Try again later', 'mhm-rentiva' ),
					__( 'Try a different device', 'mhm-rentiva' ),
					__( 'Check your internet connection', 'mhm-rentiva' ),
				),
			),
		);

		return $suggestions[ $error_type ][ $error_code ] ?? array(
			__( 'Refresh the page', 'mhm-rentiva' ),
			__( 'Try again later', 'mhm-rentiva' ),
			__( 'Contact customer service', 'mhm-rentiva' ),
		);
	}

	/**
	 * Error severity level
	 */
	public static function get_error_severity( string $error_type, string $error_code ): string {
		$severity_map = array(
			self::ERROR_TYPE_BOOKING => array(
				'vehicle_not_available' => self::SEVERITY_MEDIUM,
				'booking_failed'        => self::SEVERITY_HIGH,
				'invalid_dates'         => self::SEVERITY_LOW,
			),
			self::ERROR_TYPE_PAYMENT => array(
				'payment_failed'     => self::SEVERITY_HIGH,
				'insufficient_funds' => self::SEVERITY_MEDIUM,
				'card_expired'       => self::SEVERITY_MEDIUM,
			),
			self::ERROR_TYPE_SYSTEM  => array(
				'database_error'   => self::SEVERITY_CRITICAL,
				'server_error'     => self::SEVERITY_CRITICAL,
				'maintenance_mode' => self::SEVERITY_MEDIUM,
			),
		);

		return $severity_map[ $error_type ][ $error_code ] ?? self::SEVERITY_MEDIUM;
	}

	/**
	 * Error icon
	 */
	public static function get_error_icon( string $error_type, string $severity ): string {
		$icons = array(
			self::SEVERITY_LOW      => '⚠️',
			self::SEVERITY_MEDIUM   => '⚠️',
			self::SEVERITY_HIGH     => '❌',
			self::SEVERITY_CRITICAL => '🚨',
		);

		return $icons[ $severity ] ?? '⚠️';
	}

	/**
	 * Error color
	 */
	public static function get_error_color( string $severity ): string {
		$colors = array(
			self::SEVERITY_LOW      => '#f39c12',
			self::SEVERITY_MEDIUM   => '#e67e22',
			self::SEVERITY_HIGH     => '#e74c3c',
			self::SEVERITY_CRITICAL => '#c0392b',
		);

		return $colors[ $severity ] ?? '#e67e22';
	}

	/**
	 * Error action button
	 */
	public static function get_error_action( string $error_type, string $error_code ): array {
		$actions = array(
			self::ERROR_TYPE_BOOKING => array(
				'vehicle_not_available' => array(
					'text'  => __( 'Select Different Date', 'mhm-rentiva' ),
					'url'   => '#',
					'class' => 'button button-primary',
				),
				'booking_failed'        => array(
					'text'  => __( 'Try Again', 'mhm-rentiva' ),
					'url'   => '#',
					'class' => 'button button-primary',
				),
			),
			self::ERROR_TYPE_PAYMENT => array(
				'payment_failed' => array(
					'text'  => __( 'Retry Payment', 'mhm-rentiva' ),
					'url'   => '#',
					'class' => 'button button-primary',
				),
			),
		);

		return $actions[ $error_type ][ $error_code ] ?? array(
			'text'  => __( 'Try Again', 'mhm-rentiva' ),
			'url'   => '#',
			'class' => 'button button-primary',
		);
	}

	/**
	 * Error notification HTML
	 */
	public static function render_error_notification( string $error_type, string $error_code, array $context = array() ): string {
		$message     = self::get_user_friendly_error( $error_type, $error_code, $context );
		$severity    = self::get_error_severity( $error_type, $error_code );
		$icon        = self::get_error_icon( $error_type, $severity );
		$color       = self::get_error_color( $severity );
		$suggestions = self::get_recovery_suggestions( $error_type, $error_code );
		$action      = self::get_error_action( $error_type, $error_code );

		$suggestions_html = '';
		if ( ! empty( $suggestions ) ) {
			$suggestions_html = '<ul class="error-suggestions">';
			foreach ( $suggestions as $suggestion ) {
				$suggestions_html .= '<li>' . esc_html( $suggestion ) . '</li>';
			}
			$suggestions_html .= '</ul>';
		}

		$action_html = '';
		if ( ! empty( $action ) ) {
			$action_html = sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $action['url'] ),
				esc_attr( $action['class'] ),
				esc_html( $action['text'] )
			);
		}

		return sprintf(
			'<div class="mhm-error-notification" style="border-left-color: %s;">
                <div class="error-header">
                    <span class="error-icon">%s</span>
                    <span class="error-message">%s</span>
                </div>
                %s
                %s
            </div>',
			esc_attr( $color ),
			esc_html( $icon ),
			esc_html( $message ),
			$suggestions_html,
			$action_html
		);
	}

	/**
	 * Success notification HTML
	 */
	public static function render_success_notification( string $action_type, array $context = array() ): string {
		$message = self::get_success_message( $action_type, $context );

		return sprintf(
			'<div class="mhm-success-notification">
                <div class="success-header">
                    <span class="success-icon">✅</span>
                    <span class="success-message">%s</span>
                </div>
            </div>',
			esc_html( $message )
		);
	}

	/**
	 * Warning notification HTML
	 */
	public static function render_warning_notification( string $warning_type, array $context = array() ): string {
		$message = self::get_warning_message( $warning_type, $context );

		return sprintf(
			'<div class="mhm-warning-notification">
                <div class="warning-header">
                    <span class="warning-icon">⚠️</span>
                    <span class="warning-message">%s</span>
                </div>
            </div>',
			esc_html( $message )
		);
	}

	/**
	 * Info notification HTML
	 */
	public static function render_info_notification( string $info_type, array $context = array() ): string {
		$message = self::get_info_message( $info_type, $context );

		return sprintf(
			'<div class="mhm-info-notification">
                <div class="info-header">
                    <span class="info-icon">ℹ️</span>
                    <span class="info-message">%s</span>
                </div>
            </div>',
			esc_html( $message )
		);
	}

	/**
	 * Get the external documentation URL
	 */
	public static function get_docs_url(): string {
		return 'https://maxhandmade.github.io/mhm-rentiva-docs/';
	}

	/**
	 * Render the documentation button HTML
	 *
	 * @param bool $echo Whether to echo or return the HTML.
	 * @return string The button HTML.
	 */
	public static function render_docs_button( bool $echo = true ): string {
		$html = sprintf(
			'<a href="%s" target="_blank" class="button button-secondary mhm-docs-btn">
                <span class="dashicons dashicons-book-alt" style="margin-top: 4px;"></span>
                %s
            </a>',
			esc_url( self::get_docs_url() ),
			esc_html( __( 'Documentation', 'mhm-rentiva' ) )
		);

		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $html;
	}
}
