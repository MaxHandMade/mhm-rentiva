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
     * The search's availability filter: drops every vehicle booked for the window.
     *
     * The window is the booking form's: pickup date and time to the return
     * date at the same time -- the return time is always the pickup time.
     * Called without a time (the signature this method has always had), the
     * default pickup time (`mhmrentiva_default_pickup_time`, 10:00) is used at
     * both ends. "Booked" is the one rule in BookingConflicts -- the booking
     * paths' statuses, payment deadline and buffer, and blocked days -- so a
     * vehicle the search lists is one the booking form accepts.
     *
     * The booked set is computed once, before the caller's query, and comes
     * back as `AND <posts>.ID NOT IN (...)`, so totals and pages count only
     * what is listed. An empty or reversed window filters nothing.
     *
     * Callers that build their own filter use the same set directly:
     * BookingConflicts::booked_vehicle_ids().
     *
     * @param string $pickup_date Y-m-d, site-local.
     * @param string $return_date Y-m-d, site-local.
     * @param string $pickup_time Optional H:i; empty means the default pickup time.
     * @return string SQL fragment
     */
    public static function get_availability_subquery(string $pickup_date, string $return_date, string $pickup_time = ''): string
    {
        global $wpdb;

        // Guard: empty dates must skip availability filtering entirely.
        if ('' === $pickup_date || '' === $return_date) {
            return '';
        }

        // A time that is not a time (or a default filter answering garbage)
        // falls back -- it must not switch the filter off and list every
        // booked vehicle as free.
        if (1 !== preg_match('/^\d{2}:\d{2}$/', $pickup_time) || null === \MHMRentiva\Admin\Booking\Helpers\SiteTime::instant($pickup_date, $pickup_time)) {
            $pickup_time = (string) apply_filters('mhmrentiva_default_pickup_time', '10:00');
        }
        if (1 !== preg_match('/^\d{2}:\d{2}$/', $pickup_time) || null === \MHMRentiva\Admin\Booking\Helpers\SiteTime::instant($pickup_date, $pickup_time)) {
            $pickup_time = '10:00';
        }

        $start_ts = \MHMRentiva\Admin\Booking\Helpers\SiteTime::instant($pickup_date, $pickup_time);
        $end_ts   = \MHMRentiva\Admin\Booking\Helpers\SiteTime::instant($return_date, $pickup_time);

        if (null === $start_ts || null === $end_ts || $end_ts <= $start_ts) {
            return '';
        }

        $booked = \MHMRentiva\Admin\Booking\Helpers\BookingConflicts::booked_vehicle_ids($start_ts, $end_ts);
        if (array() === $booked) {
            return '';
        }

        return $wpdb->prepare(
            " AND {$wpdb->posts}.ID NOT IN (" . implode(',', array_fill(0, count($booked), '%d')) . ')',
            $booked
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
