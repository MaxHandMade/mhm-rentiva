<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Vehicle Data Helper
 *
 * Centralized helper for retrieving vehicle data, handling legacy meta keys,
 * and ensuring consistency across the plugin.
 */
class VehicleDataHelper {



	/**
	 * Get vehicle price per day
	 *
	 * Checks multiple meta keys for backward compatibility.
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return float Price per day
	 */
	public static function get_price_per_day(int $vehicle_id): float
	{
		// 1. Prioritize Standard Key
		$price = get_post_meta($vehicle_id, MetaKeys::VEHICLE_PRICE_PER_DAY, true);
		if (! empty($price) && is_numeric($price) && floatval($price) > 0) {
			return floatval($price);
		}

		// 2. Legacy fallback (DEV MODE ONLY)
		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			$meta_keys = array(
				'_mhmrentiva_daily_price',
				'_mhmrentiva_price',
				'daily_price',
				'price_per_day',
				'_price_per_day',
				'_mhmrentiva_price_per_day',
				'price',
			);

			foreach ($meta_keys as $key) {
				$price = get_post_meta($vehicle_id, $key, true);
				if (! empty($price) && is_numeric($price) && floatval($price) > 0) {
					return floatval($price);
				}
			}
		}

		return 0.0;
	}

	/**
	 * Get vehicle year
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return string Vehicle year
	 */
	public static function get_year(int $vehicle_id): string
	{
		$val = get_post_meta($vehicle_id, MetaKeys::VEHICLE_YEAR, true);
		if (! empty($val)) {
			return (string) $val;
		}

		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			$keys = array( '_year', 'year' );
			foreach ($keys as $key) {
				$val = get_post_meta($vehicle_id, $key, true);
				if (! empty($val)) {
					return (string) $val;
				}
			}
		}

		return '';
	}

	/**
	 * Get vehicle mileage
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return string Vehicle mileage
	 */
	public static function get_mileage(int $vehicle_id): string
	{
		$keys = array( MetaKeys::VEHICLE_MILEAGE, '_mileage', 'mileage' );
		foreach ($keys as $key) {
			$val = get_post_meta($vehicle_id, $key, true);
			if (! empty($val)) {
				return (string) $val;
			}
		}
		return '';
	}

	/**
	 * Get vehicle seats
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return string Vehicle seats
	 */
	public static function get_seats(int $vehicle_id): string
	{
		$val = get_post_meta($vehicle_id, MetaKeys::VEHICLE_SEATS, true);
		if (! empty($val)) {
			return (string) $val;
		}

		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			$keys = array( '_seats', 'seats' );
			foreach ($keys as $key) {
				$val = get_post_meta($vehicle_id, $key, true);
				if (! empty($val)) {
					return (string) $val;
				}
			}
		}

		return '';
	}

	/**
	 * Get vehicle featured status
	 */
	public static function is_featured(int $vehicle_id): bool
	{
		$val = get_post_meta($vehicle_id, MetaKeys::VEHICLE_FEATURED, true);
		if ($val === '1') {
			return true;
		}

		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			return get_post_meta($vehicle_id, '_mhmrentiva_is_featured', true) === '1';
		}

		return false;
	}

	/**
	 * Is this id a vehicle whose page a logged-out visitor could already read?
	 *
	 * This is the canonical gate for every PUBLIC surface that accepts a vehicle
	 * id -- shortcodes, `wp_ajax_nopriv_*` handlers and capability-free REST
	 * routes. It answers the only question those surfaces are entitled to ask:
	 * "could this visitor have read the vehicle's own page?"
	 *
	 * Three checks, each for a distinct reason:
	 *
	 *   - post type: an id belonging to some other CPT must not be answered as
	 *     though it were a vehicle.
	 *   - post password: core counts a password-protected post as publicly
	 *     viewable, but its content sits behind the password, so its price,
	 *     schedule and reviews must sit behind it too.
	 *   - `is_post_publicly_viewable()`: draft, pending, private, auto-draft and
	 *     trashed vehicles have no public page. Answering for them would both
	 *     disclose unpublished business data and hand an anonymous caller an
	 *     enumeration oracle -- "success for unpublished vehicle ids, failure for
	 *     everything else" is itself the disclosure.
	 *
	 * Deliberately caller-independent: it does NOT widen for an administrator.
	 * Several consumers cache their result in a role-agnostic transient
	 * (`AvailabilityCalendar::get_availability_data()`,
	 * `SearchResults::get_filter_options()`), so a capability-dependent answer
	 * here would let one privileged page-view poison a shared cache with
	 * unpublished data and serve it to the next anonymous visitor.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 * @return bool True when a logged-out visitor could already read this vehicle.
	 */
	public static function is_publicly_readable(int $vehicle_id): bool
	{
		$post = get_post($vehicle_id);

		if (! $post instanceof \WP_Post || \MHMRentiva\Admin\Vehicle\PostType\Vehicle::POST_TYPE !== $post->post_type) {
			return false;
		}

		if ('' !== (string) $post->post_password) {
			return false;
		}

		return is_post_publicly_viewable($post);
	}

	/**
	 * Get vehicle status
	 */
	public static function get_status(int $vehicle_id): string
	{
		$status = get_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, true);
		if (! empty($status)) {
			return (string) $status;
		}

		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			$old = get_post_meta($vehicle_id, '_mhmrentiva_vehicle_availability', true);
			if (empty($old)) {
				$old = get_post_meta($vehicle_id, '_mhmrentiva_vehicle_availability', true);
			}

			$mapping = array(
				'yes'         => 'active',
				'no'          => 'inactive',
				'1'           => 'active',
				'active'      => 'active',
				'0'           => 'inactive',
				'inactive'    => 'inactive',
				'passive'     => 'inactive',
				'maintenance' => 'maintenance',
			);

			if (isset($mapping[ $old ])) {
				return $mapping[ $old ];
			}
		}

		return 'active'; // Default
	}

	/**
	 * Get status label
	 */
	public static function get_status_label(string $status): string
	{
		$labels = array(
			'active'      => __('Active', 'mhm-rentiva'),
			'inactive'    => __('Inactive', 'mhm-rentiva'),
			'maintenance' => __('Maintenance', 'mhm-rentiva'),
		);
		return $labels[ $status ] ?? ucfirst($status);
	}
	/**
	 * Get fuel type label
	 *
	 * @param string $key Fuel type key
	 * @return string Translated label
	 */
	public static function get_fuel_type_label(string $key): string
	{
		$types = array(
			'gasoline' => __('Gasoline', 'mhm-rentiva'),
			'petrol'   => __('Gasoline', 'mhm-rentiva'), // Legacy
			'diesel'   => __('Diesel', 'mhm-rentiva'),
			'lpg'      => __('LPG', 'mhm-rentiva'),
			'electric' => __('Electric', 'mhm-rentiva'),
			'hybrid'   => __('Hybrid', 'mhm-rentiva'),
		);

		return $types[ $key ] ?? $key;
	}

	/**
	 * Get transmission label
	 *
	 * @param string $key Transmission key
	 * @return string Translated label
	 */
	public static function get_transmission_label(string $key): string
	{
		$types = array(
			'manual'    => __('Manual', 'mhm-rentiva'),
			'auto'      => __('Automatic', 'mhm-rentiva'),
			'semi_auto' => __('Semi-Automatic', 'mhm-rentiva'),
		);

		return $types[ $key ] ?? $key;
	}

	/**
	 * Vehicle placeholder image URL, for a vehicle with no featured image.
	 *
	 * THE SINGLE SOURCE. This is the mechanism VehiclesGrid and VehiclesList
	 * already used (as two byte-identical private copies); it now lives here so
	 * the account templates and the booking form's alternative-vehicle cards
	 * can reach it too. Those three templates used to hardcode
	 * `assets/images/no-image.png`, a file this plugin has never shipped --
	 * every vehicle without a featured image rendered a broken <img> on the
	 * customer's bookings list and booking detail screens.
	 *
	 * It resolves in two steps:
	 *   1. a real image, if a site or a future release drops one of the
	 *      recognised filenames into assets/images/ (the list is unchanged
	 *      from the two originals, `no-image.png` included -- so shipping such
	 *      a file later needs no code change);
	 *   2. otherwise an inline SVG data URI -- a flat grey card reading
	 *      "Vehicle Image". No file, no HTTP request, no third-party asset,
	 *      and nothing that can 404.
	 *
	 * @return string Image URL or data URI. Never empty.
	 */
	public static function get_placeholder_image_url(): string
	{
		$possible_files = array(
			'placeholder-vehicle.jpg',
			'placeholder-vehicle.png',
			'placeholder-vehicle.svg',
			'no-image.jpg',
			'no-image.png',
		);

		foreach ($possible_files as $filename) {
			$file_path = MHMRENTIVA_PLUGIN_DIR . 'assets/images/' . $filename;
			if (file_exists($file_path)) {
				return MHMRENTIVA_PLUGIN_URL . 'assets/images/' . $filename;
			}
		}

		return self::PLACEHOLDER_IMAGE_DATA_URI;
	}

	/**
	 * Inline fallback used by get_placeholder_image_url().
	 *
	 * A 300x200 SVG: `<rect fill="#ddd"/>` plus centred `Vehicle Image` text in
	 * #999. Base64 rather than raw so it can sit in an `src` attribute without
	 * any escaping question. Deliberately NOT run through __() -- it is baked
	 * into the encoded payload, and making it translatable would mean building
	 * and encoding the SVG on every call for a string no screen reader reads
	 * (every consumer supplies its own alt text).
	 */
	private const PLACEHOLDER_IMAGE_DATA_URI = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5WZWhpY2xlIEltYWdlPC90ZXh0Pjwvc3ZnPg==';
}
