<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Util {


	/**
	 * Converts date/time strings to timestamps in site timezone
	 */
	public static function parse_datetimes( string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time ): array|\WP_Error {
		try {
			// Simplified timezone handling: treating input as "raw" local time

			// Date format check
			// Use default times if time values are empty
			if ( empty( $pickup_time ) ) {
				$pickup_time = apply_filters( 'mhm_rentiva_default_pickup_time', '10:00' );
			}
			if ( empty( $dropoff_time ) ) {
				$dropoff_time = apply_filters( 'mhm_rentiva_default_dropoff_time', '10:00' );
			}

			if (
				! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $pickup_date ) ||
				! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dropoff_date ) ||
				! preg_match( '/^\d{2}:\d{2}$/', $pickup_time ) ||
				! preg_match( '/^\d{2}:\d{2}$/', $dropoff_time )
			) {
				throw new \InvalidArgumentException( __( 'Invalid date/time format.', 'mhm-rentiva' ) );
			}

			// Create Timestamps using strtotime()
			// This treats the date string as if it's already in the server's time zone
			// avoiding double-offset issues when comparing with DB strings.
			$pickup_string  = $pickup_date . ' ' . $pickup_time;
			$dropoff_string = $dropoff_date . ' ' . $dropoff_time;

			$start_ts = strtotime( $pickup_string );
			$end_ts   = strtotime( $dropoff_string );

			if ( $start_ts === false || $end_ts === false ) {
				throw new \InvalidArgumentException( __( 'Invalid date/time format.', 'mhm-rentiva' ) );
			}

			// Date validation
			if ( $end_ts <= $start_ts ) {
				throw new \InvalidArgumentException( __( 'End date must be after start date.', 'mhm-rentiva' ) );
			}

			return array(
				'start_ts' => $start_ts,
				'end_ts'   => $end_ts,
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'invalid_datetime', __( 'Invalid date/time format.', 'mhm-rentiva' ) );
		}
	}

	/**
	 * Calculates rental days
	 */
	public static function rental_days( int $start_ts, int $end_ts ): int {
		// Convert Unix timestamps to DateTime objects
		$start_date = new \DateTime();
		$start_date->setTimestamp( $start_ts );

		$end_date = new \DateTime();
		$end_date->setTimestamp( $end_ts );

		// Calculate date difference
		$interval = $start_date->diff( $end_date );

		// Get number of days (only days, ignore hours)
		$days = $interval->days;

		// Minimum 1 day
		return max( 1, $days );
	}

	/**
	 * Calculates total price with weekend multiplier
	 */
	public static function total_price( int $vehicle_id, int $days, int $start_ts = 0 ): float {
		$price_per_day = (float) get_post_meta( $vehicle_id, '_mhm_rentiva_price_per_day', true );

		if ( $price_per_day <= 0 ) {
			return 0.0;
		}

		// If no start date or short rental, simple calc
		// However, user wants multiplier logic.

		// Apply Base Price Multiplier
		$base_multiplier = (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_vehicle_base_price', 1.0 );
		if ( $base_multiplier > 0 && 1.0 != $base_multiplier ) {
			$price_per_day = $price_per_day * $base_multiplier;
		}

		$multiplier = (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_vehicle_weekend_multiplier', 1.2 );

		// Safety check for multiplier
		if ( $multiplier <= 1.0 ) {
			return $price_per_day * $days;
		}

		if ( $start_ts <= 0 ) {
			return $price_per_day * $days;
		}

		$total      = 0.0;
		$current_ts = $start_ts;

		// Iterate through each day
		for ( $i = 0; $i < $days; $i++ ) {
			// Check day of week (0 = Sunday, 6 = Saturday) for the current checking day
			// We use getdate or gmdate('w') based on timestamp.
			// Note: rental days are 24h blocks. Logic typically applies to ANY day overlapping weekend?
			// Usually car rental charges "day rate" for that specific day.

			$day_of_week = (int) gmdate( 'w', $current_ts );

			// Sat (6) or Sun (0)
			if ( 6 === $day_of_week || 0 === $day_of_week ) {
				$total += ( $price_per_day * $multiplier );
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
	public static function has_overlap( int $vehicle_id, int $start_ts, int $end_ts ): bool {
		// ⚡ Optimized: direct SQL query for faster checks
		global $wpdb;

		// ⭐ Get buffer time (default 60 minutes) and convert to seconds
		$buffer_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_booking_buffer_time', '60' );
		$buffer_seconds = $buffer_minutes * 60;

		$current_time = current_time( 'mysql' );

		// ⭐ Exclude pending bookings with expired payment deadline
		// Only count pending bookings that haven't expired their payment deadline
		// TIMEZONE FIX: Use _mhm_start_date and _mhm_end_date with UNIX_TIMESTAMP to compare raw local times
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_mhm_vehicle_id'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_mhm_status'
            INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_mhm_start_date'
            INNER JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_mhm_end_date'
            LEFT JOIN {$wpdb->postmeta} pm5 ON p.ID = pm5.post_id AND pm5.meta_key = '_mhm_payment_deadline'
            WHERE p.post_type = 'vehicle_booking' 
            AND p.post_status = 'publish'
            AND pm1.meta_value = %d
            AND pm2.meta_value IN ('pending', 'confirmed', 'in_progress')
            AND (
                -- Overlap Logic: (StartA < EndB) AND ((EndA + Buffer) > StartB)
                -- A = Existing Booking in DB (Local Time), B = New Request (Raw Timestamp)
                (UNIX_TIMESTAMP(pm3.meta_value) < %d) AND 
                ((UNIX_TIMESTAMP(pm4.meta_value) + %d) > %d)
            )
            AND (
                pm2.meta_value != 'pending' OR 
                pm5.meta_value IS NULL OR 
                pm5.meta_value = '' OR 
                pm5.meta_value > %s
            )
        ",
				$vehicle_id,
				$end_ts,        // New Request END
				$buffer_seconds, // Buffer
				$start_ts,      // New Request START
				$current_time
			)
		);

		return (int) $result > 0;
	}

	/**
	 * Atomic overlap check (with database lock)
	 */
	public static function has_overlap_locked( int $vehicle_id, int $start_ts, int $end_ts ): bool {
		global $wpdb;

		// Lock vehicle's postmeta records
		$wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key LIKE %s
             FOR UPDATE",
				$vehicle_id,
				$wpdb->esc_like( '_mhm_' ) . '%'
			)
		);

		// Conflict check with accurate date interval handling
		// ⭐ Exclude pending bookings with expired payment deadline

		// BUFFER TIME
		$buffer_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_booking_buffer_time', '60' );
		$buffer_seconds = $buffer_minutes * 60;

		$current_time = current_time( 'mysql' );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm1
             INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
             INNER JOIN {$wpdb->postmeta} pm3 ON pm1.post_id = pm3.post_id
             INNER JOIN {$wpdb->postmeta} pm4 ON pm1.post_id = pm4.post_id
             LEFT JOIN {$wpdb->postmeta} pm5 ON pm1.post_id = pm5.post_id AND pm5.meta_key = '_mhm_payment_deadline'
             INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm1.meta_key = '_mhm_vehicle_id' AND pm1.meta_value = %d
             AND pm2.meta_key = '_mhm_status' AND pm2.meta_value IN ('pending', 'confirmed', 'in_progress')
             AND pm3.meta_key = '_mhm_start_date' AND pm4.meta_key = '_mhm_end_date'
             AND (
                 -- Overlap Logic: (StartA < EndB) AND ((EndA + Buffer) > StartB)
                 (UNIX_TIMESTAMP(pm3.meta_value) < %d) AND 
                 ((UNIX_TIMESTAMP(pm4.meta_value) + %d) > %d)
             )
             AND (
                 pm2.meta_value != 'pending' OR 
                 pm5.meta_value IS NULL OR 
                 pm5.meta_value = '' OR 
                 pm5.meta_value > %s
             )",
				$vehicle_id,
				$end_ts,
				$buffer_seconds,
				$start_ts,
				$current_time
			)
		);
		return $count > 0;
	}

	/**
	 * Checks vehicle availability status
	 */
	public static function is_vehicle_available( int $vehicle_id ): bool {
		// STANDART META KEY: _mhm_vehicle_availability
		$available = get_post_meta( $vehicle_id, '_mhm_vehicle_availability', true );
		return $available === 'active';
	}

	/**
	 * Checks availability and returns result (with caching)
	 */
	public static function check_availability( int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time ): array {
		// Validate vehicle existence
		if ( get_post_type( $vehicle_id ) !== 'vehicle' ) {
			return array(
				'ok'      => false,
				'code'    => 'vehicle_not_found',
				'message' => __( 'Selected vehicle not found. Please select a valid vehicle.', 'mhm-rentiva' ),
			);
		}

		// Validate vehicle availability status
		if ( ! self::is_vehicle_available( $vehicle_id ) ) {
			return array(
				'ok'      => false,
				'code'    => 'vehicle_unavailable',
				'message' => __( 'This vehicle is currently not available for rental. Please select another vehicle.', 'mhm-rentiva' ),
			);
		}

		// Parse date/time
		$datetime_result = self::parse_datetimes( $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );

		if ( is_wp_error( $datetime_result ) ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_input',
				'message' => __( 'Invalid date selection. Please check your pickup and return dates.', 'mhm-rentiva' ),
			);
		}

		$start_ts = $datetime_result['start_ts'];
		$end_ts   = $datetime_result['end_ts'];

		// ⭐ Check from cache (but with shorter TTL for critical checks)
		// Cache is useful for performance but can show stale data
		// For critical operations, we'll use has_overlap_locked instead
		$cached_result = \MHMRentiva\Admin\Booking\Helpers\Cache::getAvailability( $vehicle_id, $start_ts, $end_ts );
		if ( $cached_result !== null ) {
			// ⚠️ Cache hit - but verify with real-time check if result is "available"
			// This prevents showing stale "available" data when a booking was just created
			if ( $cached_result['ok'] === true ) {
				// Double-check with real-time overlap detection (no cache)
				// This ensures we don't show stale "available" data
				if ( self::has_overlap( $vehicle_id, $start_ts, $end_ts ) ) {
					// Cache was stale - return unavailable
					return array(
						'ok'      => false,
						'code'    => 'unavailable',
						'message' => __( 'This vehicle is already booked for the selected dates. Please choose different dates or select another vehicle.', 'mhm-rentiva' ),
					);
				}
			}
			return $cached_result;
		}

		// Overlap detection
		if ( self::has_overlap( $vehicle_id, $start_ts, $end_ts ) ) {
			$result = array(
				'ok'      => false,
				'code'    => 'unavailable',
				'message' => __( 'This vehicle is already booked for the selected dates. Please choose different dates or select another vehicle.', 'mhm-rentiva' ),
			);
		} else {
			// Calculate days and pricing
			$days          = self::rental_days( $start_ts, $end_ts );
			$price_per_day = (float) get_post_meta( $vehicle_id, '_mhm_rentiva_price_per_day', true );
			$total_price   = self::total_price( $vehicle_id, $days, $start_ts );

			$result = array(
				'ok'            => true,
				'code'          => 'ok',
				'message'       => __( '✅ Great! This vehicle is available for your selected dates.', 'mhm-rentiva' ),
				'days'          => $days,
				'price_per_day' => $price_per_day,
				'total_price'   => $total_price,
				'start_ts'      => $start_ts,
				'end_ts'        => $end_ts,
			);
		}

		// Save result to cache
		\MHMRentiva\Admin\Booking\Helpers\Cache::setAvailability( $vehicle_id, $start_ts, $end_ts, $result );

		return $result;
	}

	/**
	 * Get alternative vehicle suggestions
	 */
	public static function get_alternative_vehicles( int $original_vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time, int $limit = 2 ): array {
		try {
			// Parse date/time
			$datetime_result = self::parse_datetimes( $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );

			// WP_Error check
			if ( is_wp_error( $datetime_result ) ) {
				return array();
			}

			$start_ts = $datetime_result['start_ts'];
			$end_ts   = $datetime_result['end_ts'];
		} catch ( \InvalidArgumentException $e ) {
			// Return empty array on date parse error
			return array();
		}

		// Get original vehicle information
		$original_vehicle = get_post( $original_vehicle_id );
		if ( ! $original_vehicle ) {
			return array();
		}

		$original_price    = (float) get_post_meta( $original_vehicle_id, '_mhm_rentiva_price_per_day', true );
		$original_features = get_post_meta( $original_vehicle_id, '_mhm_rentiva_features', true );
		$original_features = is_array( $original_features ) ? $original_features : array();

		// ⭐ Get original vehicle category and location (if available)
		$original_category = '';
		$original_location = '';

		// Check for vehicle category taxonomy
		$vehicle_categories = wp_get_post_terms( $original_vehicle_id, 'vehicle_category', array( 'fields' => 'ids' ) );
		if ( ! empty( $vehicle_categories ) && ! is_wp_error( $vehicle_categories ) ) {
			$original_category = $vehicle_categories[0];
		}

		// Check for vehicle location (meta or taxonomy)
		$original_location = get_post_meta( $original_vehicle_id, '_mhm_rentiva_location', true );
		if ( empty( $original_location ) ) {
			// Try taxonomy
			$vehicle_locations = wp_get_post_terms( $original_vehicle_id, 'vehicle_location', array( 'fields' => 'ids' ) );
			if ( ! empty( $vehicle_locations ) && ! is_wp_error( $vehicle_locations ) ) {
				$original_location = $vehicle_locations[0];
			}
		}

		// Find available vehicles

		// ⚡ Optimized: fetch only active vehicles with a sane limit
		// ⭐ Build query args with category/location filtering if available
		$query_args = array(
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => 20, // Limit to at most 20 vehicles
			'post__not_in'   => array( $original_vehicle_id ),
			'meta_query'     => array(
				array(
					'key'     => '_mhm_vehicle_availability',
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

		// ⭐ Filter by category if original vehicle has a category
		if ( ! empty( $original_category ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'vehicle_category',
					'field'    => 'term_id',
					'terms'    => $original_category,
					'operator' => 'IN',
				),
			);
		}

		// ⭐ Filter by location if original vehicle has a location
		if ( ! empty( $original_location ) ) {
			// If location is a term ID (taxonomy)
			if ( is_numeric( $original_location ) ) {
				if ( ! isset( $query_args['tax_query'] ) ) {
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

		$all_vehicles = get_posts( $query_args );

		// ⚡ Optimized: meta query already filtered – use directly
		$available_vehicles = $all_vehicles;

		$alternatives = array();
		$days         = self::rental_days( $start_ts, $end_ts );

		// ⚡ Optimized: batch meta fetch to avoid N+1 queries
		$vehicle_ids  = array_map(
			function ( $v ) {
				return $v->ID;
			},
			$available_vehicles
		);
		$vehicle_meta = array();

		if ( ! empty( $vehicle_ids ) ) {
			global $wpdb;
			$ids_placeholder = implode( ',', array_fill( 0, count( $vehicle_ids ), '%d' ) );
			$meta_results    = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_key, meta_value 
                 FROM {$wpdb->postmeta} 
                 WHERE post_id IN ({$ids_placeholder})
                 AND meta_key IN ('_mhm_rentiva_price_per_day', '_mhm_rentiva_features')",
					$vehicle_ids
				),
				ARRAY_A
			);

			// Organize meta
			foreach ( $meta_results as $meta ) {
				$vehicle_meta[ $meta['post_id'] ][ $meta['meta_key'] ] = $meta['meta_value'];
			}
		}

		foreach ( $available_vehicles as $vehicle ) {
			// Availability check for this vehicle
			$has_overlap = self::has_overlap( $vehicle->ID, $start_ts, $end_ts );

			if ( ! $has_overlap ) {
				// ⚡ Optimized: reuse batch meta result
				$price_per_day = (float) ( $vehicle_meta[ $vehicle->ID ]['_mhm_rentiva_price_per_day'] ?? 0 );
				$total_price   = $price_per_day * $days;

				// Extract vehicle features from batch results
				$features_raw = $vehicle_meta[ $vehicle->ID ]['_mhm_rentiva_features'] ?? '';
				$features     = array();

				if ( is_array( $features_raw ) ) {
					$features = $features_raw;
				} elseif ( is_string( $features_raw ) && ! empty( $features_raw ) ) {
					// Unserialize if stored as serialized string
					$unserialized = maybe_unserialize( $features_raw );
					$features     = is_array( $unserialized ) ? $unserialized : array();
				}

				// ⭐ Get vehicle category and location for similarity calculation
				$vehicle_category = '';
				$vehicle_location = '';

				$vehicle_categories = wp_get_post_terms( $vehicle->ID, 'vehicle_category', array( 'fields' => 'ids' ) );
				if ( ! empty( $vehicle_categories ) && ! is_wp_error( $vehicle_categories ) ) {
					$vehicle_category = $vehicle_categories[0];
				}

				$vehicle_location_meta = get_post_meta( $vehicle->ID, '_mhm_rentiva_location', true );
				if ( ! empty( $vehicle_location_meta ) ) {
					$vehicle_location = $vehicle_location_meta;
				} else {
					$vehicle_locations = wp_get_post_terms( $vehicle->ID, 'vehicle_location', array( 'fields' => 'ids' ) );
					if ( ! empty( $vehicle_locations ) && ! is_wp_error( $vehicle_locations ) ) {
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
					'similarity_score' => $similarity_score,
					'image'            => get_the_post_thumbnail_url( $vehicle->ID, 'medium' ),
					'currency_symbol'  => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
				);
			}
		}

		// Sort by similarity score (high to low)
		usort(
			$alternatives,
			function ( $a, $b ) {
				return $b['similarity_score'] <=> $a['similarity_score'];
			}
		);

		// ⚡ Optimized: apply limit as early as possible
		return array_slice( $alternatives, 0, min( $limit, 5 ) ); // Maksimum 5 alternatif
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
		$price_diff  = abs( $original_price - $alternative_price );
		$price_score = max( 0, 30 - ( $price_diff / max( $original_price, 1 ) * 30 ) );
		$score      += $price_score;

		// Feature similarity (40% - reduced from 60%)
		if ( ! empty( $original_features ) && ! empty( $alternative_features ) ) {
			$common_features = array_intersect( $original_features, $alternative_features );
			$feature_score   = ( count( $common_features ) / max( count( $original_features ), 1 ) ) * 40;
			$score          += $feature_score;
		} else {
			$score += 20; // Default score (reduced from 30)
		}

		// ⭐ Category similarity (20% - NEW)
		if ( ! empty( $original_category ) && ! empty( $alternative_category ) ) {
			if ( $alternative_category == $original_category ) {
				$score += 20; // Same category - full points
			} else {
				$score += 5; // Different category - minimal points
			}
		} elseif ( empty( $original_category ) && empty( $alternative_category ) ) {
			$score += 10; // Both have no category - partial points
		}

		// ⭐ Location similarity (10% - NEW)
		if ( ! empty( $original_location ) && ! empty( $alternative_location ) ) {
			if ( $alternative_location == $original_location ) {
				$score += 10; // Same location - full points
			} else {
				$score += 2; // Different location - minimal points
			}
		} elseif ( empty( $original_location ) && empty( $alternative_location ) ) {
			$score += 5; // Both have no location - partial points
		}

		return min( $score, $max_score );
	}

	/**
	 * Advanced availability check (with alternative suggestions)
	 */
	public static function check_availability_with_alternatives( int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time ): array {
		// Normal availability check
		$availability_result = self::check_availability( $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );

		// If vehicle is not available, add alternative suggestions

		if ( ! $availability_result['ok'] ) {

			$alternatives = self::get_alternative_vehicles( $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );

			if ( ! empty( $alternatives ) ) {
				$availability_result['alternatives'] = $alternatives;
				$availability_result['message']      = __( '❌ Selected vehicle is not available, but we found similar vehicles for you:', 'mhm-rentiva' );
			} else {
				$availability_result['message'] = __( '❌ Sorry, no vehicles are available for the selected dates. Please try different dates.', 'mhm-rentiva' );
			}
		}

		return $availability_result;
	}

	/**
	 * Atomic availability check (with locking).
	 */
	public static function check_availability_locked( int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time ): array {
		return \MHMRentiva\Admin\Booking\Helpers\Locker::withLock(
			$vehicle_id,
			function () use ( $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time ) {
				// Validate vehicle existence
				if ( get_post_type( $vehicle_id ) !== 'vehicle' ) {
					return array(
						'ok'      => false,
						'code'    => 'vehicle_not_found',
						'message' => __( 'Vehicle not found.', 'mhm-rentiva' ),
					);
				}

				// Validate vehicle availability status
				if ( ! self::is_vehicle_available( $vehicle_id ) ) {
					return array(
						'ok'      => false,
						'code'    => 'vehicle_unavailable',
						'message' => __( 'Vehicle is currently not available for rental.', 'mhm-rentiva' ),
					);
				}

				// Parse date/time
				$datetime_result = self::parse_datetimes( $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );

				if ( is_wp_error( $datetime_result ) ) {
					return array(
						'ok'      => false,
						'code'    => 'invalid_input',
						'message' => $datetime_result->get_error_message(),
					);
				}

				$start_ts = $datetime_result['start_ts'];
				$end_ts   = $datetime_result['end_ts'];

				// Atomic overlap detection
				if ( self::has_overlap_locked( $vehicle_id, $start_ts, $end_ts ) ) {
					return array(
						'ok'      => false,
						'code'    => 'unavailable',
						'message' => __( 'Vehicle is not available in the selected date range.', 'mhm-rentiva' ),
					);
				}

				// Calculate rental days and pricing
				$days          = self::rental_days( $start_ts, $end_ts );
				$price_per_day = (float) get_post_meta( $vehicle_id, '_mhm_rentiva_price_per_day', true );
				$total_price   = self::total_price( $vehicle_id, $days, $start_ts );

				return array(
					'ok'            => true,
					'code'          => 'ok',
					'message'       => __( 'Vehicle is available on selected dates.', 'mhm-rentiva' ),
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
