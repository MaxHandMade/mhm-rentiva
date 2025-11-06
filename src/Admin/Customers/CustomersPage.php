<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Customers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customers page class
 * 
 * This class displays the customers page.
 * Moved from Menu.php - safe refactoring process
 */
final class CustomersPage
{
    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    /**
     * Hook'ları kaydet
     * 
     * @return void
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        
        // Register AJAX actions
        add_action('wp_ajax_mhm_rentiva_get_customer_stats', [self::class, 'ajax_get_customer_stats']);
        add_action('wp_ajax_mhm_rentiva_get_customers_data', [self::class, 'ajax_get_customers_data']);
        add_action('wp_ajax_mhm_rentiva_bulk_action_customers', [self::class, 'ajax_bulk_action_customers']);
        add_action('wp_ajax_mhm_rentiva_get_customer_details', [self::class, 'ajax_get_customer_details']);
        add_action('wp_ajax_mhm_rentiva_export_customers', [self::class, 'ajax_export_customers']);
        
        // Create database indexes
        add_action('admin_init', [self::class, 'maybe_create_database_indexes']);
        
        // Register new customer page hooks
        AddCustomerPage::register();
    }

    /**
     * Render customers page
     * 
     * @return void
     */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check action parameters
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'add-customer':
                    AddCustomerPage::render();
                    return;
                case 'view':
                    self::render_customer_view();
                    return;
                case 'edit':
                    self::render_customer_edit();
                    return;
            }
        }

        // Assets already loaded via hook

        echo '<div class="wrap mhm-rentiva-wrap customers-page">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Customers', 'mhm-rentiva') . '</h1>';
        echo '<hr class="wp-header-end">';
        
        // Display Developer Mode banner and limit notices
        \MHMRentiva\Admin\Core\ProFeatureNotice::displayDeveloperModeAndLimits('customers', [
            __('Unlimited Customers', 'mhm-rentiva'),
            __('Advanced Customer Management', 'mhm-rentiva'),
        ]);
        
        // Customer statistics cards
        self::render_customer_stats();
        
        // Monthly booking calendar
        self::render_customer_calendar();
        
        // WordPress standart edit.php stili
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Customers', 'mhm-rentiva') . '</h1>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=add-customer')) . '" class="page-title-action">' . esc_html__('Add New Customer', 'mhm-rentiva') . '</a>';
        echo '<hr class="wp-header-end">';
        
        // Customer list table
        $customers_table = new \MHMRentiva\Admin\Utilities\ListTable\CustomersListTable();
        $customers_table->prepare_items();
        
        echo '<form method="post">';
        echo '<input type="hidden" name="page" value="mhm-rentiva-customers">';
        wp_nonce_field('mhm_rentiva_customers_bulk_action', 'mhm_rentiva_customers_nonce');
        
        $customers_table->display();
        
        echo '</form>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render customer monthly booking calendar - same structure as Tools page
     */
    private static function render_customer_calendar(): void
    {
        // Get month and year from URL parameters, otherwise use current month/year
        $current_month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
        $current_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        
        // Check invalid values
        if ($current_month < 1 || $current_month > 12) {
            $current_month = (int) date('n');
        }
        if ($current_year < 2020 || $current_year > 2030) {
            $current_year = (int) date('Y');
        }
        
        // Month names - Manual for global compatibility
        $month_names = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        $current_month_name = $month_names[$current_month];
        $days_in_month = (int)date('t', mktime(0, 0, 0, $current_month, 1, $current_year));
        $today = date('j');
        
        // Get customer registration dates
        $customer_registration_days = self::get_customer_registration_days($current_month, $current_year);
        
        ?>
        <div class="calendar-container customers-page">
            <div class="calendar-header">
                <button id="prevMonth">&lt;</button>
                <h2 id="monthYear"><?php echo esc_html($current_month_name . ' ' . $current_year); ?> - <?php _e('Customer Registrations', 'mhm-rentiva'); ?></h2>
                <button id="nextMonth">&gt;</button>
            </div>
            <div class="calendar-days">
                <div class="day-name"><?php _e('Mon', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Tue', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Wed', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Thu', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Fri', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Sat', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Sun', 'mhm-rentiva'); ?></div>
            </div>
            <div id="calendarDays" class="calendar-grid">
                <?php
                // Find which day of the week the first day of the month is
                $first_day_of_month = date('w', mktime(0, 0, 0, $current_month, 1, $current_year));
                // Adjust so Monday = 1, Sunday = 0
                $first_day_of_month = ($first_day_of_month == 0) ? 6 : $first_day_of_month - 1;
                
                // Last days of previous month
                $prev_month = $current_month == 1 ? 12 : $current_month - 1;
                $prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
                $prev_days_in_month = cal_days_in_month(CAL_GREGORIAN, $prev_month, $prev_year);
                
                for ($i = $first_day_of_month; $i > 0; $i--) {
                    $day = $prev_days_in_month - $i + 1;
                    echo '<div class="prev-date">' . $day . '</div>';
                }
                
                // Days of this month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $is_today = ($day == $today && $current_month == date('n') && $current_year == date('Y'));
                    $has_customer_registration = isset($customer_registration_days[$day]);
                    
                    $classes = [];
                    if ($is_today) $classes[] = 'today';
                    if ($has_customer_registration) $classes[] = 'customer-registered';
                    
                    echo '<div class="' . implode(' ', $classes) . '" data-day="' . $day . '">';
                    echo $day;
                    if ($has_customer_registration) {
                        $customer_info = $customer_registration_days[$day];
                        $customer_name = $customer_info['name'] ?? 'Unknown';
                        $customer_email = $customer_info['email'] ?? '';
                        echo '<span class="customer-icon" title="' . esc_attr($customer_name . ' - ' . $customer_email) . '">👤</span>';
                    }
                    echo '</div>';
                }
                
                // First days of next month
                $last_day_of_month = date('w', mktime(0, 0, 0, $current_month, (int)$days_in_month, $current_year));
                $last_day_of_month = ($last_day_of_month == 0) ? 6 : $last_day_of_month - 1;
                $next_days = 6 - $last_day_of_month;
                
                for ($j = 1; $j <= $next_days; $j++) {
                    echo '<div class="next-date">' . $j . '</div>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get customer data for calendar
     */
    private static function get_calendar_customers(): array
    {
        // Get customer data (example - adjust according to your actual data source)
        $customers = [];
        
        // Get customer data from WordPress users
        $users = get_users([
            'role' => 'customer',
            'number' => 20, // First 20 customers
            'orderby' => 'registered',
            'order' => 'DESC'
        ]);
        
        foreach ($users as $user) {
            $customers[] = [
                'id' => $user->ID,
                'name' => $user->display_name ?: $user->user_login,
                'email' => $user->user_email
            ];
        }
        
        // Add sample data if no customers
        if (empty($customers)) {
            $customers = [
                [
                    'id' => 1,
                    'name' => __('Sample Customer 1', 'mhm-rentiva'),
                    'email' => 'ornek1@example.com'
                ],
                [
                    'id' => 2,
                    'name' => __('Sample Customer 2', 'mhm-rentiva'),
                    'email' => 'ornek2@example.com'
                ]
            ];
        }
        
        return $customers;
    }

    /**
     * Load CSS and JS files directly (from render method)
     * 
     * @return void
     */
    private static function enqueue_assets_direct(): void
    {
        // Load core CSS and JS using AssetManager
        if (class_exists('MHMRentiva\\Admin\\Core\\AssetManager')) {
            \MHMRentiva\Admin\Core\AssetManager::enqueue_core_css();
            \MHMRentiva\Admin\Core\AssetManager::enqueue_core_js();
            \MHMRentiva\Admin\Core\AssetManager::enqueue_stats_cards();
            \MHMRentiva\Admin\Core\AssetManager::enqueue_calendars();
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
        
        // Component CSS files
        wp_enqueue_style(
            'mhm-stats-cards',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
            ['mhm-core-css'],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_style(
            'mhm-calendars',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/calendars.css',
            ['mhm-core-css'],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_style(
            'mhm-rentiva-customers',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/customers.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        // JavaScript dosyası
        wp_enqueue_script(
            'mhm-rentiva-customers',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );
        
        // Takvim JavaScript dosyası
        wp_enqueue_script(
            'mhm-customers-calendar',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers-calendar.js',
            [],
            MHM_RENTIVA_VERSION,
            true
        );
        
        // Localization
        wp_localize_script('mhm-customers-calendar', 'mhmCustomersCalendar', [
            'strings' => [
                'selectedDate' => __('Selected date', 'mhm-rentiva')
            ],
            'customerRegistrations' => self::get_customer_registration_days((int) (self::sanitize_text_field_safe($_GET['month'] ?? date('n'))), (int) (self::sanitize_text_field_safe($_GET['year'] ?? date('Y'))))
        ]);
        
        // AJAX için gerekli değişkenler
        wp_localize_script('mhm-rentiva-customers', 'mhm_rentiva_customers', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_rentiva_customers_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'mhm-rentiva'),
                'error' => __('An error occurred.', 'mhm-rentiva'),
                'success' => __('Operation successful.', 'mhm-rentiva'),
                'confirm_delete' => __('Are you sure you want to delete this customer?', 'mhm-rentiva'),
                'no_customers' => __('No customers found.', 'mhm-rentiva'),
            ]
        ]);
    }

    /**
     * Load CSS and JS files (via hook)
     * 
     * @param string $hook Current admin page hook
     * @return void
     */
    public static function enqueue_assets(string $hook): void
    {
        // Load only on customers page
        if (strpos($hook, 'mhm-rentiva-customers') === false) {
            return;
        }

        // CSS files - same structure as Tools page
        wp_enqueue_style(
            'mhm-stats-cards',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_style(
            'mhm-simple-calendars',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/simple-calendars.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_style(
            'mhm-rentiva-customers',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/customers.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        // JavaScript dosyası
        wp_enqueue_script(
            'mhm-rentiva-customers',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );
        
        // Takvim JavaScript dosyası
        wp_enqueue_script(
            'mhm-customers-calendar',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers-calendar.js',
            [],
            MHM_RENTIVA_VERSION,
            true
        );
        
        // Localization
        wp_localize_script('mhm-customers-calendar', 'mhmCustomersCalendar', [
            'strings' => [
                'selectedDate' => __('Selected date', 'mhm-rentiva')
            ],
            'customerRegistrations' => self::get_customer_registration_days((int) (self::sanitize_text_field_safe($_GET['month'] ?? date('n'))), (int) (self::sanitize_text_field_safe($_GET['year'] ?? date('Y'))))
        ]);
        
        // AJAX için gerekli değişkenler
        wp_localize_script('mhm-rentiva-customers', 'mhm_rentiva_customers', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_rentiva_customers_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'mhm-rentiva'),
                'error' => __('An error occurred.', 'mhm-rentiva'),
                'success' => __('Operation successful.', 'mhm-rentiva'),
                'confirm_delete' => __('Are you sure you want to delete this customer?', 'mhm-rentiva'),
                'no_customers' => __('No customers found.', 'mhm-rentiva'),
            ]
        ]);
    }

    /**
     * Render customer statistics cards
     * 
     * @return void
     */
    private static function render_customer_stats(): void
    {
        $stats = self::get_customer_stats();
        
        ?>
        <div class="mhm-stats-cards">
            <div class="stats-grid">
                <!-- Total Customers -->
                <div class="stat-card stat-card-total">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['total']); ?></div>
                        <div class="stat-label"><?php esc_html_e('TOTAL CUSTOMERS', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['total']); ?> <?php _e('Registered', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Active Customers -->
                <div class="stat-card stat-card-active">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['active']); ?></div>
                        <div class="stat-label"><?php esc_html_e('ACTIVE CUSTOMERS', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['active']); ?> <?php _e('Active', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- New Customers -->
                <div class="stat-card stat-card-new">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-plus-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['new']); ?></div>
                        <div class="stat-label"><?php esc_html_e('NEW THIS MONTH', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['new']); ?> <?php _e('New', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Monthly Average Customers -->
                <div class="stat-card stat-card-average">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['average']); ?></div>
                        <div class="stat-label"><?php esc_html_e('MONTHLY AVERAGE CUSTOMERS', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['average_trend']); ?> <?php _e('vs last month', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }


    /**
     * Get customer registration dates for specific month
     * 
     * @param int $month Month (1-12)
     * @param int $year Year
     * @return array Customer registration dates [day => customer_info]
     */
    private static function get_customer_registration_days(int $month, int $year): array
    {
        global $wpdb;
        
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, date('t', mktime(0, 0, 0, $month, 1, $year)));
        
        $query = $wpdb->prepare("
            SELECT 
                DAY(u.user_registered) as day,
                u.display_name as customer_name,
                u.user_email as customer_email
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhm_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1
                AND u.user_registered >= %s
                AND u.user_registered <= %s
            GROUP BY u.ID, u.user_email, u.display_name, u.user_registered
            ORDER BY day ASC
        ", $start_date, $end_date . ' 23:59:59');
        
        $results = $wpdb->get_results($query);
        
        $registration_days = [];
        foreach ($results as $result) {
            $day = (int) $result->day;
            $customer_name = $result->customer_name ?: 'Unknown';
            $customer_email = $result->customer_email;
            
            // Store as array if multiple customers on same day
            if (!isset($registration_days[$day])) {
                $registration_days[$day] = [];
            }
            
            $registration_days[$day][] = [
                'name' => $customer_name,
                'email' => $customer_email
            ];
        }
        
        return $registration_days;
    }

    /**
     * Belirli ay için rezervasyon günlerini al (optimize edilmiş)
     * 
     * @param int $month Ay (1-12)
     * @param int $year Yıl
     * @return array Rezervasyonlu günler
     */
    private static function get_booking_days(int $month, int $year): array
    {
        return CustomersOptimizer::get_booking_days_optimized($month, $year);
    }

    /**
     * Get customer statistics (optimized)
     * 
     * @return array
     */
    private static function get_customer_stats(): array
    {
        return CustomersOptimizer::get_customer_stats_optimized();
    }

    /**
     * AJAX: Get customer statistics
     */
    public static function ajax_get_customer_stats(): void
    {
        check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No permission.', 'mhm-rentiva'));
        }
        
        $stats = self::get_customer_stats();
        wp_send_json_success($stats);
    }

    /**
     * AJAX: Get customer data
     */
    public static function ajax_get_customers_data(): void
    {
        check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No permission.', 'mhm-rentiva'));
        }
        
        // Şimdilik boş veri döndür
        wp_send_json_success([]);
    }

    /**
     * AJAX: Bulk action process
     */
    public static function ajax_bulk_action_customers(): void
    {
        check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No permission.', 'mhm-rentiva'));
        }
        
        $action = self::sanitize_text_field_safe($_POST['bulk_action'] ?? '');
        $customer_ids = array_map('intval', $_POST['customer_ids'] ?? []);
        
        if (empty($action) || empty($customer_ids)) {
            wp_send_json_error(__('Invalid parameters.', 'mhm-rentiva'));
        }
        
        // Şimdilik başarı mesajı döndür
        wp_send_json_success([
            'message' => sprintf(__('%d customers processed.', 'mhm-rentiva'), count($customer_ids))
        ]);
    }

    /**
     * AJAX: Get customer details (optimized)
     */
    public static function ajax_get_customer_details(): void
    {
        check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No permission.', 'mhm-rentiva'));
        }
        
        $customer_id = intval($_POST['customer_id'] ?? 0);
        
        if (empty($customer_id)) {
            wp_send_json_error(__('Customer ID required.', 'mhm-rentiva'));
        }
        
        $customer_data = CustomersOptimizer::get_customer_details_optimized($customer_id);
        
        if (!$customer_data) {
            wp_send_json_error(__('Customer not found.', 'mhm-rentiva'));
        }
        
        wp_send_json_success($customer_data);
    }

    /**
     * AJAX: Export customers
     */
    public static function ajax_export_customers(): void
    {
        check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No permission.', 'mhm-rentiva'));
        }
        
        // Şimdilik başarı mesajı döndür
        wp_send_json_success([
            'message' => __('Export process started.', 'mhm-rentiva')
        ]);
    }

    /**
     * Render customer view page
     * 
     * @return void
     */
    private static function render_customer_view(): void
    {
        if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
            wp_die(__('Invalid customer ID.', 'mhm-rentiva'));
        }

        $customer_id = intval($_GET['customer_id']);
        $customer = get_user_by('id', $customer_id);
        
        if (!$customer) {
            wp_die(__('Customer not found.', 'mhm-rentiva'));
        }

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Customer Details', 'mhm-rentiva') . '</h1>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers')) . '" class="page-title-action">' . esc_html__('Customers List', 'mhm-rentiva') . '</a>';
        echo '<hr class="wp-header-end">';
        
        echo '<div class="customer-details">';
        echo '<table class="form-table">';
        echo '<tbody>';
        
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Customer Name', 'mhm-rentiva') . '</th>';
        echo '<td><strong>' . esc_html($customer->display_name) . '</strong></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Email', 'mhm-rentiva') . '</th>';
        echo '<td><a href="mailto:' . esc_attr($customer->user_email) . '">' . esc_html($customer->user_email) . '</a></td>';
        echo '</tr>';
        
        $phone = get_user_meta($customer_id, 'mhm_rentiva_phone', true);
        if ($phone) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Phone', 'mhm-rentiva') . '</th>';
            echo '<td>' . esc_html($phone) . '</td>';
            echo '</tr>';
        }
        
        $address = get_user_meta($customer_id, 'mhm_rentiva_address', true);
        if ($address) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Address', 'mhm-rentiva') . '</th>';
            echo '<td>' . esc_html($address) . '</td>';
            echo '</tr>';
        }
        
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Registration Date', 'mhm-rentiva') . '</th>';
        echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($customer->user_registered))) . '</td>';
        echo '</tr>';
        
        echo '</tbody>';
        echo '</table>';
        
        // Booking statistics
        $booking_stats = self::get_customer_booking_stats($customer_id);
        if ($booking_stats) {
            echo '<h2>' . esc_html__('Booking Statistics', 'mhm-rentiva') . '</h2>';
            echo '<table class="form-table">';
            echo '<tbody>';
            
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Total Bookings', 'mhm-rentiva') . '</th>';
            echo '<td>' . esc_html($booking_stats['booking_count']) . '</td>';
            echo '</tr>';
            
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Total Spending', 'mhm-rentiva') . '</th>';
            echo '<td>' . esc_html($booking_stats['total_spent']) . ' ' . esc_html($booking_stats['currency']) . '</td>';
            echo '</tr>';
            
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Last Booking', 'mhm-rentiva') . '</th>';
            echo '<td>' . esc_html($booking_stats['last_booking']) . '</td>';
            echo '</tr>';
            
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('First Booking', 'mhm-rentiva') . '</th>';
            echo '<td>' . esc_html($booking_stats['first_booking']) . '</td>';
            echo '</tr>';
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '</div>';
        
        echo '<p class="submit">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=edit&customer_id=' . $customer_id)) . '" class="button button-primary">' . esc_html__('Edit', 'mhm-rentiva') . '</a>';
        echo ' <a href="' . esc_url(admin_url('edit.php?post_type=vehicle_booking&customer_email=' . $customer->user_email)) . '" class="button">' . esc_html__('View Bookings', 'mhm-rentiva') . '</a>';
        echo ' <a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers')) . '" class="button">' . esc_html__('Go Back', 'mhm-rentiva') . '</a>';
        echo '</p>';
        
        echo '</div>';
    }

    /**
     * Get customer booking statistics (optimized)
     * 
     * @param int $customer_id
     * @return array|null
     */
    private static function get_customer_booking_stats(int $customer_id): ?array
    {
        $customer_data = CustomersOptimizer::get_customer_details_optimized($customer_id);
        
        if (!$customer_data) {
            return null;
        }
        
        return [
            'booking_count' => $customer_data['booking_count'],
            'total_spent' => $customer_data['total_spent'],
            'last_booking' => $customer_data['last_booking'],
            'first_booking' => $customer_data['first_booking'],
            'currency' => $customer_data['currency']
        ];
    }

    /**
     * Render customer edit page
     * 
     * @return void
     */
    private static function render_customer_edit(): void
    {
        if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
            wp_die(__('Invalid customer ID.', 'mhm-rentiva'));
        }

        $customer_id = intval($_GET['customer_id']);
        $customer = get_user_by('id', $customer_id);
        
        if (!$customer) {
            wp_die(__('Customer not found.', 'mhm-rentiva'));
        }

        // Form processing
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['mhm_rentiva_edit_customer_nonce'], 'mhm_rentiva_edit_customer')) {
            $customer_name = self::sanitize_text_field_safe($_POST['customer_name']);
            $customer_email = sanitize_email((string) ($_POST['customer_email'] ?? ''));
            $customer_phone = self::sanitize_text_field_safe($_POST['customer_phone']);
            $customer_address = sanitize_textarea_field((string) ($_POST['customer_address'] ?? ''));

            if (empty($customer_name) || empty($customer_email)) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Customer name and email fields are required.', 'mhm-rentiva') . '</p></div>';
            } else {
                // Update user information
                wp_update_user([
                    'ID' => $customer_id,
                    'display_name' => $customer_name,
                    'user_email' => $customer_email,
                    'first_name' => $customer_name,
                ]);
                
                // Update meta information
                update_user_meta($customer_id, 'mhm_rentiva_phone', $customer_phone);
                update_user_meta($customer_id, 'mhm_rentiva_address', $customer_address);
                
                // Clear cache
                \MHMRentiva\Admin\Customers\CustomersOptimizer::clear_cache($customer_id);
                
                echo '<div class="notice notice-success"><p>' . esc_html__('Customer information updated successfully.', 'mhm-rentiva') . '</p></div>';
                
                // Get updated information
                $customer = get_user_by('id', $customer_id);
            }
        }

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Edit Customer', 'mhm-rentiva') . '</h1>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $customer_id)) . '" class="page-title-action">' . esc_html__('View', 'mhm-rentiva') . '</a>';
        echo '<hr class="wp-header-end">';
        
        echo '<form method="post" action="">';
        wp_nonce_field('mhm_rentiva_edit_customer', 'mhm_rentiva_edit_customer_nonce');
        
        echo '<table class="form-table">';
        echo '<tbody>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="customer_name">' . esc_html__('Customer Name', 'mhm-rentiva') . '</label></th>';
        echo '<td><input name="customer_name" type="text" id="customer_name" value="' . esc_attr($customer->display_name) . '" class="regular-text" required /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="customer_email">' . esc_html__('Email', 'mhm-rentiva') . '</label></th>';
        echo '<td><input name="customer_email" type="email" id="customer_email" value="' . esc_attr($customer->user_email) . '" class="regular-text" required /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="customer_phone">' . esc_html__('Phone', 'mhm-rentiva') . '</label></th>';
        echo '<td><input name="customer_phone" type="tel" id="customer_phone" value="' . esc_attr(get_user_meta($customer_id, 'mhm_rentiva_phone', true)) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="customer_address">' . esc_html__('Address', 'mhm-rentiva') . '</label></th>';
        echo '<td><textarea name="customer_address" id="customer_address" rows="3" cols="50" class="large-text">' . esc_textarea(get_user_meta($customer_id, 'mhm_rentiva_address', true)) . '</textarea></td>';
        echo '</tr>';
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr__('Update', 'mhm-rentiva') . '">';
        echo ' <a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $customer_id)) . '" class="button">' . esc_html__('Cancel', 'mhm-rentiva') . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }

    /**
     * Create database indexes (runs once)
     * 
     * @return void
     */
    public static function maybe_create_database_indexes(): void
    {
        // Only for admin users and runs once
        if (!current_user_can('manage_options') || get_option('mhm_rentiva_customers_indexes_created')) {
            return;
        }

        // Create indexes
        $success = \MHMRentiva\Admin\Customers\CustomersOptimizer::create_database_indexes();
        
        if ($success) {
            // Mark that indexes have been created
            update_option('mhm_rentiva_customers_indexes_created', true);
        }
    }

}
