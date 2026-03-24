<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Emails\Core\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReminderScheduler {

	public static function register(): void {
		add_action( 'mhm_rentiva_booking_created', array( self::class, 'schedule_reminder' ) );
		add_action( 'mhm_rentiva_send_booking_reminder', array( self::class, 'send_reminder' ) );
	}

	public static function schedule_reminder( int $booking_id ): void {
		// Toggle off entirely if reminders disabled
		if ( SettingsCore::get( 'mhm_rentiva_booking_send_reminder_emails', '1' ) !== '1' ) {
			return;
		}

		$hours_before = (int) SettingsCore::get( 'mhm_rentiva_booking_reminder_hours_before', 24 );
		if ( $hours_before <= 0 ) {
			return;
		}

		// Try to get pickup date from helper; fallback to meta
		$pickup = '';
		if ( class_exists( 'MHMRentiva\\Admin\\Core\\Utilities\\BookingQueryHelper' ) ) {
			$dateInfo = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingDateInfo( $booking_id );
			$pickup   = (string) ( $dateInfo['pickup_date'] ?? '' );
		}
		if ( $pickup === '' ) {
			$pickup = (string) get_post_meta( $booking_id, '_mhm_pickup_date', true );
		}
		if ( $pickup === '' ) {
			return;
		}

		$pickupTs = strtotime( $pickup );
		if ( $pickupTs === false ) {
			return;
		}

		$when = $pickupTs - ( $hours_before * HOUR_IN_SECONDS );
		if ( $when <= time() ) {
			return; // too late to schedule
		}

		// Schedule single reminder event
		wp_schedule_single_event( $when, 'mhm_rentiva_send_booking_reminder', array( $booking_id ) );
	}

	public static function send_reminder( int $booking_id ): void {
		// Respect toggle at send-time too
		if ( SettingsCore::get( 'mhm_rentiva_booking_send_reminder_emails', '1' ) !== '1' ) {
			return;
		}
		// Send reminder to customer with finalized template key
		Mailer::sendBookingEmail( 'booking_reminder_customer', $booking_id, 'customer' );
	}
}
