<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

use MHMRentiva\Core\Financial\AnalyticsService;
use MHMRentiva\Core\Services\Metrics\MetricCacheManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handle AJAX requests for vendor analytics.
 */
class AnalyticsController
{
    public static function register(): void
    {
        add_action('wp_ajax_mhm_fetch_vendor_stats', array(self::class, 'fetch_vendor_stats'));
    }

    public static function fetch_vendor_stats(): void
    {
        check_ajax_referer('mhm_rentiva_vendor_nonce', 'nonce');

        if (! current_user_can('rentiva_vendor')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'mhm-rentiva')), 403);
            exit;
        }

        $vendor_id = get_current_user_id();

        $start_date_raw = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date_raw   = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

        $start_ts = strtotime($start_date_raw);
        $end_ts   = strtotime($end_date_raw);

        if (! $start_ts || ! $end_ts || $start_ts > $end_ts) {
            wp_send_json_error(array('message' => __('Invalid date range', 'mhm-rentiva')), 400);
            exit;
        }

        // Include end of day
        $end_ts = $end_ts + 86399;
        $window_seconds = $end_ts - $start_ts;
        $window_days = max(1, (int) round($window_seconds / DAY_IN_SECONDS));

        // Previous period for growth calculation
        $prev_start_ts = $start_ts - $window_seconds;
        $prev_end_ts   = $start_ts;

        // Fetch Metrics bypassing transient where possible.
        // Although metric cache manager handles basic flushes, custom dates shouldn't be long-term cached.
        $metrics = array();

        // Current revenue
        $metrics['revenue'] = AnalyticsService::get_revenue_period($vendor_id, $start_ts, $end_ts);
        $metrics['revenue_prev'] = AnalyticsService::get_revenue_period($vendor_id, $prev_start_ts, $prev_end_ts);

        // Vendor generic metrics
        $vendor_metrics = AnalyticsService::get_vendor_operational_metrics($vendor_id, $start_ts, $end_ts);
        $metrics['occupancy_rate']    = $vendor_metrics['occupancy_rate'] ?? 0.0;
        $metrics['cancellation_rate'] = $vendor_metrics['cancellation_rate'] ?? 0.0;

        // Growth (Sıfır Bölme Koruması implemented in get_growth_rate natively or handled manually)
        $metrics['growth'] = null;
        if ($metrics['revenue_prev'] > 0) {
            $metrics['growth'] = (($metrics['revenue'] - $metrics['revenue_prev']) / max(1, $metrics['revenue_prev'])) * 100;
        } elseif ($metrics['revenue'] > 0) {
            $metrics['growth'] = 100.0; // From 0 to something
        }

        // Avg Booking value
        $metrics['avg_booking_value'] = AnalyticsService::get_avg_booking_value($vendor_id, $start_ts, $end_ts);

        // Sparklines
        $metrics['sparkline'] = AnalyticsService::get_sparkline_data($vendor_id, $start_ts, $end_ts, $window_days);

        // Top Vehicles
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
            $perf = AnalyticsService::get_vehicle_performance($vid, $start_ts, $end_ts);

            if ($perf['revenue_period'] > 0 || $perf['cancellation_count'] > 0 || $perf['occupancy_rate'] > 0) {
                $sparkline = AnalyticsService::get_sparkline_data($vendor_id, $start_ts, $end_ts, $window_days, $vid);

                $vehicles[] = array(
                    'vehicle_id'         => $vid,
                    'title'              => get_the_title($vid),
                    'revenue'            => $perf['revenue_period'],
                    'occupancy_rate'     => $perf['occupancy_rate'],
                    'cancellation_rate'  => $perf['cancellation_rate'],
                    'cancellation_count' => $perf['cancellation_count'],
                    'sparkline'          => SparklineRenderer::render($sparkline, 720, 80, '#8b5cf6'),
                );
            }
        }

        // Sort descending by revenue
        usort($vehicles, static function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        $metrics['top_vehicles'] = array_slice($vehicles, 0, 5);

        // Re-render sparklines for the UI in HTML directly so JS doesn't have to build SVG
        $metrics['sparkline_html'] = SparklineRenderer::render($metrics['sparkline'], 720, 80, '#2f54ff');

        // Formatting for the frontend
        $format_currency = static function (float $amount): string {
            if (function_exists('wc_price')) {
                return (string) wp_strip_all_tags(wc_price($amount));
            }
            $symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '₺';
            return $symbol . number_format($amount, 2, '.', ',');
        };

        $metrics['revenue_formatted'] = $format_currency((float) $metrics['revenue']);
        $metrics['avg_booking_formatted'] = $format_currency((float) $metrics['avg_booking_value']);

        $growth_class = 'is-neutral';
        $growth_label = '—';
        if ($metrics['growth'] !== null) {
            if ($metrics['growth'] > 0.0) {
                $growth_class = 'is-up';
                $growth_label = '↑ ' . number_format($metrics['growth'], 1) . '%';
            } elseif ($metrics['growth'] < 0.0) {
                $growth_class = 'is-down';
                $growth_label = '↓ ' . number_format(abs($metrics['growth']), 1) . '%';
            } else {
                $growth_class = 'is-neutral';
                $growth_label = '0.0%';
            }
        }
        $metrics['growth_html'] = '<span class="mhm-rentiva-dashboard__kpi-trend ' . esc_attr($growth_class) . '">' . esc_html($growth_label) . '</span>';

        // Format top vehicles to HTML table rows
        ob_start();
        foreach ($metrics['top_vehicles'] as $vehicle) {
?>
            <tr>
                <td><?php echo esc_html($vehicle['title']); ?></td>
                <td class="is-currency"><?php echo esc_html($format_currency((float) $vehicle['revenue'])); ?></td>
                <td><?php echo esc_html(number_format((float) $vehicle['occupancy_rate'], 1)); ?>%</td>
                <td>
                    <div class="mhm-rentiva-dashboard__inline-sparkline">
                        <?php echo $vehicle['sparkline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                    </div>
                </td>
            </tr>
<?php
        }
        $metrics['top_vehicles_html'] = ob_get_clean();

        wp_send_json_success($metrics);
    }
}
