<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Financial analytics aggregation service — Ledger is the ONLY source of truth.
 *
 * This class NEVER reads:
 *   - wp_posts / post_meta (booking CPT)
 *   - mhm_payout CPT
 *   - Any table other than mhm_rentiva_ledger
 *
 * Base filter applied to all queries:
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
        $table = $wpdb->prefix . 'mhm_rentiva_ledger';

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
        $table = $wpdb->prefix . 'mhm_rentiva_ledger';

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
     * SQL:
     *   SELECT DATE(created_at) AS day, SUM(amount) AS amount
     *   FROM {ledger}
     *   WHERE vendor_id = %d
     *     AND status = 'cleared'
     *     AND type IN ('commission_credit', 'commission_refund')
     *     AND created_at >= %s
     *     AND created_at < %s
     *   GROUP BY DATE(created_at)
     *   ORDER BY day ASC
     *
     * Performance note: GROUP BY DATE(created_at) cannot use the partial index on
     * created_at directly, but the WHERE created_at >= %s prefix bound keeps the
     * scan range small (≤30 days). Acceptable at current scale. A pre-aggregated
     * daily_summary table is the recommended future upgrade for heavy load.
     *
     * @param  int   $vendor_id   The vendor user ID.
     * @param  int   $from_ts     Start Unix timestamp (inclusive).
     * @param  int   $to_ts       End Unix timestamp (exclusive).
     * @param  int   $window_days Expected number of days (for backfilling gaps).
     * @return float[]            Ordered daily net amounts, oldest first.
     */
    public static function get_sparkline_data(
        int $vendor_id,
        int $from_ts,
        int $to_ts,
        int $window_days = 7
    ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_ledger';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) AS day, SUM(amount) AS amount
				FROM {$table}
				WHERE vendor_id = %d
				AND status = %s
				AND type IN (%s, %s)
				AND created_at >= %s
				AND created_at < %s
				GROUP BY DATE(created_at)
				ORDER BY day ASC",
                $vendor_id,
                'cleared',
                'commission_credit',
                'commission_refund',
                gmdate('Y-m-d H:i:s', $from_ts),
                gmdate('Y-m-d H:i:s', $to_ts)
            )
        );

        // Build a day → amount map from DB results.
        $by_day = array();
        foreach ($rows as $row) {
            $by_day[$row->day] = (float) $row->amount;
        }

        // Backfill all expected days with 0.0 for missing dates.
        $points = array();
        for ($i = 0; $i < $window_days; $i++) {
            $day_key  = gmdate('Y-m-d', $from_ts + ($i * DAY_IN_SECONDS));
            $points[] = $by_day[$day_key] ?? 0.0;
        }

        return $points;
    }
}
