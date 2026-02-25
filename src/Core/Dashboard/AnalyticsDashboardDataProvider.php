<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

use MHMRentiva\Core\Financial\AnalyticsService;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds analytics data for the vendor financial dashboard.
 *
 * Called by DashboardDataProvider for vendor context only.
 * All data originates exclusively from AnalyticsService (ledger-only).
 *
 * Output contract — $data['analytics'] array:
 *   revenue_30d        float    Cleared net revenue, last 30 days
 *   revenue_30d_prev   float    Cleared net revenue, prior 30 days (for growth context)
 *   growth_7d          ?float   7-day growth %; null = no prior-period baseline
 *   avg_booking_value  float    Per-booking average (commission_credit only)
 *   sparkline_7d       float[]  7 daily buckets, oldest first, 0.0-backfilled
 *   sparkline_30d      float[]  30 daily buckets, oldest first, 0.0-backfilled
 *
 * @since 4.21.0
 */
final class AnalyticsDashboardDataProvider
{
    /**
     * Build analytics data for a vendor.
     *
     * @param int $vendor_id  WordPress vendor user ID.
     * @param int $now_ts     Unix timestamp anchor. Defaults to time().
     * @return array<string, mixed>
     */
    public static function build(int $vendor_id, int $now_ts = 0): array
    {
        if ($now_ts === 0) {
            $now_ts = time();
        }

        $thirty_days = 30 * DAY_IN_SECONDS;
        $seven_days  = 7  * DAY_IN_SECONDS;

        $from_30d        = $now_ts - $thirty_days;
        $from_30d_prev   = $from_30d - $thirty_days;
        $from_7d         = $now_ts - $seven_days;

        return array(
            'revenue_30d'       => AnalyticsService::get_revenue_period($vendor_id, $from_30d, $now_ts),
            'revenue_30d_prev'  => AnalyticsService::get_revenue_period($vendor_id, $from_30d_prev, $from_30d),
            'growth_7d'         => AnalyticsService::get_growth_rate($vendor_id, 7, $now_ts),
            'avg_booking_value' => AnalyticsService::get_avg_booking_value($vendor_id, $from_30d, $now_ts),
            'sparkline_7d'      => AnalyticsService::get_sparkline_data($vendor_id, $from_7d, $now_ts, 7),
            'sparkline_30d'     => AnalyticsService::get_sparkline_data($vendor_id, $from_30d, $now_ts, 30),
        );
    }
}
