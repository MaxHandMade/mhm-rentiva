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
class QueryHelper {


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

        // Guard: empty dates must skip availability filtering entirely.
        // strtotime('' . ' 00:00:00') silently returns today's timestamp instead of false.
        if ('' === $pickup_date || '' === $return_date) {
            return '';
        }

        // Convert to timestamps for comparison (consistent with existing logic)
        $start_ts = strtotime($pickup_date . ' 00:00:00');
        $end_ts   = strtotime($return_date . ' 23:59:59');

        if (!$start_ts || !$end_ts) {
            return '';
        }

        /**
         * Logic: We want vehicles that do NOT have a booking overlapping with the range.
         * Overlap definition: (start1 < end2) AND (end1 > start2)
         *
         * IMPORTANT: We must also exclude bookings where _mhmrentiva_status = 'cancelled'.
         * WordPress post_status stays as 'publish' even after plugin-level cancellation;
         * the actual status is stored in the _mhmrentiva_status postmeta field.
         */
        return $wpdb->prepare(
            " AND NOT EXISTS (
                SELECT 1 
                FROM {$wpdb->posts} as bookings
                INNER JOIN {$wpdb->postmeta} as m1 ON (bookings.ID = m1.post_id AND m1.meta_key = '_mhmrentiva_vehicle_id')
                INNER JOIN {$wpdb->postmeta} as m2 ON (bookings.ID = m2.post_id AND m2.meta_key = '_mhmrentiva_start_ts')
                INNER JOIN {$wpdb->postmeta} as m3 ON (bookings.ID = m3.post_id AND m3.meta_key = '_mhmrentiva_end_ts')
                WHERE bookings.post_type = 'mhmrentiva_booking'
                AND bookings.post_status IN ('publish', 'mhm-confirmed', 'mhm-pending')
                AND m1.meta_value = {$wpdb->posts}.ID
                AND (CAST(m2.meta_value AS SIGNED) <= %d)
                AND (CAST(m3.meta_value AS SIGNED) >= %d)
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} as ms
                    WHERE ms.post_id = bookings.ID
                    AND ms.meta_key = '_mhmrentiva_status'
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
     * Accepts a single ID (int) or multiple IDs (array) for checkbox-style filtering.
     *
     * When $expand_to_city=true, the input IDs are first expanded to all
     * location IDs sharing their city in wp_rentiva_transfer_locations. This
     * lets transfer search match vehicles by route-origin city, not just the
     * exact location_id — e.g. a "Kadıköy → Esenler" route (both İstanbul)
     * accepts a vehicle parked anywhere in İstanbul. Rental keeps strict
     * matching (default false).
     *
     * @param int|array $location_ids   Single location ID or array of IDs.
     * @param bool      $expand_to_city Optional. Expand IDs to all locations in same city.
     * @return string SQL fragment
     */
    public static function get_location_subquery(int|array $location_ids, bool $expand_to_city = false): string
    {
        global $wpdb;

        // Normalize to array and filter out invalid values.
        $ids = is_array($location_ids) ? array_filter(array_map('intval', $location_ids)) : ( $location_ids > 0 ? array( $location_ids ) : array() );

        if (empty($ids)) {
            return '';
        }

        // City expansion: resolve all location IDs sharing the requested IDs' cities,
        // via the locations table, preferring the new name over the legacy one.
        // Neither is guaranteed to exist: locations are a Transfer add-on feature and
        // Lite creates no such table, so the legacy name -- which was previously
        // assumed rather than probed -- must be probed too. With no table there is
        // nothing to expand, so city expansion is skipped and the strict ID matching
        // below stands, exactly as when the table has no city data.
        $loc_table = null;
        if ($expand_to_city) {
            foreach (array( 'rentiva_transfer_locations', 'mhm_rentiva_transfer_locations' ) as $candidate) {
                $candidate_table = $wpdb->prefix . $candidate;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $candidate_table)) === $candidate_table) {
                    $loc_table = preg_replace('/[^A-Za-z0-9_]/', '', $candidate_table) ?? '';
                    break;
                }
            }
        }

        if ($loc_table !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Add-on table has no core API; the result feeds a single request's search.
            $cities = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT DISTINCT city FROM %i WHERE id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ") AND city <> ''",
                    array_merge(array( $loc_table ), $ids)
                )
            );

            if (! empty($cities)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Add-on table has no core API; the result feeds a single request's search.
                $expanded_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        'SELECT id FROM %i WHERE city IN (' . implode(',', array_fill(0, count($cities), '%s')) . ')',
                        array_merge(array( $loc_table ), $cities)
                    )
                );
                if (! empty($expanded_ids)) {
                    $ids = array_map('intval', $expanded_ids);
                }
            }
        }

        $loc_meta_key   = MetaKeys::VEHICLE_LOCATION_ID;
        $vendor_loc_key = MetaKeys::VENDOR_LOCATION_ID;
        $global_default = (int) SettingsCore::get('mhmrentiva_default_rental_location', 0);

        $prepare_args = array_merge(
            array( $loc_meta_key ),
            $ids,
            array( $loc_meta_key, $vendor_loc_key ),
            $ids,
            array( $vendor_loc_key, $global_default ),
            $ids
        );

        /**
         * SQL Logic for Hybrid Location (Full Hierarchy):
         *
         * 1. Direct match on vehicle meta (_mhmrentiva_location_id)
         * 2. OR: If vehicle has NO location meta, inherit from author (vendor) meta (_mhmrentiva_vendor_location_id)
         * 3. OR: If neither vehicle nor author has location meta, inherit from Global Default (Option)
         */
        // Each IN list is generated inline from count($ids) so the number of
        // placeholders is visibly tied to the number of values, and every value
        // -- including the global-default id -- goes through prepare().
        return $wpdb->prepare(
            " AND (
                /* 1. Direct match on vehicle meta */
                EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} as loc_meta
                    WHERE loc_meta.post_id = {$wpdb->posts}.ID
                    AND loc_meta.meta_key = %s
                    AND loc_meta.meta_value IN (" . implode(', ', array_fill(0, count($ids), '%d')) . ")
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
                            AND vendor_meta.meta_value IN (" . implode(', ', array_fill(0, count($ids), '%d')) . ")
                        )
                        /* 3. Fallback to Global Default if no vendor meta exists */
                        OR (
                            NOT EXISTS (
                                SELECT 1 FROM {$wpdb->usermeta} as vendor_exists
                                WHERE vendor_exists.user_id = {$wpdb->posts}.post_author
                                AND vendor_exists.meta_key = %s
                                AND vendor_exists.meta_value != ''
                            )
                            AND %d IN (" . implode(', ', array_fill(0, count($ids), '%d')) . ')
                        )
                    )
                )
            )',
            $prepare_args
        );
    }
}
