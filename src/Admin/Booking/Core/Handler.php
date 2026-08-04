<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Core;

if (! defined('ABSPATH')) {
	exit;
}


final class Handler {

	/**
	 * ⭐ Get cancellation policy from settings
	 *
	 * @return string Cancellation policy (e.g., '24_hours', '48_hours', 'no_refund')
	 */
	public static function get_cancellation_policy(): string
	{
		// Get deadline hours from settings (consistent with SettingsCore)
		$deadline_hours = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
			'mhmrentiva_booking_cancellation_deadline_hours',
			24 // Default: 24 hours
		);

		// Map hours to policy string
		$policy = 'no_refund';

		if ($deadline_hours >= 168) {
			$policy = '7_days';
		} elseif ($deadline_hours >= 72) {
			$policy = '72_hours';
		} elseif ($deadline_hours >= 48) {
			$policy = '48_hours';
		} elseif ($deadline_hours > 0) {
			$policy = '24_hours';
		}

		return apply_filters('mhmrentiva_cancellation_policy', $policy, $deadline_hours);
	}

	/**
	 * ⭐ Get payment deadline from settings (consistent with WooCommerceBridge)
	 *
	 * @return string Payment deadline in 'Y-m-d H:i:s' format (UTC/GMT)
	 */
	public static function get_payment_deadline(): string
	{
		// Get payment deadline minutes from settings (default: 30 minutes)
		$deadline_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
			'mhmrentiva_booking_payment_deadline_minutes',
			30
		);

		// Minimum 5 minutes (filterable)
		$min_minutes = apply_filters('mhmrentiva_min_payment_deadline', 5);

		if ($deadline_minutes < $min_minutes) {
			$deadline_minutes = $min_minutes;
		}

		return gmdate( 'Y-m-d H:i:s', time() + ( $deadline_minutes * MINUTE_IN_SECONDS ) );
	}
}
