<?php

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Customer analytics require bounded aggregate/meta queries for reporting outputs.

namespace MHMRentiva\Admin\Reports\BusinessLogic;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Utilities\Export\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerReport {


	public static function get_data( string $start_date, string $end_date ): array {
		global $wpdb;

		$cache_key = 'mhm_customer_report_' . md5( $start_date . $end_date );
		$data      = get_transient( $cache_key );

		if ( $data === false ) {
			// ✅ OPTIMIZED QUERY - Performance increase with pivot technique
			$customers = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                    COALESCE(pm_email.meta_value, 'unknown') as email,
                    COALESCE(pm_name.meta_value, %s) as name,
                    COUNT(*) as booking_count,
                    SUM(COALESCE(pm_price.meta_value, 0)) as total_spent,
                    AVG(COALESCE(pm_price.meta_value, 0)) as avg_booking_value,
                    MAX(p.post_date) as last_booking_date,
                    MIN(p.post_date) as first_booking_date
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_customer_email'
                 LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id AND pm_name.meta_key = '_mhm_customer_name'
                 LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_mhm_total_price'
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status = 'publish'
                 AND DATE(p.post_date) BETWEEN %s AND %s
                 AND pm_email.meta_value IS NOT NULL
                 AND pm_email.meta_value != ''
                 GROUP BY pm_email.meta_value
                 ORDER BY total_spent DESC
                 LIMIT 100",
					__( 'Unknown', 'mhm-rentiva' ),
					$start_date,
					$end_date
				)
			);

			// Debug: Check customer data
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// Customer segmentation with hardcoded thresholds
			$segments = array(
				'vip'     => array(),      // 5000+ spending
				'regular' => array(),  // 1000+ spending
				'new'     => array(),      // First time
			);

			foreach ( $customers as $customer ) {
				if ( $customer->total_spent >= 5000 ) {
					$segments['vip'][] = $customer;
				} elseif ( $customer->total_spent >= 1000 ) {
					$segments['regular'][] = $customer;
				} else {
					$segments['new'][] = $customer;
				}
			}

