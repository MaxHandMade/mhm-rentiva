<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Engine;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Booking\Helpers\Util;

final class TransferSearchEngine
{

	/**
	 * Search for available transfer vehicles
	 *
	 * @param array $criteria Search criteria (origin_id, destination_id, date, time, pax, luggage_big, luggage_small)
	 * @return array Array of available vehicles with pricing info
	 */
	public static function search(array $criteria): array
	{
		global $wpdb;

		// 1. Validate Criteria
		$origin_id      = intval($criteria['origin_id']);
		$destination_id = intval($criteria['destination_id']);
		$date           = sanitize_text_field($criteria['date']); // YYYY-MM-DD
		$time           = sanitize_text_field($criteria['time']); // HH:MM
		$adults         = intval($criteria['adults']);
		$children       = intval($criteria['children']);
		$total_pax      = $adults + $children;

		$luggage_big   = intval($criteria['luggage_big']);
		$luggage_small = intval($criteria['luggage_small']);

		// Calculate Luggage Score
		$luggage_score = ($luggage_small * 1) + ($luggage_big * 2.5);

		// 2. Get Route Info
		// Check for table existence (backward compatibility during migration)
		$table_routes = $wpdb->prefix . 'rentiva_transfer_routes';
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_routes'") != $table_routes) {
			$table_routes = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
		}

		$route = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_routes} 
             WHERE origin_id = %d AND destination_id = %d",
				$origin_id,
				$destination_id
			)
		);

		if (! $route) {
			return array(); // No route found
		}

		// 3. Prepare Time Range
		// Assuming user inputs local time, we need timestamps
		// Using existing parse logic or WP timezone
		$timezone = wp_timezone();
		try {
			$start_datetime = new \DateTimeImmutable("$date $time", $timezone);
			$start_ts       = $start_datetime->getTimestamp();

			// End time = Start + Route Duration + Buffer (Buffer handled by has_overlap, but here we just need booking duration)
			// But wait, has_overlap needs End Timestamp of the NEW booking to check against others.
			// Route duration is in minutes.
			$duration_seconds = $route->duration_min * 60;
			$end_ts           = $start_ts + $duration_seconds;
		} catch (\Exception $e) {
			return array(); // Invalid date/time
		}

		// 4. SQL Filtering (Service Type, Capacity, Luggage)
		// We select posts that MATCH the criteria
		$args = array(
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				// Service Type (OR condition for new/old keys)
				array(
					'relation' => 'OR',
					array(
						'key'     => '_rentiva_vehicle_service_type',
						'value'   => array('transfer', 'both'),
						'compare' => 'IN',
					),
					array(
						'key'     => '_mhm_vehicle_service_type',
						'value'   => array('transfer', 'both'),
						'compare' => 'IN',
					),
				),
				// Max Pax (OR condition)
				array(
					'relation' => 'OR',
					array(
						'key'     => '_rentiva_transfer_max_pax',
						'value'   => $total_pax,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => '_mhm_transfer_max_pax',
						'value'   => $total_pax,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
				// Luggage Score (OR condition)
				array(
					'relation' => 'OR',
					array(
						'key'     => '_rentiva_transfer_max_luggage_score',
						'value'   => $luggage_score,
						'compare' => '>=',
						'type'    => 'DECIMAL',
					),
					array(
						'key'     => '_mhm_transfer_max_luggage_score',
						'value'   => $luggage_score,
						'compare' => '>=',
						'type'    => 'DECIMAL',
					),
				),
			),
		);

		$vehicles           = get_posts($args);
		$available_vehicles = array();

		foreach ($vehicles as $vehicle) {
			// 5. Availability Check (Util::has_overlap)
			// has_overlap checks if THIS vehicle has bookings that overlap with our requested time
			// It internally handles Buffer Time logic (Existing End + Buffer > New Start)
			if (Util::has_overlap($vehicle->ID, $start_ts, $end_ts)) {
				continue; // Skip if overlap exists
			}

			// 5b. Specific Luggage Limit Check (New Logic)
			// Retrieve with fallback
			$vehicle_max_big = get_post_meta($vehicle->ID, '_rentiva_vehicle_max_big_luggage', true);
			if ($vehicle_max_big === '') {
				$vehicle_max_big = get_post_meta($vehicle->ID, '_mhm_vehicle_max_big_luggage', true);
			}

			$vehicle_max_small = get_post_meta($vehicle->ID, '_rentiva_vehicle_max_small_luggage', true);
			if ($vehicle_max_small === '') {
				$vehicle_max_small = get_post_meta($vehicle->ID, '_mhm_vehicle_max_small_luggage', true);
			}

			// If explicit big luggage limit is set, validate
			if ($vehicle_max_big !== '' && $luggage_big > (int) $vehicle_max_big) {
				continue;
			}

			// If explicit small luggage limit is set, validate
			if ($vehicle_max_small !== '' && $luggage_small > (int) $vehicle_max_small) {
				continue;
			}

			// 6. Pricing Calculation
			$price = 0.0;
			if ($route->pricing_method === 'fixed') {
				$price = (float) $route->base_price;
			} else {
				// Calculated: Distance * Price Per KM
				$price = (float) $route->distance_km * (float) $route->base_price;
				// Min Price Check
				if ((float) $route->min_price > 0 && $price < (float) $route->min_price) {
					$price = (float) $route->min_price;
				}
			}

			// Apply Vehicle Multiplier if exists (Proposed in specs, let's implement if meta exists)
			// Spec said: "_rentiva_transfer_price_multiplier"
			$multiplier = get_post_meta($vehicle->ID, '_rentiva_transfer_price_multiplier', true);
			if (! $multiplier) {
				$multiplier = get_post_meta($vehicle->ID, '_mhm_transfer_price_multiplier', true);
			}

			if ($multiplier && is_numeric($multiplier)) {
				$price *= (float) $multiplier;
			}

			// Retrieve capacities for frontend display (with fallback)
			$max_pax = get_post_meta($vehicle->ID, '_rentiva_transfer_max_pax', true);
			if (! $max_pax) {
				$max_pax = get_post_meta($vehicle->ID, '_mhm_transfer_max_pax', true);
			}

			$luggage_cap = get_post_meta($vehicle->ID, '_rentiva_transfer_max_luggage_score', true);
			if (! $luggage_cap) {
				$luggage_cap = get_post_meta($vehicle->ID, '_mhm_transfer_max_luggage_score', true);
			}

			// Prepare Result
			$available_vehicles[] = array(
				'id'                => $vehicle->ID,
				'title'             => $vehicle->post_title,
				'image'             => get_the_post_thumbnail_url($vehicle->ID, 'medium'),
				'price'             => $price,
				'currency'          => function_exists('get_woocommerce_currency_symbol') ? call_user_func('get_woocommerce_currency_symbol') : '$',
				'max_pax'           => $max_pax,
				'pax_capacity'      => $max_pax, // redundancy for frontend
				'luggage_capacity'  => $luggage_cap,
				'max_big_luggage'   => $vehicle_max_big,
				'max_small_luggage' => $vehicle_max_small,
				'route_id'          => $route->id,
				'pricing_method'    => $route->pricing_method,
				'duration'          => $route->duration_min,
				'distance'          => $route->distance_km,
			);
		}

		return $available_vehicles;
	}
}
