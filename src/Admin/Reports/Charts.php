<?php

namespace MHMRentiva\Admin\Reports;

use MHMRentiva\Admin\Core\AssetManager;

/**
 * Charts class - Manages charts on the Reports page
 */
class Charts
{
    /**
     * Enqueues scripts
     */
    public static function enqueue_scripts(): void
    {
        // ✅ Enqueue Chart.js library
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
            [],
            '4.4.0',
            true
        );

        // ✅ Enqueue External JavaScript file
        wp_enqueue_script(
            'mhm-reports-charts',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/reports-charts.js',
            ['jquery', 'chart-js'],
            MHM_RENTIVA_VERSION,
            true
        );

        // ✅ Dynamic settings with Localization
        wp_localize_script('mhm-reports-charts', 'mhmRentivaCharts', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_reports_nonce'),
            'locale' => get_locale(),
            'currencySymbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
            'strings' => [
                'daily_revenue' => __('Daily Revenue', 'mhm-rentiva'),
                'daily_bookings' => __('Daily Bookings', 'mhm-rentiva'),
                'vip_customers' => __('VIP Customers', 'mhm-rentiva'),
                'regular_customers' => __('Regular Customers', 'mhm-rentiva'),
                'new_customers' => __('New Customers', 'mhm-rentiva'),
                'no_data' => __('No data found', 'mhm-rentiva'),
                'error_loading' => __('Error loading data', 'mhm-rentiva')
            ]
        ]);
    }

    /**
     * Generic chart renderer to reduce code duplication
     */
    private static function render_chart(string $chart_type, string $start_date, string $end_date): void
    {
        $chart_id = $chart_type . '-chart-' . uniqid();
        $init_method = 'init' . ucfirst($chart_type) . 'Chart';
        ?>
        <canvas id="<?php echo esc_attr($chart_id); ?>"></canvas>

        <script>
        jQuery(document).ready(function($) {
            if (typeof window.mhmRentivaCharts !== 'undefined' && window.mhmRentivaCharts.<?php echo esc_js($init_method); ?>) {
                window.mhmRentivaCharts.<?php echo esc_js($init_method); ?>(
                    '<?php echo esc_attr($chart_id); ?>',
                    '<?php echo esc_js($start_date); ?>',
                    '<?php echo esc_js($end_date); ?>'
                );
            }
        });
        </script>
        <?php
    }

    public static function render_revenue_chart(string $start_date, string $end_date): void
    {
        self::render_chart('revenue', $start_date, $end_date);
    }

    public static function render_bookings_chart(string $start_date, string $end_date): void
    {
        self::render_chart('bookings', $start_date, $end_date);
    }

    public static function render_vehicles_chart(string $start_date, string $end_date): void
    {
        self::render_chart('vehicles', $start_date, $end_date);
    }

    public static function render_customers_chart(string $start_date, string $end_date): void
    {
        self::render_chart('customers', $start_date, $end_date);
    }

    public static function render_booking_status_chart(string $start_date, string $end_date): void
    {
        // This method serves the same function as render_bookings_chart
        self::render_bookings_chart($start_date, $end_date);
    }
}
