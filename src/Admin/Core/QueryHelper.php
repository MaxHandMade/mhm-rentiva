<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;



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
         *
         * IMPORTANT: We must also exclude bookings where _mhm_status = 'cancelled'.
         * WordPress post_status stays as 'publish' even after plugin-level cancellation;
         * the actual status is stored in the _mhm_status postmeta field.
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
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} as ms
                    WHERE ms.post_id = bookings.ID
                    AND ms.meta_key = '_mhm_status'
                    AND ms.meta_value IN ('cancelled', 'refunded')
                )
            )",
            $end_ts,
            $start_ts
        );
    }
    /**
     * Generates a subquery for hybrid location filtering.
     * Logic: Vehicle Meta -> Author Meta -> Global Default
     * 
     * @param int $location_id
     * @return string SQL fragment
     */
    public static function get_location_subquery(int $location_id): string
    {
        global $wpdb;

        if ($location_id <= 0) {
            return "";
        }

        $loc_meta_key = MetaKeys::VEHICLE_LOCATION_ID;
        $vendor_loc_key = MetaKeys::VENDOR_LOCATION_ID;
        $global_default = (int) SettingsCore::get('mhm_rentiva_default_rental_location', 0);

        /**
         * SQL Logic for Hybrid Location (Full Hierarchy):
         * 
         * 1. Direct match on vehicle meta (_mhm_rentiva_location_id)
         * 2. OR: If vehicle has NO location meta, inherit from author (vendor) meta (_mhm_rentiva_vendor_location_id)
         * 3. OR: If neither vehicle nor author has location meta, inherit from Global Default (Option)
         */
        return $wpdb->prepare(
            " AND (
                /* 1. Direct match on vehicle meta */
                EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} as loc_meta 
                    WHERE loc_meta.post_id = {$wpdb->posts}.ID 
                    AND loc_meta.meta_key = %s 
                    AND loc_meta.meta_value = %d
                )
                /* 2. Inherit from Vendor (Author) if vehicle meta is empty/missing */
                OR (
                    NOT EXISTS (
                        SELECT 1 FROM {$wpdb->postmeta} as loc_exists 
                        WHERE loc_exists.post_id = {$wpdb->posts}.ID 
                        AND loc_exists.meta_key = %s 
                        AND loc_exists.meta_value != ''
                    )
                    AND (
                        EXISTS (
                            SELECT 1 FROM {$wpdb->usermeta} as vendor_meta
                            WHERE vendor_meta.user_id = {$wpdb->posts}.post_author
                            AND vendor_meta.meta_key = %s
                            AND vendor_meta.meta_value = %d
                        )
                        /* 3. Fallback to Global Default if no vendor meta exists */
                        OR (
                            NOT EXISTS (
                                SELECT 1 FROM {$wpdb->usermeta} as vendor_exists
                                WHERE vendor_exists.user_id = {$wpdb->posts}.post_author
                                AND vendor_exists.meta_key = %s
                                AND vendor_exists.meta_value != ''
                            )
                            AND %d = %d
                        )
                    )
                )
            )",
            $loc_meta_key,
            $location_id,
            $loc_meta_key,
            $vendor_loc_key,
            $location_id,
            $vendor_loc_key,
            $location_id,
            $global_default
        );
    }
}