			// Regional distribution (simple city-based approach)
			$regional_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                    COUNT(*) as customer_count,
                    SUM(pm_price.meta_value) as total_revenue,
                    AVG(pm_price.meta_value) as avg_spending
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status = 'publish'
                 AND pm_price.meta_key = '_mhm_total_price'
                 AND DATE(p.post_date) BETWEEN %s AND %s
                 GROUP BY DATE(p.post_date)
                 ORDER BY total_revenue DESC
                 LIMIT 10",
					$start_date,
					$end_date
				)
			);

			// Repeat customers
			$repeat_customers = array_filter(
				$customers,
				function ( $customer ) {
					return $customer->booking_count > 1;
				}
			);

			// Customer lifecycle
			$customer_lifecycle = array(
				'new_customers'       => count(
					array_filter(
						$customers,
						function ( $customer ) use ( $start_date, $end_date ) {
							return $customer->first_booking_date >= $start_date && $customer->first_booking_date <= $end_date;
						}
					)
				),
				'returning_customers' => count( $repeat_customers ),
				'total_customers'     => count( $customers ),
			);

			// Calculate average spending
			$total_spent  = array_sum( array_column( $customers, 'total_spent' ) );
			$avg_spending = count( $customers ) > 0 ? $total_spent / count( $customers ) : 0;

			// Customer loyalty (repeat booking rate)
			$loyalty_rate = count( $customers ) > 0 ? ( count( $repeat_customers ) / count( $customers ) ) * 100 : 0;

			// Format data
			foreach ( $customers as &$customer ) {
				$customer->total_spent        = number_format( (float) $customer->total_spent, 2 );
				$customer->avg_booking_value  = number_format( (float) $customer->avg_booking_value, 2 );
				$customer->last_booking_date  = gmdate( 'd.m.Y', (int) strtotime( $customer->last_booking_date ) );
				$customer->first_booking_date = gmdate( 'd.m.Y', (int) strtotime( $customer->first_booking_date ) );
			}

			$data = array(
				'customers'     => array_slice( $customers, 0, 50 ),
				'segments'      => $segments,
				'regional_data' => $regional_data,
				'lifecycle'     => $customer_lifecycle,
				'summary'       => array(
					'total_customers'  => count( $customers ),
					'repeat_customers' => count( $repeat_customers ),
					'loyalty_rate'     => round( $loyalty_rate, 1 ),
					'avg_spending'     => number_format( $avg_spending, 2 ),
					'total_revenue'    => number_format( $total_spent, 2 ),
				),
				'date_range'    => array(
					'start' => $start_date,
					'end'   => $end_date,
					'days'  => ( strtotime( $end_date ) - strtotime( $start_date ) ) / ( 60 * 60 * 24 ) + 1,
				),
			);

			set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
		}

		return $data;
	}

	public static function get_customer_details( string $email, string $start_date, string $end_date ): array {
		global $wpdb;

		// Customer booking history
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_date,
                    pm_vehicle.meta_value as vehicle_id,
                    pm_status.meta_value as status,
                    pm_price.meta_value as total_price,
                    pm_start.meta_value as start_date,
                    pm_end.meta_value as end_date
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id
             INNER JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id
             INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id
             INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm_email.meta_key = '_mhm_contact_email'
             AND pm_email.meta_value = %s
             AND pm_vehicle.meta_key = '_mhm_vehicle_id'
             AND pm_status.meta_key = '_mhm_status'
             AND pm_price.meta_key = '_mhm_total_price'
             AND pm_start.meta_key = '_mhm_start_ts'
             AND pm_end.meta_key = '_mhm_end_ts'
             AND DATE(p.post_date) BETWEEN %s AND %s
             ORDER BY p.post_date DESC",
				$email,
				$start_date,
				$end_date
			)
		);

		// Add vehicle titles
		foreach ( $bookings as &$booking ) {
			$booking->vehicle_title        = get_the_title( $booking->vehicle_id ) ?: __( 'Unknown Vehicle', 'mhm-rentiva' );
			$booking->status_label         = Status::get_label( $booking->status );
			$booking->start_date_formatted = gmdate( 'd.m.Y', (int) $booking->start_date );
			$booking->end_date_formatted   = gmdate( 'd.m.Y', (int) $booking->end_date );
		}

		$total_spent    = array_sum( array_column( $bookings, 'total_price' ) );
		$total_bookings = count( $bookings );

		return array(
			'email'             => $email,
			'total_bookings'    => $total_bookings,
			'total_spent'       => $total_spent,
			'avg_booking_value' => $total_bookings > 0 ? round( $total_spent / $total_bookings, 2 ) : 0,
			'bookings'          => $bookings,
			'last_booking'      => $total_bookings > 0 ? $bookings[0]->post_date : null,
		);
	}

	public static function get_customer_segments( string $start_date, string $end_date ): array {
		$data = self::get_data( $start_date, $end_date );

		// Customer segmentation
		$segments = array(
			'high_value' => array(
				'label'         => __( 'High Value Customers', 'mhm-rentiva' ),
				'criteria'      => __( '5000+ total spending', 'mhm-rentiva' ),
				'customers'     => $data['segments']['vip'],
				'count'         => count( $data['segments']['vip'] ),
				'total_revenue' => array_sum( array_column( $data['segments']['vip'], 'total_spent' ) ),
			),
			'regular'    => array(
				'label'         => __( 'Regular Customers', 'mhm-rentiva' ),
				'criteria'      => __( '1000-4999 total spending', 'mhm-rentiva' ),
				'customers'     => $data['segments']['regular'],
				'count'         => count( $data['segments']['regular'] ),
				'total_revenue' => array_sum( array_column( $data['segments']['regular'], 'total_spent' ) ),
			),
			'new'        => array(
				'label'         => __( 'New Customers', 'mhm-rentiva' ),
				'criteria'      => __( 'First-time booking', 'mhm-rentiva' ),
				'customers'     => $data['segments']['new'],
				'count'         => count( $data['segments']['new'] ),
				'total_revenue' => array_sum( array_column( $data['segments']['new'], 'total_spent' ) ),
			),
		);

		return $segments;
	}

	public static function export_customer_data( string $start_date, string $end_date, string $format = 'csv' ): void {
		$data = self::get_data( $start_date, $end_date );

		$export_data = array();

		// Add header
		$export_data[] = array(
			'Email',
			'Name',
			'Booking_Count',
			'Total_Spending',
			'Avg_Booking_Value',
			'Last_Booking',
			'First_Booking',
		);

		// Add customer data
		foreach ( $data['customers'] as $customer ) {
			$export_data[] = array(
				$customer->email,
				$customer->name,
				$customer->booking_count,
				$customer->total_spent,
				$customer->avg_booking_value,
				$customer->last_booking_date,
				$customer->first_booking_date,
			);
		}

		$filename = sprintf( 'mhm-rentiva-customers-%s-%s', $start_date, $end_date );
		Export::export_data( $export_data, $filename, $format );
	}
}
