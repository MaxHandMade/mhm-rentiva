<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Core;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * Booking URL helper
 *
 * Single place that knows where the booking page lives and how a "book this
 * vehicle" link is built. The base URL is RAW (no HTML entities) so that both
 * PHP (add_query_arg) and JavaScript can append query args to it safely;
 * escaping happens at the output edge only.
 */
final class BookingUrl {

	/**
	 * Raw booking page URL: setting, physical page, filter, or home.
	 *
	 * ShortcodeUrlManager::get_page_url() returns esc_url() output (`&` becomes
	 * `&#038;`), so it is decoded and re-sanitized for the raw form. A rejected
	 * value (e.g. a `javascript:` setting) falls back to the home URL.
	 */
	public static function base_url(): string
	{
		$url = esc_url_raw(
			wp_specialchars_decode(ShortcodeUrlManager::get_page_url('rentiva_booking_form'), ENT_QUOTES)
		);

		return '' !== $url ? $url : home_url('/');
	}

	/**
	 * Booking link for one vehicle, carrying the prefixed wire names the
	 * booking form reads.
	 */
	public static function for_vehicle( int $vehicle_id ): string
	{
		$params = array( AbstractShortcode::query_var('vehicle_id') => $vehicle_id );

		// Resolve pickup location: vehicle → vendor → global default (same hierarchy as QueryHelper).
		$location_id = (int) get_post_meta($vehicle_id, '_mhmrentiva_location_id', true);
		if (! $location_id) {
			$vendor_id   = (int) get_post_field('post_author', $vehicle_id);
			$location_id = (int) get_user_meta($vendor_id, '_mhmrentiva_vendor_location_id', true);
		}
		if (! $location_id) {
			$location_id = (int) SettingsCore::get('mhmrentiva_default_rental_location', 0);
		}
		if ($location_id > 0) {
			$params[ AbstractShortcode::query_var('pickup_location') ] = $location_id;
		}

		return (string) add_query_arg($params, self::base_url());
	}
}
