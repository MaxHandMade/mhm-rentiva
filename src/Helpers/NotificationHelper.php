<?php

declare(strict_types=1);

namespace MHMRentiva\Helpers;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification Helper Class
 *
 * Handles generic email notifications for the system.
 * Designed to be extensible for future notification types (Reminders, Confirmations, etc.)
 *
 * @since 4.5.4
 */
class NotificationHelper {

	/**
	 * Send Auto Cancellation Email
	 *
	 * @param int $booking_id
	 * @return bool
	 */
	public static function send_auto_cancel_email( int $booking_id ): bool {
		// 1. Check if the feature is enabled
		$enabled = SettingsCore::get( 'mhm_rentiva_send_auto_cancel_email', '0' );
		if ( $enabled !== '1' ) {
			return false;
		}

		// 2. Get Booking & Customer Data
		// 3. Send Email using Mailer (Modern System)
		// This will automatically wrap the content with the layout and use the template registry
		if ( class_exists( '\\MHMRentiva\\Admin\\Emails\\Core\\Mailer' ) ) {
			return \MHMRentiva\Admin\Emails\Core\Mailer::sendBookingEmail( 'auto_cancel', $booking_id, 'customer' );
		}

		// Fallback for safety (should not happen if autoloader works)
		return false;
	}
}
