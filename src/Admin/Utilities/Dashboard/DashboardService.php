<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Dashboard;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Core\Services\TrendMath;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard Service Class
 *
 * Handles all business logic and database queries for the MHM Rentiva Dashboard.
 * Separates data fetching from presentation logic.
 *
 * @since 4.6.3
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard analytics require controlled aggregate SQL across core/meta tables.
final class DashboardService {



	/**
	 * Get all dashboard metrics in a single structured array.
	 */
	public static function get_comprehensive_stats(): array {
		return array(
			'metrics'          => self::get_dashboard_metrics(),
			'recent_bookings'  => self::get_recent_bookings(),
			'booking_stats'    => self::get_booking_stats(),
			'vehicle_stats'    => self::get_vehicle_stats(),
			'revenue_data'     => self::get_revenue_data(),
			'customer_stats'   => self::get_customer_detail_stats(),
			'message_stats'    => self::get_message_stats(),
			'recent_messages'  => self::get_recent_messages(),
			'notifications'    => self::get_system_notifications(),
			'deposit_stats'    => self::get_deposit_stats(),
			'pending_payments' => self::get_pending_payments(),
		);
	}

	/**
	 * Get main dashboard metrics - No cache (Fresh data every time)
	 */
	public static function get_dashboard_metrics(): array {
		global $wpdb;

		$current_month_start = gmdate( 'Y-m-01 00:00:00' );
		$current_month_end   = gmdate( 'Y-m-t 23:59:59' );

		// Total bookings - EXCLUDING TRASH
		$total_bookings = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'private', 'pending') AND post_status != 'trash'",
				'vehicle_booking'
			)
		);

		// This month bookings - EXCLUDING TRASH
		$bookings_this_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = %s AND post_status IN ('publish', 'private', 'pending') AND post_status != 'trash'
             AND post_date >= %s AND post_date <= %s",
				'vehicle_booking',
				$current_month_start,
				$current_month_end
			)
		);

		// Total revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
		$total_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm.meta_key = %s
             AND pm_status.meta_key = %s
             AND pm_status.meta_value IN (%s, %s)",
				'vehicle_booking',
				'_mhm_total_price',
				'_mhm_status',
				'completed',
				'confirmed'
			)
		);

		// This month revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
		$monthly_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm.meta_key = %s
             AND pm_status.meta_key = %s
             AND pm_status.meta_value IN (%s, %s)",
				'vehicle_booking',
				$current_month_start,
				$current_month_end,
				'_mhm_total_price',
				'_mhm_status',
				'completed',
				'confirmed'
			)
		);

		// Total vehicles - EXCLUDING TRASH
		$total_vehicles = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'private', 'pending') AND post_status != 'trash'",
				'vehicle'
			)
		);

		// Available vehicles - EXCLUDING TRASH - Using VEHICLE_STATUS
		$available_vehicles = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND (pm_status.meta_value = 'active' OR pm_status.meta_value IS NULL OR pm_status.meta_value = '')",
				\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS,
				'vehicle'
			)
		);

		// Customer statistics - From booking data (THIS MONTH ONLY)
		$customer_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                COUNT(DISTINCT pm_email.meta_value) as total_customers,
                COUNT(DISTINCT CASE WHEN p.post_date >= %s THEN pm_email.meta_value END) as new_customers
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_customer_email'
             WHERE p.post_type = 'vehicle_booking' 
             AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm_email.meta_value != '' AND pm_email.meta_value IS NOT NULL",
				$current_month_start,
				$current_month_start,
				$current_month_end
			)
		);

		$total_customers_this_month = (int) ( $customer_stats->total_customers ?? 0 );
		$new_customers_this_month   = (int) ( $customer_stats->new_customers ?? 0 );

		// Total customers - ALL TIME
		$total_customers_all_time = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm_email.meta_value) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = %s
             WHERE p.post_type = %s 
             AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm_email.meta_value != '' AND pm_email.meta_value IS NOT NULL",
				'_mhm_customer_email',
				'vehicle_booking'
			)
		);

		return array(
			'total_bookings'             => $total_bookings,
			'bookings_this_month'        => $bookings_this_month,
			'total_revenue'              => $total_revenue,
			'monthly_revenue'            => $monthly_revenue,
			'total_vehicles'             => $total_vehicles,
			'available_vehicles'         => $available_vehicles,
			'total_customers_this_month' => $total_customers_this_month,
			'total_customers_all_time'   => $total_customers_all_time,
			'new_customers_this_month'   => $new_customers_this_month,
		);
	}

	/**
	 * Period-over-period deltas for the dashboard stat cards.
	 *
	 * @return array<string,array{format:string,value:int,direction:string}>
	 */
	public static function get_metric_deltas(): array {
		$current_start  = gmdate( 'Y-m-01 00:00:00' );
		$current_end    = gmdate( 'Y-m-t 23:59:59' );
		$previous_start = gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of last month' ) );
		$previous_end   = gmdate( 'Y-m-t 23:59:59', strtotime( 'last day of last month' ) );

		return array(
			'bookings'  => self::shape_delta(
				self::count_bookings_between( $current_start, $current_end ),
				self::count_bookings_between( $previous_start, $previous_end )
			),
			'revenue'   => self::shape_delta(
				(int) round( self::sum_revenue_between( $current_start, $current_end ) ),
				(int) round( self::sum_revenue_between( $previous_start, $previous_end ) )
			),
			'customers' => self::shape_delta(
				self::count_new_customers_between( $current_start, $current_end ),
				self::count_new_customers_between( $previous_start, $previous_end )
			),
		);
	}

	/**
	 * Turn a current/previous pair into a render-ready delta, applying the
	 * mixed-format rule: pct when previous>0, abs when only current>0, else neutral.
	 *
	 * @return array{format:string,value:int,direction:string}
	 */
	private static function shape_delta( int $current, int $previous ): array {
		if ( $previous > 0 ) {
			$t   = TrendMath::calculate_trend_from_totals( $current, $previous );
			$dir = 'neutral' === $t['direction'] ? 'none' : $t['direction'];
			return array(
				'format'    => 'pct',
				'value'     => $t['trend'],
				'direction' => $dir,
			);
		}
		if ( $current > 0 ) {
			return array(
				'format'    => 'abs',
				'value'     => $current,
				'direction' => 'up',
			);
		}
		return array(
			'format'    => 'neutral',
			'value'     => 0,
			'direction' => 'none',
		);
	}

	private static function count_bookings_between( string $start, string $end ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'vehicle_booking'
                 AND post_status IN ('publish','private','pending') AND post_status != 'trash'
                 AND post_date >= %s AND post_date <= %s",
				$start,
				$end
			)
		);
	}

	private static function sum_revenue_between( string $start, string $end ): float {
		global $wpdb;
		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status IN ('publish','private','pending') AND p.post_status != 'trash'
                 AND p.post_date >= %s AND p.post_date <= %s
                 AND pm.meta_key = '_mhm_total_price'
                 AND pm_status.meta_key = '_mhm_status'
                 AND pm_status.meta_value IN ('completed','confirmed')",
				$start,
				$end
			)
		);
	}

	private static function count_new_customers_between( string $start, string $end ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm_email.meta_value)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_customer_email'
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status IN ('publish','private','pending') AND p.post_status != 'trash'
                 AND p.post_date >= %s AND p.post_date <= %s
                 AND pm_email.meta_value != '' AND pm_email.meta_value IS NOT NULL",
				$start,
				$end
			)
		);
	}

	/**
	 * Booking count grouped by status, for the dashboard StatusBreakdown widget.
	 *
	 * @return array<int,array{status:string,label:string,count:int,dot:string}>
	 */
	public static function get_status_breakdown(): array {
		global $wpdb;

		// LEFT JOIN + COALESCE: bookings with no/empty `_mhm_status` meta must
		// still be counted (bucketed as 'pending', mirroring
		// \MHMRentiva\Admin\Booking\Core\Status::get()'s fallback) so every
		// non-trashed booking lands in exactly one bucket and the counts sum
		// to total_bookings. An INNER JOIN + `!= ''` filter (the old query)
		// silently drops status-less bookings from every bucket.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(NULLIF(pm.meta_value, ''), 'pending') AS status, COUNT(*) AS cnt
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mhm_status'
                 WHERE p.post_type = %s
                 AND p.post_status IN ('publish','private','pending') AND p.post_status != 'trash'
                 GROUP BY COALESCE(NULLIF(pm.meta_value, ''), 'pending')
                 ORDER BY cnt DESC",
				'vehicle_booking'
			),
			ARRAY_A
		) ?: array();

		// This palette is intentionally the WordPress-admin-native color set from
		// the dashboard redesign mockup (StatusBreakdown widget dots), and
		// deliberately diverges from \MHMRentiva\Admin\Booking\Core\Status::get_color()
		// (the booking-list/badge palette). Do not "fix" this to match Status::get_color() --
		// the two palettes serve different UI contexts by design.
		$dots = array(
			'pending'         => '#dba617',
			'pending_payment' => '#dba617',
			'confirmed'       => '#2271b1',
			'in_progress'     => '#00a32a',
			'completed'       => '#8c8f94',
			'cancelled'       => '#d63638',
			'refunded'        => '#d63638',
			'no_show'         => '#d63638',
			'draft'           => '#c3c4c7',
		);

		return array_map(
			static function ( array $row ) use ( $dots ): array {
				$status = (string) $row['status'];
				return array(
					'status' => $status,
					'label'  => Status::get_label( $status ),
					'count'  => (int) $row['cnt'],
					'dot'    => $dots[ $status ] ?? '#646970',
				);
			},
			$rows
		);
	}

	/**
	 * Get recent bookings - Cached
	 */
	public static function get_recent_bookings(): array {
		$cache_key = 'mhm_rentiva_dashboard_recent_bookings_v4';
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                p.ID as id,
                p_veh.post_title as vehicle_title,
                pm_plate.meta_value as vehicle_plate,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(pm_first.meta_value,''), ' ', COALESCE(pm_last.meta_value,''))), ''),
                    pm_name.meta_value,
                    pm_name2.meta_value,
                    ''
                ) as customer_name,
                pm_phone.meta_value  as customer_phone,
                pm_pickup.meta_value as pickup_date,
                pm_time.meta_value   as pickup_time,
                pm_status.meta_value as status,
                CASE WHEN pm_transfer.meta_value IS NOT NULL THEN 'transfer' ELSE 'rental' END as booking_type
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_vid      ON p.ID = pm_vid.post_id      AND pm_vid.meta_key      = %s
             LEFT JOIN {$wpdb->posts}    p_veh        ON pm_vid.meta_value = p_veh.ID
             LEFT JOIN {$wpdb->postmeta} pm_plate     ON p_veh.ID = pm_plate.post_id AND pm_plate.meta_key  = %s
             LEFT JOIN {$wpdb->postmeta} pm_first     ON p.ID = pm_first.post_id    AND pm_first.meta_key   = %s
             LEFT JOIN {$wpdb->postmeta} pm_last      ON p.ID = pm_last.post_id     AND pm_last.meta_key    = %s
             LEFT JOIN {$wpdb->postmeta} pm_name      ON p.ID = pm_name.post_id     AND pm_name.meta_key    = '_mhm_customer_name'
             LEFT JOIN {$wpdb->postmeta} pm_name2     ON p.ID = pm_name2.post_id    AND pm_name2.meta_key   = '_mhm_contact_name'
             LEFT JOIN {$wpdb->postmeta} pm_phone     ON p.ID = pm_phone.post_id    AND pm_phone.meta_key   = %s
             LEFT JOIN {$wpdb->postmeta} pm_pickup    ON p.ID = pm_pickup.post_id   AND pm_pickup.meta_key  = %s
             LEFT JOIN {$wpdb->postmeta} pm_time      ON p.ID = pm_time.post_id     AND pm_time.meta_key    = '_mhm_start_time'
             LEFT JOIN {$wpdb->postmeta} pm_status    ON p.ID = pm_status.post_id   AND pm_status.meta_key  = %s
             LEFT JOIN {$wpdb->postmeta} pm_transfer  ON p.ID = pm_transfer.post_id AND pm_transfer.meta_key = '_mhm_transfer_origin_id'
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending')
             ORDER BY p.post_date DESC
             LIMIT 3",
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_VEHICLE_ID,
				\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LICENSE_PLATE,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_CUSTOMER_FIRST_NAME,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_CUSTOMER_LAST_NAME,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_CUSTOMER_PHONE,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_PICKUP_DATE,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
				'vehicle_booking'
			),
			ARRAY_A
		);

		$bookings_data = $bookings ?: array();

		// Fill missing customer info via WooCommerce order or WordPress user fallback.
		foreach ( $bookings_data as &$booking ) {
			if ( ! empty( $booking['customer_name'] ) || empty( $booking['id'] ) ) {
				continue;
			}
			$booking_id = (int) $booking['id'];

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
							$booking['customer_name'] = trim( $first . ' ' . $last );
						}
						if ( empty( $booking['customer_phone'] ) ) {
							$booking['customer_phone'] = $order->get_billing_phone();
						}
						continue;
					}
				}
			}

			$user_id = get_post_meta( $booking_id, '_mhm_customer_user_id', true );
			if ( $user_id ) {
				$user = get_userdata( (int) $user_id );
				if ( $user ) {
					$first = $user->first_name;
					$last  = $user->last_name;
					if ( $first || $last ) {
						$booking['customer_name'] = trim( $first . ' ' . $last );
					}
					if ( empty( $booking['customer_phone'] ) ) {
						$booking['customer_phone'] = get_user_meta( (int) $user_id, 'phone', true );
					}
				}
			}
		}
		unset( $booking );

		set_transient( $cache_key, $bookings_data, 12 * HOUR_IN_SECONDS );

		return $bookings_data;
	}

	/**
	 * Get paginated recent bookings for the dashboard REST endpoint.
	 *
	 * @param int $page     Page number (1-based).
	 * @param int $per_page Items per page.
	 * @return array{ items: array, total: int, total_pages: int }
	 */
	public static function get_recent_bookings_paginated( int $page, int $per_page ): array {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status IN ('publish', 'private', 'pending')",
				'vehicle_booking'
			)
		);

		// Resolve locations table — new name takes priority over legacy name.
		$new_loc_table          = $wpdb->prefix . 'rentiva_transfer_locations';
		$old_loc_table          = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
		$locations_table        = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_loc_table ) ) === $new_loc_table )
			? $new_loc_table
			: $old_loc_table;
		$locations_table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $locations_table ) ) === $locations_table );

		// Two literal statements rather than one whose SELECT list and JOINs are
		// glued together from PHP fragments: the transfer-locations table is an
		// add-on feature that may simply not exist, and a JOIN cannot be made
		// conditional in SQL. Where it does exist its name is bound through %i.
		$bookings = $locations_table_exists
			? $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                p.ID as id,
                p_veh.post_title as vehicle_title,
                pm_plate.meta_value as vehicle_plate,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(pm_first.meta_value,''), ' ', COALESCE(pm_last.meta_value,''))), ''),
                    pm_name.meta_value,
                    pm_name2.meta_value,
                    ''
                ) as customer_name,
                pm_pickup.meta_value as pickup_date,
                pm_status.meta_value as status,
                pm_total.meta_value as total_price
                , loc_veh.name as vehicle_location
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_vid     ON p.ID = pm_vid.post_id    AND pm_vid.meta_key    = %s
             LEFT JOIN {$wpdb->posts}    p_veh      ON pm_vid.meta_value = p_veh.ID
             LEFT JOIN {$wpdb->postmeta} pm_plate   ON p_veh.ID = pm_plate.post_id AND pm_plate.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_veh_loc ON p_veh.ID = pm_veh_loc.post_id AND pm_veh_loc.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_first   ON p.ID = pm_first.post_id  AND pm_first.meta_key  = %s
             LEFT JOIN {$wpdb->postmeta} pm_last    ON p.ID = pm_last.post_id   AND pm_last.meta_key   = %s
             LEFT JOIN {$wpdb->postmeta} pm_name    ON p.ID = pm_name.post_id   AND pm_name.meta_key   = '_mhm_customer_name'
             LEFT JOIN {$wpdb->postmeta} pm_name2   ON p.ID = pm_name2.post_id  AND pm_name2.meta_key  = '_mhm_contact_name'
             LEFT JOIN {$wpdb->postmeta} pm_pickup  ON p.ID = pm_pickup.post_id AND pm_pickup.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_status  ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_total   ON p.ID = pm_total.post_id  AND pm_total.meta_key  = '_mhm_total_price'
             LEFT JOIN %i loc_veh ON pm_veh_loc.meta_value = loc_veh.id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending')
             ORDER BY pm_pickup.meta_value DESC, p.post_date DESC
             LIMIT %d OFFSET %d",
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_VEHICLE_ID,
					\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LICENSE_PLATE,
					\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID,
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_CUSTOMER_FIRST_NAME,
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_CUSTOMER_LAST_NAME,
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_PICKUP_DATE,
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
					$locations_table,
					'vehicle_booking',
					$per_page,
					$offset
				),
				ARRAY_A
			)
			: $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                p.ID as id,
                p_veh.post_title as vehicle_title,
                pm_plate.meta_value as vehicle_plate,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(pm_first.meta_value,''), ' ', COALESCE(pm_last.meta_value,''))), ''),
                    pm_name.meta_value,
                    pm_name2.meta_value,
                    ''
                ) as customer_name,
                pm_pickup.meta_value as pickup_date,
                pm_status.meta_value as status,
                pm_total.meta_value as total_price
                , NULL as vehicle_location
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_vid     ON p.ID = pm_vid.post_id    AND pm_vid.meta_key    = %s
             LEFT JOIN {$wpdb->posts}    p_veh      ON pm_vid.meta_value = p_veh.ID
             LEFT JOIN {$wpdb->postmeta} pm_plate   ON p_veh.ID = pm_plate.post_id AND pm_plate.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_veh_loc ON p_veh.ID = pm_veh_loc.post_id AND pm_veh_loc.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_first   ON p.ID = pm_first.post_id  AND pm_first.meta_key  = %s
             LEFT JOIN {$wpdb->postmeta} pm_last    ON p.ID = pm_last.post_id   AND pm_last.meta_key   = %s
             LEFT JOIN {$wpdb->postmeta} pm_name    ON p.ID = pm_name.post_id   AND pm_name.meta_key   = '_mhm_customer_name'
             LEFT JOIN {$wpdb->postmeta} pm_name2   ON p.ID = pm_name2.post_id  AND pm_name2.meta_key  = '_mhm_contact_name'
             LEFT JOIN {$wpdb->postmeta} pm_pickup  ON p.ID = pm_pickup.post_id AND pm_pickup.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_status  ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_total   ON p.ID = pm_total.post_id  AND pm_total.meta_key  = '_mhm_total_price'
             
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending')
             ORDER BY pm_pickup.meta_value DESC, p.post_date DESC
             LIMIT %d OFFSET %d",
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_VEHICLE_ID,
					\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LICENSE_PLATE,
					\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID,
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_CUSTOMER_FIRST_NAME,
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_CUSTOMER_LAST_NAME,
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_PICKUP_DATE,
					\MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
					'vehicle_booking',
					$per_page,
					$offset
				),
				ARRAY_A
			);

		$bookings = $bookings ?: array();

		// Fill missing customer info via WooCommerce order or WordPress user
		// fallback (shared with ReportRepository — see BookingEnricher).
		\MHMRentiva\Core\Repository\BookingEnricher::enrich_customer_info( $bookings );

		$items = array_map(
			function ( array $b ): array {
				$b['display_id']   = mhm_rentiva_get_display_id( (int) $b['id'] );
				$b['status_label'] = \MHMRentiva\Admin\Booking\Core\Status::get_label( $b['status'] ?? '' );
				return $b;
			},
			$bookings
		);

		return array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Get booking summary stats: monthly, confirmed, pending counts.
	 */
	public static function get_booking_stats(): array {
		global $wpdb;
		$month_start = gmdate( 'Y-m-01 00:00:00' );
		$month_end   = gmdate( 'Y-m-t 23:59:59' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                    COUNT(DISTINCT p.ID) as total,
                    SUM(CASE WHEN p.post_date >= %s AND p.post_date <= %s THEN 1 ELSE 0 END) as monthly,
                    SUM(CASE WHEN pm_status.meta_value = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN pm_status.meta_value = 'pending' THEN 1 ELSE 0 END) as pending
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
                WHERE p.post_type = %s AND p.post_status != %s",
				$month_start,
				$month_end,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
				'vehicle_booking',
				'trash'
			)
		);

		return array(
			'total'     => (int) ( $row->total     ?? 0 ),
			'monthly'   => (int) ( $row->monthly   ?? 0 ),
			'confirmed' => (int) ( $row->confirmed ?? 0 ),
			'pending'   => (int) ( $row->pending   ?? 0 ),
		);
	}

	/**
	 * Get vehicle statistics (CURRENT MONTH ONLY)
	 */
	public static function get_vehicle_stats(): array {
		global $wpdb;

		$current_month_start = gmdate( 'Y-m-01 00:00:00' );
		$current_month_end   = gmdate( 'Y-m-t 23:59:59' );

		// Get all vehicles with status - Using VEHICLE_STATUS
		$vehicle_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                COUNT(DISTINCT v.ID) as total_vehicles,
                COUNT(DISTINCT CASE WHEN pm_status.meta_value = %s THEN v.ID END) as inactive,
                COUNT(DISTINCT CASE WHEN pm_status.meta_value = %s THEN v.ID END) as maintenance
             FROM {$wpdb->posts} v
             LEFT JOIN {$wpdb->postmeta} pm_status ON v.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE v.post_type = %s AND v.post_status = %s",
				'inactive',
				'maintenance',
				\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS,
				'vehicle',
				'publish'
			)
		);

		$inactive    = (int) ( $vehicle_stats->inactive ?? 0 );
		$maintenance = (int) ( $vehicle_stats->maintenance ?? 0 );

		$month_start_ts = strtotime( $current_month_start );
		$month_end_ts   = strtotime( $current_month_end );

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT pm_vehicle.meta_value as vehicle_id,
                    pm_pickup.meta_value as pickup_date,
                    COALESCE(pm_return1.meta_value, pm_return2.meta_value, pm_return3.meta_value) as return_date
             FROM {$wpdb->posts} b
             INNER JOIN {$wpdb->postmeta} pm_vehicle ON b.ID = pm_vehicle.post_id AND pm_vehicle.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_pickup ON b.ID = pm_pickup.post_id AND pm_pickup.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_return1 ON b.ID = pm_return1.post_id AND pm_return1.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_return2 ON b.ID = pm_return2.post_id AND pm_return2.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_return3 ON b.ID = pm_return3.post_id AND pm_return3.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_status ON b.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE b.post_type = %s
             AND b.post_status IN ('publish', 'private', 'pending')
             AND b.post_status != 'trash'
             AND b.post_date >= %s AND b.post_date <= %s
             AND pm_status.meta_value IN (%s, %s, %s)
             AND pm_vehicle.meta_value IS NOT NULL AND pm_vehicle.meta_value != ''
             AND pm_pickup.meta_value IS NOT NULL AND pm_pickup.meta_value != ''
             AND (pm_return1.meta_value IS NOT NULL OR pm_return2.meta_value IS NOT NULL OR pm_return3.meta_value IS NOT NULL)",
				'_mhm_vehicle_id',
				'_mhm_pickup_date',
				'_mhm_return_date',
				'_mhm_dropoff_date',
				'_mhm_end_date',
				'_mhm_status',
				'vehicle_booking',
				$current_month_start,
				$current_month_end,
				'confirmed',
				'active',
				'pending'
			)
		);

		$reserved_vehicle_ids = array();
		if ( $bookings ) {
			foreach ( $bookings as $booking ) {
				$pickup_ts = strtotime( $booking->pickup_date );
				$return_ts = strtotime( $booking->return_date );

				if ( $pickup_ts === false || $return_ts === false ) {
					continue;
				}

				$overlaps = ( $pickup_ts <= $month_end_ts && $return_ts >= $month_start_ts );
				if ( $overlaps ) {
					$reserved_vehicle_ids[] = (int) $booking->vehicle_id;
				}
			}
		}

		$reserved = count( array_unique( $reserved_vehicle_ids ) );

		$available_vehicles_with_status = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT v.ID)
             FROM {$wpdb->posts} v
             LEFT JOIN {$wpdb->postmeta} pm_status ON v.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE v.post_type = %s 
             AND v.post_status = %s
             AND (
                pm_status.meta_value = %s 
                OR pm_status.meta_value IS NULL 
                OR pm_status.meta_value = ''
             )",
				\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS,
				'vehicle',
				'publish',
				'active'
			)
		);

		$available = max( 0, $available_vehicles_with_status - $reserved );

		return array(
			'available'   => $available,
			'reserved'    => $reserved,
			'maintenance' => $maintenance,
			'inactive'    => $inactive,
		);
	}

	/**
	 * Get revenue data for Chart.js
	 */
	public static function get_revenue_data(): array {
		global $wpdb;

		$revenue_data = array();
		for ( $i = 6; $i >= 0; $i-- ) {
			// Use WordPress local time so DATE(post_date) comparison is consistent
			$date    = wp_date( 'Y-m-d', strtotime( "-{$i} days", current_time( 'timestamp' ) ) );
			$revenue = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                 WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s) AND p.post_status != %s
                 AND DATE(p.post_date) = %s
                 AND pm.meta_key = %s
                 AND pm_status.meta_key = %s
                 AND pm_status.meta_value IN (%s, %s)",
					'vehicle_booking',
					'publish',
					'private',
					'pending',
					'trash',
					$date,
					'_mhm_total_price',
					'_mhm_status',
					'completed',
					'confirmed'
				)
			);

			// Pass ISO date so JavaScript can format it consistently without re-calculating dates
			$revenue_data[] = array(
				'date'    => $date,
				'revenue' => $revenue,
			);
		}

		$this_week_start = wp_date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
		$this_week_end   = wp_date( 'Y-m-d', strtotime( 'sunday this week', current_time( 'timestamp' ) ) );

		$weekly_total = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s) AND p.post_status != %s
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm.meta_key = %s
             AND pm_status.meta_key = %s
             AND pm_status.meta_value IN (%s, %s)",
				'vehicle_booking',
				'publish',
				'private',
				'pending',
				'trash',
				$this_week_start,
				$this_week_end . ' 23:59:59',
				'_mhm_total_price',
				'_mhm_status',
				'completed',
				'confirmed'
			)
		);

		$last_week_start = wp_date( 'Y-m-d', strtotime( 'monday last week', current_time( 'timestamp' ) ) );
		$last_week_end   = wp_date( 'Y-m-d', strtotime( 'sunday last week', current_time( 'timestamp' ) ) );

		$last_weekly_total = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s) AND p.post_status != %s
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm.meta_key = %s
             AND pm_status.meta_key = %s
             AND pm_status.meta_value IN (%s, %s)",
				'vehicle_booking',
				'publish',
				'private',
				'pending',
				'trash',
				$last_week_start,
				$last_week_end . ' 23:59:59',
				'_mhm_total_price',
				'_mhm_status',
				'completed',
				'confirmed'
			)
		);

		return array(
			'daily_data'        => $revenue_data,
			'weekly_total'      => $weekly_total,
			'last_weekly_total' => $last_weekly_total,
		);
	}

	/**
	 * Get customer detailed statistics
	 */
	public static function get_customer_detail_stats(): array {
		$stats        = self::get_dashboard_metrics();
		$avg_spending = self::calculate_customer_avg_spending();

		return array(
			'total'          => $stats['total_customers_this_month'],
			'new_this_month' => $stats['new_customers_this_month'],
			'active'         => $stats['total_customers_this_month'],
			'avg_spending'   => $avg_spending,
		);
	}

	/**
	 * Calculate average customer spending
	 */
	private static function calculate_customer_avg_spending(): string {
		global $wpdb;
		$current_month_start = gmdate( 'Y-m-01 00:00:00' );
		$current_month_end   = gmdate( 'Y-m-t 23:59:59' );

		$total_spending = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s) AND p.post_status != %s
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm.meta_key = %s
             AND pm_status.meta_key = %s
             AND pm_status.meta_value IN (%s, %s)",
				'vehicle_booking',
				'publish',
				'private',
				'pending',
				'trash',
				$current_month_start,
				$current_month_end,
				'_mhm_total_price',
				'_mhm_status',
				'completed',
				'confirmed'
			)
		);

		$total_customers = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm_email.meta_value)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s) AND p.post_status != %s
             AND pm_status.meta_value IN (%s, %s)
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm_email.meta_value != '' AND pm_email.meta_value IS NOT NULL",
				'_mhm_customer_email',
				'_mhm_status',
				'vehicle_booking',
				'publish',
				'private',
				'pending',
				'trash',
				'completed',
				'confirmed',
				$current_month_start,
				$current_month_end
			)
		);

		$avg = ( $total_customers > 0 ) ? ( $total_spending / $total_customers ) : 0.00;
		return number_format( $avg, 2 );
	}

	/**
	 * Get message statistics - No cache (fresh count for notification badge accuracy)
	 */
	public static function get_message_stats(): array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN pm.meta_value = 'pending'  THEN 1 ELSE 0 END) as pending,
					SUM(CASE WHEN pm.meta_value = 'answered' THEN 1 ELSE 0 END) as answered,
					COUNT(*) as total
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mhm_message_status'
				WHERE p.post_type = %s AND p.post_status = %s",
				'mhm_message',
				'publish'
			)
		);

		return array(
			'pending'  => (int) ( $row->pending  ?? 0 ),
			'answered' => (int) ( $row->answered ?? 0 ),
			'total'    => (int) ( $row->total    ?? 0 ),
		);
	}



	/**
	 * Get recent messages - Cached
	 */
	public static function get_recent_messages(): array {
		$cache_key = 'mhm_rentiva_recent_messages_' . get_current_user_id();
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_content, p.post_date,
                    COALESCE(pm1.meta_value, '') as customer_name,
                    COALESCE(pm2.meta_value, 'pending') as status
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = %s
             ORDER BY p.post_date DESC LIMIT 3",
				'_mhm_customer_name',
				'_mhm_message_status',
				'mhm_message',
				'publish'
			),
			ARRAY_A
		);

		$status_labels = array(
			'pending'  => __( 'Pending', 'mhm-rentiva' ),
			'answered' => __( 'Answered', 'mhm-rentiva' ),
			'closed'   => __( 'Closed', 'mhm-rentiva' ),
		);

		$data = array();
		foreach ( $messages ?: array() as $msg ) {
			$status = strtolower( trim( $msg['status'] ?: 'pending' ) );
			$data[] = array(
				'id'            => $msg['ID'],
				'customer_name' => $msg['customer_name'] ?: __( 'Anonymous', 'mhm-rentiva' ),
				'content'       => $msg['post_content'],
				'date'          => gmdate( 'd.m.Y H:i', (int) strtotime( $msg['post_date'] ) ),
				'status'        => $status,
				'status_label'  => $status_labels[ $status ] ?? ucfirst( $status ),
			);
		}

		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );
		return $data;
	}



	/**
	 * Get system notifications
	 */
	public static function get_system_notifications(): array {
		$notifications = array();

		// Messages
		$msg_stats = self::get_message_stats();
		if ( $msg_stats['pending'] > 0 ) {
			$notifications[] = array(
				'type'    => 'warning',
				'icon'    => 'dashicons-email-alt',
				'title'   => __( 'Pending Messages', 'mhm-rentiva' ),
				'message' => sprintf(
					/* translators: %s: number of pending messages */
					__( '%s pending messages', 'mhm-rentiva' ),
					number_format_i18n( $msg_stats['pending'] )
				),
				'time'    => __( 'Now', 'mhm-rentiva' ),
			);
		}

		// High-level systems or logic checks could be added here
		// ... (truncated for brevity based on existing DashboardPage logic)

		return array_slice( $notifications, 0, 4 );
	}

	/**
	 * Get deposit statistics
	 */
	public static function get_deposit_stats(): array {
		global $wpdb;
		$current_month_start = gmdate( 'Y-m-01 00:00:00' );
		$current_month_end   = gmdate( 'Y-m-t 23:59:59' );

		// Total deposit bookings this month
		$deposit_bookings = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status != %s
             AND pm.meta_key = %s AND pm.meta_value = %s
             AND p.post_date >= %s AND p.post_date <= %s",
				'vehicle_booking',
				'trash',
				'_mhm_payment_type',
				'deposit',
				$current_month_start,
				$current_month_end
			)
		);

		// Pending deposits (remaining_amount > 0, not cancelled/completed/refunded)
		$pending_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) as cnt,
                    SUM(CAST(pm_remaining.meta_value AS DECIMAL(10,2))) as total
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = %s AND pm_type.meta_value = %s
             INNER JOIN {$wpdb->postmeta} pm_remaining ON p.ID = pm_remaining.post_id AND pm_remaining.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s AND p.post_status != %s
             AND CAST(pm_remaining.meta_value AS DECIMAL(10,2)) > 0
             AND pm_status.meta_value NOT IN ('cancelled', 'refunded', 'completed')",
				'_mhm_payment_type',
				'deposit',
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_REMAINING_AMOUNT,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
				'vehicle_booking',
				'trash'
			)
		);

		// Completed deposits (remaining_amount = 0 or null, status = completed/confirmed)
		$completed_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) as cnt,
                    SUM(CAST(pm_deposit.meta_value AS DECIMAL(10,2))) as total
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = %s AND pm_type.meta_value = %s
             INNER JOIN {$wpdb->postmeta} pm_deposit ON p.ID = pm_deposit.post_id AND pm_deposit.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             WHERE p.post_type = %s AND p.post_status != %s
             AND pm_status.meta_value IN ('completed', 'confirmed')",
				'_mhm_payment_type',
				'deposit',
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_DEPOSIT_AMOUNT,
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
				'vehicle_booking',
				'trash'
			)
		);

		return array(
			'deposit_bookings'         => $deposit_bookings,
			'deposit_trend'            => 0,
			'pending_deposits'         => (int) ( $pending_row->cnt ?? 0 ),
			'pending_deposit_amount'   => (float) ( $pending_row->total ?? 0 ),
			'completed_deposits'       => (int) ( $completed_row->cnt ?? 0 ),
			'completed_deposit_amount' => (float) ( $completed_row->total ?? 0 ),
		);
	}

	/**
	 * Get pending payments
	 */
	public static function get_pending_payments(): array {
		return self::collect_pending_payments()['items'];
	}

	/**
	 * Shared scan behind get_pending_payments() and get_payments_summary()'s
	 * `pending_total`. Walks the same query rows once and returns BOTH:
	 * - `items`: the display list, capped at 10 (existing UI behaviour).
	 * - `total`: the authoritative sum across ALL rows the query returns —
	 *   NOT capped at 10. It is bounded only by the query's own `LIMIT 50`.
	 *
	 * @return array{items:array,total:float}
	 */
	private static function collect_pending_payments(): array {
		global $wpdb;

		$now = current_time( 'mysql' );

		// Status-aware: pull bookings that have at least one WC order attached
		// (deposit or remaining). Filter by WC order status in PHP.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as booking_id, p.post_title,
                    pm_name.meta_value as customer_name,
                    CAST(COALESCE(pm_deposit_amount.meta_value, '0') AS DECIMAL(10,2)) as deposit_amount,
                    CAST(COALESCE(pm_remaining.meta_value, '0') AS DECIMAL(10,2)) as remaining_amount,
                    pm_deadline.meta_value as deadline,
                    pm_status.meta_value as booking_status,
                    pm_deposit_order.meta_value as deposit_order_id,
                    pm_remaining_order.meta_value as remaining_order_id
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id AND pm_name.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_deposit_amount ON p.ID = pm_deposit_amount.post_id AND pm_deposit_amount.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_remaining ON p.ID = pm_remaining.post_id AND pm_remaining.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_deadline ON p.ID = pm_deadline.post_id AND pm_deadline.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_deposit_order ON p.ID = pm_deposit_order.post_id AND pm_deposit_order.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_remaining_order ON p.ID = pm_remaining_order.post_id AND pm_remaining_order.meta_key = %s
             WHERE p.post_type = %s
             AND p.post_status != %s
             AND pm_status.meta_value NOT IN ('cancelled', 'refunded', 'completed')
             AND ( pm_deposit_order.meta_value IS NOT NULL OR pm_remaining_order.meta_value IS NOT NULL )
             ORDER BY pm_deadline.meta_value ASC LIMIT 50",
				'_mhm_customer_name',
				'_mhm_deposit_amount',
				'_mhm_remaining_amount',
				'_mhm_payment_deadline',
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
				'_mhm_woocommerce_order_id',
				'_mhm_remaining_order_id',
				'vehicle_booking',
				'trash'
			),
			ARRAY_A
		) ?: array();

		$status_labels = array(
			'pending'   => __( 'Pending', 'mhm-rentiva' ),
			'confirmed' => __( 'Confirmed', 'mhm-rentiva' ),
			'active'    => __( 'Active', 'mhm-rentiva' ),
			'deposit'   => __( 'Deposit', 'mhm-rentiva' ),
		);

		$type_labels = array(
			'deposit'   => __( 'Deposit', 'mhm-rentiva' ),
			'remaining' => __( 'Remaining', 'mhm-rentiva' ),
		);

		$pending_order_statuses = array( 'pending', 'on-hold' );

		$payments        = array();
		$total           = 0.0;
		$has_wc_function = function_exists( 'wc_get_order' );

		// Walk every row the query returned (bounded by the SQL LIMIT 50
		// above) so `$total` is authoritative. Only the display list
		// ($payments) is capped at 10 — the old code stopped scanning rows
		// entirely once 10 were collected, which is fine for the widget list
		// but would silently undercount an aggregate total.
		foreach ( $rows as $row ) {
			$booking_id         = (int) $row['booking_id'];
			$deposit_order_id   = (int) ( $row['deposit_order_id'] ?? 0 );
			$remaining_order_id = (int) ( $row['remaining_order_id'] ?? 0 );
			$deadline           = $row['deadline'] ?? '';
			$is_overdue         = $deadline && strtotime( $deadline ) < strtotime( $now );
			$booking_status     = strtolower( $row['booking_status'] ?? 'pending' );

			// Use WC order totals as the authoritative source — booking-side
			// `_mhm_deposit_amount` / `_mhm_remaining_amount` may drift (e.g.
			// some hooks zero out the remaining amount after status changes).
			// Emit one row per pending WC order so admins see every payment due.
			$display_id    = mhm_rentiva_get_display_id( $booking_id );
			$customer_name = $row['customer_name'] ?? '';
			$deadline_fmt  = $deadline ? wp_date( 'd.m.Y', strtotime( $deadline ) ) : '—';
			$status_label  = $status_labels[ $booking_status ] ?? ucfirst( $booking_status );

			if ( $has_wc_function && $deposit_order_id > 0 ) {
				$deposit_order = wc_get_order( $deposit_order_id );
				if ( $deposit_order && in_array( $deposit_order->get_status(), $pending_order_statuses, true ) ) {
					$amount = (float) $deposit_order->get_total();
					$total += $amount;
					if ( count( $payments ) < 10 ) {
						$payments[] = array(
							'booking_id'    => $booking_id,
							'display_id'    => $display_id,
							'customer_name' => $customer_name,
							'amount'        => $amount,
							'deadline'      => $deadline_fmt,
							'status'        => $booking_status,
							'status_label'  => $status_label,
							'type'          => 'deposit',
							'type_label'    => $type_labels['deposit'],
							'order_id'      => $deposit_order_id,
							'is_overdue'    => (bool) $is_overdue,
						);
					}
				}
			}

			if ( $has_wc_function && $remaining_order_id > 0 ) {
				$remaining_order = wc_get_order( $remaining_order_id );
				if ( $remaining_order && in_array( $remaining_order->get_status(), $pending_order_statuses, true ) ) {
					$amount = (float) $remaining_order->get_total();
					$total += $amount;
					if ( count( $payments ) < 10 ) {
						$payments[] = array(
							'booking_id'    => $booking_id,
							'display_id'    => $display_id,
							'customer_name' => $customer_name,
							'amount'        => $amount,
							'deadline'      => $deadline_fmt,
							'status'        => $booking_status,
							'status_label'  => $status_label,
							'type'          => 'remaining',
							'type_label'    => $type_labels['remaining'],
							'order_id'      => $remaining_order_id,
							'is_overdue'    => (bool) $is_overdue,
						);
					}
				}
			}
		}

		return array(
			'items' => $payments,
			'total' => $total,
		);
	}

	/**
	 * Two aggregate payment figures for the dashboard Payments summary card.
	 *
	 * `this_month_collected` was removed (owner decision) — true cash
	 * collected cannot be computed reliably; it depended on the same
	 * drifting `_mhm_remaining_amount` field that `pending_total` no longer
	 * uses (see below).
	 *
	 * @return array{pending_total:float,deposit_blocked:float}
	 */
	public static function get_payments_summary(): array {
		global $wpdb;

		// Authoritative: reuse get_pending_payments()'s WC-order-status scan
		// instead of summing the drifting `_mhm_remaining_amount` meta in
		// pure SQL. Bounded by that query's own LIMIT (see
		// collect_pending_payments() docblock).
		$pending_total = self::collect_pending_payments()['total'];

		// Deposit-only: mirrors get_deposit_stats()'s `_mhm_payment_type` =
		// 'deposit' filter. Without it, full-payment bookings (which also
		// get `_mhm_deposit_amount` written, equal to the full total — see
		// DepositCalculator::calculate_booking_deposit()) were counted here
		// as "deposit held", inflating the figure by the entire booking total.
		$deposit_blocked = (float) $wpdb->get_var(
			"SELECT SUM(CAST(pm_dep.meta_value AS DECIMAL(10,2)))
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = '_mhm_payment_type' AND pm_type.meta_value = 'deposit'
             INNER JOIN {$wpdb->postmeta} pm_dep ON p.ID = pm_dep.post_id AND pm_dep.meta_key = '_mhm_deposit_amount'
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status IN ('publish','private','pending') AND p.post_status != 'trash'
             AND pm_status.meta_value IN ('confirmed','in_progress')"
		);

		return array(
			'pending_total'   => $pending_total,
			'deposit_blocked' => $deposit_blocked,
		);
	}
}
