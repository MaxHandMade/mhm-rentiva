<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Booking\Core\Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReminderScheduler {

	public static function register(): void {
		add_action( 'mhmrentiva_booking_created', array( self::class, 'schedule_reminder' ) );
		add_action( 'mhmrentiva_send_booking_reminder', array( self::class, 'send_reminder' ) );
	}

	public static function schedule_reminder( int $booking_id ): void {
		// Toggle off entirely if reminders disabled
		if ( SettingsCore::get( 'mhmrentiva_booking_send_reminder_emails', '1' ) !== '1' ) {
			return;
		}

		$hours_before = (int) SettingsCore::get( 'mhmrentiva_booking_reminder_hours_before', 24 );
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
			$pickup = (string) get_post_meta( $booking_id, '_mhmrentiva_pickup_date', true );
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
		wp_schedule_single_event( $when, 'mhmrentiva_send_booking_reminder', array( $booking_id ) );
	}

	public static function send_reminder( int $booking_id ): void {
		if ( ! self::should_send_reminder( $booking_id ) ) {
			return;
		}
		// Send reminder to customer with finalized template key
		Mailer::sendBookingEmail( 'booking_reminder_customer', $booking_id, 'customer' );
	}

	/**
	 * Whether a "starting soon" reminder should still be sent at fire time.
	 *
	 * The reminder is scheduled when the booking is created, so by the time the
	 * cron event fires the booking may have changed state — most notably it could
	 * have been auto-cancelled for non-payment. Skip terminal / inactive statuses
	 * so cancelled, refunded, no-show, completed or draft bookings never receive a
	 * reminder. Respects the global toggle as before.
	 *
	 * @param int $booking_id Booking post ID.
	 * @return bool
	 */
	public static function should_send_reminder( int $booking_id ): bool {
		if ( SettingsCore::get( 'mhmrentiva_booking_send_reminder_emails', '1' ) !== '1' ) {
			return false;
		}

		$skip = array(
			Status::CANCELLED,
			Status::REFUNDED,
			Status::NO_SHOW,
			Status::COMPLETED,
			Status::DRAFT,
		);

		return ! in_array( Status::get( $booking_id ), $skip, true );
	}
}
