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

        $metrics = array(
            'revenue_30d'       => AnalyticsService::get_revenue_period($vendor_id, $from_30d, $now_ts),
            'revenue_30d_prev'  => AnalyticsService::get_revenue_period($vendor_id, $from_30d_prev, $from_30d),
            'growth_7d'         => AnalyticsService::get_growth_rate($vendor_id, 7, $now_ts),
            'avg_booking_value' => AnalyticsService::get_avg_booking_value($vendor_id, $from_30d, $now_ts),
            'sparkline_7d'      => AnalyticsService::get_sparkline_data($vendor_id, $from_7d, $now_ts, 7),
            'sparkline_30d'     => AnalyticsService::get_sparkline_data($vendor_id, $from_30d, $now_ts, 30),
            'top_vehicles'      => self::build_top_vehicles($vendor_id, $from_30d, $now_ts),
        );

        return $metrics;
    }

    /**
     * Build top vehicles list by revenue within window.
     * 
     * @return array<int, array<string, mixed>>
     */
    private static function build_top_vehicles(int $vendor_id, int $from_ts, int $to_ts): array
    {
        global $wpdb;

        $vehicle_ids_raw = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_author = %d AND post_status = 'publish'",
                'vehicle',
                $vendor_id
            )
        );

        $vehicles = array();
        foreach (array_map('intval', $vehicle_ids_raw) as $vid) {
            $perf = AnalyticsService::get_vehicle_performance($vid, $from_ts, $to_ts);

            if ($perf['revenue_period'] > 0 || $perf['cancellation_count'] > 0 || $perf['occupancy_rate'] > 0) {
                // Determine 7-day sparkline for this specific vehicle
                $sparkline = AnalyticsService::get_sparkline_data($vendor_id, $to_ts - (7 * DAY_IN_SECONDS), $to_ts, 7, $vid);

                $vehicles[] = array(
                    'vehicle_id'         => $vid,
                    'title'              => get_the_title($vid),
                    'revenue'            => $perf['revenue_period'],
                    'occupancy_rate'     => $perf['occupancy_rate'],
                    'cancellation_rate'  => $perf['cancellation_rate'],
                    'cancellation_count' => $perf['cancellation_count'],
                    'sparkline_7d'       => $sparkline,
                );
            }
        }

        // Sort descending by revenue
        usort($vehicles, static function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        // Return top 5
        return array_slice($vehicles, 0, 5);
    }
}
