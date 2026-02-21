<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class QueryHelper
 * Handles centralized SQL fragments and query modifications.
 *
 * @since 1.4.1
 */
class QueryHelper
{

    /**
     * Generates a NOT EXISTS subquery for vehicle availability.
     * 
     * @param string $pickup_date Y-m-d format
     * @param string $return_date Y-m-d format
     * @return string SQL fragment
     */
    public static function get_availability_subquery(string $pickup_date, string $return_date): string
    {
        global $wpdb;

        // Convert to timestamps for comparison (consistent with existing logic)
        $start_ts = strtotime($pickup_date . ' 00:00:00');
        $end_ts   = strtotime($return_date . ' 23:59:59');

        if (!$start_ts || !$end_ts) {
            return "";
        }

        /**
         * Logic: We want vehicles that do NOT have a booking overlapping with the range.
         * Overlap definition: (start1 < end2) AND (end1 > start2)
         */
        return $wpdb->prepare(
            " AND NOT EXISTS (
                SELECT 1 
                FROM {$wpdb->posts} as bookings
                INNER JOIN {$wpdb->postmeta} as m1 ON (bookings.ID = m1.post_id AND m1.meta_key = '_mhm_vehicle_id')
                INNER JOIN {$wpdb->postmeta} as m2 ON (bookings.ID = m2.post_id AND m2.meta_key = '_mhm_start_ts')
                INNER JOIN {$wpdb->postmeta} as m3 ON (bookings.ID = m3.post_id AND m3.meta_key = '_mhm_end_ts')
                WHERE bookings.post_type = 'vehicle_booking'
                AND bookings.post_status IN ('publish', 'mhm-confirmed', 'mhm-pending')
                AND m1.meta_value = {$wpdb->posts}.ID
                AND (CAST(m2.meta_value AS SIGNED) <= %d)
                AND (CAST(m3.meta_value AS SIGNED) >= %d)
            )",
            $end_ts,
            $start_ts
        );
    }
}
