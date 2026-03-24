<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Reports;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vehicle analytics rely on controlled aggregate/meta queries for admin reporting.



use MHMRentiva\Admin\Utilities\Export\Export;



final class VehicleReport {


	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	public static function get_data( string $start_date, string $end_date ): array {
		// Security: Validate user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		// Security: Sanitize input dates
		$start_date = self::sanitize_text_field_safe( $start_date );
		$end_date   = self::sanitize_text_field_safe( $end_date );

		// Security: Validate date format
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			return array();
		}

		global $wpdb;

		$cache_key = 'mhm_vehicle_report_' . md5( $start_date . $end_date );
		$data      = get_transient( $cache_key );

		if ( $data === false ) {
			// Most rented vehicles
			$top_vehicles = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm_vehicle.meta_value as vehicle_id,
                        COUNT(*) as booking_count,
                        SUM(pm_price.meta_value) as total_revenue,
                        AVG(pm_price.meta_value) as avg_revenue_per_booking
                 FROM {$wpdb->postmeta} pm_vehicle
                 INNER JOIN {$wpdb->postmeta} pm_price ON pm_vehicle.post_id = pm_price.post_id
                 INNER JOIN {$wpdb->posts} p ON pm_vehicle.post_id = p.ID
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status = 'publish'
                 AND pm_vehicle.meta_key = '_mhm_vehicle_id'
                 AND pm_price.meta_key = '_mhm_total_price'
                 AND DATE(p.post_date) BETWEEN %s AND %s
                 GROUP BY pm_vehicle.meta_value
                 ORDER BY total_revenue DESC
                 LIMIT 20",
					$start_date,
					$end_date
				)
			);

			// Vehicle occupancy rates
			$occupancy_rates = self::calculate_occupancy_rates( $start_date, $end_date );

			// Category-based performance
			$category_performance = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.name as category_name,
                        COUNT(DISTINCT p.ID) as vehicle_count,
                        COUNT(DISTINCT b.ID) as booking_count,
                        SUM(pm_price.meta_value) as total_revenue
                 FROM {$wpdb->terms} t
                 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                 LEFT JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id AND pm_vehicle.meta_key = '_mhm_vehicle_id'
                 LEFT JOIN {$wpdb->posts} b ON pm_vehicle.meta_value = b.ID AND b.post_type = 'vehicle_booking'
                 LEFT JOIN {$wpdb->postmeta} pm_price ON b.ID = pm_price.post_id AND pm_price.meta_key = '_mhm_total_price'
                 WHERE p.post_type = 'vehicle'
                 AND p.post_status = 'publish'
                 AND tt.taxonomy = 'vehicle_category'
                 AND (b.ID IS NULL OR DATE(b.post_date) BETWEEN %s AND %s)
                 GROUP BY t.term_id, t.name
                 ORDER BY total_revenue DESC",
					$start_date,
					$end_date
				)
			);

			// Add vehicle titles and categories
			foreach ( $top_vehicles as &$vehicle ) {
				$vehicle->vehicle_title           = get_the_title( $vehicle->vehicle_id ) ?: __( 'Unknown Vehicle', 'mhm-rentiva' );
				$vehicle->avg_revenue_per_booking = round( (float) $vehicle->avg_revenue_per_booking, 2 );

				// Get vehicle category
				$categories          = get_the_terms( $vehicle->vehicle_id, 'vehicle_category' );
				$vehicle->categories = $categories ? array_column( $categories, 'name' ) : array();
			}

			// General statistics
			$total_vehicles = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'vehicle' AND post_status = 'publish'"
			);

			$active_vehicles = count(
				array_filter(
					$occupancy_rates,
					function ( $rate ) {
						return $rate['occupancy_rate'] > 0;
					}
				)
			);

			$avg_occupancy = count( $occupancy_rates ) > 0 ?
				array_sum( array_column( $occupancy_rates, 'occupancy_rate' ) ) / count( $occupancy_rates ) : 0;

			$data = array(
				'top_vehicles'         => array_slice( $top_vehicles, 0, 10 ),
				'occupancy_rates'      => array_slice( $occupancy_rates, 0, 10 ),
				'category_performance' => $category_performance,
				'summary'              => array(
					'total_vehicles'     => $total_vehicles,
					'active_vehicles'    => $active_vehicles,
					'avg_occupancy_rate' => round( (float) $avg_occupancy, 1 ),
					'total_revenue'      => array_sum( array_column( $top_vehicles, 'total_revenue' ) ),
				),
				'date_range'           => array(
					'start' => $start_date,
					'end'   => $end_date,
					'days'  => ( strtotime( $end_date ) - strtotime( $start_date ) ) / ( 60 * 60 * 24 ) + 1,
				),
			);

			set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
		}

		return $data;
	}

	private static function calculate_occupancy_rates( string $start_date, string $end_date ): array {
		global $wpdb;

		$vehicles = get_posts(
			array(
				'post_type'      => 'vehicle',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$occupancy_rates = array();

		foreach ( $vehicles as $vehicle ) {
			$total_days     = self::calculate_date_range_days( $start_date, $end_date );
			$booked_days    = self::calculate_vehicle_booked_days( $vehicle->ID, $start_date, $end_date );
			$occupancy_rate = $total_days > 0 ? ( $booked_days / $total_days ) * 100 : 0;

			// Include only rented vehicles
			if ( $booked_days > 0 ) {
				$occupancy_rates[] = array(
					'vehicle_id'     => $vehicle->ID,
					'vehicle_title'  => $vehicle->post_title,
					'occupancy_rate' => round( (float) $occupancy_rate, 1 ),
					'booked_days'    => $booked_days,
					'total_days'     => $total_days,
				);
			}
		}

		// Sort by occupancy rate
		usort(
			$occupancy_rates,
			function ( $a, $b ) {
				return $b['occupancy_rate'] <=> $a['occupancy_rate'];
			}
		);

		return $occupancy_rates;
	}

	private static function calculate_date_range_days( string $start, string $end ): int {
		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );
		return (int) ceil( ( $end_ts - $start_ts ) / 86400 );
	}

	private static function calculate_vehicle_booked_days( int $vehicle_id, string $start_date, string $end_date ): int {
		global $wpdb;

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm_start.meta_value as start_ts, pm_end.meta_value as end_ts
             FROM {$wpdb->postmeta} pm_vehicle
             INNER JOIN {$wpdb->postmeta} pm_start ON pm_vehicle.post_id = pm_start.post_id
             INNER JOIN {$wpdb->postmeta} pm_end ON pm_vehicle.post_id = pm_end.post_id
             INNER JOIN {$wpdb->posts} p ON pm_vehicle.post_id = p.ID
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm_vehicle.meta_key = '_mhm_vehicle_id'
             AND pm_vehicle.meta_value = %d
             AND pm_start.meta_key = '_mhm_start_ts'
             AND pm_end.meta_key = '_mhm_end_ts'
             AND pm_start.meta_value < %d
             AND pm_end.meta_value > %d",
				$vehicle_id,
				strtotime( $end_date ),
				strtotime( $start_date )
			)
		);

		$booked_days = 0;
		$range_start = strtotime( $start_date );
		$range_end   = strtotime( $end_date );

		foreach ( $bookings as $booking ) {
			$booking_start = max( $range_start, (int) $booking->start_ts );
			$booking_end   = min( $range_end, (int) $booking->end_ts );

			if ( $booking_end > $booking_start ) {
				$days         = ceil( ( $booking_end - $booking_start ) / 86400 );
				$booked_days += $days;
			}
		}

		return (int) $booked_days;
	}

	public static function get_vehicle_utilization( int $vehicle_id, string $start_date, string $end_date ): array {
		global $wpdb;

		// Vehicle reservation history
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_date, pm_status.meta_value as status,
                    pm_start.meta_value as start_ts, pm_end.meta_value as end_ts,
                    pm_price.meta_value as revenue
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id
             INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm_vehicle.meta_key = '_mhm_vehicle_id'
             AND pm_vehicle.meta_value = %d
             AND pm_status.meta_key = '_mhm_status'
             AND pm_start.meta_key = '_mhm_start_ts'
             AND pm_end.meta_key = '_mhm_end_ts'
             AND pm_price.meta_key = '_mhm_total_price'
             AND DATE(p.post_date) BETWEEN %s AND %s
             ORDER BY p.post_date DESC",
				$vehicle_id,
				$start_date,
				$end_date
			)
		);

		$total_revenue       = array_sum( array_column( $bookings, 'revenue' ) );
		$total_bookings      = count( $bookings );
		$successful_bookings = count(
			array_filter(
				$bookings,
				function ( $booking ) {
					return in_array( $booking->status, array( 'completed', 'confirmed' ) );
				}
			)
		);

		$success_rate = $total_bookings > 0 ? round( (float) ( ( $successful_bookings / $total_bookings ) * 100 ), 1 ) : 0;

		return array(
			'vehicle_id'              => $vehicle_id,
			'vehicle_title'           => get_the_title( $vehicle_id ),
			'total_bookings'          => $total_bookings,
			'successful_bookings'     => $successful_bookings,
			'success_rate'            => $success_rate,
			'total_revenue'           => $total_revenue,
			'avg_revenue_per_booking' => $total_bookings > 0 ? round( (float) ( $total_revenue / $total_bookings ), 2 ) : 0,
			'bookings'                => $bookings,
		);
	}

	public static function get_vehicle_performance_trends( int $vehicle_id, int $months = 6 ): array {
		global $wpdb;

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( "-{$months} months" ) );

		$monthly_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(p.post_date, '%%Y-%%m') as month,
                    COUNT(*) as bookings,
                    SUM(pm_price.meta_value) as revenue
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm_vehicle.meta_key = '_mhm_vehicle_id'
             AND pm_vehicle.meta_value = %d
             AND pm_price.meta_key = '_mhm_total_price'
             AND DATE(p.post_date) BETWEEN %s AND %s
             GROUP BY DATE_FORMAT(p.post_date, '%%Y-%%m')
             ORDER BY month",
				$vehicle_id,
				$start_date,
				$end_date
			)
		);

		return array(
			'vehicle_id'          => $vehicle_id,
			'vehicle_title'       => get_the_title( $vehicle_id ),
			'monthly_data'        => $monthly_data,
			'total_revenue'       => array_sum( array_column( $monthly_data, 'revenue' ) ),
			'total_bookings'      => array_sum( array_column( $monthly_data, 'bookings' ) ),
			'avg_monthly_revenue' => count( $monthly_data ) > 0 ? round( (float) ( array_sum( array_column( $monthly_data, 'revenue' ) ) / count( $monthly_data ) ), 2 ) : 0,
		);
	}

	public static function export_vehicle_data( string $start_date, string $end_date, string $format = 'csv' ): void {
		// Security: Validate user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mhm-rentiva' ) );
		}

		// Security: Sanitize input dates
		$start_date = self::sanitize_text_field_safe( $start_date );
		$end_date   = self::sanitize_text_field_safe( $end_date );

		// Security: Validate date format
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			wp_die( esc_html__( 'Invalid date format.', 'mhm-rentiva' ) );
		}

		$data = self::get_data( $start_date, $end_date );

		$export_data = array();

		// Add header
		$export_data[] = array(
			'Vehicle_ID',
			'Vehicle_Name',
			'Booking_Count',
			'Total_Revenue',
			'Average_Revenue',
			'Occupancy_Rate',
		);

		// Add vehicle data
		foreach ( $data['top_vehicles'] as $vehicle ) {
			$occupancy = array_filter(
				$data['occupancy_rates'],
				function ( $rate ) use ( $vehicle ) {
					return $rate['vehicle_id'] == $vehicle->vehicle_id;
				}
			);

			$occupancy_rate = ! empty( $occupancy ) ? reset( $occupancy )['occupancy_rate'] : 0;

			$export_data[] = array(
				$vehicle->vehicle_id,
				$vehicle->vehicle_title,
				$vehicle->booking_count,
				number_format( $vehicle->total_revenue, 2, ',', '.' ),
				number_format( $vehicle->avg_revenue_per_booking, 2, ',', '.' ),
				$occupancy_rate . '%',
			);
		}

		$filename = sprintf( 'mhm-rentiva-vehicles-%1$s-%2$s', $start_date, $end_date );
		Export::export_data( $export_data, $filename, $format );
	}
}
