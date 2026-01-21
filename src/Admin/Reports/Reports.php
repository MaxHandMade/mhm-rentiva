<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Reports;

use MHMRentiva\Admin\Reports\BusinessLogic\BookingReport;
use MHMRentiva\Admin\Reports\BusinessLogic\CustomerReport;
use MHMRentiva\Admin\Reports\BusinessLogic\RevenueReport;
use MHMRentiva\Admin\Vehicle\Reports\VehicleReport;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Reports\Repository\ReportRepository;
use MHMRentiva\Admin\Core\Utilities\Templates;

if (!defined('ABSPATH')) {
    exit;
}

final class Reports
{
    /**
     * Get currency symbol
     */
    public static function get_currency_symbol(): string
    {
        return \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
    }

    public static function register(): void
    {
        // Add dashboard widgets
        add_action('wp_dashboard_setup', [self::class, 'add_dashboard_widgets']);

        // AJAX handlers
        add_action('wp_ajax_mhm_rentiva_reports_data', [self::class, 'ajax_get_data']);

        // Admin scripts
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);

        // Cache clearing
        add_action('wp_ajax_mhm_rentiva_clear_reports_cache', [self::class, 'ajax_clear_cache']);
    }

    public static function add_dashboard_widgets(): void
    {
        wp_add_dashboard_widget(
            'mhm_rentiva_stats',
            __('MHM Rentiva Statistics', 'mhm-rentiva'),
            [self::class, 'render_stats_widget']
        );

        wp_add_dashboard_widget(
            'mhm_rentiva_revenue_chart',
            __('Revenue Chart', 'mhm-rentiva'),
            [self::class, 'render_revenue_widget']
        );
    }

    public static function render_stats_widget(): void
    {
        $stats = self::get_dashboard_stats();
?>
        <div class="mhm-rentiva-dashboard-stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo esc_html($stats['total_bookings']); ?></span>
                <span class="stat-label"><?php _e('Total Bookings', 'mhm-rentiva'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo esc_html($stats['monthly_revenue']); ?> <?php echo esc_html(get_option('mhm_rentiva_currency', 'USD')); ?></span>
                <span class="stat-label"><?php _e('This Month Revenue', 'mhm-rentiva'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo esc_html($stats['active_bookings']); ?></span>
                <span class="stat-label"><?php _e('Active Reservations', 'mhm-rentiva'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo esc_html($stats['occupancy_rate']); ?>%</span>
                <span class="stat-label"><?php _e('Occupancy Rate', 'mhm-rentiva'); ?></span>
            </div>
        </div>
<?php
    }

    public static function render_revenue_widget(): void
    {
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');

        Charts::render_revenue_chart($start_date, $end_date);
    }

    public static function get_dashboard_stats(): array
    {
        // Central cache management
        $stats = false;
        if (class_exists('\MHMRentiva\Admin\Core\Utilities\CacheManager')) {
            $stats = \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache('dashboard_stats');
        }

        if ($stats === false) {
            global $wpdb;

            // Total bookings
            $total_bookings = ReportRepository::get_total_bookings_count();

            // This month revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
            $current_month_start = date('Y-m-01');
            $current_month_end = date('Y-m-t');
            $monthly_revenue = ReportRepository::get_monthly_revenue_amount(
                $current_month_start,
                date('Y-m-d', strtotime($current_month_end . ' +1 day'))
            );

            // Active bookings
            $active_bookings = ReportRepository::get_active_bookings_count();

            // Occupancy rate (simple calculation)
            $total_vehicles = ReportRepository::get_total_vehicles_count();

            $occupancy_rate = 0;
            if ($total_vehicles > 0 && $active_bookings > 0) {
                $occupancy_rate = min(100, round(($active_bookings / $total_vehicles) * 100));
            }

            $stats = [
                'total_bookings' => number_format($total_bookings),
                'monthly_revenue' => number_format($monthly_revenue, 0, ',', '.'),
                'active_bookings' => number_format($active_bookings),
                'occupancy_rate' => $occupancy_rate,
            ];

            // Central cache management
            if (class_exists('\MHMRentiva\Admin\Core\Utilities\CacheManager')) {
                \MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache('dashboard_stats', '', $stats);
            }
        }

        return $stats;
    }

    public static function ajax_get_data(): void
    {
        check_ajax_referer('mhm_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
            return;
        }

        $type = sanitize_key($_POST['type'] ?? '');
        $start_date = sanitize_text_field((string) (($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'))) ?: date('Y-m-d', strtotime('-30 days'))));
        $end_date = sanitize_text_field((string) (($_POST['end_date'] ?? date('Y-m-d')) ?: date('Y-m-d')));

        // License check
        if (!Mode::featureEnabled(Mode::FEATURE_REPORTS_ADV)) {
            $max_days = Mode::reportsMaxRangeDays();
            $date_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);

            if ($date_diff > $max_days) {
                wp_send_json_error(__('Maximum 30 days of data can be displayed in Lite version.', 'mhm-rentiva'));
                return;
            }
        }

        $data = [];

        try {
            switch ($type) {
                case 'revenue':
                    $data = RevenueReport::get_data($start_date, $end_date);
                    break;
                case 'bookings':
                    $data = BookingReport::get_data($start_date, $end_date);
                    break;
                case 'vehicles':
                    $data = VehicleReport::get_data($start_date, $end_date);
                    break;
                case 'customers':
                    $data = CustomerReport::get_data($start_date, $end_date);
                    break;
                default:
                    wp_send_json_error(__('Invalid report type', 'mhm-rentiva'));
                    return;
            }

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Clear reports cache
     */
    public static function ajax_clear_cache(): void
    {
        check_ajax_referer('mhm_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
            return;
        }

        // Cache clearing
        $cache_keys = [
            'mhm_rentiva_reports_revenue',
            'mhm_rentiva_reports_bookings',
            'mhm_rentiva_reports_customers',
            'mhm_rentiva_reports_vehicles',
            'mhm_rentiva_dashboard_stats'
        ];

        foreach ($cache_keys as $key) {
            delete_transient($key);
        }

        wp_send_json_success(__('Cache cleared successfully', 'mhm-rentiva'));
    }

    /**
     * Clear reports cache - Internal function
     */
    private static function clear_reports_cache(): void
    {
        // Cache clearing
        $cache_keys = [
            'mhm_revenue_report_',
            'mhm_booking_report_',
            'mhm_customer_report_',
            'mhm_vehicle_report_',
            'mhm_rentiva_dashboard_stats'
        ];

        // Clear all cache keys
        global $wpdb;
        foreach ($cache_keys as $key_prefix) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $key_prefix . '%'
            ));
        }
    }

    public static function enqueue_scripts(string $hook): void
    {
        // Load only on reports page and dashboard
        if (strpos($hook, 'mhm-rentiva-reports') === false && $hook !== 'index.php') {
            return;
        }

        // Load core JavaScript files using AssetManager
        if (class_exists('MHMRentiva\\Admin\\Core\\AssetManager')) {
            \MHMRentiva\Admin\Core\AssetManager::enqueue_core_js();
        }

        // Load core CSS files in correct order
        wp_enqueue_style(
            'mhm-css-variables',
            plugins_url('assets/css/core/css-variables.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
            [],
            defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.2'
        );

        wp_enqueue_style(
            'mhm-core-css',
            plugins_url('assets/css/core/core.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
            ['mhm-css-variables'],
            defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.2'
        );

        wp_enqueue_style(
            'mhm-animations',
            plugins_url('assets/css/core/animations.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
            ['mhm-css-variables'],
            defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.2'
        );

        // Load statistics cards CSS
        wp_enqueue_style(
            'mhm-stats-cards',
            plugins_url('assets/css/components/stats-cards.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
            ['mhm-core-css'],
            defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.2'
        );

        // Load admin reports CSS
        wp_enqueue_style(
            'mhm-admin-reports',
            plugins_url('assets/css/admin/admin-reports.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
            ['mhm-core-css'],
            (defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.2') . '.4' // Add version for cache busting
        );

        // Reports JavaScript
        wp_enqueue_script(
            'mhm-admin-reports',
            plugins_url('assets/js/admin/reports.js', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
            ['jquery'],
            defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.2',
            true
        );

        // AJAX nonce for reports
        wp_localize_script('mhm-admin-reports', 'mhm_reports_nonce', ['nonce' => wp_create_nonce('mhm_reports_nonce')]);

        Charts::enqueue_scripts();
    }

    /**
     * Renders the main reports page
     */
    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Pro feature check
        $is_pro = Mode::featureEnabled(Mode::FEATURE_REPORTS_ADV);

        echo '<div class="wrap mhm-rentiva-reports-wrap">';
        echo '<h1>' . esc_html__('Reports', 'mhm-rentiva') . '</h1>';

        // Pro feature notices and Developer Mode banner
        \MHMRentiva\Admin\Core\ProFeatureNotice::displayPageProNotice('reports');

        // Statistics cards - at the top of page
        self::render_stats_cards();

        // Filters
        $start_date = isset($_GET['start_date']) ? sanitize_text_field((string) ($_GET['start_date'] ?: '')) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? sanitize_text_field((string) ($_GET['end_date'] ?: '')) : date('Y-m-d');

        // Date validation
        if (!strtotime($start_date) || !strtotime($end_date)) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = date('Y-m-d');
        }

        // Date sorting check
        if (strtotime($start_date) > strtotime($end_date)) {
            $temp = $start_date;
            $start_date = $end_date;
            $end_date = $temp;
        }

        // Cache clearing check - Only if date parameters exist
        if (isset($_GET['start_date']) || isset($_GET['end_date'])) {
            self::clear_reports_cache();
        }

        // Debug: Date filtering check
        if (defined('WP_DEBUG') && WP_DEBUG) {

            // Check available dates in database (using prepared statement for security)
            global $wpdb;
            $available_dates = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(post_date) as date, COUNT(*) as count 
                 FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND post_status = %s 
                 GROUP BY DATE(post_date) 
                 ORDER BY date DESC 
                 LIMIT 10",
                'vehicle_booking',
                'publish'
            ));
        }

        echo '<div class="mhm-rentiva-reports-filters">';
        echo '<form method="get" action="" id="reports-filter-form">';
        echo '<input type="hidden" name="page" value="mhm-rentiva-reports">';

        // Preserve current tab
        if (isset($_GET['tab'])) {
            echo '<input type="hidden" name="tab" value="' . esc_attr(sanitize_key($_GET['tab'])) . '">';
        }

        echo '<div class="filter-row">';
        echo '<label for="start_date">' . esc_html__('Start Date:', 'mhm-rentiva') . '</label>';
        echo '<input type="date" id="start_date" name="start_date" value="' . esc_attr($start_date) . '" required>';

        echo '<label for="end_date">' . esc_html__('End Date:', 'mhm-rentiva') . '</label>';
        echo '<input type="date" id="end_date" name="end_date" value="' . esc_attr($end_date) . '" required>';

        echo '<button type="submit" class="button button-primary" id="filter-button">' . esc_html__('Filter', 'mhm-rentiva') . '</button>';
        echo '<button type="button" class="button" id="reset-filter">' . esc_html__('Reset', 'mhm-rentiva') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';

        // Base report tabs (can be extended via filter hook)
        $tabs = [
            'overview' => __('Overview', 'mhm-rentiva'),
            'revenue' => __('Revenue Report', 'mhm-rentiva'),
            'bookings' => __('Booking Report', 'mhm-rentiva'),
            'vehicles' => __('Vehicle Report', 'mhm-rentiva'),
            'customers' => __('Customer Report', 'mhm-rentiva'),
        ];

        /**
         * Filter: Allow addons and third-party plugins to add custom report tabs
         * 
         * @param array<string, string> $tabs Array of tab_key => tab_label pairs
         * @return array Modified tabs array
         * 
         * @example
         * add_filter('mhm_rentiva_report_tabs', function($tabs) {
         *     $tabs['custom-report'] = __('Custom Report', 'my-plugin');
         *     return $tabs;
         * });
         */
        $tabs = apply_filters('mhm_rentiva_report_tabs', $tabs);

        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';

        echo '<div class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $label) {
            $active = $current_tab === $tab ? ' nav-tab-active' : '';
            $url = add_query_arg(['tab' => $tab, 'start_date' => $start_date, 'end_date' => $end_date]);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $active . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';

        // Tab content
        echo '<div class="tab-content">';

        // Check if custom tab rendering is handled via action hook
        $custom_tab_handled = false;

        /**
         * Action: Allow addons to render custom report tabs
         * 
         * @param string $current_tab Current tab key
         * @param string $start_date  Start date filter
         * @param string $end_date    End date filter
         * @param bool   $handled     Reference to indicate if tab was handled
         * 
         * @example
         * add_action('mhm_rentiva_render_report_tab', function($tab, $start_date, $end_date, &$handled) {
         *     if ($tab === 'custom-report') {
         *         echo '<h2>Custom Report</h2>';
         *         // Render custom report...
         *         $handled = true;
         *     }
         * }, 10, 4);
         */
        do_action_ref_array('mhm_rentiva_render_report_tab', [&$current_tab, &$start_date, &$end_date, &$custom_tab_handled]);

        // If custom tab was handled, skip default rendering
        if (!$custom_tab_handled) {
            switch ($current_tab) {
                case 'overview':
                    self::render_overview_tab($start_date, $end_date);
                    break;
                case 'revenue':
                    self::render_revenue_tab($start_date, $end_date);
                    break;
                case 'bookings':
                    self::render_bookings_tab($start_date, $end_date);
                    break;
                case 'vehicles':
                    self::render_vehicles_tab($start_date, $end_date);
                    break;
                case 'customers':
                    self::render_customers_tab($start_date, $end_date);
                    break;
                default:
                    // Default case for unknown tabs
                    echo '<p>' . esc_html__('Report for this section is not yet implemented.', 'mhm-rentiva') . '</p>';
                    break;
            }
        }

        echo '</div>';

        echo '</div>';
    }

    private static function render_overview_tab(string $start_date, string $end_date): void
    {
        // Get data - Real data based on date range
        $revenue_data = RevenueReport::get_data($start_date, $end_date);
        $booking_data = BookingReport::get_data($start_date, $end_date);
        $customer_data = CustomerReport::get_data($start_date, $end_date);
        $vehicle_data = VehicleReport::get_data($start_date, $end_date);
        $vehicle_categories_data = ReportRepository::get_vehicle_category_performance($start_date, $end_date);

        // Use Repository for customer data
        $real_customers = ReportRepository::get_customer_spending_data($start_date, $end_date);

        Templates::render('admin/reports/overview', [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'revenue_data' => $revenue_data,
            'booking_data' => $booking_data,
            'customer_data' => $customer_data,
            'vehicle_data' => $vehicle_data,
            'vehicle_categories_data' => $vehicle_categories_data,
            'real_customers' => $real_customers
        ]);
    }





    private static function render_revenue_tab(string $start_date, string $end_date): void
    {
        $data = RevenueReport::get_data($start_date, $end_date);

        Templates::render('admin/reports/revenue', [
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'data'       => $data
        ]);
    }

    private static function render_bookings_tab(string $start_date, string $end_date): void
    {
        $data = BookingReport::get_data($start_date, $end_date);

        Templates::render('admin/reports/bookings', [
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'data'       => $data
        ]);
    }

    private static function render_vehicles_tab(string $start_date, string $end_date): void
    {
        $data = VehicleReport::get_data($start_date, $end_date);
        $vehicle_categories_data = ReportRepository::get_vehicle_category_performance($start_date, $end_date);

        Templates::render('admin/reports/vehicles', [
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'data'       => $data,
            'vehicle_categories_data' => $vehicle_categories_data
        ]);
    }

    private static function render_customers_tab(string $start_date, string $end_date): void
    {
        $data = CustomerReport::get_data($start_date, $end_date);

        // Use Repository for customer data
        $real_customers = ReportRepository::get_customer_spending_data($start_date, $end_date);

        // Customer segments
        $customer_segments = [
            'new' => 0,
            'returning' => 0,
            'active' => 0,
            'total' => 0
        ];

        if (!empty($real_customers)) {
            $customer_segments['total'] = count($real_customers);
            $customer_segments['returning'] = count(array_filter($real_customers, function ($customer) {
                return $customer->booking_count > 1;
            }));
            $customer_segments['new'] = $customer_segments['total'] - $customer_segments['returning'];
            $customer_segments['active'] = $customer_segments['total'];
        }

        Templates::render('admin/reports/customers', [
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'customer_data' => $data,
            'real_customers' => $real_customers,
            'customer_segments' => $customer_segments
        ]);
    }

    /**
     * Render statistics cards
     */
    private static function render_stats_cards(): void
    {
        $stats = self::get_dashboard_stats();

        Templates::render('admin/reports/stats-cards', [
            'stats' => $stats,
            'currency_symbol' => self::get_currency_symbol()
        ]);
    }
}
