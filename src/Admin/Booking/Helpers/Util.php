<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking availability checks require controlled SQL and meta/tax queries for date overlap detection.







final class Util {



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
				$pickup_time = apply_filters('mhmrentiva_default_pickup_time', '10:00');
			}
			if (empty($dropoff_time)) {
				$dropoff_time = apply_filters('mhmrentiva_default_dropoff_time', '10:00');
			}

			if (
				! preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickup_date) ||
				! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dropoff_date) ||
				! preg_match('/^\d{2}:\d{2}$/', $pickup_time) ||
				! preg_match('/^\d{2}:\d{2}$/', $dropoff_time)
			) {
				throw new \InvalidArgumentException(__('Invalid date/time format.', 'mhm-rentiva'));
			}

			// Local wall time in the site timezone -> the real instant.
			$start_ts = SiteTime::instant($pickup_date, $pickup_time);
			$end_ts   = SiteTime::instant($dropoff_date, $dropoff_time);

			if (null === $start_ts || null === $end_ts) {
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
	 * Calculates rental days: whole local calendar days (site timezone), at
	 * least 1. See SiteTime::rental_days().
	 */
	public static function rental_days(int $start_ts, int $end_ts): int
	{
		return SiteTime::rental_days($start_ts, $end_ts);
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

		$min_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_vehicle_min_rental_days', 1);
		$max_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_vehicle_max_rental_days', 30);

		if ($days < $min_days) {
			return new \WP_Error('min_days', sprintf(
				/* translators: %d: number of days */
				_n('Minimum rental period is %d day.', 'Minimum rental period is %d days.', $min_days, 'mhm-rentiva'),
				$min_days
			));
		}

		if ($max_days > 0 && $days > $max_days) {
			return new \WP_Error('max_days', sprintf(
				/* translators: %d: number of days */
				_n('Maximum rental period is %d day.', 'Maximum rental period is %d days.', $max_days, 'mhm-rentiva'),
				$max_days
			));
		}

		return true;
	}

	/**
	 * The rental amount for $days starting at $start_ts.
	 *
	 * Kept for its callers; the computation lives in RentalPricing so the
	 * quote, the charge and every other path agree on one number.
	 */
	public static function total_price(int $vehicle_id, int $days, int $start_ts = 0): float
	{
		return \MHMRentiva\Admin\Booking\Pricing\RentalPricing::quote_for_days($vehicle_id, $days, $start_ts)->rental_amount;
	}

	/**
	 * The rental amount, itemised -- a view of RentalPricing's quote in the
	 * array shape this method has always returned.
	 *
	 * @return array{
	 *     total: float,
	 *     base_price_per_day: float,
	 *     days: int,
	 *     weekend_days: int,
	 *     weekday_days: int,
	 *     weekend_multiplier: float,
	 *     weekend_surcharge: float
	 * }
	 */
	public static function price_breakdown(int $vehicle_id, int $days, int $start_ts = 0): array
	{
		$quote = \MHMRentiva\Admin\Booking\Pricing\RentalPricing::quote_for_days($vehicle_id, $days, $start_ts);

		return array(
			'total'              => $quote->rental_amount,
			'base_price_per_day' => $quote->daily_price,
			'days'               => $days,
			'weekend_days'       => $quote->weekend_days,
			'weekday_days'       => $days - $quote->weekend_days,
			'weekend_multiplier' => $quote->weekend_multiplier,
			'weekend_surcharge'  => $quote->weekend_surcharge,
		);
	}

	/**
	 * Is the vehicle booked for [start_ts, end_ts)? A booking overlaps the
	 * window (buffer included) or the window reaches one of the vehicle's
	 * blocked days. The rule lives in BookingConflicts; this is its customer
	 * reading, kept under the name every caller knows.
	 */
	public static function has_overlap(int $vehicle_id, int $start_ts, int $end_ts): bool
	{
		return BookingConflicts::is_booked($vehicle_id, $start_ts, $end_ts);
	}

	/**
	 * Atomic overlap check (with database lock)
	 *
	 * A query that could not run reads as "no overlap" here, as it always has;
	 * a caller that must not treat that as free uses count_overlaps_locked().
	 *
	 * @param int $exclude_booking_id A booking to leave out of the check: the one
	 *                                being edited, which would otherwise conflict
	 *                                with itself. 0 checks every booking.
	 */
	public static function has_overlap_locked(int $vehicle_id, int $start_ts, int $end_ts, int $exclude_booking_id = 0): bool
	{
		return (int) self::count_overlaps_locked($vehicle_id, $start_ts, $end_ts, $exclude_booking_id) > 0;
	}

	/**
	 * The locked conflict count for a customer booking, or null when the
	 * booking query did not produce one.
	 *
	 * Overlapping bookings, counted under the lock by
	 * BookingConflicts::count_booking_overlaps_locked() (query(), its row
	 * count checked, then the argument-less get_var()), plus one when the
	 * window reaches a blocked day of the vehicle. The blocked days are read
	 * after the vehicle's meta cache is dropped: a list cached before the lock
	 * was taken is not what the lock protects.
	 *
	 * Operator paths do not come here; they count bookings only.
	 */
	public static function count_overlaps_locked(int $vehicle_id, int $start_ts, int $end_ts, int $exclude_booking_id = 0): ?int
	{
		$count = BookingConflicts::count_booking_overlaps_locked($vehicle_id, $start_ts, $end_ts, $exclude_booking_id);
		if (null === $count) {
			return null;
		}

		wp_cache_delete($vehicle_id, 'post_meta');

		return BookingConflicts::hits_blocked_day($vehicle_id, $start_ts, $end_ts) ? $count + 1 : $count;
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

		// A vehicle that is not offered for rental at all is not a date problem: keep that
		// refusal as it is instead of turning it into "not available, try these".
		if ($primary_result['ok'] || 'service_unavailable' === ( $primary_result['code'] ?? '' )) {
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
	 * The one sentence every rental entry point answers a transfer-only vehicle with.
	 */
	public static function rental_service_refusal(): string
	{
		return __('This vehicle is not available for rental. Please select another vehicle.', 'mhm-rentiva');
	}

	/**
	 * The refusal every rental availability check gives before it looks at
	 * dates: no such vehicle, not offered for rental, not active, or a window
	 * that does not parse. Null when none applies; otherwise the refusal,
	 * else the parsed window under 'window'.
	 *
	 * @return array{ok: bool, code?: string, message?: string, window?: array{start_ts: int, end_ts: int}}
	 */
	private static function availability_precheck(int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array
	{
		// Validate vehicle existence
		if (get_post_type($vehicle_id) !== 'mhmrentiva_vehicle') {
			return array(
				'ok'      => false,
				'code'    => 'vehicle_not_found',
				'message' => __('Selected vehicle not found. Please select a valid vehicle.', 'mhm-rentiva'),
			);
		}

		// A transfer-only vehicle is not a rental vehicle. Every rental surface
		// that asks "is it free?" comes through here -- the booking form's
		// availability AJAX, the public REST route and the admin manual
		// booking -- so the refusal lives once, before any cached answer.
		// (The add-on's transfer checkout does not call this method.)
		if (! \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::allows_service($vehicle_id, 'rental')) {
			return array(
				'ok'      => false,
				'code'    => 'service_unavailable',
				'message' => self::rental_service_refusal(),
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

		return array(
			'ok'     => true,
			'window' => $datetime_result,
		);
	}

	/**
	 * The "free" answer: days and price for the window.
	 *
	 * @return array<string, mixed>
	 */
	private static function available_result(int $vehicle_id, int $start_ts, int $end_ts): array
	{
		$days = self::rental_days($start_ts, $end_ts);

		return array(
			'ok'            => true,
			'code'          => 'ok',
			'message'       => __('✅ Great! This vehicle is available for your selected dates.', 'mhm-rentiva'),
			'days'          => $days,
			'price_per_day' => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($vehicle_id),
			'total_price'   => self::total_price($vehicle_id, $days, $start_ts),
			'start_ts'      => $start_ts,
			'end_ts'        => $end_ts,
		);
	}

	/**
	 * The operator's availability check (manual booking): the same refusals
	 * as check_availability(), but bookings only and straight from the
	 * database. A blocked day does not refuse the operator -- the booking is
	 * saved and the days are named in a notice -- and a customer's cached
	 * "booked" for the same window must not refuse them either, so this
	 * neither reads nor writes the availability cache.
	 *
	 * @return array<string, mixed> check_availability()'s shape, plus 'blocked_days' (list of `Y-m-d`) on success.
	 */
	public static function check_availability_for_operator(int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array
	{
		$precheck = self::availability_precheck($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);
		if (! $precheck['ok'] || ! isset($precheck['window'])) {
			return $precheck;
		}

		$start_ts = $precheck['window']['start_ts'];
		$end_ts   = $precheck['window']['end_ts'];

		if (BookingConflicts::bookings_overlap($vehicle_id, $start_ts, $end_ts)) {
			return array(
				'ok'      => false,
				'code'    => 'unavailable',
				'message' => __('This vehicle is already booked for the selected dates. Please choose different dates or select another vehicle.', 'mhm-rentiva'),
			);
		}

		$result                 = self::available_result($vehicle_id, $start_ts, $end_ts);
		$result['blocked_days'] = BookingConflicts::blocked_days_in($vehicle_id, $start_ts, $end_ts);

		return $result;
	}

	/**
	 * Checks availability and returns result (with caching)
	 *
	 * The customer's check: a booking or a blocked day makes the window
	 * unavailable (Util::has_overlap()). A cached "available" is re-verified;
	 * a cached "unavailable" is served as it is, which is why every writer of
	 * bookings or blocked days drops the vehicle's cache.
	 */
	public static function check_availability(int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array
	{
		$precheck = self::availability_precheck($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);
		if (! $precheck['ok'] || ! isset($precheck['window'])) {
			return $precheck;
		}

		$datetime_result = $precheck['window'];

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
			$result = self::available_result($vehicle_id, $start_ts, $end_ts);
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
		$original_features = get_post_meta($original_vehicle_id, '_mhmrentiva_features', true);
		$original_features = is_array($original_features) ? $original_features : array();

		// ⭐ Get original vehicle category and location (if available)
		$original_category = '';
		$original_location = '';

		// Check for vehicle category taxonomy
		$vehicle_categories = wp_get_post_terms($original_vehicle_id, 'mhmrentiva_vehicle_category', array( 'fields' => 'ids' ));
		if (! empty($vehicle_categories) && ! is_wp_error($vehicle_categories)) {
			$original_category = $vehicle_categories[0];
		}

		// Check for vehicle location (meta or taxonomy)
		$original_location = get_post_meta($original_vehicle_id, '_mhmrentiva_location', true);
		if (empty($original_location)) {
			// Try taxonomy
			$vehicle_locations = wp_get_post_terms($original_vehicle_id, 'vehicle_location', array( 'fields' => 'ids' ));
			if (! empty($vehicle_locations) && ! is_wp_error($vehicle_locations)) {
				$original_location = $vehicle_locations[0];
			}
		}

		// Find available vehicles

		// ⚡ Optimized: fetch only active vehicles with a sane limit
		$query_args = array(
			'post_type'      => 'mhmrentiva_vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => 20, // Limit to at most 20 vehicles
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_mhmrentiva_vehicle_status',
					'value'   => 'active',
					'compare' => '=',
				),
				array(
					'key'     => '_mhmrentiva_price_per_day',
					'value'   => 0,
					'compare' => '>',
				),
			),
		);

		// Suggestions are rental suggestions: never offer a transfer-only vehicle.
		$query_args = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::restrict_query_to_service($query_args, 'rental');

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
					'key'     => '_mhmrentiva_location',
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
			// The IN list is generated inline from count($vehicle_ids) so the
			// placeholder count is visibly tied to the argument count, and the
			// IDs go through prepare() as the single replacement array.
			$meta_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_key, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE post_id IN (" . implode(',', array_fill(0, count($vehicle_ids), '%d')) . ")
                 AND meta_key IN ('_mhmrentiva_price_per_day', '_mhmrentiva_features', '_mhmrentiva_seats', '_mhmrentiva_transmission', '_mhmrentiva_fuel_type')",
					$vehicle_ids
				),
				ARRAY_A
			);

			// Organize meta
			foreach ($meta_results as $meta) {
				$vehicle_meta[ $meta['post_id'] ][ $meta['meta_key'] ] = $meta['meta_value'];
			}
		}

		foreach ($available_vehicles as $vehicle) {
			// Availability check for this vehicle
			$has_overlap = self::has_overlap($vehicle->ID, $start_ts, $end_ts);

			if (! $has_overlap) {
				// ⚡ Optimized: reuse batch meta result
				$price_per_day = (float) ( $vehicle_meta[ $vehicle->ID ]['_mhmrentiva_price_per_day'] ?? 0 );
				$total_price   = $price_per_day * $days;

				// Extract vehicle features from batch results
				$features_raw = $vehicle_meta[ $vehicle->ID ]['_mhmrentiva_features'] ?? '';
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

				$vehicle_categories = wp_get_post_terms($vehicle->ID, 'mhmrentiva_vehicle_category', array( 'fields' => 'ids' ));
				if (! empty($vehicle_categories) && ! is_wp_error($vehicle_categories)) {
					$vehicle_category = $vehicle_categories[0];
				}

				$vehicle_location_meta = get_post_meta($vehicle->ID, '_mhmrentiva_location', true);
				if (! empty($vehicle_location_meta)) {
					$vehicle_location = $vehicle_location_meta;
				} else {
					$vehicle_locations = wp_get_post_terms($vehicle->ID, 'vehicle_location', array( 'fields' => 'ids' ));
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
					'seats'            => (string) ( $vehicle_meta[ $vehicle->ID ]['_mhmrentiva_seats'] ?? '' ),
					'transmission'     => (string) ( $vehicle_meta[ $vehicle->ID ]['_mhmrentiva_transmission'] ?? '' ),
					'fuel_type'        => (string) ( $vehicle_meta[ $vehicle->ID ]['_mhmrentiva_fuel_type'] ?? '' ),
					'similarity_score' => $similarity_score,
					'image'            => get_the_post_thumbnail_url($vehicle->ID, 'medium'),
					'currency_symbol'  => \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol(),
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
		$price_score = max(0, 30 - ( $price_diff / max($original_price, 1) * 30 ));
		$score      += $price_score;

		// Feature similarity (40% - reduced from 60%)
		if (! empty($original_features) && ! empty($alternative_features)) {
			$common_features = array_intersect($original_features, $alternative_features);
			$feature_score   = ( count($common_features) / max(count($original_features), 1) ) * 40;
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
	 * Whether a booking's rental period is actually over.
	 *
	 * Both callers used to decide this with `strtotime( $dropoff_date ) < time()`,
	 * which resolves a bare date to midnight: a car due back today at 18:00 read
	 * as "returned" from 00:00 onwards, so settling the balance in the morning
	 * marked the booking completed while the customer still had the vehicle.
	 * Completed feeds availability, dashboards and reporting, so it is not
	 * cosmetic.
	 *
	 * The stored drop-off time is now honoured, interpreted in the site's
	 * timezone. When a booking has no time recorded the end of that day is used
	 * rather than its start -- the conservative direction, since the error it can
	 * make is "not finished yet" instead of "finished early".
	 */
	public static function rental_has_ended(int $booking_id): bool
	{
		$dropoff_date = (string) ( get_post_meta($booking_id, '_mhmrentiva_dropoff_date', true)
			?: get_post_meta($booking_id, '_mhmrentiva_end_date', true) );

		if ('' === $dropoff_date) {
			return false;
		}

		$dropoff_time = (string) ( get_post_meta($booking_id, '_mhmrentiva_dropoff_time', true)
			?: get_post_meta($booking_id, '_mhmrentiva_end_time', true) );

		if ('' === $dropoff_time) {
			$dropoff_time = '23:59';
		}

		try {
			$dropoff = new \DateTimeImmutable($dropoff_date . ' ' . $dropoff_time, wp_timezone());
		} catch (\Exception $e) {
			return false;
		}

		return $dropoff->getTimestamp() < time();
	}

	// check_availability_locked() lived here until 6.0.3: a correctly built locked
	// availability check that nothing ever called, in either edition. It was the
	// only caller of Locker::withLock(), which is how the locking layer came to be
	// entirely dead while the live booking path ran unprotected (finding C-01).
	// The live path now locks for itself in
	// WooCommerceBridge::create_booking_from_data(); this scaffolding is removed
	// rather than left as a second, unexercised way to do the same thing.
}
