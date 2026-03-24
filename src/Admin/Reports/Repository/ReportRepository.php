<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Reports\Repository;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for Report Data
 *
 * Centralizes all raw SQL queries used in reports.
 * Modernized to use custom `mhm_bookings` table for high performance.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Reporting repository intentionally executes aggregate SQL for analytics across bookings/meta dimensions.
class ReportRepository {


	public static function get_total_bookings_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s",
				'vehicle_booking',
				'trash'
			)
		);
	}

	/**
	 * Get monthly revenue amount (completed and confirmed only)
	 */
	public static function get_monthly_revenue_amount( string $start_date, string $end_date ): float {
		global $wpdb;
		$meta_price  = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_TOTAL_PRICE;
		$meta_status = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS;

		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm_price.meta_value AS DECIMAL(10,2)))
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s
             AND p.post_status != 'trash'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s
             AND p.post_date < %s",
				$meta_price,
				$meta_status,
				'vehicle_booking',
				$start_date,
				$end_date
			)
		);
	}

	/**
	 * Get active bookings count
	 */
	public static function get_active_bookings_count(): int {
		global $wpdb;
		$meta_status = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s
             AND p.post_status != 'trash'
             AND pm_status.meta_value IN ('confirmed', 'in_progress')",
				$meta_status,
				'vehicle_booking'
			)
		);
	}

	/**
	 * Get total vehicles count (Still using wp_posts for Vehicles)
	 */
	public static function get_total_vehicles_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				'vehicle',
				'publish'
			)
		);
	}

	/**
	 * Get daily revenue data
	 */
	public static function get_daily_revenue_data( string $start_date, string $end_date ): array {
		global $wpdb;
		$meta_price  = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_TOTAL_PRICE;
		$meta_status = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(p.post_date) as date, SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) as revenue
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s
             AND p.post_status != 'trash'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY DATE(p.post_date)
             ORDER BY date",
				$meta_price,
				$meta_status,
				'vehicle_booking',
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			)
		);
	}

	/**
	 * Get payment method distribution
	 */
	public static function get_payment_method_distribution( string $start_date, string $end_date ): array {
		global $wpdb;
		$meta_price = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_TOTAL_PRICE;
		// Payment method usually stored in _mhm_payment_gateway
		$meta_gateway = '_mhm_payment_gateway';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                COALESCE(pm_gw.meta_value, 'unknown') as method, 
                SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) as revenue, 
                COUNT(*) as count
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_gw ON p.ID = pm_gw.post_id AND pm_gw.meta_key = %s
             WHERE p.post_type = %s
             AND p.post_status != 'trash'
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY pm_gw.meta_value
             ORDER BY revenue DESC",
				$meta_price,
				$meta_gateway,
				'vehicle_booking',
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			)
		);
	}

	/**
	 * Get monthly revenue comparison
	 */
	public static function get_monthly_revenue_comparison( string $start_date, string $end_date ): array {
		global $wpdb;
		$meta_price  = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_TOTAL_PRICE;
		$meta_status = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(p.post_date, '%%Y-%%m') as month,
                    SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s
             AND p.post_status != 'trash'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY DATE_FORMAT(p.post_date, '%%Y-%%m')
             ORDER BY month",
				$meta_price,
				$meta_status,
				'vehicle_booking',
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			)
		);
	}

	/**
	 * Get revenue by period
	 */
	public static function get_revenue_by_period( string $start_date, string $end_date, string $period = 'daily' ): array {
		global $wpdb;
		$meta_price  = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_TOTAL_PRICE;
		$meta_status = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS;

		$date_format = match ( $period ) {
			'monthly' => '%Y-%m',
			'weekly' => '%Y-%u',
			'yearly' => '%Y',
			default => '%Y-%m-%d'
		};

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(p.post_date, %s) as period,
                    SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s
             AND p.post_status != 'trash'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY period
             ORDER BY period",
				$date_format,
				$meta_price,
				$meta_status,
				'vehicle_booking',
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			)
		);
	}

	/**
	 * Get top revenue sources (vehicles)
	 */
	public static function get_top_revenue_sources( string $start_date, string $end_date, int $limit = 10 ): array {
		global $wpdb;
		$meta_vid    = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_VEHICLE_ID;
		$meta_price  = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_TOTAL_PRICE;
		$meta_status = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm_vid.meta_value as vehicle_id,
                    SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_vid ON p.ID = pm_vid.post_id AND pm_vid.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s
             AND p.post_status != 'trash'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY pm_vid.meta_value
             ORDER BY revenue DESC
             LIMIT %d",
				$meta_vid,
				$meta_price,
				$meta_status,
				'vehicle_booking',
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$limit
			)
		);
	}

	/**
	 * Get vehicle category performance
	 */
	public static function get_vehicle_category_performance( string $start_date, string $end_date ): array {
		global $wpdb;
		$meta_vid    = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_VEHICLE_ID;
		$meta_status = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                t.name as category_name,
                COUNT(p_booking.ID) as booking_count
            FROM {$wpdb->terms} t
            LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->posts} v ON tr.object_id = v.ID 
                AND v.post_type = 'vehicle' 
                AND v.post_status = 'publish'
            LEFT JOIN {$wpdb->postmeta} pm_vid ON v.ID = pm_vid.meta_value AND pm_vid.meta_key = %s
            LEFT JOIN {$wpdb->posts} p_booking ON pm_vid.post_id = p_booking.ID 
                AND p_booking.post_type = 'vehicle_booking'
                AND p_booking.post_status != 'trash'
                AND p_booking.post_date >= %s AND p_booking.post_date <= %s
            LEFT JOIN {$wpdb->postmeta} pm_status ON p_booking.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE tt.taxonomy = 'vehicle_category'
            AND (pm_status.meta_value IS NULL OR pm_status.meta_value != 'trash')
            GROUP BY t.term_id, t.name
            ORDER BY booking_count DESC",
				$meta_vid,
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$meta_status
			)
		);
	}

	/**
	 * Get customer spending data
	 */
	public static function get_customer_spending_data( string $start_date, string $end_date ): array {
		global $wpdb;
		$meta_email  = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_CUSTOMER_EMAIL;
		$meta_name   = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_CUSTOMER_FIRST_NAME;
		$meta_price  = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_TOTAL_PRICE;
		$meta_status = \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                pm_email.meta_value as customer_email,
                pm_name.meta_value as customer_name,
                COUNT(*) as booking_count,
                SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) as total_spent,
                MAX(p.post_date) as last_booking
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id AND pm_name.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status != 'trash'
            AND pm_status.meta_value IN ('completed', 'confirmed')
            AND pm_email.meta_value IS NOT NULL AND pm_email.meta_value != ''
            AND p.post_date >= %s AND p.post_date <= %s
            GROUP BY pm_email.meta_value, pm_name.meta_value
            ORDER BY total_spent DESC",
				$meta_email,
				$meta_name,
				$meta_price,
				$meta_status,
				'vehicle_booking',
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			)
		);
	}
	/**
	 * Get upcoming operations (Rentals + Transfers)
	 *
	 * @param int $limit Number of records to fetch per type
	 * @return array Mixed array of operations sorted by date
	 */
	/**
	 * Returns upcoming operations as a plain array (backwards compatible).
	 */
	public static function get_upcoming_operations( int $limit = 5 ): array {
		return self::get_upcoming_operations_paginated( 1, $limit, 0 )['items'];
	}

	/**
	 * Returns paginated upcoming operations within a day window.
	 *
	 * @param int $page     1-based page number.
	 * @param int $per_page Items per page.
	 * @param int $days     Day window (0 = no upper limit).
	 * @return array{ items: array, total: int, total_pages: int }
	 */
	public static function get_upcoming_operations_paginated( int $page = 1, int $per_page = 5, int $days = 7 ): array {
		global $wpdb;
		$operations = array();
		$now        = current_time( 'mysql' );
		$upper      = $days > 0 ? gmdate( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) ) : null;

		// Optimize: Suppress errors in case return_date column is missing in older DB versions
		$wpdb->suppress_errors();

		// Resolve locations table name (new name takes priority over legacy name)
		$new_loc_table  = $wpdb->prefix . 'rentiva_transfer_locations';
		$old_loc_table  = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
		$locations_table = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_loc_table ) ) === $new_loc_table )
			? $new_loc_table
			: $old_loc_table;
		$locations_table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $locations_table ) ) === $locations_table );

		$location_select = $locations_table_exists
			? ", loc_origin.name as origin, loc_dest.name as destination, loc_veh.name as vehicle_location"
			: ", NULL as origin, NULL as destination, NULL as vehicle_location";

		$location_joins = $locations_table_exists
			? "LEFT JOIN {$locations_table} loc_origin ON pm_transfer.meta_value = loc_origin.id
               LEFT JOIN {$locations_table} loc_dest ON pm_dest.meta_value = loc_dest.id
               LEFT JOIN {$locations_table} loc_veh ON pm_veh_loc.meta_value = loc_veh.id"
			: '';

		$vehicle_plate_key    = \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LICENSE_PLATE;
		$vehicle_location_key = \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID;

		try {
			// Fetch all upcoming bookings from wp_posts; detect transfers by _mhm_transfer_origin_id
			$sql = "SELECT
                    p.ID as id,
                    pm_vid.meta_value as vehicle_id,
                    p_veh.post_title as vehicle_title,
                    pm_plate.meta_value as vehicle_plate,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(pm_first.meta_value, ''), ' ', COALESCE(pm_last.meta_value, ''))), ''),
                        pm_name.meta_value,
                        pm_name2.meta_value,
                        ''
                    ) as customer_name,
                    COALESCE(pm_phone.meta_value, pm_phone2.meta_value, '') as customer_phone,
                    pm_pickup.meta_value as start_date,
                    pm_time.meta_value as start_time,
                    pm_return.meta_value as end_date,
                    pm_status.meta_value as status,
                    CASE WHEN pm_transfer.meta_value IS NOT NULL THEN 'transfer' ELSE 'rental' END as type
                    {$location_select}
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_vid ON p.ID = pm_vid.post_id AND pm_vid.meta_key = %s
                LEFT JOIN {$wpdb->posts} p_veh ON pm_vid.meta_value = p_veh.ID
                LEFT JOIN {$wpdb->postmeta} pm_plate ON p_veh.ID = pm_plate.post_id AND pm_plate.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_veh_loc ON p_veh.ID = pm_veh_loc.post_id AND pm_veh_loc.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_first ON p.ID = pm_first.post_id AND pm_first.meta_key = '_mhm_customer_first_name'
                LEFT JOIN {$wpdb->postmeta} pm_last ON p.ID = pm_last.post_id AND pm_last.meta_key = '_mhm_customer_last_name'
                LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id AND pm_name.meta_key = '_mhm_customer_name'
                LEFT JOIN {$wpdb->postmeta} pm_name2 ON p.ID = pm_name2.post_id AND pm_name2.meta_key = '_mhm_contact_name'
                LEFT JOIN {$wpdb->postmeta} pm_phone ON p.ID = pm_phone.post_id AND pm_phone.meta_key = '_mhm_customer_phone'
                LEFT JOIN {$wpdb->postmeta} pm_phone2 ON p.ID = pm_phone2.post_id AND pm_phone2.meta_key = '_booking_customer_phone'
                LEFT JOIN {$wpdb->postmeta} pm_pickup ON p.ID = pm_pickup.post_id AND pm_pickup.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = '_mhm_start_time'
                LEFT JOIN {$wpdb->postmeta} pm_return ON p.ID = pm_return.post_id AND pm_return.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_transfer ON p.ID = pm_transfer.post_id AND pm_transfer.meta_key = '_mhm_transfer_origin_id'
                LEFT JOIN {$wpdb->postmeta} pm_dest ON p.ID = pm_dest.post_id AND pm_dest.meta_key = '_mhm_transfer_destination_id'
                {$location_joins}
                WHERE p.post_type = %s
                AND pm_status.meta_value IN ('confirmed', 'pending', 'active')
                AND pm_pickup.meta_value >= %s";

			$prepare_args = array(
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_VEHICLE_ID,
				$vehicle_plate_key,
				$vehicle_location_key,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_PICKUP_DATE,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_RETURN_DATE,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
				'vehicle_booking',
				$now,
			);

			if ( $upper ) {
				$sql           .= ' AND pm_pickup.meta_value <= %s';
				$prepare_args[] = $upper;
			}

			$sql .= ' ORDER BY pm_pickup.meta_value ASC';

			$rentals = $wpdb->get_results(
				$wpdb->prepare(
					$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is built from safe interpolated table/column strings and %s placeholders only.
					$prepare_args
				),
				ARRAY_A
			);

			if ( $rentals ) {
				$operations = array_merge( $operations, $rentals );
			}
		} catch ( \Exception $e ) {
			unset( $e ); // Gracefully skip rentals query failures.
		}

		// 2. Transfers (if table exists)
		$transfer_table = $wpdb->prefix . 'mhm_transfers';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $transfer_table ) ) === $transfer_table ) {
			$transfer_sql  = "SELECT
                    id,
                    customer_name,
                    NULL as customer_phone,
                    NULL as vehicle_title,
                    NULL as vehicle_plate,
                    pickup_date as start_date,
                    origin,
                    destination,
                    status,
                    'transfer' as type
                FROM %i
                WHERE status IN ('confirmed', 'pending')
                AND pickup_date >= %s";
			$transfer_args = array( $transfer_table, $now );

			if ( $upper ) {
				$transfer_sql   .= ' AND pickup_date <= %s';
				$transfer_args[] = $upper;
			}

			$transfer_sql .= ' ORDER BY pickup_date ASC';

			$transfers = $wpdb->get_results(
				$wpdb->prepare( $transfer_sql, $transfer_args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);

			if ( $transfers ) {
				$operations = array_merge( $operations, $transfers );
			}
		}

		$wpdb->suppress_errors( false );

		// Fill missing customer info via WooCommerce order or WordPress user fallback.
		foreach ( $operations as &$op ) {
			if ( ! empty( $op['customer_name'] ) || empty( $op['id'] ) ) {
				continue;
			}

			$booking_id = (int) $op['id'];

			// Try WooCommerce order.
			if ( function_exists( 'wc_get_order' ) ) {
				$order_id = get_post_meta( $booking_id, '_mhm_woocommerce_order_id', true )
					?: get_post_meta( $booking_id, '_mhm_wc_order_id', true )
					?: get_post_meta( $booking_id, '_mhm_order_id', true )
					?: get_post_meta( $booking_id, '_booking_order_id', true );

				if ( $order_id ) {
					$order = wc_get_order( $order_id );
					if ( $order ) {
						$first = $order->get_billing_first_name();
						$last  = $order->get_billing_last_name();
						if ( $first || $last ) {
							$op['customer_name'] = trim( $first . ' ' . $last );
						}
						if ( empty( $op['customer_phone'] ) ) {
							$op['customer_phone'] = $order->get_billing_phone();
						}
						continue;
					}
				}
			}

			// Try WordPress user.
			$user_id = get_post_meta( $booking_id, '_mhm_customer_user_id', true );
			if ( $user_id ) {
				$user = get_userdata( (int) $user_id );
				if ( $user ) {
					$first = $user->first_name;
					$last  = $user->last_name;
					if ( $first || $last ) {
						$op['customer_name'] = trim( $first . ' ' . $last );
					}
					if ( empty( $op['customer_phone'] ) ) {
						$op['customer_phone'] = get_user_meta( (int) $user_id, 'phone', true );
					}
				}
			}
		}
		unset( $op );

		// Sort merged results by date
		usort(
			$operations,
			function ( $a, $b ) {
				return strtotime( $a['start_date'] ) - strtotime( $b['start_date'] );
			}
		);

		$total       = count( $operations );
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
		$offset      = ( max( 1, $page ) - 1 ) * $per_page;

		return array(
			'items'       => array_slice( $operations, $offset, $per_page ),
			'total'       => $total,
			'total_pages' => $total_pages,
		);
	}
}
