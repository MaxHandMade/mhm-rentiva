<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Core;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Public/legacy hook names kept stable for compatibility.

use MHMRentiva\Admin\Booking\Helpers\Cache;



final class Hooks {


	public static function register(): void
	{
		// Cache invalidation hooks
		add_action('mhmrentiva_booking_created', array( self::class, 'invalidate_availability_cache' ), 10, 1);
		add_action('mhmrentiva_booking_status_changed', array( self::class, 'invalidate_availability_cache' ), 10, 1);

		// Status automation hooks
		add_action('mhmrentiva_booking_status_changed', array( self::class, 'handle_status_automation' ), 10, 3);
	}

	/**
	 * Clear cache when booking is created or status changes
	 */
	public static function invalidate_availability_cache(int $booking_id): void
	{
		$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		if ($vehicle_id) {
			Cache::invalidateVehicle($vehicle_id);
		}
	}

	/**
	 * Automatic actions on status changes
	 */
	public static function handle_status_automation(int $booking_id, string $old_status, string $new_status): void
	{
		// Send email when booking is confirmed
		if ($new_status === Status::CONFIRMED && $old_status === Status::PENDING) {
			do_action('mhmrentiva_send_confirmation_email', $booking_id);
		}

		// Clear cache when booking is cancelled
		if ($new_status === Status::CANCELLED) {
			self::invalidate_availability_cache($booking_id);
		}

		// Log when booking is completed
		if ($new_status === Status::COMPLETED) {
			do_action('mhmrentiva_booking_completed', $booking_id);
		}

		// Special hook for refund process
		if ($new_status === Status::REFUNDED) {
			do_action('mhmrentiva_booking_refunded', $booking_id);
		}
	}
}
