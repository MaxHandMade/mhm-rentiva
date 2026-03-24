<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Financial analytics aggregation service — Ledger is the ONLY source of truth.
 *
 * This 
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
class conventionally reads ONLY from mhm_rentiva_ledger.
 * Exception: get_vehicle_performance() resolves vehicle ownership and dates
 * via wp_postmeta because vehicles and bookings are CPTs.
 *
 * Base filter applied to all ledger queries:
 *   WHERE vendor_id = ?
 *   AND status = 'cleared'
 *   AND type IN ('commission_credit', 'commission_refund')
 *
 * Time windows are non-overlapping and UTC-normalized.
 * All timestamps passed in are Unix timestamps (integer), converted internally
 * via gmdate() to ensure no timezone drift between PHP and MySQL.
 *
 * @since 4.21.0
 */
final class AnalyticsService
{
	// -------------------------------------------------------------------------
	// Aggregate Revenue Methods
	// -------------------------------------------------------------------------

    /**
     * Total cleared net revenue for a vendor within a time window.
     *
     * NET = commission_credit (positive) + commission_refund (negative).
     * Produces the true vendor earning for the period.
     *
     * SQL:
     *   SELECT SUM(amount)
     *   FROM {ledger}
     *   WHERE vendor_id = %d
     *     AND status = 'cleared'
     *     AND type IN ('commission_credit', 'commission_refund')
     *     AND created_at >= %s
     *     AND created_at < %s
     */
    public static function get_revenue_period(
        int $vendor_id,
        int $from_ts,
        int $to_ts
    ): float {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'mhm_rentiva_ledger' );

        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount)
				FROM {$table}
				WHERE vendor_id = %d
				AND status = %s
				AND type IN (%s, %s)
				AND created_at >= %s
				AND created_at < %s",
                $vendor_id,
                'cleared',
                'commission_credit',
                'commission_refund',
                gmdate('Y-m-d H:i:s', $from_ts),
                gmdate('Y-m-d H:i:s', $to_ts)
            )
        );

        return (float) $sum;
    }

	// -------------------------------------------------------------------------
	// Growth Rate
	// -------------------------------------------------------------------------

    /**
     * Revenue growth percentage for a relative window size.
     *
     * NON-OVERLAPPING windows (e.g. for 7d):
     *   current:  [$now - 7d, $now)
     *   previous: [$now - 14d, $now - 7d)
     *
     * Formula: ((current - previous) / previous) * 100
     *
     * Returns NULL — not 0.0 — when previous_period = 0.
     * Rationale: 0.0 means "no change". NULL means "insufficient data".
     * These are semantically different in a financial reporting context.
     *
     * @param int    $vendor_id   The vendor user ID.
     * @param int    $window_days Number of days per comparison window (7 or 30).
     * @param int    $now_ts      Unix timestamp anchoring "now" for the calculation.
     * @return float|null Growth % or NULL if previous period has no cleared revenue.
     */
    public static function get_growth_rate(
        int $vendor_id,
        int $window_days = 7,
        int $now_ts = 0
    ): ?float {
        if ($now_ts === 0) {
            $now_ts = time();
        }

        $window_seconds = $window_days * DAY_IN_SECONDS;
        $current_start  = $now_ts - $window_seconds;
        $previous_start = $current_start - $window_seconds;

        $current  = self::get_revenue_period($vendor_id, $current_start, $now_ts);
        $previous = self::get_revenue_period($vendor_id, $previous_start, $current_start);

        // Guard: if previous period has zero revenue, growth % is undefined (not "0%").
        if ($previous === 0.0) {
            return null;
        }

        // Banker-safe rounding: PHP_ROUND_HALF_UP for consistent financial reporting precision.
        return round((($current - $previous) / $previous) * 100.0, 2, PHP_ROUND_HALF_UP);
    }

	// -------------------------------------------------------------------------
	// Average Booking Value
	// -------------------------------------------------------------------------

    /**
     * Average cleared commission_credit value per unique booking.
     *
     * Booking count = COUNT(DISTINCT booking_id)
     *   WHERE type = 'commission_credit' AND status = 'cleared'
     *
     * Refund rows are excluded from the booking count because refunds do not
     * represent distinct bookings — they are reversal events of existing bookings.
     *
     * SQL:
     *   SELECT
     *     SUM(amount)             AS total_amount,
     *     COUNT(DISTINCT booking_id) AS booking_count
     *   FROM {ledger}
     *   WHERE vendor_id = %d
     *     AND status = 'cleared'
     *     AND type = 'commission_credit'
     *     AND created_at >= %s
     *     AND created_at < %s
     *
     * Returns 0.0 if no cleared credits exist in the window.
     */
    public static function get_avg_booking_value(
        int $vendor_id,
        int $from_ts,
        int $to_ts
    ): float {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'mhm_rentiva_ledger' );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
					SUM(amount) AS total_amount,
					COUNT(DISTINCT booking_id) AS booking_count
				FROM {$table}
				WHERE vendor_id = %d
				AND status = %s
				AND type = %s
				AND created_at >= %s
				AND created_at < %s",
                $vendor_id,
                'cleared',
                'commission_credit',
                gmdate('Y-m-d H:i:s', $from_ts),
                gmdate('Y-m-d H:i:s', $to_ts)
            )
        );

        $booking_count = (int) ($row->booking_count ?? 0);
        $total_amount  = (float) ($row->total_amount ?? 0.0);

        if ($booking_count === 0) {
            return 0.0;
        }

        return round($total_amount / $booking_count, 2, PHP_ROUND_HALF_UP);
    }

	// -------------------------------------------------------------------------
	// Sparkline Data Builder
	// -------------------------------------------------------------------------

    /**
     * Build daily aggregate data points for SVG sparkline rendering.
     *
     * Groups cleared commission_credit + commission_refund amounts by calendar day (UTC).
     * Days with no activity are backfilled with 0.0 so the returned array always
     * has exactly $window_days entries — one per day, oldest to newest.
     *
     * @param  int   $vendor_id   The vendor user ID.
     * @param  int   $from_ts     Start Unix timestamp (inclusive).
     * @param  int   $to_ts       End Unix timestamp (exclusive).
     * @param  int   $window_days Expected number of days (for backfilling gaps).
     * @param  int|null $vehicle_id Optional vehicle ID scoped sparklines.
     * @return float[]            Ordered daily net amounts, oldest first.
     */
    public static function get_sparkline_data(
        int $vendor_id,
        int $from_ts,
        int $to_ts,
        int $window_days = 7,
        ?int $vehicle_id = null
    ): array {
        global $wpdb;
        $ledger_table = esc_sql( $wpdb->prefix . 'mhm_rentiva_ledger' );
        $meta_table   = $wpdb->postmeta;

        $join_sql = '';
        $where_sql = '';
        $query_args = array(
            $vendor_id,
            'cleared',
            'commission_credit',
            'commission_refund',
            gmdate('Y-m-d H:i:s', $from_ts),
            gmdate('Y-m-d H:i:s', $to_ts)
        );

        if ($vehicle_id) {
            $join_sql = "INNER JOIN {$meta_table} pm ON l.booking_id = pm.post_id";
            $where_sql = "AND pm.meta_key = '_mhm_vehicle_id' AND pm.meta_value = %d";
            $query_args[] = $vehicle_id;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(l.created_at) AS day, SUM(l.amount) AS amount
                FROM {$ledger_table} l
                {$join_sql}
                WHERE l.vendor_id = %d
                AND l.status = %s
                AND l.type IN (%s, %s)
                AND l.created_at >= %s
                AND l.created_at < %s
                {$where_sql}
                GROUP BY DATE(l.created_at)
                ORDER BY day ASC",
                ...$query_args
            )
        );

        $by_day = array();
        foreach ($rows as $row) {
            $by_day[$row->day] = (float) $row->amount;
        }

        $points = array();
        for ($i = 0; $i < $window_days; $i++) {
            $day_key  = gmdate('Y-m-d', $from_ts + ($i * DAY_IN_SECONDS));
            $points[] = $by_day[$day_key] ?? 0.0;
        }

        return $points;
    }

    // -------------------------------------------------------------------------
    // Vehicle-Level Performance Matrix
    // -------------------------------------------------------------------------

    /**
     * Compute aggregated performance (revenue, occupancy, cancellations) for a specific vehicle.
     * 
     * @param int $vehicle_id The vehicle post ID.
     * @param int $from_ts    Start Unix timestamp (inclusive).
     * @param int $to_ts      End Unix timestamp (exclusive).
     * @return array<string, float|int> {revenue: float, occupancy_rate: float, cancellation_count: int, cancellation_rate: float}
     */
    public static function get_vehicle_performance(
        int $vehicle_id,
        int $from_ts,
        int $to_ts
    ): array {
        global $wpdb;
        $ledger_table = esc_sql( $wpdb->prefix . 'mhm_rentiva_ledger' );
        $meta_table   = $wpdb->postmeta;
        $posts_table  = $wpdb->posts;

        $results = array(
            'revenue_period'     => 0.0,
            'occupancy_rate'     => 0.0,
            'cancellation_count' => 0,
            'cancellation_rate'  => 0.0,
        );

        // 1. Resolve Bookings related to this vehicle
        $booking_ids_raw = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT pm.post_id 
                 FROM {$meta_table} pm
                 INNER JOIN {$posts_table} p ON pm.post_id = p.ID
                 WHERE p.post_type = %s 
                   AND pm.meta_key = '_mhm_vehicle_id' 
                   AND pm.meta_value = %d",
                'vehicle_booking',
                $vehicle_id
            )
        );

        $booking_ids = array_map('intval', $booking_ids_raw);
        if (empty($booking_ids)) {
            return $results; // Fast exit if no bookings exist
        }

        // 2. Fetch Aggregated Net Revenue generated by these bookings (Within Window)
        $placeholders_ledger = implode(',', array_fill(0, count($booking_ids), '%d'));
        $ledger_query_parts  = array_merge($booking_ids, array(gmdate('Y-m-d H:i:s', $from_ts), gmdate('Y-m-d H:i:s', $to_ts)));

        $revenue = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) 
                 FROM {$ledger_table}
                 WHERE booking_id IN ({$placeholders_ledger})
                   AND status = %s
                   AND type IN (%s, %s)
                   AND created_at >= %s
                   AND created_at < %s",
                ...array_merge(
                    $booking_ids,
                    array(
                        'cleared',
                        'commission_credit',
                        'commission_refund',
                        gmdate('Y-m-d H:i:s', $from_ts),
                        gmdate('Y-m-d H:i:s', $to_ts),
                    )
                )
            )
        );

        $results['revenue_period'] = (float) $revenue;

        // 3. Operational Analytics (Occupancy & Cancellation Rate) bounded by time window
        $total_window_days  = max(1, (int) ceil(($to_ts - $from_ts) / DAY_IN_SECONDS));
        $booked_days        = 0;
        $cancellations      = 0;
        $total_window_reservations = 0;

        $placeholders_meta = implode(',', array_fill(0, count($booking_ids), '%d'));
        $raw_bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id as booking_id, 
                        MAX(CASE WHEN meta_key = '_mhm_status' THEN meta_value END) as status,
                        MAX(CASE WHEN meta_key = '_mhm_pickup_date' THEN meta_value END) as pickup_date,
                        MAX(CASE WHEN meta_key = '_mhm_return_date' THEN meta_value END) as return_date
                 FROM {$meta_table} 
                 WHERE post_id IN ({$placeholders_meta})
                   AND meta_key IN ('_mhm_status', '_mhm_pickup_date', '_mhm_return_date')
                 GROUP BY post_id",
                ...$booking_ids
            ),
            ARRAY_A
        );

        foreach ($raw_bookings as $b) {
            $pickup_ts = strtotime((string) $b['pickup_date']);
            $return_ts = strtotime((string) $b['return_date']);

            if (!$pickup_ts || !$return_ts) {
                continue;
            }

            // Window Intersection Guard
            if ($pickup_ts < $to_ts && $return_ts > $from_ts) {
                $total_window_reservations++;

                $status = sanitize_key((string) $b['status']);
                if ($status === 'cancelled' || $status === 'refunded') {
                    $cancellations++;
                } else if ($status === 'completed' || $status === 'confirmed' || $status === 'in_progress') {
                    // Accumulate bounding days for Occupancy
                    $overlap_start = max($from_ts, $pickup_ts);
                    $overlap_end   = min($to_ts, $return_ts);
                    $days          = (int) ceil(($overlap_end - $overlap_start) / DAY_IN_SECONDS);

                    if ($days > 0) {
                        $booked_days += $days;
                    }
                }
            }
        }

        $results['cancellation_count'] = $cancellations;

        if ($total_window_reservations > 0) {
            $results['cancellation_rate'] = round(($cancellations / $total_window_reservations) * 100, 2);
        }

        // Bound max occupancy to 100% (in case overlapping bookings sum to > max window)
        $occupancy_raw = ($booked_days / $total_window_days) * 100.0;
        $results['occupancy_rate'] = round(min(100.0, $occupancy_raw), 2);

        return $results;
    }

    /**
     * Compute aggregated operational performance for all vehicles owned by a vendor.
     * 
     * @param int $vendor_id  The vendor's user ID.
     * @param int $from_ts    Start Unix timestamp.
     * @param int $to_ts      End Unix timestamp.
     * @return array<string, float|int> {occupancy_rate: float, cancellation_rate: float}
     */
    public static function get_vendor_operational_metrics(
        int $vendor_id,
        int $from_ts,
        int $to_ts
    ): array {
        global $wpdb;

        $results = array(
            'occupancy_rate'    => 0.0,
            'cancellation_rate' => 0.0,
        );

        $vehicle_ids_raw = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_author = %d",
                'vehicle',
                $vendor_id
            )
        );

        $vehicle_ids = array_map('intval', $vehicle_ids_raw);
        if (empty($vehicle_ids)) {
            return $results;
        }

        $total_window_days  = max(1, (int) ceil(($to_ts - $from_ts) / DAY_IN_SECONDS));
        $total_available    = $total_window_days * count($vehicle_ids);

        $booked_days        = 0;
        $cancellations      = 0;
        $total_reservations = 0;

        $placeholders_vehicles = implode(',', array_fill(0, count($vehicle_ids), '%d'));
        $raw_bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm_vid.post_id as booking_id, 
                        MAX(CASE WHEN meta.meta_key = '_mhm_status' THEN meta.meta_value END) as status,
                        MAX(CASE WHEN meta.meta_key = '_mhm_pickup_date' THEN meta.meta_value END) as pickup_date,
                        MAX(CASE WHEN meta.meta_key = '_mhm_return_date' THEN meta.meta_value END) as return_date
                 FROM {$wpdb->postmeta} pm_vid
                 INNER JOIN {$wpdb->postmeta} meta ON pm_vid.post_id = meta.post_id
                 WHERE pm_vid.meta_key = '_mhm_vehicle_id'
                   AND pm_vid.meta_value IN ({$placeholders_vehicles})
                   AND meta.meta_key IN ('_mhm_status', '_mhm_pickup_date', '_mhm_return_date')
                 GROUP BY pm_vid.post_id",
                ...$vehicle_ids
            ),
            ARRAY_A
        );

        foreach ($raw_bookings as $b) {
            $pickup_ts = strtotime((string) $b['pickup_date']);
            $return_ts = strtotime((string) $b['return_date']);

            if (!$pickup_ts || !$return_ts) continue;

            if ($pickup_ts < $to_ts && $return_ts > $from_ts) {
                $total_reservations++;
                $status = sanitize_key((string) $b['status']);
                if ($status === 'cancelled' || $status === 'refunded') {
                    $cancellations++;
                } else if ($status === 'completed' || $status === 'confirmed' || $status === 'in_progress') {
                    $overlap_start = max($from_ts, $pickup_ts);
                    $overlap_end   = min($to_ts, $return_ts);
                    $days          = (int) ceil(($overlap_end - $overlap_start) / DAY_IN_SECONDS);
                    if ($days > 0) {
                        $booked_days += $days;
                    }
                }
            }
        }

        if ($total_reservations > 0) {
            $results['cancellation_rate'] = round(($cancellations / $total_reservations) * 100, 2);
        }

        $occupancy_raw = ($booked_days / $total_available) * 100.0;
        $results['occupancy_rate'] = round(min(100.0, $occupancy_raw), 2);

        return $results;
    }
}
