<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery -- Booking availability checks require controlled SQL and meta/tax queries for date overlap detection.







final class Util
{


	/**
	 * Converts date/time strings to timestamps in site timezone
	 */
	public static function parse_datetimes(string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array|\WP_Error
	{
		try {
			// Simplified timezone handling: treating input as "raw" local time

			// Date format check
			// Use default times if time values are empty
			if (empty($pickup_time)) {
				$pickup_time = apply_filters('mhm_rentiva_default_pickup_time', '10:00');
			}
			if (empty($dropoff_time)) {
				$dropoff_time = apply_filters('mhm_rentiva_default_dropoff_time', '10:00');
			}

			if (
				! preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickup_date) ||
				! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dropoff_date) ||
				! preg_match('/^\d{2}:\d{2}$/', $pickup_time) ||
				! preg_match('/^\d{2}:\d{2}$/', $dropoff_time)
			) {
				throw new \InvalidArgumentException(__('Invalid date/time format.', 'mhm-rentiva'));
			}

			// ⭐ GLOBAL FIX: Use WordPress timezone for interpretation
			$tz = wp_timezone();
			$pickup_dt  = new \DateTime($pickup_date . ' ' . $pickup_time, $tz);
			$dropoff_dt = new \DateTime($dropoff_date . ' ' . $dropoff_time, $tz);

			$start_ts = $pickup_dt->getTimestamp();
			$end_ts   = $dropoff_dt->getTimestamp();

			if ($start_ts === false || $end_ts === false) {
				throw new \InvalidArgumentException(__('Invalid date/time format.', 'mhm-rentiva'));
			}

			// Date validation
			if ($end_ts <= $start_ts) {
				throw new \InvalidArgumentException(__('End date must be after start date.', 'mhm-rentiva'));
			}

			return array(
				'start_ts' => $start_ts,
				'end_ts'   => $end_ts,
			);
		} catch (\Exception $e) {
			return new \WP_Error('invalid_datetime', __('Invalid date/time format.', 'mhm-rentiva'));
		}
	}

	/**
	 * Calculates rental days
	 */
	public static function rental_days(int $start_ts, int $end_ts): int
	{
		// Convert Unix timestamps to DateTime objects
		$start_date = new \DateTime();
		$start_date->setTimestamp($start_ts);

		$end_date = new \DateTime();
		$end_date->setTimestamp($end_ts);

		// Calculate date difference
		$interval = $start_date->diff($end_date);

		// Get number of days (only days, ignore hours)
		$days = $interval->days;

		// Minimum 1 day
		return max(1, $days);
	}

	/**
	 * Validates rental duration against plugin settings
	 *
	 * @param int $start_ts Start timestamp
	 * @param int $end_ts End timestamp
	 * @return bool|\WP_Error True if valid, WP_Error otherwise
	 */
	public static function validate_rental_duration(int $start_ts, int $end_ts)
	{
		$days = self::rental_days($start_ts, $end_ts);

		$min_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1);
		$max_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30);

		if ($days < $min_days) {
			/* translators: %d: number of days */
			return new \WP_Error('min_days', sprintf(__('Minimum rental period is %d days.', 'mhm-rentiva'), $min_days));
		}

		if ($max_days > 0 && $days > $max_days) {
			/* translators: %d: number of days */
			return new \WP_Error('max_days', sprintf(__('Maximum rental period is %d days.', 'mhm-rentiva'), $max_days));
		}

		return true;
	}

	/**
	 * Calculates total price with weekend multiplier
	 */
	public static function total_price(int $vehicle_id, int $days, int $start_ts = 0): float
	{
		$price_per_day = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($vehicle_id);

		if ($price_per_day <= 0) {
			return 0.0;
		}

		// If no start date or short rental, simple calc
		// However, user wants multiplier logic.

		// Apply Base Price Multiplier
		$base_multiplier = (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_base_price', 1.0);
		if ($base_multiplier > 0 && 1.0 != $base_multiplier) {
			$price_per_day = $price_per_day * $base_multiplier;
		}

		$multiplier = (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_weekend_multiplier', 1.2);

		// Safety check for multiplier
		if ($multiplier <= 1.0) {
			return $price_per_day * $days;
		}

		if ($start_ts <= 0) {
			return $price_per_day * $days;
		}

		$total      = 0.0;
		$current_ts = $start_ts;

		// Iterate through each day
		for ($i = 0; $i < $days; $i++) {
			// Check day of week (0 = Sunday, 6 = Saturday) for the current checking day
			// We use getdate or gmdate('w') based on timestamp.
			// Note: rental days are 24h blocks. Logic typically applies to ANY day overlapping weekend?
			// Usually car rental charges "day rate" for that specific day.

			$day_of_week = (int) gmdate('w', $current_ts);

			// Sat (6) or Sun (0)
			if (6 === $day_of_week || 0 === $day_of_week) {
				$total += ($price_per_day * $multiplier);
			} else {
				$total += $price_per_day;
			}

			// Advance 24 hours
			$current_ts += 86400;
		}

		return $total;
	}

	/**
	 * Checks for overlap in the specified date range for the vehicle
	 */
	public static function has_overlap(int $vehicle_id, int $start_ts, int $end_ts): bool
	{
		// ⚡ Optimized: direct SQL query for faster checks
		global $wpdb;

		// ⭐ TIMEZONE SYNC: Ensure MySQL and PHP are using the same timezone offset
		$gmt_offset = (float) get_option('gmt_offset');
		$offset_string = ($gmt_offset >= 0 ? '+' : '-') . sprintf('%02d:%02d', abs((int)$gmt_offset), abs(($gmt_offset - (int)$gmt_offset) * 60));
		$wpdb->query($wpdb->prepare("SET time_zone = %s", $offset_string));

		$current_time_local = current_time('mysql');
		$current_time_gmt = current_time('mysql', 1);

		// ⭐ Get buffer time (default 60 minutes) and convert to seconds
		$buffer_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_buffer_time', '60');
		$buffer_seconds = $buffer_minutes * 60;

		// ⭐ OMNI-QUERY: Support legacy, manual, and new frontend bookings simultaneously
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_vid ON p.ID = pm_vid.post_id AND pm_vid.meta_key = '_mhm_vehicle_id'
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
            -- Date Source (Common between all types)
            INNER JOIN {$wpdb->postmeta} pm_date_s ON p.ID = pm_date_s.post_id AND pm_date_s.meta_key = '_mhm_pickup_date'
            INNER JOIN {$wpdb->postmeta} pm_date_e ON p.ID = pm_date_e.post_id AND pm_date_e.meta_key = '_mhm_dropoff_date'
            -- Time Source (Fallbacks for different sources)
            LEFT JOIN {$wpdb->postmeta} pm_time_s ON p.ID = pm_time_s.post_id AND pm_time_s.meta_key IN ('_mhm_pickup_time', '_mhm_start_time')
            LEFT JOIN {$wpdb->postmeta} pm_time_e ON p.ID = pm_time_e.post_id AND pm_time_e.meta_key IN ('_mhm_dropoff_time', '_mhm_end_time')
            -- Fast TS Source (If exists)
            LEFT JOIN {$wpdb->postmeta} pm_ts_s ON p.ID = pm_ts_s.post_id AND pm_ts_s.meta_key = '_mhm_start_ts'
            LEFT JOIN {$wpdb->postmeta} pm_ts_e ON p.ID = pm_ts_e.post_id AND pm_ts_e.meta_key = '_mhm_end_ts'
            -- Payment Deadline
            LEFT JOIN {$wpdb->postmeta} pm_deadline ON p.ID = pm_deadline.post_id AND pm_deadline.meta_key = '_mhm_payment_deadline'
            
            WHERE p.post_type = 'vehicle_booking'
            AND p.post_status = 'publish'
            AND pm_vid.meta_value = %d
            AND pm_status.meta_value IN ('pending_payment', 'pending', 'confirmed', 'in_progress')
            AND (
                -- Symmetrical Overlap Logic: (StartA < EndB + Buffer) AND (StartB < EndA + Buffer)
                -- This ensures buffer time is respected BOTH ways regardless of order
                COALESCE(CAST(pm_ts_s.meta_value AS UNSIGNED), UNIX_TIMESTAMP(CONCAT(pm_date_s.meta_value, ' ', COALESCE(pm_time_s.meta_value, '10:00')))) < (%d + %d)
                AND
                %d < (COALESCE(CAST(pm_ts_e.meta_value AS UNSIGNED), UNIX_TIMESTAMP(CONCAT(pm_date_e.meta_value, ' ', COALESCE(pm_time_e.meta_value, '10:00')))) + %d)
            )
            AND (
                pm_status.meta_value NOT IN ('pending_payment', 'pending') OR
                pm_deadline.meta_value IS NULL OR
                pm_deadline.meta_value = '' OR
                pm_deadline.meta_value > %s
            )
        ",
				$vehicle_id,
				$end_ts,          // %d (REQ_End)
				$buffer_seconds,   // %d (Buffer for End)
				$start_ts,        // %d (REQ_Start)
				$buffer_seconds,   // %d (Buffer for Start)
				$current_time_gmt // %s (Deadline)
			)
		);

		return (int) $result > 0;
	}

	/**
	 * Atomic overlap check (with database lock)
	 */
	public static function has_overlap_locked(int $vehicle_id, int $start_ts, int $end_ts): bool
	{
		global $wpdb;

		// Lock vehicle's postmeta records
		$wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key LIKE %s
             FOR UPDATE",
				$vehicle_id,
				$wpdb->esc_like('_mhm_') . '%'
			)
		);

		// Conflict check with accurate date interval handling
		// ⭐ Exclude pending bookings with expired payment deadline

		// BUFFER TIME
		// ⭐ TIMEZONE SYNC
		$gmt_offset = (float) get_option('gmt_offset');
		$offset_string = ($gmt_offset >= 0 ? '+' : '-') . sprintf('%02d:%02d', abs((int)$gmt_offset), abs(($gmt_offset - (int)$gmt_offset) * 60));
		$wpdb->query($wpdb->prepare("SET time_zone = %s", $offset_string));

		$current_time_gmt = current_time('mysql', 1);

		$buffer_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_buffer_time', '60');
		$buffer_seconds = $buffer_minutes * 60;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_vid ON p.ID = pm_vid.post_id AND pm_vid.meta_key = '_mhm_vehicle_id'
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
             -- Date Source
             INNER JOIN {$wpdb->postmeta} pm_date_s ON p.ID = pm_date_s.post_id AND pm_date_s.meta_key = '_mhm_pickup_date'
             INNER JOIN {$wpdb->postmeta} pm_date_e ON p.ID = pm_date_e.post_id AND pm_date_e.meta_key = '_mhm_dropoff_date'
             -- Time Source Fallbacks
             LEFT JOIN {$wpdb->postmeta} pm_time_s ON p.ID = pm_time_s.post_id AND pm_time_s.meta_key IN ('_mhm_pickup_time', '_mhm_start_time')
             LEFT JOIN {$wpdb->postmeta} pm_time_e ON p.ID = pm_time_e.post_id AND pm_time_e.meta_key IN ('_mhm_dropoff_time', '_mhm_end_time')
             -- TS Source
             LEFT JOIN {$wpdb->postmeta} pm_ts_s ON p.ID = pm_ts_s.post_id AND pm_ts_s.meta_key = '_mhm_start_ts'
             LEFT JOIN {$wpdb->postmeta} pm_ts_e ON p.ID = pm_ts_e.post_id AND pm_ts_e.meta_key = '_mhm_end_ts'
             -- Payment Deadline
             LEFT JOIN {$wpdb->postmeta} pm_deadline ON p.ID = pm_deadline.post_id AND pm_deadline.meta_key = '_mhm_payment_deadline'
             
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm_vid.meta_value = %d
             AND pm_status.meta_value IN ('pending_payment', 'pending', 'confirmed', 'in_progress')
             AND (
                 -- Symmetrical Overlap Logic
                 COALESCE(CAST(pm_ts_s.meta_value AS UNSIGNED), UNIX_TIMESTAMP(CONCAT(pm_date_s.meta_value, ' ', COALESCE(pm_time_s.meta_value, '10:00')))) < (%d + %d)
                 AND
                 %d < (COALESCE(CAST(pm_ts_e.meta_value AS UNSIGNED), UNIX_TIMESTAMP(CONCAT(pm_date_e.meta_value, ' ', COALESCE(pm_time_e.meta_value, '10:00')))) + %d)
             )
             AND (
                 pm_status.meta_value NOT IN ('pending_payment', 'pending') OR
                 pm_deadline.meta_value IS NULL OR
                 pm_deadline.meta_value = '' OR
                 pm_deadline.meta_value > %s
             )",
				$vehicle_id,
				$end_ts,
				$buffer_seconds,
				$start_ts,
				$buffer_seconds,
				$current_time_gmt
			)
		);
		return $count > 0;
	}

	/**
	 * Checks vehicle availability status
	 */
	public static function is_vehicle_available(int $vehicle_id): bool
	{
		return \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($vehicle_id) === 'active';
	}

	/**
	 * Detailed Availability Check with Alternative Suggestions
	 *
	 * This is the primary entry point for the Booking Form AJAX availability check.
	 * It checks the requested vehicle first, and if unavailable, provides alternatives.
	 *
	 * @return array{ok: bool, message: string, alternatives?: array, code?: string}
	 */
	public static function check_availability_with_alternatives(
		int $vehicle_id,
		string $pickup_date,
		string $pickup_time,
		string $dropoff_date,
		string $dropoff_time
	): array {
		// 1. Check Primary Vehicle Availability
		$primary_result = self::check_availability($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

		if ($primary_result['ok']) {
			return $primary_result;
		}

		// 2. If Not Available, Find Alternatives
		$alternatives = self::get_alternative_vehicles($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time, 3);

		if (! empty($alternatives)) {
			return array(
				'ok'           => false,
				'code'         => 'unavailable_with_alternatives',
				'message'      => __('Selected vehicle is not available, but we can suggest similar vehicles:', 'mhm-rentiva'),
				'alternatives' => $alternatives,
			);
		}

		// If no alternatives found either
		return array(
			'ok'      => false,
			'code'    => 'unavailable',
			'message' => __('Sorry, no vehicle found for selected dates. Please try different dates.', 'mhm-rentiva'),
		);
	}

	/**
	 * Checks availability and returns result (with caching)
	 */
	public static function check_availability(int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array
	{
		// Validate vehicle existence
		if (get_post_type($vehicle_id) !== 'vehicle') {
			return array(
				'ok'      => false,
				'code'    => 'vehicle_not_found',
				'message' => __('Selected vehicle not found. Please select a valid vehicle.', 'mhm-rentiva'),
			);
		}

		// Validate vehicle availability status
		if (! self::is_vehicle_available($vehicle_id)) {
			return array(
				'ok'      => false,
				'code'    => 'vehicle_unavailable',
				'message' => __('This vehicle is currently not available for rental. Please select another vehicle.', 'mhm-rentiva'),
			);
		}

		// Parse date/time
		$datetime_result = self::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

		if (is_wp_error($datetime_result)) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_input',
				'message' => __('Invalid date selection. Please check your pickup and return dates.', 'mhm-rentiva'),
			);
		}

		$start_ts = $datetime_result['start_ts'];
		$end_ts   = $datetime_result['end_ts'];

		// ⭐ Check from cache (but with shorter TTL for critical checks)
		// Cache is useful for performance but can show stale data
		// For critical operations, we'll use has_overlap_locked instead
		$cached_result = \MHMRentiva\Admin\Booking\Helpers\Cache::getAvailability($vehicle_id, $start_ts, $end_ts);
		if ($cached_result !== null) {
			// ⚠️ Cache hit - but verify with real-time check if result is "available"
			// This prevents showing stale "available" data when a booking was just created
			if ($cached_result['ok'] === true) {
				// Double-check with real-time overlap detection (no cache)
				// This ensures we don't show stale "available" data
				if (self::has_overlap($vehicle_id, $start_ts, $end_ts)) {
					// Cache was stale - return unavailable
					return array(
						'ok'      => false,
						'code'    => 'unavailable',
						'message' => __('This vehicle is already booked for the selected dates. Please choose different dates or select another vehicle.', 'mhm-rentiva'),
					);
				}
			}
			return $cached_result;
		}

		// Overlap detection
		if (self::has_overlap($vehicle_id, $start_ts, $end_ts)) {
			$result = array(
				'ok'      => false,
				'code'    => 'unavailable',
				'message' => __('This vehicle is already booked for the selected dates. Please choose different dates or select another vehicle.', 'mhm-rentiva'),
			);
		} else {
			// Calculate days and pricing
			$days          = self::rental_days($start_ts, $end_ts);
			$price_per_day = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($vehicle_id);
			$total_price   = self::total_price($vehicle_id, $days, $start_ts);

			$result = array(
				'ok'            => true,
				'code'          => 'ok',
				'message'       => __('✅ Great! This vehicle is available for your selected dates.', 'mhm-rentiva'),
				'days'          => $days,
				'price_per_day' => $price_per_day,
				'total_price'   => $total_price,
				'start_ts'      => $start_ts,
				'end_ts'        => $end_ts,
			);
		}

		// Save result to cache
		\MHMRentiva\Admin\Booking\Helpers\Cache::setAvailability($vehicle_id, $start_ts, $end_ts, $result);

		return $result;
	}

	/**
	 * Get alternative vehicle suggestions
	 */
	public static function get_alternative_vehicles(int $original_vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time, int $limit = 2): array
	{
		try {
			// Parse date/time
			$datetime_result = self::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

			// WP_Error check
			if (is_wp_error($datetime_result)) {
				return array();
			}

			$start_ts = $datetime_result['start_ts'];
			$end_ts   = $datetime_result['end_ts'];
		} catch (\InvalidArgumentException $e) {
			// Return empty array on date parse error
			return array();
		}

		// Get original vehicle information
		$original_vehicle = get_post($original_vehicle_id);
		if (! $original_vehicle) {
			return array();
		}

		$original_price    = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($original_vehicle_id);
		$original_features = get_post_meta($original_vehicle_id, '_mhm_rentiva_features', true);
		$original_features = is_array($original_features) ? $original_features : array();

		// ⭐ Get original vehicle category and location (if available)
		$original_category = '';
		$original_location = '';

		// Check for vehicle category taxonomy
		$vehicle_categories = wp_get_post_terms($original_vehicle_id, 'vehicle_category', array('fields' => 'ids'));
		if (! empty($vehicle_categories) && ! is_wp_error($vehicle_categories)) {
			$original_category = $vehicle_categories[0];
		}

		// Check for vehicle location (meta or taxonomy)
		$original_location = get_post_meta($original_vehicle_id, '_mhm_rentiva_location', true);
		if (empty($original_location)) {
			// Try taxonomy
			$vehicle_locations = wp_get_post_terms($original_vehicle_id, 'vehicle_location', array('fields' => 'ids'));
			if (! empty($vehicle_locations) && ! is_wp_error($vehicle_locations)) {
				$original_location = $vehicle_locations[0];
			}
		}

		// Find available vehicles

		// ⚡ Optimized: fetch only active vehicles with a sane limit
		$query_args = array(
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => 20, // Limit to at most 20 vehicles
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_mhm_vehicle_status',
					'value'   => 'active',
					'compare' => '=',
				),
				array(
					'key'     => '_mhm_rentiva_price_per_day',
					'value'   => 0,
					'compare' => '>',
				),
			),
		);

		// We will prioritize same-category vehicles via calculate_vehicle_similarity later 
		// instead of hard-filtering here, to avoid returning empty suggestions.

		// ⭐ Filter by location if original vehicle has a location
		if (! empty($original_location)) {
			// If location is a term ID (taxonomy)
			if (is_numeric($original_location)) {
				if (! isset($query_args['tax_query'])) {
					$query_args['tax_query'] = array();
				}
				$query_args['tax_query'][]           = array(
					'taxonomy' => 'vehicle_location',
					'field'    => 'term_id',
					'terms'    => $original_location,
					'operator' => 'IN',
				);
				$query_args['tax_query']['relation'] = 'AND';
			} else {
				// If location is a meta value
				$query_args['meta_query'][] = array(
					'key'     => '_mhm_rentiva_location',
					'value'   => $original_location,
					'compare' => '=',
				);
			}
		}

		$all_vehicles = get_posts($query_args);

		$all_vehicles = array_values(
			array_filter(
				$all_vehicles,
				static function ($vehicle) use ($original_vehicle_id) {
					return isset($vehicle->ID) && (int) $vehicle->ID !== $original_vehicle_id;
				}
			)
		);

		// ⚡ Optimized: meta query already filtered – use directly
		$available_vehicles = $all_vehicles;

		$alternatives = array();
		$days         = self::rental_days($start_ts, $end_ts);

		// ⚡ Optimized: batch meta fetch to avoid N+1 queries
		$vehicle_ids  = array_map(
			function ($v) {
				return $v->ID;
			},
			$available_vehicles
		);
		$vehicle_meta = array();

		if (! empty($vehicle_ids)) {
			global $wpdb;
			$ids_placeholder = implode(',', array_fill(0, count($vehicle_ids), '%d'));
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic placeholder list is built from integer-only vehicle IDs.
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN placeholders are generated from integer-only vehicle IDs.
			$meta_results    = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_key, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE post_id IN ({$ids_placeholder})
                 AND meta_key IN ('_mhm_rentiva_price_per_day', '_mhm_rentiva_features', '_mhm_rentiva_seats', '_mhm_rentiva_transmission', '_mhm_rentiva_fuel_type')",
					...$vehicle_ids
				),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			// phpcs:enable

			// Organize meta
			foreach ($meta_results as $meta) {
				$vehicle_meta[$meta['post_id']][$meta['meta_key']] = $meta['meta_value'];
			}
		}

		foreach ($available_vehicles as $vehicle) {
			// Availability check for this vehicle
			$has_overlap = self::has_overlap($vehicle->ID, $start_ts, $end_ts);

			if (! $has_overlap) {
				// ⚡ Optimized: reuse batch meta result
				$price_per_day = (float) ($vehicle_meta[$vehicle->ID]['_mhm_rentiva_price_per_day'] ?? 0);
				$total_price   = $price_per_day * $days;

				// Extract vehicle features from batch results
				$features_raw = $vehicle_meta[$vehicle->ID]['_mhm_rentiva_features'] ?? '';
				$features     = array();

				if (is_array($features_raw)) {
					$features = $features_raw;
				} elseif (is_string($features_raw) && ! empty($features_raw)) {
					// Unserialize if stored as serialized string
					$unserialized = maybe_unserialize($features_raw);
					$features     = is_array($unserialized) ? $unserialized : array();
				}

				// ⭐ Get vehicle category and location for similarity calculation
				$vehicle_category = '';
				$vehicle_location = '';

				$vehicle_categories = wp_get_post_terms($vehicle->ID, 'vehicle_category', array('fields' => 'ids'));
				if (! empty($vehicle_categories) && ! is_wp_error($vehicle_categories)) {
					$vehicle_category = $vehicle_categories[0];
				}

				$vehicle_location_meta = get_post_meta($vehicle->ID, '_mhm_rentiva_location', true);
				if (! empty($vehicle_location_meta)) {
					$vehicle_location = $vehicle_location_meta;
				} else {
					$vehicle_locations = wp_get_post_terms($vehicle->ID, 'vehicle_location', array('fields' => 'ids'));
					if (! empty($vehicle_locations) && ! is_wp_error($vehicle_locations)) {
						$vehicle_location = $vehicle_locations[0];
					}
				}

				// Calculate similarity score (now includes category and location)
				$similarity_score = self::calculate_vehicle_similarity(
					$original_features,
					$features,
					$original_price,
					$price_per_day,
					$original_category,
					$vehicle_category,
					$original_location,
					$vehicle_location
				);

				$alternatives[] = array(
					'id'               => $vehicle->ID,
					'title'            => $vehicle->post_title,
					'excerpt'          => $vehicle->post_excerpt,
					'price_per_day'    => $price_per_day,
					'total_price'      => $total_price,
					'days'             => $days,
					'features'         => $features,
					'seats'            => (string) ($vehicle_meta[$vehicle->ID]['_mhm_rentiva_seats'] ?? ''),
					'transmission'     => (string) ($vehicle_meta[$vehicle->ID]['_mhm_rentiva_transmission'] ?? ''),
					'fuel_type'        => (string) ($vehicle_meta[$vehicle->ID]['_mhm_rentiva_fuel_type'] ?? ''),
					'similarity_score' => $similarity_score,
					'image'            => get_the_post_thumbnail_url($vehicle->ID, 'medium'),
					'currency_symbol'  => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
				);
			}
		}

		// Sort by similarity score (high to low)
		usort(
			$alternatives,
			function ($a, $b) {
				return $b['similarity_score'] <=> $a['similarity_score'];
			}
		);

		// ⚡ Optimized: apply limit as early as possible
		return array_slice($alternatives, 0, min($limit, 5)); // Maksimum 5 alternatif
	}

	/**
	 * Calculate vehicle similarity score
	 * ⭐ Enhanced with category and location matching
	 */
	private static function calculate_vehicle_similarity(
		array $original_features,
		array $alternative_features,
		float $original_price,
		float $alternative_price,
		$original_category = '',
		$alternative_category = '',
		$original_location = '',
		$alternative_location = ''
	): float {
		$score     = 0;
		$max_score = 100;

		// Price similarity (30% - reduced from 40%)
		$price_diff  = abs($original_price - $alternative_price);
		$price_score = max(0, 30 - ($price_diff / max($original_price, 1) * 30));
		$score      += $price_score;

		// Feature similarity (40% - reduced from 60%)
		if (! empty($original_features) && ! empty($alternative_features)) {
			$common_features = array_intersect($original_features, $alternative_features);
			$feature_score   = (count($common_features) / max(count($original_features), 1)) * 40;
			$score          += $feature_score;
		} else {
			$score += 20; // Default score (reduced from 30)
		}

		// ⭐ Category similarity (20% - NEW)
		if (! empty($original_category) && ! empty($alternative_category)) {
			if ($alternative_category == $original_category) {
				$score += 20; // Same category - full points
			} else {
				$score += 5; // Different category - minimal points
			}
		} elseif (empty($original_category) && empty($alternative_category)) {
			$score += 10; // Both have no category - partial points
		}

		// ⭐ Location similarity (10% - NEW)
		if (! empty($original_location) && ! empty($alternative_location)) {
			if ($alternative_location == $original_location) {
				$score += 10; // Same location - full points
			} else {
				$score += 2; // Different location - minimal points
			}
		} elseif (empty($original_location) && empty($alternative_location)) {
			$score += 5; // Both have no location - partial points
		}

		return min($score, $max_score);
	}


	/**
	 * Atomic availability check (with locking).
	 */
	public static function check_availability_locked(int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array
	{
		return \MHMRentiva\Admin\Booking\Helpers\Locker::withLock(
			$vehicle_id,
			function () use ($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time) {
				// Validate vehicle existence
				if (get_post_type($vehicle_id) !== 'vehicle') {
					return array(
						'ok'      => false,
						'code'    => 'vehicle_not_found',
						'message' => __('Vehicle not found.', 'mhm-rentiva'),
					);
				}

				// Validate vehicle availability status
				if (! self::is_vehicle_available($vehicle_id)) {
					return array(
						'ok'      => false,
						'code'    => 'vehicle_unavailable',
						'message' => __('Vehicle is currently not available for rental.', 'mhm-rentiva'),
					);
				}

				// Parse date/time
				$datetime_result = self::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

				if (is_wp_error($datetime_result)) {
					return array(
						'ok'      => false,
						'code'    => 'invalid_input',
						'message' => $datetime_result->get_error_message(),
					);
				}

				$start_ts = $datetime_result['start_ts'];
				$end_ts   = $datetime_result['end_ts'];

				// Atomic overlap detection
				if (self::has_overlap_locked($vehicle_id, $start_ts, $end_ts)) {
					return array(
						'ok'      => false,
						'code'    => 'unavailable',
						'message' => __('Vehicle is not available in the selected date range.', 'mhm-rentiva'),
					);
				}

				// Calculate rental days and pricing
				$days          = self::rental_days($start_ts, $end_ts);
				$price_per_day = (float) get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true);
				$total_price   = self::total_price($vehicle_id, $days, $start_ts);

				return array(
					'ok'            => true,
					'code'          => 'ok',
					'message'       => __('Vehicle is available on selected dates.', 'mhm-rentiva'),
					'days'          => $days,
					'price_per_day' => $price_per_day,
					'total_price'   => $total_price,
					'start_ts'      => $start_ts,
					'end_ts'        => $end_ts,
				);
			}
		);
	}
}
