<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Addons;

use MHMRentiva\Admin\Booking\Core\Status;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AddonBooking {


	public static function get_booking_addons( int $booking_id ): array {
		$selected_addons = get_post_meta( $booking_id, '_mhmrentiva_selected_addons', true )
		    ?: get_post_meta( $booking_id, 'mhmrentiva_selected_addons', true );
		$addon_details   = get_post_meta( $booking_id, '_mhmrentiva_addon_details', true )
		    ?: get_post_meta( $booking_id, 'mhmrentiva_addon_details', true );
		$addon_total     = (float) ( get_post_meta( $booking_id, '_mhmrentiva_addon_total', true )
		    ?: get_post_meta( $booking_id, 'mhmrentiva_addon_total', true ) );

		return array(
			'selected_addons' => $selected_addons ?: array(),
			'addon_details'   => $addon_details ?: array(),
			'addon_total'     => $addon_total ?: 0.0,
		);
	}

	public static function calculate_booking_addon_total( int $booking_id ): float {
		$addons = self::get_booking_addons( $booking_id );
		return $addons['addon_total'];
	}

	public static function get_addon_revenue_report( \DateTime $start_date, \DateTime $end_date ): array {
		global $wpdb;

		$start = $start_date->format( 'Y-m-d H:i:s' );
		$end   = $end_date->format( 'Y-m-d H:i:s' );

		// Booking status is META, not post status.
		//
		// This query used to filter `p.post_status IN ('confirmed','completed')`
		// and therefore matched nothing, ever: every booking row is `publish`
		// (measured on dev: 29 publish, 2 auto-draft, neither of those two
		// values present at all), and the real status lives in
		// `_mhmrentiva_status`, which is what Status::get()/set() read and write.
		// The method returned an empty breakdown and 0.0 revenue on every call
		// and was harmless only because nothing called it -- the danger was the
		// name, which promised a revenue report to whoever wired it up next.
		//
		// AddonUsageCountTest pins both halves: that no booking carries its
		// status in post_status, and that the counts are right now that the
		// status join is against the meta table.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT
                pm.meta_value as addon_details,
                p.post_date as booking_date
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->postmeta} sm
                ON sm.post_id = p.ID AND sm.meta_key = '_mhmrentiva_status'
            WHERE pm.meta_key = '_mhmrentiva_addon_details'
            AND p.post_type = 'mhmrentiva_booking'
            AND p.post_status = 'publish'
            AND sm.meta_value IN ( %s, %s )
            AND p.post_date BETWEEN %s AND %s
        ",
				Status::CONFIRMED,
				Status::COMPLETED,
				$start,
				$end
			),
			ARRAY_A
		);

		$addon_stats   = array();
		$total_revenue = 0.0;

		foreach ( $results as $result ) {
			$addon_details = maybe_unserialize( $result['addon_details'] );

			if ( is_array( $addon_details ) ) {
				foreach ( $addon_details as $addon ) {
					$addon_id    = $addon['id'] ?? 0;
					$addon_title = $addon['title'] ?? 'Unknown';
					// Units taken and what the booking charged for the line. Rows
					// stored before quantities and line totals ({id, title, price})
					// read as one unit at the unit price.
					$quantity = max( 1, (int) ( $addon['quantity'] ?? 1 ) );
					$revenue  = isset( $addon['line_total'] ) ? (float) $addon['line_total'] : (float) ( $addon['price'] ?? 0 );

					if ( ! isset( $addon_stats[ $addon_id ] ) ) {
						$addon_stats[ $addon_id ] = array(
							'title'   => $addon_title,
							'count'   => 0,
							'revenue' => 0.0,
						);
					}

					$addon_stats[ $addon_id ]['count']   += $quantity;
					$addon_stats[ $addon_id ]['revenue'] += $revenue;
					$total_revenue                       += $revenue;
				}
			}
		}

		return array(
			'addon_stats'   => $addon_stats,
			'total_revenue' => $total_revenue,
			'period'        => array(
				'start' => $start,
				'end'   => $end,
			),
		);
	}
}
