<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Reports;

use MHMRentiva\Admin\Reports\BusinessLogic\BookingReport;
use MHMRentiva\Admin\Reports\BusinessLogic\CustomerReport;
use MHMRentiva\Admin\Reports\BusinessLogic\RevenueReport;
use MHMRentiva\Admin\Vehicle\Reports\VehicleReport;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Booking\Core\Status;

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
        // ✅ CACHE OPTIMIZATION - Central cache management
        $stats = false;
        if (class_exists('\MHMRentiva\Admin\Core\Utilities\CacheManager')) {
            $stats = \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache('dashboard_stats');
        }

        if ($stats === false) {
            global $wpdb;

            // Total bookings
            $total_bookings = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                'vehicle_booking', 'publish'
            ));

            // This month revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
            $current_month_start = date('Y-m-01');
            $current_month_end = date('Y-m-t');
            $monthly_revenue = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status = 'publish'
                 AND pm.meta_key = '_mhm_total_price'
                 AND pm_status.meta_key = '_mhm_status'
                 AND pm_status.meta_value IN ('completed', 'confirmed')
                 AND p.post_date >= %s
                 AND p.post_date < %s",
                $current_month_start, date('Y-m-d', strtotime($current_month_end . ' +1 day'))
            ));

            // Active bookings
            $active_bookings = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status = 'publish'
                 AND pm.meta_key = '_mhm_status'
                 AND pm.meta_value IN ('confirmed', 'in_progress')"
            ));

            // Occupancy rate (simple calculation)
            $total_vehicles = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                'vehicle', 'publish'
            ));

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

            // ✅ CACHE OPTIMIZATION - Central cache management
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
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/css-variables.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_style(
            'mhm-core-css',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/core.css',
            ['mhm-css-variables'],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_style(
            'mhm-animations',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/animations.css',
            ['mhm-css-variables'],
            MHM_RENTIVA_VERSION
        );
        
        // Load statistics cards CSS
        wp_enqueue_style(
            'mhm-stats-cards',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
            ['mhm-core-css'],
            MHM_RENTIVA_VERSION
        );

        // Load admin reports CSS
        wp_enqueue_style(
            'mhm-admin-reports',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/admin-reports.css',
            ['mhm-core-css'],
            MHM_RENTIVA_VERSION . '.4' // Add version for cache busting
        );
        
        // Reports JavaScript
        wp_enqueue_script(
            'mhm-admin-reports',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/reports.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );
        
        // AJAX nonce for reports
        wp_localize_script('mhm-admin-reports', 'mhm_reports_nonce', wp_create_nonce('mhm_reports_nonce'));

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
            
            // Check available dates in database
            global $wpdb;
            $available_dates = $wpdb->get_results("SELECT DATE(post_date) as date, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = 'vehicle_booking' AND post_status = 'publish' GROUP BY DATE(post_date) ORDER BY date DESC LIMIT 10");
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

        // Report tabs
        $tabs = [
            'overview' => __('Overview', 'mhm-rentiva'),
            'revenue' => __('Revenue Report', 'mhm-rentiva'),
            'bookings' => __('Booking Report', 'mhm-rentiva'),
            'vehicles' => __('Vehicle Report', 'mhm-rentiva'),
            'customers' => __('Customer Report', 'mhm-rentiva'),
        ];

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
        }
        echo '</div>';

        echo '</div>';
    }

    private static function render_overview_tab(string $start_date, string $end_date): void
    {
        echo '<div class="mhm-rentiva-overview">';

        // Overview title
        echo '<div class="overview-header">';
        echo '<h2>' . esc_html__('Overview Dashboard', 'mhm-rentiva') . '</h2>';
        echo '<p class="overview-description">' . sprintf(
            esc_html__('Key metrics and trends for %s to %s', 'mhm-rentiva'),
            date('d.m.Y', strtotime($start_date)),
            date('d.m.Y', strtotime($end_date))
        ) . '</p>';
        echo '</div>';

        // Simple cards - Grid layout
        self::render_overview_cards($start_date, $end_date);

        echo '</div>';
    }

    private static function render_overview_cards(string $start_date, string $end_date): void
    {
        // Get data - Real data based on date range
        $revenue_data = RevenueReport::get_data($start_date, $end_date);
        $booking_data = BookingReport::get_data($start_date, $end_date);
        $customer_data = CustomerReport::get_data($start_date, $end_date);
        $vehicle_data = VehicleReport::get_data($start_date, $end_date);
        
        // Debug: Check current data
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }

        // Debug: Check data structure (development only)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            global $wpdb;
            
            // Real data check in database
            $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'vehicle_booking' AND post_status = 'publish'");
            $total_vehicles = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'vehicle' AND post_status = 'publish'");
            
            
        }

        echo '<div class="overview-cards-grid">';
        
        // Revenue Analytics Card
        echo '<div class="analytics-card revenue-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Revenue Analytics', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">📊</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-chart">';
        echo '<div class="chart-bars">';
        
        // Revenue trend bars (sample data)
        $daily_revenue = [];
        $max_revenue = 1;
        
        // Safe data retrieval
        if (isset($revenue_data['daily'])) {
            $raw_data = $revenue_data['daily'];
            
            // Convert stdClass to array if needed
            if (is_object($raw_data)) {
                $daily_revenue = json_decode(json_encode($raw_data), true);
            } elseif (is_array($raw_data)) {
                $daily_revenue = $raw_data;
            }
            
            // Maximum calculation
            if (!empty($daily_revenue) && is_array($daily_revenue)) {
                $revenues = [];
                foreach ($daily_revenue as $item) {
                    if (is_array($item) && isset($item['revenue'])) {
                        $revenues[] = $item['revenue'];
                    } elseif (is_object($item) && isset($item->revenue)) {
                        $revenues[] = $item->revenue;
                    }
                }
                if (!empty($revenues)) {
                    $max_revenue = max($revenues);
                }
            }
        }
        
        // Create 7-day bar
        for ($i = 0; $i < 7; $i++) {
            $day_revenue = 0;
            
            // Safe data access
            if (!empty($daily_revenue) && is_array($daily_revenue) && isset($daily_revenue[$i])) {
                $item = $daily_revenue[$i];
                if (is_array($item) && isset($item['revenue'])) {
                    $day_revenue = $item['revenue'];
                } elseif (is_object($item) && isset($item->revenue)) {
                    $day_revenue = $item->revenue;
                }
            }
            
            // Leave zero if no data
            
            $bar_height = $max_revenue > 0 ? ($day_revenue / $max_revenue) * 100 : 0;
            $min_height = $day_revenue > 0 ? 20 : 5; // Minimum visible height
            $final_height = max($bar_height, $min_height);
            echo '<div class="chart-bar" style="height: ' . $final_height . '%;">';
            echo '<div class="bar-value">' . number_format((float)$day_revenue, 0, ',', '.') . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<div class="chart-labels">';
        echo '<span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="analytics-metrics">';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Total Revenue</span>';
        echo '<span class="metric-value">' . number_format((float)($revenue_data['total'] ?? 0), 0, ',', '.') . self::get_currency_symbol() . '</span>';
        echo '</div>';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Daily Average</span>';
        echo '<span class="metric-value">' . number_format((float)($revenue_data['avg_daily'] ?? 0), 0, ',', '.') . self::get_currency_symbol() . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Bookings Analytics Card
        echo '<div class="analytics-card bookings-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Bookings Analytics', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">📈</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-chart">';
        echo '<div class="chart-bars">';
        
        // Booking status bars - based on data structure from debug log
        $booking_statuses = [
            'confirmed' => 0,
            'pending' => 0,
            'cancelled' => 0,
            'completed' => 0
        ];
        
        // Debug log has status_distribution array
        if (is_array($booking_data) && isset($booking_data['status_distribution'])) {
            foreach ($booking_data['status_distribution'] as $status_item) {
                if (is_object($status_item) && isset($status_item->status) && isset($status_item->count)) {
                    $status = $status_item->status;
                    if (isset($booking_statuses[$status])) {
                        $booking_statuses[$status] = (int)$status_item->count;
                    }
                }
            }
        }
        
        $max_bookings = max($booking_statuses) ?: 1;
        
        foreach ($booking_statuses as $status => $count) {
            $bar_height = $max_bookings > 0 ? ($count / $max_bookings) * 100 : 0;
            $min_height = $count > 0 ? 20 : 5; // Minimum visible height
            $final_height = max($bar_height, $min_height);
            echo '<div class="chart-bar ' . $status . '" style="height: ' . $final_height . '%;">';
            echo '<div class="bar-value">' . $count . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<div class="chart-labels">';
        echo '<span>Confirmed</span><span>Pending</span><span>Cancelled</span><span>Completed</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="analytics-metrics">';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Total Bookings</span>';
        echo '<span class="metric-value">' . number_format((int)($booking_data['total_bookings'] ?? 0)) . '</span>';
        echo '</div>';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Success Rate</span>';
        echo '<span class="metric-value">%' . (100 - ((float)($booking_data['cancellation_rate'] ?? 0))) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Customer Analytics Card
        echo '<div class="analytics-card customers-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Customer Analytics', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">👥</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-chart">';
        echo '<div class="chart-bars">';
        
        // Customer segments - Real data based on date range
        $customer_segments = [
            'new' => 0,
            'returning' => 0,
            'active' => 0,
            'total' => 0
        ];
        
        // Get customer data based on date range
        global $wpdb;
        
        // Find customers who made bookings in selected date range
        $real_customers = $wpdb->get_results($wpdb->prepare("
            SELECT 
                pm_email.meta_value as customer_email,
                pm_name.meta_value as customer_name,
                COUNT(*) as booking_count,
                SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) as total_spent,
                MAX(p.post_date) as last_booking
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id 
                AND pm_email.meta_key = '_mhm_customer_email'
            LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id 
                AND pm_name.meta_key = '_mhm_customer_name'
            LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id 
                AND pm_price.meta_key = '_mhm_total_price'
            WHERE p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
                AND pm_email.meta_value IS NOT NULL
                AND pm_email.meta_value != ''
                AND DATE(p.post_date) BETWEEN %s AND %s
            GROUP BY pm_email.meta_value
            ORDER BY total_spent DESC
        ", $start_date, $end_date));
        
        if (!empty($real_customers)) {
            $customer_segments['total'] = count($real_customers);
            $customer_segments['returning'] = count(array_filter($real_customers, function($customer) {
                return $customer->booking_count > 1;
            }));
            $customer_segments['new'] = $customer_segments['total'] - $customer_segments['returning'];
            $customer_segments['active'] = $customer_segments['total'];
        }
        
        // Debug: Check customer segments data
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        
        $max_customers = max($customer_segments) ?: 1;
        
        foreach ($customer_segments as $segment => $count) {
            $bar_height = $max_customers > 0 ? ($count / $max_customers) * 100 : 0;
            $min_height = $count > 0 ? 20 : 5; // Minimum visible height
            $final_height = max($bar_height, $min_height);
            echo '<div class="chart-bar ' . $segment . '" style="height: ' . $final_height . '%;">';
            echo '<div class="bar-value">' . $count . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<div class="chart-labels">';
        echo '<span>New</span><span>Returning</span><span>Active</span><span>Total</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="analytics-metrics">';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Total Customers</span>';
        echo '<span class="metric-value">' . number_format((int)($customer_data['summary']['total_customers'] ?? 0)) . '</span>';
        echo '</div>';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Retention Rate</span>';
        echo '<span class="metric-value">%' . ((float)($customer_data['summary']['retention_rate'] ?? 0)) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Vehicle Analytics Card
        echo '<div class="analytics-card vehicles-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Vehicle Analytics', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">🚗</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-chart">';
        echo '<div class="chart-bars">';
        
        // Vehicle performance bars - Real data based on date range
        $vehicle_performance = [
            'hatchback' => 0,
            'sedan' => 0,
            'suv' => 0,
            'coupe' => 0
        ];
        
        // Get vehicle categories based on date range
        global $wpdb;
        
        $vehicle_categories = $wpdb->get_results($wpdb->prepare("
            SELECT 
                t.name as category_name,
                COUNT(DISTINCT p.ID) as vehicle_count,
                COUNT(DISTINCT b.ID) as booking_count
            FROM {$wpdb->terms} t
            LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID 
                AND p.post_type = 'vehicle' 
                AND p.post_status = 'publish'
            LEFT JOIN {$wpdb->posts} b ON p.ID = (
                SELECT pm_vehicle.meta_value 
                FROM {$wpdb->postmeta} pm_vehicle 
                WHERE pm_vehicle.post_id = b.ID 
                AND pm_vehicle.meta_key = '_mhm_vehicle_id'
            )
            AND b.post_type = 'vehicle_booking'
            AND b.post_status = 'publish'
            AND DATE(b.post_date) BETWEEN %s AND %s
            WHERE tt.taxonomy = 'vehicle_category'
            GROUP BY t.term_id, t.name
            ORDER BY vehicle_count DESC
        ", $start_date, $end_date));
        
        if (!empty($vehicle_categories)) {
            foreach ($vehicle_categories as $category) {
                $category_name = strtolower($category->category_name);
                $booking_count = (int)$category->booking_count; // Number of bookings in date range
                
                switch ($category_name) {
                    case 'hatchback':
                        $vehicle_performance['hatchback'] = $booking_count;
                        break;
                    case 'sedan':
                        $vehicle_performance['sedan'] = $booking_count;
                        break;
                    case 'suv':
                        $vehicle_performance['suv'] = $booking_count;
                        break;
                    case 'coupe':
                        $vehicle_performance['coupe'] = $booking_count;
                        break;
                }
            }
        }
        
        // Debug: Check vehicle performance data
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        
        $max_occupancy = max($vehicle_performance) ?: 1;
        
        foreach ($vehicle_performance as $category => $vehicle_count) {
            $bar_height = $max_occupancy > 0 ? ($vehicle_count / $max_occupancy) * 100 : 0;
            $min_height = $vehicle_count > 0 ? 20 : 5; // Minimum visible height
            $final_height = max($bar_height, $min_height);
            echo '<div class="chart-bar ' . $category . '" style="height: ' . $final_height . '%;">';
            echo '<div class="bar-value">' . $vehicle_count . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<div class="chart-labels">';
        echo '<span>Hatchback</span><span>Sedan</span><span>SUV</span><span>Coupe</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="analytics-metrics">';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Total Vehicles</span>';
        echo '<span class="metric-value">' . number_format((int)($vehicle_data['summary']['total_vehicles'] ?? 0)) . '</span>';
        echo '</div>';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Avg Occupancy</span>';
        echo '<span class="metric-value">%' . ((float)($vehicle_data['summary']['avg_occupancy_rate'] ?? 0)) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    private static function render_revenue_tab(string $start_date, string $end_date): void
    {
        $data = RevenueReport::get_data($start_date, $end_date);

        echo '<div class="mhm-rentiva-revenue-report">';

        // Revenue title
        echo '<div class="overview-header">';
        echo '<h2>' . esc_html__('Revenue Report', 'mhm-rentiva') . '</h2>';
        echo '<p class="overview-description">' . sprintf(
            esc_html__('Revenue analysis and trends for %s to %s', 'mhm-rentiva'),
            date('d.m.Y', strtotime($start_date)),
            date('d.m.Y', strtotime($end_date))
        ) . '</p>';
        echo '</div>';

        // Revenue analytics cards
        echo '<div class="overview-cards-grid">';
        
        // Total Revenue Card
        echo '<div class="analytics-card revenue-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Total Revenue', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">💰</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-metrics">';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Total Revenue</span>';
        echo '<span class="metric-value">' . number_format((float)($data['total'] ?? 0), 0, ',', '.') . self::get_currency_symbol() . '</span>';
        echo '</div>';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Daily Average</span>';
        echo '<span class="metric-value">' . number_format((float)(($data['total'] ?? 0) / max(1, count($data['daily'] ?? []))), 0, ',', '.') . self::get_currency_symbol() . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Revenue Trend Card
        echo '<div class="analytics-card bookings-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Revenue Trend', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">📈</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-chart">';
        echo '<div class="chart-bars">';
        
        // Last 7 days revenue trend
        $daily_revenue = $data['daily'] ?? [];
        $max_revenue = 1;
        
        if (!empty($daily_revenue)) {
            $revenues = [];
            foreach ($daily_revenue as $item) {
                if (is_object($item) && isset($item->revenue)) {
                    $revenues[] = (float)$item->revenue;
                } elseif (is_array($item) && isset($item['revenue'])) {
                    $revenues[] = (float)$item['revenue'];
                }
            }
            if (!empty($revenues)) {
                $max_revenue = max($revenues);
            }
        }
        
        // Create 7-day bar
        for ($i = 0; $i < 7; $i++) {
            $day_revenue = 0;
            
            if (!empty($daily_revenue) && isset($daily_revenue[$i])) {
                $item = $daily_revenue[$i];
                if (is_object($item) && isset($item->revenue)) {
                    $day_revenue = (float)$item->revenue;
                } elseif (is_array($item) && isset($item['revenue'])) {
                    $day_revenue = (float)$item['revenue'];
                }
            }
            
            $bar_height = $max_revenue > 0 ? ($day_revenue / $max_revenue) * 100 : 0;
            $min_height = $day_revenue > 0 ? 20 : 5;
            $final_height = max($bar_height, $min_height);
            echo '<div class="chart-bar" style="height: ' . $final_height . '%;">';
            echo '<div class="bar-value">' . number_format($day_revenue, 0, ',', '.') . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<div class="chart-labels">';
        echo '<span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';

        // Detail table
        echo '<div class="data-table-container">';
        echo '<h3>' . esc_html__('Daily Revenue Details', 'mhm-rentiva') . '</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Date', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Revenue', 'mhm-rentiva') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($data['daily'] as $day) {
            echo '<tr>';
            echo '<td>' . esc_html(date('d.m.Y', strtotime($day->date))) . '</td>';
            echo '<td>' . number_format((float)$day->revenue, 2, ',', '.') . self::get_currency_symbol() . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        echo '</div>';
    }

    private static function render_bookings_tab(string $start_date, string $end_date): void
    {
        $data = BookingReport::get_data($start_date, $end_date);

        echo '<div class="mhm-rentiva-booking-report">';

        // Booking title
        echo '<div class="overview-header">';
        echo '<h2>' . esc_html__('Booking Report', 'mhm-rentiva') . '</h2>';
        echo '<p class="overview-description">' . sprintf(
            esc_html__('Booking analysis and status distribution for %s to %s', 'mhm-rentiva'),
            date('d.m.Y', strtotime($start_date)),
            date('d.m.Y', strtotime($end_date))
        ) . '</p>';
        echo '</div>';

        // Booking analytics cards
        echo '<div class="overview-cards-grid">';
        
        // Total Bookings Card
        echo '<div class="analytics-card revenue-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Total Bookings', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">📅</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-metrics">';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Total Bookings</span>';
        echo '<span class="metric-value">' . number_format((int)($data['total_bookings'] ?? 0)) . '</span>';
        echo '</div>';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Cancellation Rate</span>';
        echo '<span class="metric-value">%' . ($data['cancellation_rate'] ?? 0) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Booking Status Distribution Card
        echo '<div class="analytics-card bookings-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Status Distribution', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">📊</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-chart">';
        echo '<div class="chart-bars">';
        
        // Booking status distribution
        $status_distribution = $data['status_distribution'] ?? [];
        $booking_statuses = [
            'confirmed' => 0,
            'pending' => 0,
            'cancelled' => 0,
            'completed' => 0
        ];
        
        if (!empty($status_distribution)) {
            foreach ($status_distribution as $status) {
                $status_name = strtolower($status->status ?? '');
                if (isset($booking_statuses[$status_name])) {
                    $booking_statuses[$status_name] = (int)$status->count;
                }
            }
        }
        
        $max_bookings = max($booking_statuses) ?: 1;
        
        foreach ($booking_statuses as $status => $count) {
            $bar_height = $max_bookings > 0 ? ($count / $max_bookings) * 100 : 0;
            $min_height = $count > 0 ? 20 : 5;
            $final_height = max($bar_height, $min_height);
            echo '<div class="chart-bar ' . $status . '" style="height: ' . $final_height . '%;">';
            echo '<div class="bar-value">' . $count . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<div class="chart-labels">';
        echo '<span>Confirmed</span><span>Pending</span><span>Cancelled</span><span>Completed</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';

        // Detail table
        echo '<div class="data-table-container">';
        echo '<h3>' . esc_html__('Status Distribution Details', 'mhm-rentiva') . '</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Status', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Count', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Percentage', 'mhm-rentiva') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($data['status_distribution'] as $status) {
            $percentage = $data['total_bookings'] > 0 ? round(($status->count / $data['total_bookings']) * 100, 1) : 0;
            echo '<tr>';
            echo '<td>' . esc_html(Status::get_label($status->status)) . '</td>';
            echo '<td>' . number_format((int)$status->count) . '</td>';
            echo '<td>%' . $percentage . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        echo '</div>';
    }

    private static function render_vehicles_tab(string $start_date, string $end_date): void
    {
        $data = VehicleReport::get_data($start_date, $end_date);

        echo '<div class="mhm-rentiva-vehicle-report">';

        // Vehicle title
        echo '<div class="overview-header">';
        echo '<h2>' . esc_html__('Vehicle Report', 'mhm-rentiva') . '</h2>';
        echo '<p class="overview-description">' . sprintf(
            esc_html__('Vehicle performance and rental analysis for %s to %s', 'mhm-rentiva'),
            date('d.m.Y', strtotime($start_date)),
            date('d.m.Y', strtotime($end_date))
        ) . '</p>';
        echo '</div>';

        // Vehicle analytics cards
        echo '<div class="overview-cards-grid">';
        
        // Vehicle Performance Card
        echo '<div class="analytics-card vehicles-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Vehicle Performance', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">🚗</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-chart">';
        echo '<div class="chart-bars">';
        
        // Vehicle category performance - Real data based on date range
        $vehicle_categories = [
            'hatchback' => 0,
            'sedan' => 0,
            'suv' => 0,
            'coupe' => 0
        ];
        
        // Get vehicle categories based on date range
        global $wpdb;
        
        $vehicle_categories_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                t.name as category_name,
                COUNT(DISTINCT b.ID) as booking_count
            FROM {$wpdb->terms} t
            LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID 
                AND p.post_type = 'vehicle' 
                AND p.post_status = 'publish'
            LEFT JOIN {$wpdb->posts} b ON p.ID = (
                SELECT pm_vehicle.meta_value 
                FROM {$wpdb->postmeta} pm_vehicle 
                WHERE pm_vehicle.post_id = b.ID 
                AND pm_vehicle.meta_key = '_mhm_vehicle_id'
            )
            AND b.post_type = 'vehicle_booking'
            AND b.post_status = 'publish'
            AND DATE(b.post_date) BETWEEN %s AND %s
            WHERE tt.taxonomy = 'vehicle_category'
            GROUP BY t.term_id, t.name
            ORDER BY booking_count DESC
        ", $start_date, $end_date));
        
        if (!empty($vehicle_categories_data)) {
            foreach ($vehicle_categories_data as $category) {
                $category_name = strtolower($category->category_name);
                $booking_count = (int)$category->booking_count; // Number of bookings in date range
                
                switch ($category_name) {
                    case 'hatchback':
                        $vehicle_categories['hatchback'] = $booking_count;
                        break;
                    case 'sedan':
                        $vehicle_categories['sedan'] = $booking_count;
                        break;
                    case 'suv':
                        $vehicle_categories['suv'] = $booking_count;
                        break;
                    case 'coupe':
                        $vehicle_categories['coupe'] = $booking_count;
                        break;
                }
            }
        }
        
        // Debug: Check vehicle categories data
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        
        $max_vehicles = max($vehicle_categories) ?: 1;
        
        foreach ($vehicle_categories as $category => $count) {
            $bar_height = $max_vehicles > 0 ? ($count / $max_vehicles) * 100 : 0;
            $min_height = $count > 0 ? 20 : 5;
            $final_height = max($bar_height, $min_height);
            echo '<div class="chart-bar ' . $category . '" style="height: ' . $final_height . '%;">';
            echo '<div class="bar-value">' . $count . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<div class="chart-labels">';
        echo '<span>Hatchback</span><span>Sedan</span><span>SUV</span><span>Coupe</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Top Vehicles Card
        echo '<div class="analytics-card customers-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Top Vehicles', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">🏆</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-metrics">';
        
        // Top 3 vehicles
        $top_vehicles = $data['top_vehicles'] ?? [];
        $display_count = 0;
        
        foreach ($top_vehicles as $vehicle) {
            if ($display_count >= 3) break;
            
            echo '<div class="metric-row">';
            echo '<span class="metric-label">' . esc_html($vehicle->vehicle_title ?? 'Unknown') . '</span>';
            echo '<span class="metric-value">' . number_format((int)($vehicle->booking_count ?? 0)) . ' rentals</span>';
            echo '</div>';
            
            $display_count++;
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';

        // Detail table
        echo '<div class="data-table-container">';
        echo '<h3>' . esc_html__('Most Rented Vehicles', 'mhm-rentiva') . '</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Vehicle', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Rental Count', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Total Revenue', 'mhm-rentiva') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($data['top_vehicles'] as $vehicle) {
            echo '<tr>';
            echo '<td>' . esc_html($vehicle->vehicle_title) . '</td>';
            echo '<td>' . number_format((int)$vehicle->booking_count) . '</td>';
            echo '<td>' . number_format((float)$vehicle->total_revenue, 0, ',', '.') . self::get_currency_symbol() . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        echo '</div>';
    }

    private static function render_customers_tab(string $start_date, string $end_date): void
    {
        $data = CustomerReport::get_data($start_date, $end_date);

        echo '<div class="mhm-rentiva-customer-report">';

        // Customer title
        echo '<div class="overview-header">';
        echo '<h2>' . esc_html__('Customer Report', 'mhm-rentiva') . '</h2>';
        echo '<p class="overview-description">' . sprintf(
            esc_html__('Customer analysis and spending patterns for %s to %s', 'mhm-rentiva'),
            date('d.m.Y', strtotime($start_date)),
            date('d.m.Y', strtotime($end_date))
        ) . '</p>';
        echo '</div>';

        // Customer segments - Real data based on date range
        $customer_segments = [
            'new' => 0,
            'returning' => 0,
            'active' => 0,
            'total' => 0
        ];
        
        // Get customer data based on date range
        global $wpdb;
        
        // Find customers who made bookings in selected date range
        $real_customers = $wpdb->get_results($wpdb->prepare("
            SELECT 
                pm_email.meta_value as customer_email,
                pm_name.meta_value as customer_name,
                COUNT(*) as booking_count,
                SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) as total_spent,
                MAX(p.post_date) as last_booking
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id 
                AND pm_email.meta_key = '_mhm_customer_email'
            LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id 
                AND pm_name.meta_key = '_mhm_customer_name'
            LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id 
                AND pm_price.meta_key = '_mhm_total_price'
            WHERE p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
                AND pm_email.meta_value IS NOT NULL
                AND pm_email.meta_value != ''
                AND DATE(p.post_date) BETWEEN %s AND %s
            GROUP BY pm_email.meta_value
            ORDER BY total_spent DESC
        ", $start_date, $end_date));
        
        if (!empty($real_customers)) {
            $customer_segments['total'] = count($real_customers);
            $customer_segments['returning'] = count(array_filter($real_customers, function($customer) {
                return $customer->booking_count > 1;
            }));
            $customer_segments['new'] = $customer_segments['total'] - $customer_segments['returning'];
            $customer_segments['active'] = $customer_segments['total'];
        }
        
        // Debug: Check customer segments data
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }

        // Customer analytics cards
        echo '<div class="overview-cards-grid">';
        
        // Customer Summary Card - With real data
        echo '<div class="analytics-card customers-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Customer Summary', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">👥</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-metrics">';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Total Customers</span>';
        echo '<span class="metric-value">' . number_format((int)$customer_segments['total']) . '</span>';
        echo '</div>';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Repeat Customers</span>';
        echo '<span class="metric-value">' . number_format((int)$customer_segments['returning']) . '</span>';
        echo '</div>';
        echo '<div class="metric-row">';
        echo '<span class="metric-label">Average Spending</span>';
        $avg_spending = !empty($real_customers) ? array_sum(array_column($real_customers, 'total_spent')) / count($real_customers) : 0;
        echo '<span class="metric-value">' . number_format((float)$avg_spending, 2, ',', '.') . self::get_currency_symbol() . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Customer Lifecycle Card
        echo '<div class="analytics-card customers-analytics">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Customer Lifecycle', 'mhm-rentiva') . '</h3>';
        echo '<span class="card-icon">📈</span>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="analytics-chart">';
        echo '<div class="chart-bars">';
        
        $max_customers = max($customer_segments) ?: 1;
        
        foreach ($customer_segments as $segment => $count) {
            $bar_height = $max_customers > 0 ? ($count / $max_customers) * 100 : 0;
            $min_height = $count > 0 ? 20 : 5;
            $final_height = max($bar_height, $min_height);
            echo '<div class="chart-bar ' . $segment . '" style="height: ' . $final_height . '%;">';
            echo '<div class="bar-value">' . $count . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<div class="chart-labels">';
        echo '<span>New</span><span>Returning</span><span>Active</span><span>Total</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';

        // Detail table
        echo '<div class="data-table-container">';
        echo '<h3>' . esc_html__('Top Spending Customers', 'mhm-rentiva') . '</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Customer', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Booking Count', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Total Spending', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Last Booking', 'mhm-rentiva') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        // Use real customer data
        if (!empty($real_customers)) {
            foreach ($real_customers as $customer) {
            echo '<tr>';
                echo '<td>' . esc_html($customer->customer_name) . '<br><small>' . esc_html($customer->customer_email) . '</small></td>';
            echo '<td>' . number_format((int)$customer->booking_count) . '</td>';
                echo '<td>' . number_format((float)$customer->total_spent, 2, ',', '.') . self::get_currency_symbol() . '</td>';
                echo '<td>' . esc_html(date('d.m.Y', strtotime($customer->last_booking))) . '</td>';
            echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">' . esc_html__('No customer data found.', 'mhm-rentiva') . '</td></tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render statistics cards
     */
    private static function render_stats_cards(): void
    {
        $stats = self::get_dashboard_stats();
        
        ?>
        <div class="mhm-stats-cards">
            <div class="stats-grid">
                <!-- Total bookings -->
                <div class="stat-card stat-card-total-bookings">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['total_bookings']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Total Bookings', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['total_bookings']); ?> <?php esc_html_e('Total', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Monthly Revenue -->
                <div class="stat-card stat-card-monthly-revenue">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['monthly_revenue']); ?><?php echo esc_html(self::get_currency_symbol()); ?></div>
                        <div class="stat-label"><?php esc_html_e('This Month Revenue', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['monthly_revenue']); ?><?php echo esc_html(self::get_currency_symbol()); ?> <?php esc_html_e('This month', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Active bookings -->
                <div class="stat-card stat-card-active-bookings">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['active_bookings']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Active Reservations', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['active_bookings']); ?> <?php esc_html_e('Ongoing', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Occupancy Rate -->
                <div class="stat-card stat-card-occupancy-rate">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-pie"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['occupancy_rate']); ?>%</div>
                        <div class="stat-label"><?php esc_html_e('Occupancy Rate', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['occupancy_rate']); ?>% <?php esc_html_e('Capacity', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
