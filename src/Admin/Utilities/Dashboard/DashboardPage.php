<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Dashboard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load plugin textdomain for translations
 * 
 * This function loads the plugin's language files to enable translation support.
 * It should only be defined once to avoid conflicts.
 */
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../../languages/');
    }
    mhm_rentiva_load_textdomain();
}

/**
 * Dashboard page class
 * 
 * This class displays the main control panel.
 * Moved from Menu.php - safe refactoring process
 */
final class DashboardPage
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
     * Register WordPress hooks and actions for dashboard functionality
     * 
     * This method sets up all necessary WordPress hooks:
     * - Enqueues scripts and styles for dashboard page
     * - Registers AJAX handlers for data refresh and cache clearing
     * - Sets up cache clearing hooks when posts are saved/deleted
     * 
     * @return void
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        // Remove admin_notices hook - no longer needed
        // add_action('admin_notices', [self::class, 'add_dashboard_stats_cards']);
        
        add_action('wp_ajax_mhm_refresh_dashboard_data', [self::class, 'ajax_refresh_dashboard_data']);
        add_action('wp_ajax_mhm_clear_dashboard_cache', [self::class, 'ajax_clear_dashboard_cache']);
        
        add_action('save_post_vehicle_booking', [self::class, 'clear_cache_on_booking_change']);
        add_action('delete_post', [self::class, 'clear_cache_on_booking_delete']);
        add_action('save_post_vehicle', [self::class, 'clear_cache_on_vehicle_change']);
        add_action('save_post_mhm_message', [self::class, 'clear_cache_on_message_change']);
    }

    /**
     * Render dashboard page
     * 
     * @return void
     */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap mhm-rentiva-dashboard">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Control Panel', 'mhm-rentiva') . '</h1>';
        echo '<hr class="wp-header-end">';
        
        // Dashboard content
        self::render_dashboard_content();
        
        echo '</div>';
    }

    /**
     * Get currency symbol
     * 
     * @deprecated Use CurrencyHelper::get_currency_symbol() instead
     */
    private static function get_currency_symbol(): string
    {
        return \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
    }

    /**
     * Render dashboard content
     */
    private static function render_dashboard_content(): void
    {
        echo '<div class="mhm-dashboard-content">';
        
        // Add statistics cards at the top
        self::render_stats_cards();
        
        // Quick Actions Panel
        self::render_quick_actions();
        
        // Icon Statistics (2 columns) - Right below quick actions
        echo '<div class="mhm-dashboard-row">';
        self::render_customer_stats();
        self::render_vehicle_status();
        echo '</div>';
        
        // Messages below Customer Statistics
        echo '<div class="mhm-dashboard-row">';
        self::render_messages_widget();
        self::render_recent_bookings();
        echo '</div>';
        
        // Detailed Statistics (2 columns)
        echo '<div class="mhm-dashboard-row">';
        self::render_revenue_chart();
        self::render_notifications_widget();
        echo '</div>';
        
        // Deposit Statistics (2 columns)
        echo '<div class="mhm-dashboard-row">';
        self::render_deposit_stats();
        self::render_pending_payments();
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
                <!-- Monthly Bookings -->
                <div class="stat-card stat-card-total-bookings">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['bookings_this_month']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Monthly Bookings', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['total_bookings']); ?> <?php esc_html_e('total', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Monthly Revenue -->
                <div class="stat-card stat-card-total-revenue">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html(number_format((float)$stats['monthly_revenue'], 2)); ?> <?php echo esc_html(self::get_currency_symbol()); ?></div>
                        <div class="stat-label"><?php esc_html_e('Monthly Revenue', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html(number_format((float)$stats['total_revenue'], 2)); ?> <?php echo esc_html(self::get_currency_symbol()); ?> <?php esc_html_e('total', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Total Vehicles -->
                <div class="stat-card stat-card-total-vehicles">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-car"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['total_vehicles']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Total Vehicles', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['available_vehicles']); ?> <?php esc_html_e('available', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Monthly Customers -->
                <div class="stat-card stat-card-total-customers">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['total_customers_this_month']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Monthly Customers', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['total_customers_all_time']); ?> <?php esc_html_e('total', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render quick actions panel
     */
    private static function render_quick_actions(): void
    {
        echo '<div class="mhm-quick-actions">';
        echo '<h2>' . esc_html__('Quick Actions', 'mhm-rentiva') . '</h2>';
        echo '<div class="quick-actions-grid">';
        
        // Add New Vehicle
        echo '<a href="' . admin_url('post-new.php?post_type=vehicle') . '" class="quick-action-card">';
        echo '<span class="dashicons dashicons-plus-alt"></span>';
        echo '<span class="action-title">' . esc_html__('Add New Vehicle', 'mhm-rentiva') . '</span>';
        echo '</a>';
        
        // New Booking
        echo '<a href="' . admin_url('post-new.php?post_type=vehicle_booking') . '" class="quick-action-card">';
        echo '<span class="dashicons dashicons-calendar-alt"></span>';
        echo '<span class="action-title">' . esc_html__('New Booking', 'mhm-rentiva') . '</span>';
        echo '</a>';
        
        // Add Customer
        echo '<a href="' . admin_url('admin.php?page=mhm-rentiva-customers&action=add-customer') . '" class="quick-action-card">';
        echo '<span class="dashicons dashicons-admin-users"></span>';
        echo '<span class="action-title">' . esc_html__('Add Customer', 'mhm-rentiva') . '</span>';
        echo '</a>';
        
        // Reports
        echo '<a href="' . admin_url('admin.php?page=mhm-rentiva-reports') . '" class="quick-action-card">';
        echo '<span class="dashicons dashicons-chart-bar"></span>';
        echo '<span class="action-title">' . esc_html__('Reports', 'mhm-rentiva') . '</span>';
        echo '</a>';
        
        // Settings
        echo '<a href="' . admin_url('admin.php?page=mhm-rentiva-settings') . '" class="quick-action-card">';
        echo '<span class="dashicons dashicons-admin-settings"></span>';
        echo '<span class="action-title">' . esc_html__('Settings', 'mhm-rentiva') . '</span>';
        echo '</a>';
        
        // Email Templates
        echo '<a href="' . admin_url('admin.php?page=mhm-rentiva-settings&tab=email') . '" class="quick-action-card">';
        echo '<span class="dashicons dashicons-email-alt"></span>';
        echo '<span class="action-title">' . esc_html__('Email Templates', 'mhm-rentiva') . '</span>';
        echo '</a>';
        
        // Additional Services
        echo '<a href="' . admin_url('post-new.php?post_type=vehicle_addon') . '" class="quick-action-card">';
        echo '<span class="dashicons dashicons-admin-tools"></span>';
        echo '<span class="action-title">' . esc_html__('Additional Services', 'mhm-rentiva') . '</span>';
        echo '</a>';
        
        // Messages
        echo '<a href="' . admin_url('admin.php?page=mhm-rentiva-messages') . '" class="quick-action-card">';
        echo '<span class="dashicons dashicons-format-chat"></span>';
        echo '<span class="action-title">' . esc_html__('Messages', 'mhm-rentiva') . '</span>';
        echo '</a>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render recent bookings table
     */
    private static function render_recent_bookings(): void
    {
        $recent_bookings = self::get_recent_bookings();
        
        echo '<div class="mhm-dashboard-widget">';
        echo '<h3>' . esc_html__('Recent Bookings', 'mhm-rentiva') . '</h3>';
        echo '<div class="widget-content">';
        
        if (!empty($recent_bookings)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__('ID', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Customer', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Vehicle', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Date', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Status', 'mhm-rentiva') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($recent_bookings as $booking) {
                $status = $booking['status'] ?? 'pending';
                $status_class = self::get_booking_status_class($status);
                // Get translated status label
                $status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label($status);
                echo '<tr>';
                echo '<td><strong>#' . esc_html($booking['id']) . '</strong></td>';
                echo '<td>' . esc_html($booking['customer_name']) . '</td>';
                echo '<td>' . esc_html($booking['vehicle_title']) . '</td>';
                echo '<td>' . esc_html($booking['pickup_date']) . '</td>';
                echo '<td><span class="status-badge ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p class="no-data">' . esc_html__('No bookings found yet.', 'mhm-rentiva') . '</p>';
        }
        
        echo '<div class="widget-footer">';
        echo '<a href="' . admin_url('edit.php?post_type=vehicle_booking') . '" class="button button-secondary">' . esc_html__('View All Bookings', 'mhm-rentiva') . '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render vehicle status summary
     */
    private static function render_vehicle_status(): void
    {
        $vehicle_stats = self::get_vehicle_stats();
        
        echo '<div class="mhm-dashboard-widget">';
        echo '<h3>' . esc_html__('Vehicle Status', 'mhm-rentiva') . '</h3>';
        echo '<div class="widget-content">';
        
        echo '<div class="vehicle-status-grid">';
        
        // Available Vehicles
        echo '<div class="status-item available">';
        echo '<div class="status-icon"><span class="dashicons dashicons-yes-alt"></span></div>';
        echo '<div class="status-info">';
        echo '<div class="status-number">' . esc_html($vehicle_stats['available']) . '</div>';
        echo '<div class="status-label">' . esc_html__('Available', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Reserved Vehicles
        echo '<div class="status-item reserved">';
        echo '<div class="status-icon"><span class="dashicons dashicons-calendar-alt"></span></div>';
        echo '<div class="status-info">';
        echo '<div class="status-number">' . esc_html($vehicle_stats['reserved']) . '</div>';
        echo '<div class="status-label">' . esc_html__('Reserved', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Vehicles Under Maintenance
        echo '<div class="status-item maintenance">';
        echo '<div class="status-icon"><span class="dashicons dashicons-hammer"></span></div>';
        echo '<div class="status-info">';
        echo '<div class="status-number">' . esc_html($vehicle_stats['maintenance']) . '</div>';
        echo '<div class="status-label">' . esc_html__('Maintenance', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Inactive Vehicles
        echo '<div class="status-item inactive">';
        echo '<div class="status-icon"><span class="dashicons dashicons-dismiss"></span></div>';
        echo '<div class="status-info">';
        echo '<div class="status-number">' . esc_html($vehicle_stats['inactive']) . '</div>';
        echo '<div class="status-label">' . esc_html__('Inactive', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<div class="widget-footer">';
        echo '<a href="' . admin_url('edit.php?post_type=vehicle') . '" class="button button-secondary">' . esc_html__('View All Vehicles', 'mhm-rentiva') . '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render revenue chart
     */
    private static function render_revenue_chart(): void
    {
        $revenue_data = self::get_revenue_data();
        
        echo '<div class="mhm-dashboard-widget">';
        echo '<h3>' . esc_html__('Revenue Trend (Last 14 Days)', 'mhm-rentiva') . '</h3>';
        echo '<div class="widget-content">';
        
        echo '<div class="mhm-rentiva-chart-container">';
        echo '<canvas id="revenue-chart-canvas" width="400" height="200"></canvas>';
        echo '</div>';
        
        echo '<div class="revenue-summary">';
        echo '<div class="summary-item">';
        echo '<span class="summary-label">' . esc_html__('This Week:', 'mhm-rentiva') . '</span>';
        echo '<span class="summary-value">' . esc_html(number_format((float)$revenue_data['weekly_total'], 2)) . ' ' . esc_html(self::get_currency_symbol()) . '</span>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<span class="summary-label">' . esc_html__('Last Week:', 'mhm-rentiva') . '</span>';
        echo '<span class="summary-value">' . esc_html(number_format((float)$revenue_data['last_weekly_total'], 2)) . ' ' . esc_html(self::get_currency_symbol()) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="widget-footer">';
        echo '<a href="' . admin_url('admin.php?page=mhm-rentiva-reports') . '" class="button button-secondary">' . esc_html__('Detailed Reports', 'mhm-rentiva') . '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render customer statistics
     */
    private static function render_customer_stats(): void
    {
        // Get customer data from dashboard stats
        $stats = self::get_dashboard_stats();
        
        // Calculate average spending - Only completed/confirmed bookings (THIS MONTH ONLY)
        global $wpdb;
        $avg_spending = 0.00;
        $current_month_start = date('Y-m-01 00:00:00');
        $current_month_end = date('Y-m-t 23:59:59');
        if ($stats['total_customers_this_month'] > 0) {
            $total_spending = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                 WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
                 AND p.post_date >= %s AND p.post_date <= %s
                 AND pm.meta_key = '_mhm_total_price'
                 AND pm_status.meta_key = '_mhm_status'
                 AND pm_status.meta_value IN ('completed', 'confirmed')",
                'vehicle_booking', $current_month_start, $current_month_end
            ));
            $avg_spending = $total_spending / $stats['total_customers_this_month'];
        }
        
        $customer_stats = [
            'total' => $stats['total_customers_this_month'],
            'new_this_month' => $stats['new_customers_this_month'],
            'active' => $stats['total_customers_this_month'], // Active = Total for now
            'avg_spending' => number_format($avg_spending, 2)
        ];
        
        
        echo '<div class="mhm-dashboard-widget">';
        echo '<h3>' . esc_html__('Customer Statistics', 'mhm-rentiva') . '</h3>';
        echo '<div class="widget-content">';
        
        echo '<div class="customer-stats-grid">';
        
        // Total Customers
        echo '<div class="stat-item">';
        echo '<div class="stat-icon"><span class="dashicons dashicons-admin-users"></span></div>';
        echo '<div class="stat-info">';
        echo '<div class="stat-number">' . esc_html($customer_stats['total']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Total Customers', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // New Customers (This Month)
        echo '<div class="stat-item">';
        echo '<div class="stat-icon"><span class="dashicons dashicons-plus-alt"></span></div>';
        echo '<div class="stat-info">';
        echo '<div class="stat-number">' . esc_html($customer_stats['new_this_month']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('New This Month', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Active Customers
        echo '<div class="stat-item">';
        echo '<div class="stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>';
        echo '<div class="stat-info">';
        echo '<div class="stat-number">' . esc_html($customer_stats['active']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Active Customers', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Average Spending
        echo '<div class="stat-item">';
        echo '<div class="stat-icon"><span class="dashicons dashicons-money-alt"></span></div>';
        echo '<div class="stat-info">';
        echo '<div class="stat-number">' . esc_html($customer_stats['avg_spending']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Avg. Spending', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<div class="widget-footer">';
        echo '<a href="' . admin_url('admin.php?page=mhm-rentiva-customers') . '" class="button button-secondary">' . esc_html__('All Customers', 'mhm-rentiva') . '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render messages widget
     */
    private static function render_messages_widget(): void
    {
        $message_stats = self::get_message_stats();
        $recent_messages = self::get_recent_messages();
        
        echo '<div class="mhm-dashboard-widget">';
        echo '<h3>' . esc_html__('Messages', 'mhm-rentiva') . '</h3>';
        echo '<div class="widget-content">';
        
        // Message statistics
        echo '<div class="message-stats-grid">';
        
        // Pending Messages
        echo '<div class="stat-item pending">';
        echo '<div class="stat-icon"><span class="dashicons dashicons-clock"></span></div>';
        echo '<div class="stat-info">';
        echo '<div class="stat-number">' . esc_html($message_stats['pending']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Pending', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Answered Messages
        echo '<div class="stat-item answered">';
        echo '<div class="stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>';
        echo '<div class="stat-info">';
        echo '<div class="stat-number">' . esc_html($message_stats['answered']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Answered', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Total Messages
        echo '<div class="stat-item total">';
        echo '<div class="stat-icon"><span class="dashicons dashicons-email-alt"></span></div>';
        echo '<div class="stat-info">';
        echo '<div class="stat-number">' . esc_html($message_stats['total']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Total', 'mhm-rentiva') . '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Recent messages list
        if (!empty($recent_messages)) {
            echo '<div class="recent-messages">';
            echo '<h4>' . esc_html__('Recent Messages', 'mhm-rentiva') . '</h4>';
            echo '<ul class="message-list">';
            
            foreach ($recent_messages as $message) {
                $status_class = self::get_message_status_class($message['status']);
                $status_label = $message['status_label'] ?? ucfirst($message['status']);
                echo '<li class="message-item ' . esc_attr($status_class) . '">';
                echo '<div class="message-header">';
                echo '<span class="customer-name">' . esc_html($message['customer_name']) . '</span>';
                echo '<span class="message-date">' . esc_html($message['date']) . '</span>';
                echo '</div>';
                echo '<div class="message-preview">' . esc_html(wp_trim_words($message['content'], 15)) . '</div>';
                echo '<div class="message-status">';
                echo '<span class="status-badge ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
                echo '</div>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<p class="no-data">' . esc_html__('No messages found yet.', 'mhm-rentiva') . '</p>';
        }
        
        echo '<div class="widget-footer">';
        echo '<a href="' . admin_url('edit.php?post_type=mhm_message') . '" class="button button-secondary">' . esc_html__('View All Messages', 'mhm-rentiva') . '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render notifications widget
     */
    private static function render_notifications_widget(): void
    {
        $notifications = self::get_system_notifications();
        
        echo '<div class="mhm-dashboard-widget">';
        echo '<h3>' . esc_html__('System Notifications', 'mhm-rentiva') . '</h3>';
        echo '<div class="widget-content">';
        
        if (!empty($notifications)) {
            echo '<ul class="notification-list">';
            
            foreach ($notifications as $notification) {
                $type_class = $notification['type'];
                echo '<li class="notification-item ' . esc_attr($type_class) . '">';
                echo '<div class="notification-icon">';
                echo '<span class="dashicons ' . esc_attr($notification['icon']) . '"></span>';
                echo '</div>';
                echo '<div class="notification-content">';
                echo '<div class="notification-title">' . esc_html($notification['title']) . '</div>';
                echo '<div class="notification-message">' . esc_html($notification['message']) . '</div>';
                echo '<div class="notification-time">' . esc_html($notification['time']) . '</div>';
                echo '</div>';
                echo '</li>';
            }
            
            echo '</ul>';
        } else {
            echo '<p class="no-data">' . esc_html__('No new notifications.', 'mhm-rentiva') . '</p>';
        }
        
        echo '<div class="widget-footer">';
        echo '<a href="' . admin_url('admin.php?page=mhm-rentiva-settings') . '" class="button button-secondary">' . esc_html__('View Settings', 'mhm-rentiva') . '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Add dashboard statistics cards - No longer used
     * This function was replaced with render_stats_cards()
     */
    public static function add_dashboard_stats_cards(): void
    {
        // This function is no longer used
        // Statistics cards are displayed with render_stats_cards() function
        return;
    }

    /**
     * Refresh dashboard data via AJAX
     */
    public static function ajax_refresh_dashboard_data(): void
    {
        // Nonce check
        if (!wp_verify_nonce(self::sanitize_text_field_safe($_POST['nonce'] ?? ''), 'mhm_dashboard_nonce')) {
            wp_send_json_error(esc_html__('Security check failed', 'mhm-rentiva'));
            return;
        }

        // Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Unauthorized access', 'mhm-rentiva'));
            return;
        }

        try {
            // Get dashboard statistics
            $stats = self::get_dashboard_stats();
            
            // Get revenue data
            $revenue_data = self::get_revenue_data();
            
            // Get recent bookings
            $recent_bookings = self::get_recent_bookings();
            
            // Get vehicle statistics
            $vehicle_stats = self::get_vehicle_stats();
            
            // Get customer statistics - Use dashboard stats for customer data
            $customer_stats_data = self::get_dashboard_stats();
            $customer_stats = [
                'total' => $customer_stats_data['total_customers_this_month'],
                'new_this_month' => $customer_stats_data['new_customers_this_month'],
                'active' => $customer_stats_data['total_customers_this_month'],
                'avg_spending' => self::calculate_customer_avg_spending()
            ];
            
            // Get message statistics
            $message_stats = self::get_message_stats();
            $recent_messages = self::get_recent_messages();
            $notifications = self::get_system_notifications();
            
            // Get deposit statistics
            $deposit_stats = self::get_deposit_stats();
            $pending_payments = self::get_pending_payments();

            wp_send_json_success([
                'stats' => $stats,
                'revenue_data' => $revenue_data,
                'recent_bookings' => $recent_bookings,
                'vehicle_stats' => $vehicle_stats,
                'customer_stats' => $customer_stats,
                'message_stats' => $message_stats,
                'recent_messages' => $recent_messages,
                'notifications' => $notifications,
                'deposit_stats' => $deposit_stats,
                'pending_payments' => $pending_payments,
                'timestamp' => current_time('mysql')
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(esc_html__('Error occurred while fetching data: ', 'mhm-rentiva') . esc_html($e->getMessage()));
        }
    }

    /**
     * Load dashboard scripts and styles
     */
    public static function enqueue_scripts(string $hook): void
    {
        // Load only on dashboard page
        if (strpos($hook, 'mhm-rentiva-dashboard') !== false) {
            // Load core JavaScript using AssetManager
            if (class_exists('MHMRentiva\\Admin\\Core\\AssetManager')) {
                \MHMRentiva\Admin\Core\AssetManager::enqueue_core_js();
            }
            // Load core CSS files in correct order - WordPress standards
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
            
            wp_enqueue_style(
                'mhm-stats-cards',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
                ['mhm-core-css'],
                MHM_RENTIVA_VERSION
            );
            
            wp_enqueue_style(
                'mhm-dashboard',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/dashboard.css',
                ['mhm-stats-cards'],
                MHM_RENTIVA_VERSION
            );
            
            wp_enqueue_style(
                'mhm-dashboard-tooltips',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/dashboard-tooltips.css',
                ['mhm-dashboard'],
                MHM_RENTIVA_VERSION
            );
            
            wp_enqueue_script(
                'mhm-dashboard',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/dashboard.js',
                ['jquery', 'chart-js'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            // Load Chart.js library
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                '3.9.1',
                true
            );
            
            // Localize JavaScript variables (after Chart.js)
            $currency_symbol = self::get_currency_symbol();
            
            wp_localize_script('mhm-dashboard', 'mhm_dashboard_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_dashboard_nonce'),
                'revenue_data' => self::get_revenue_data(),
                'currency' => $currency_symbol, // Use currency symbol
            ]);
        }
    }

    /**
     * Get dashboard statistics - No cache (Fresh data every time)
     */
    private static function get_dashboard_stats(): array
    {
        global $wpdb;
        
        
        // Total bookings - EXCLUDING TRASH
        $total_bookings = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'private', 'pending') AND post_status != 'trash'",
            'vehicle_booking'
        ));
        
        // This month bookings - EXCLUDING TRASH
        $current_month_start = date('Y-m-01 00:00:00');
        $current_month_end = date('Y-m-t 23:59:59');
        $bookings_this_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = %s AND post_status IN ('publish', 'private', 'pending') AND post_status != 'trash'
             AND post_date >= %s AND post_date <= %s",
            'vehicle_booking', $current_month_start, $current_month_end
        ));
        
        // Total revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
        $total_revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm.meta_key = '_mhm_total_price'
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')",
            'vehicle_booking'
        ));
        
        // This month revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
        $monthly_revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm.meta_key = '_mhm_total_price'
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')",
            'vehicle_booking', $current_month_start, $current_month_end
        ));
        
        // Total vehicles - EXCLUDING TRASH
        $total_vehicles = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'private', 'pending') AND post_status != 'trash'",
            'vehicle'
        ));
        
        // Available vehicles - EXCLUDING TRASH - CORRECT META KEY AND VALUE
        $available_vehicles = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm.meta_key = '_mhm_vehicle_availability' AND pm.meta_value = 'active'",
            'vehicle'
        ));
        
        
        // Customer statistics - From booking data (THIS MONTH ONLY)
        $customer_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT pm_email.meta_value) as total_customers,
                COUNT(DISTINCT CASE WHEN p.post_date >= %s THEN pm_email.meta_value END) as new_customers
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_customer_email'
             WHERE p.post_type = 'vehicle_booking' 
             AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm_email.meta_value != '' AND pm_email.meta_value IS NOT NULL",
            $current_month_start, $current_month_start, $current_month_end
        ));
        
        $total_customers_this_month = (int) ($customer_stats->total_customers ?? 0);
        $new_customers_this_month = (int) ($customer_stats->new_customers ?? 0);
        
        // Total customers - ALL TIME
        $total_customers_all_time = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm_email.meta_value) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_customer_email'
             WHERE p.post_type = 'vehicle_booking' 
             AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm_email.meta_value != '' AND pm_email.meta_value IS NOT NULL"
        ));
        
        $stats = [
            'total_bookings' => $total_bookings,
            'bookings_this_month' => $bookings_this_month,
            'total_revenue' => $total_revenue,
            'monthly_revenue' => $monthly_revenue,
            'total_vehicles' => $total_vehicles,
            'available_vehicles' => $available_vehicles,
            'total_customers_this_month' => $total_customers_this_month,
            'total_customers_all_time' => $total_customers_all_time,
            'new_customers_this_month' => $new_customers_this_month,
            // Currency symbol is read from settings each time
        ];
        
        return $stats;
    }

    /**
     * Get recent bookings - Cached
     */
    private static function get_recent_bookings(): array
    {
        global $wpdb;
        
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID as id, p.post_title as vehicle_title, p.post_date,
                    pm1.meta_value as customer_name,
                    pm2.meta_value as pickup_date,
                    pm3.meta_value as status
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_mhm_customer_name'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_mhm_pickup_date'
             LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_mhm_status'
             WHERE p.post_type = %s AND p.post_status = %s
             ORDER BY p.post_date DESC
             LIMIT 5",
            'vehicle_booking', 'publish'
        ), ARRAY_A);
        $bookings_data = $bookings ?: [];
        
        // set_transient($cache_key, $bookings_data, 10 * MINUTE_IN_SECONDS);
        
        return $bookings_data;
    }

    /**
     * Get vehicle statistics - Cached (CURRENT MONTH ONLY)
     */
    private static function get_vehicle_stats(): array
    {
        global $wpdb;
        
        $current_month_start = date('Y-m-01 00:00:00');
        $current_month_end = date('Y-m-t 23:59:59');
        
        // Get all vehicles with status
        $vehicle_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT v.ID) as total_vehicles,
                COUNT(DISTINCT CASE WHEN pm_status.meta_value = 'inactive' THEN v.ID END) as inactive,
                COUNT(DISTINCT CASE WHEN pm_status.meta_value = 'maintenance' THEN v.ID END) as maintenance
             FROM {$wpdb->posts} v
             LEFT JOIN {$wpdb->postmeta} pm_status ON v.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_vehicle_status'
             WHERE v.post_type = 'vehicle' AND v.post_status = 'publish'"
        ));
        
        $total_vehicles = (int) ($vehicle_stats->total_vehicles ?? 0);
        $inactive = (int) ($vehicle_stats->inactive ?? 0);
        $maintenance = (int) ($vehicle_stats->maintenance ?? 0);
        
        // Get vehicles with active reservations THIS MONTH
        // A vehicle is reserved if it has a booking with pickup_date or return_date overlapping current month
        // and booking status is confirmed, active, or pending
        // Get all bookings first, then filter in PHP to handle different date formats
        $month_start_ts = strtotime($current_month_start);
        $month_end_ts = strtotime($current_month_end);
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT pm_vehicle.meta_value as vehicle_id,
                    pm_pickup.meta_value as pickup_date,
                    COALESCE(pm_return1.meta_value, pm_return2.meta_value, pm_return3.meta_value) as return_date
             FROM {$wpdb->posts} b
             INNER JOIN {$wpdb->postmeta} pm_vehicle ON b.ID = pm_vehicle.post_id AND pm_vehicle.meta_key = '_mhm_vehicle_id'
             INNER JOIN {$wpdb->postmeta} pm_pickup ON b.ID = pm_pickup.post_id AND pm_pickup.meta_key = '_mhm_pickup_date'
             LEFT JOIN {$wpdb->postmeta} pm_return1 ON b.ID = pm_return1.post_id AND pm_return1.meta_key = '_mhm_return_date'
             LEFT JOIN {$wpdb->postmeta} pm_return2 ON b.ID = pm_return2.post_id AND pm_return2.meta_key = '_mhm_dropoff_date'
             LEFT JOIN {$wpdb->postmeta} pm_return3 ON b.ID = pm_return3.post_id AND pm_return3.meta_key = '_mhm_end_date'
             INNER JOIN {$wpdb->postmeta} pm_status ON b.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
             WHERE b.post_type = 'vehicle_booking'
             AND b.post_status = 'publish'
             AND b.post_date >= %s AND b.post_date <= %s
             AND pm_status.meta_value IN ('confirmed', 'active', 'pending')
             AND pm_vehicle.meta_value IS NOT NULL AND pm_vehicle.meta_value != ''
             AND pm_pickup.meta_value IS NOT NULL AND pm_pickup.meta_value != ''
             AND (pm_return1.meta_value IS NOT NULL OR pm_return2.meta_value IS NOT NULL OR pm_return3.meta_value IS NOT NULL)",
            $current_month_start, $current_month_end
        ));
        
        $reserved_vehicle_ids = [];
        if ($bookings) {
            foreach ($bookings as $booking) {
                // Normalize date formats (handle YYYY-MM-DD, DD.MM.YYYY, etc.)
                $pickup_ts = strtotime($booking->pickup_date);
                $return_ts = strtotime($booking->return_date);
                
                if ($pickup_ts === false || $return_ts === false) {
                    continue;
                }
                
                // Check if booking overlaps with current month
                $overlaps = ($pickup_ts <= $month_end_ts && $return_ts >= $month_start_ts);
                
                if ($overlaps) {
                    $reserved_vehicle_ids[] = (int) $booking->vehicle_id;
                }
            }
        }
        
        $reserved = count(array_unique($reserved_vehicle_ids));
        
        // Get available vehicles: vehicles with status 'active' that are NOT reserved
        $available_vehicles_with_status = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT v.ID)
             FROM {$wpdb->posts} v
             LEFT JOIN {$wpdb->postmeta} pm_status ON v.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_vehicle_status'
             WHERE v.post_type = 'vehicle' 
             AND v.post_status = 'publish'
             AND (pm_status.meta_value = 'active' OR pm_status.meta_value IS NULL)",
        ));
        
        // Available = Active vehicles - Reserved vehicles
        $available = max(0, $available_vehicles_with_status - $reserved);
        
        $stats = [
            'available' => $available,
            'reserved' => $reserved,
            'maintenance' => $maintenance,
            'inactive' => $inactive,
        ];
        
        return $stats;
    }

    /**
     * Get revenue data - Cached
     */
    private static function get_revenue_data(): array
    {
        global $wpdb;
        
        $currency = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD');
        
        // Last 7 days revenue data - INCLUDE ALL BOOKINGS
        $revenue_data = [];
        $total_revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm.meta_key = '_mhm_total_price'
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')",
            'vehicle_booking'
        ));
        
        
        $has_daily_data = false;
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $revenue = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                 WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
                 AND DATE(p.post_date) = %s
                 AND pm.meta_key = '_mhm_total_price'
                 AND pm_status.meta_key = '_mhm_status'
                 AND pm_status.meta_value IN ('completed', 'confirmed')",
                'vehicle_booking', $date
            ));
            
            if ($revenue > 0) {
                $has_daily_data = true;
            }
            
            
            $booking_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                 WHERE p.post_type = %s AND DATE(p.post_date) = %s
                 AND pm_status.meta_key = '_mhm_status'
                 AND pm_status.meta_value IN ('completed', 'confirmed')",
                'vehicle_booking', $date
            ));
            
            // If all days show the same revenue, check booking dates
            if ($i == 0) { // For the first day
                $all_bookings = $wpdb->get_results($wpdb->prepare(
                    "SELECT p.ID, p.post_date, pm_status.meta_value as status, pm_total.meta_value as total 
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                     INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id
                     WHERE p.post_type = %s AND pm_status.meta_key = '_mhm_status' AND pm_total.meta_key = '_mhm_total_price'
                     AND pm_status.meta_value IN ('completed', 'confirmed')
                     ORDER BY p.post_date DESC",
                    'vehicle_booking'
                ));
            }
            
            $date_formatted = date('d/m', strtotime($date));
            
            $revenue_data[] = [
                'date' => $date_formatted,
                'revenue' => $revenue
            ];
        }
        // Division removed - Only real data will be shown
        
        $this_week_start = date('Y-m-d', strtotime('monday this week'));
        $this_week_end = date('Y-m-d', strtotime('sunday this week'));
        
        $weekly_total = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm.meta_key = '_mhm_total_price'
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')",
            'vehicle_booking', $this_week_start, $this_week_end . ' 23:59:59'
        ));
        
        $last_week_start = date('Y-m-d', strtotime('monday last week'));
        $last_week_end = date('Y-m-d', strtotime('sunday last week'));
        
        $last_weekly_total = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm.meta_key = '_mhm_total_price'
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')",
            'vehicle_booking', $last_week_start, $last_week_end . ' 23:59:59'
        ));
        $data = [
            'daily_data' => $revenue_data,
            'weekly_total' => $weekly_total,
            'last_weekly_total' => $last_weekly_total,
            // Currency symbol is read from settings each time
        ];
        
        return $data;
    }


    /**
     * Get message statistics - Cached
     */
    private static function get_message_stats(): array
    {
        $cache_key = 'mhm_message_stats_' . get_current_user_id();
        $cached_stats = get_transient($cache_key);
        
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        global $wpdb;
        
        // Pending messages
        $pending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status = %s
             AND pm.meta_key = '_mhm_message_status' AND pm.meta_value = %s",
            'mhm_message', 'publish', 'pending'
        ));
        
        // Answered messages
        $answered = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status = %s
             AND pm.meta_key = '_mhm_message_status' AND pm.meta_value = %s",
            'mhm_message', 'publish', 'answered'
        ));
        
        // Total messages
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'mhm_message', 'publish'
        ));
        
        $stats = [
            'pending' => $pending,
            'answered' => $answered,
            'total' => $total,
        ];
        set_transient($cache_key, $stats, 10 * MINUTE_IN_SECONDS);
        
        return $stats;
    }

    /**
     * Get recent messages - Cached
     */
    private static function get_recent_messages(): array
    {
        $cache_key = 'mhm_recent_messages_' . get_current_user_id();
        $cached_messages = get_transient($cache_key);
        
        if ($cached_messages !== false) {
            return $cached_messages;
        }
        
        global $wpdb;
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_content, p.post_date,
                    COALESCE(pm1.meta_value, '') as customer_name,
                    COALESCE(pm2.meta_value, 'pending') as status
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_mhm_customer_name'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_mhm_message_status'
             WHERE p.post_type = %s AND p.post_status = %s
             ORDER BY p.post_date DESC
             LIMIT 3",
            'mhm_message', 'publish'
        ), ARRAY_A);
        
        // Status labels mapping
        $status_labels = [
            'pending' => esc_html__('Pending', 'mhm-rentiva'),
            'answered' => esc_html__('Answered', 'mhm-rentiva'),
            'closed' => esc_html__('Closed', 'mhm-rentiva'),
        ];
        
        $messages_data = [];
        if ($messages) {
            foreach ($messages as $message) {
                $status = strtolower(trim($message['status'] ?: 'pending'));
                $messages_data[] = [
                    'id' => $message['ID'],
                    'customer_name' => $message['customer_name'] ?: esc_html__('Anonymous', 'mhm-rentiva'),
                    'content' => $message['post_content'],
                    'date' => date('d.m.Y H:i', strtotime($message['post_date'])),
                    'status' => $status, // Original status for CSS class
                    'status_label' => $status_labels[$status] ?? ucfirst($status) // Label for display
                ];
            }
        }
        set_transient($cache_key, $messages_data, 5 * MINUTE_IN_SECONDS);
        
        return $messages_data;
    }

    /**
     * Get system notifications
     */
    private static function get_system_notifications(): array
    {
        $notifications = [];
        
        
        // Check pending messages
        $message_stats = self::get_message_stats();
        if ($message_stats['pending'] > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'dashicons-email-alt',
                'title' => esc_html__('Pending Messages', 'mhm-rentiva'),
                /* translators: %d placeholder. */
                'message' => sprintf(esc_html__('%d pending messages', 'mhm-rentiva'), $message_stats['pending']),
                'time' => esc_html__('Now', 'mhm-rentiva')
            ];
        }
        
        $booking_stats = self::get_dashboard_stats();
        if ($booking_stats['total_bookings'] > 0) {
            $notifications[] = [
                'type' => 'info',
                'icon' => 'dashicons-calendar-alt',
                'title' => esc_html__('Active Bookings', 'mhm-rentiva'),
                /* translators: %d placeholder. */
                'message' => sprintf(esc_html__('%d total bookings', 'mhm-rentiva'), $booking_stats['total_bookings']),
                'time' => esc_html__('Current', 'mhm-rentiva')
            ];
        }
        
        $pending_payments = self::get_pending_payments();
        if (!empty($pending_payments)) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'dashicons-money-alt',
                'title' => esc_html__('Pending Payments', 'mhm-rentiva'),
                /* translators: %d placeholder. */
                'message' => sprintf(esc_html__('%d pending payments', 'mhm-rentiva'), count($pending_payments)),
                'time' => esc_html__('Current', 'mhm-rentiva')
            ];
        }
        
        $vehicle_stats = self::get_vehicle_stats();
        if ($vehicle_stats['maintenance'] > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'dashicons-hammer',
                'title' => esc_html__('Vehicles Under Maintenance', 'mhm-rentiva'),
                /* translators: %d placeholder. */
                'message' => sprintf(esc_html__('%d vehicles in maintenance', 'mhm-rentiva'), $vehicle_stats['maintenance']),
                'time' => esc_html__('Current', 'mhm-rentiva')
            ];
        }
        
        $system_issues = 0;
        
        // Check WordPress updates
        if (current_user_can('update_core')) {
            $update_counts = wp_get_update_data();
            if ($update_counts['counts']['total'] > 0) {
                $system_issues++;
            }
        }
        
        // Check plugin updates
        $plugin_updates = get_plugin_updates();
        if (!empty($plugin_updates)) {
            $system_issues++;
        }
        
        // Check theme updates
        $theme_updates = get_theme_updates();
        if (!empty($theme_updates)) {
            $system_issues++;
        }
        
        if ($system_issues > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'dashicons-update',
                'title' => esc_html__('System Updates Available', 'mhm-rentiva'),
                /* translators: %d placeholder. */
                'message' => sprintf(esc_html__('%d updates available', 'mhm-rentiva'), $system_issues),
                'time' => esc_html__('Current', 'mhm-rentiva')
            ];
        } else {
            $notifications[] = [
                'type' => 'success',
                'icon' => 'dashicons-yes-alt',
                'title' => esc_html__('System Status', 'mhm-rentiva'),
                'message' => esc_html__('All systems operating normally', 'mhm-rentiva'),
                'time' => esc_html__('Current', 'mhm-rentiva')
            ];
        }
        
        
        // Show last 4 messages
        return array_slice($notifications, 0, 4);
    }

    /**
     * Calculate average customer spending for current month
     */
    private static function calculate_customer_avg_spending(): string
    {
        global $wpdb;
        $avg_spending = 0.00;
        $current_month_start = date('Y-m-01 00:00:00');
        $current_month_end = date('Y-m-t 23:59:59');
        
        // Get total spending for current month
        $total_spending_this_month = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm.meta_key = '_mhm_total_price'
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')",
            'vehicle_booking', $current_month_start, $current_month_end
        ));
        
        // Get unique customer count for current month
        $total_customers_this_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm_email.meta_value)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_customer_email'
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm_email.meta_value != '' AND pm_email.meta_value IS NOT NULL",
            'vehicle_booking', $current_month_start, $current_month_end
        ));
        
        if ($total_customers_this_month > 0) {
            $avg_spending = $total_spending_this_month / $total_customers_this_month;
        }
        
        return number_format($avg_spending, 2);
    }

    /**
     * Get deposit statistics - Cached (CURRENT MONTH ONLY)
     */
    private static function get_deposit_stats(): array
    {
        global $wpdb;
        
        $current_month_start = date('Y-m-01 00:00:00');
        $current_month_end = date('Y-m-t 23:59:59');
        
        // Total booking count (THIS MONTH ONLY)
        $total_bookings = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = %s AND post_status = %s
             AND post_date >= %s AND post_date <= %s",
            'vehicle_booking', 'publish', $current_month_start, $current_month_end
        ));
        
        // Deposit bookings (this month)
        $deposit_bookings = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = %s AND p.post_status = %s 
             AND pm.meta_key = %s AND pm.meta_value = %s
             AND p.post_date >= %s AND p.post_date <= %s",
            'vehicle_booking', 'publish', '_mhm_payment_type', 'deposit', 
            $current_month_start, $current_month_end
        ));

        // Last month deposit bookings (for trend)
        $last_month_start = date('Y-m-01', strtotime('-1 month'));
        $last_month_end = date('Y-m-t', strtotime('-1 month'));
        $last_month_deposit_bookings = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = %s AND p.post_status = %s 
             AND pm.meta_key = %s AND pm.meta_value = %s
             AND p.post_date >= %s AND p.post_date <= %s",
            'vehicle_booking', 'publish', '_mhm_payment_type', 'deposit', 
            $last_month_start . ' 00:00:00', $last_month_end . ' 23:59:59'
        ));

        // Calculate deposit trend
        $deposit_trend = 0;
        if ($last_month_deposit_bookings > 0) {
            $deposit_trend = (($deposit_bookings - $last_month_deposit_bookings) / $last_month_deposit_bookings) * 100;
        } elseif ($deposit_bookings > 0) {
            $deposit_trend = 100;
        }

        // Pending deposits (with remaining amount) - THIS MONTH ONLY
        $pending_deposits = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = %s 
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm1.meta_value = %s AND CAST(pm2.meta_value AS DECIMAL(10,2)) > 0",
            '_mhm_payment_type', '_mhm_remaining_amount', 'vehicle_booking', 'publish', 
            $current_month_start, $current_month_end, 'deposit'
        ));

        // Pending deposit amount (only deposit amounts) - THIS MONTH ONLY
        $pending_deposit_amount = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm1.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = %s 
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm2.meta_value = %s AND CAST(pm1.meta_value AS DECIMAL(10,2)) > 0",
            '_mhm_deposit_amount', '_mhm_payment_type', 'vehicle_booking', 'publish', 
            $current_month_start, $current_month_end, 'deposit'
        ));

        // Completed deposits (payment status = paid) - THIS MONTH ONLY
        $completed_deposits = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = %s 
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm1.meta_value = %s AND pm2.meta_value = %s",
            '_mhm_payment_type', '_mhm_payment_status', 'vehicle_booking', 'publish', 
            $current_month_start, $current_month_end, 'deposit', 'paid'
        ));
        

        // Completed deposit amount (total amount of paid deposits) - THIS MONTH ONLY
        $completed_deposit_amount = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm1.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
             INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = %s 
             AND p.post_date >= %s AND p.post_date <= %s
             AND pm2.meta_value = %s AND pm3.meta_value = %s",
            '_mhm_deposit_amount', '_mhm_payment_type', '_mhm_payment_status', 
            'vehicle_booking', 'publish', $current_month_start, $current_month_end, 'deposit', 'paid'
        ));

        $stats = [
            'total_bookings' => $total_bookings,
            'deposit_bookings' => $deposit_bookings,
            'deposit_trend' => round($deposit_trend, 1),
            'pending_deposits' => $pending_deposits,
            'pending_deposit_amount' => $pending_deposit_amount,
            'completed_deposits' => $completed_deposits,
            'completed_deposit_amount' => $completed_deposit_amount
        ];
        
        return $stats;
    }

    /**
     * Get pending payments - Cached
     */
    private static function get_pending_payments(): array
    {
        global $wpdb;
        
        // Pending payments (deposit system) - Correct meta keys
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID as booking_id, p.post_title,
                    pm1.meta_value as customer_name,
                    CAST(pm2.meta_value AS DECIMAL(10,2)) as amount,
                    pm3.meta_value as payment_deadline,
                    pm4.meta_value as payment_status
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_mhm_customer_name'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_mhm_remaining_amount'
             LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_mhm_payment_deadline'
             LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_mhm_payment_status'
             WHERE p.post_type = %s AND p.post_status = %s
             AND pm2.meta_value IS NOT NULL AND CAST(pm2.meta_value AS DECIMAL(10,2)) > 0
             ORDER BY pm3.meta_value ASC
             LIMIT 10",
            'vehicle_booking', 'publish'
        ), ARRAY_A);
        
        $payments_data = [];
        if ($payments) {
            foreach ($payments as $payment) {
                $deadline = $payment['payment_deadline'] ? date('d.m.Y H:i', strtotime($payment['payment_deadline'])) : '—';
                $is_overdue = $payment['payment_deadline'] && strtotime($payment['payment_deadline']) < time();
                $status = $payment['payment_status'] ?: 'unpaid';
                
                $payments_data[] = [
                    'booking_id' => $payment['booking_id'],
                    'customer_name' => $payment['customer_name'] ?: esc_html__('Unknown', 'mhm-rentiva'),
                    'amount' => (float) $payment['amount'],
                    'deadline' => $deadline,
                    'status' => $status,
                    'status_label' => self::get_payment_status_label($status),
                    'is_overdue' => $is_overdue
                ];
            }
        }
        
        return $payments_data;
    }

    /**
     * Get label for payment status
     */
    private static function get_payment_status_label(string $status): string
    {
        $labels = [
            'unpaid' => esc_html__('Unpaid', 'mhm-rentiva'),
            'paid' => esc_html__('Paid', 'mhm-rentiva'),
            'pending' => esc_html__('Pending', 'mhm-rentiva'),
            'refunded' => esc_html__('Refunded', 'mhm-rentiva'),
            'failed' => esc_html__('Failed', 'mhm-rentiva'),
            'pending_verification' => esc_html__('Pending Verification', 'mhm-rentiva'),
        ];

        return $labels[$status] ?? esc_html(ucfirst($status));
    }

    /**
     * Get CSS class for payment status
     */
    private static function get_payment_status_class(string $status): string
    {
        $status_classes = [
            'unpaid' => 'status-unpaid',
            'paid' => 'status-paid',
            'refunded' => 'status-refunded',
            'failed' => 'status-failed',
            'pending_verification' => 'status-pending',
        ];
        
        return $status_classes[$status] ?? 'status-default';
    }

    /**
     * Format price
     */
    private static function format_price(float $price): string
    {
        $amount = number_format($price, 2, '.', ',');
        return $amount . ' ' . self::get_currency_symbol();
    }

    /**
     * Get CSS class for message status
     */
    private static function get_message_status_class(string $status): string
    {
        $status_classes = [
            'pending' => 'status-pending',
            'answered' => 'status-answered',
            'closed' => 'status-closed',
        ];
        
        return $status_classes[$status] ?? 'status-default';
    }

    /**
     * Get CSS class for booking status
     */
    private static function get_booking_status_class(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'status-default';
        }
        
        $status_classes = [
            'pending' => 'status-pending',
            'confirmed' => 'status-confirmed',
            'active' => 'status-active',
            'completed' => 'status-completed',
            'cancelled' => 'status-cancelled',
        ];
        
        return $status_classes[$status] ?? 'status-default';
    }

    /**
     * Render deposit statistics
     */
    private static function render_deposit_stats(): void
    {
        $deposit_stats = self::get_deposit_stats();
        
        echo '<div class="mhm-dashboard-widget">';
        echo '<h3>' . esc_html__('Deposit Statistics', 'mhm-rentiva') . '</h3>';
        echo '<div class="widget-content">';
        
        echo '<div class="stats-grid">';
        
        // Deposit Bookings
        echo '<div class="stat-card stat-card-deposit-bookings">';
        echo '<div class="stat-icon">';
        echo '<span class="dashicons dashicons-money-alt"></span>';
        echo '</div>';
        echo '<div class="stat-content">';
        echo '<div class="stat-number">' . esc_html($deposit_stats['deposit_bookings']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Deposit Bookings', 'mhm-rentiva') . '</div>';
        echo '<div class="stat-trend">';
        echo '<span class="trend-text ' . ($deposit_stats['deposit_trend'] >= 0 ? 'positive' : 'negative') . '">';
        echo ($deposit_stats['deposit_trend'] >= 0 ? '+' : '') . esc_html($deposit_stats['deposit_trend']) . '% ' . esc_html__('this month', 'mhm-rentiva');
        echo '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Pending Deposits
        echo '<div class="stat-card stat-card-pending-deposits">';
        echo '<div class="stat-icon">';
        echo '<span class="dashicons dashicons-clock"></span>';
        echo '</div>';
        echo '<div class="stat-content">';
        echo '<div class="stat-number">' . esc_html($deposit_stats['pending_deposits']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Pending Deposits', 'mhm-rentiva') . '</div>';
        echo '<div class="stat-trend">';
        echo '<span class="trend-text">' . esc_html(self::format_price($deposit_stats['pending_deposit_amount'])) . ' ' . esc_html__('total', 'mhm-rentiva') . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Completed Deposits
        echo '<div class="stat-card stat-card-completed-deposits">';
        echo '<div class="stat-icon">';
        echo '<span class="dashicons dashicons-yes"></span>';
        echo '</div>';
        echo '<div class="stat-content">';
        echo '<div class="stat-number">' . esc_html($deposit_stats['completed_deposits']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Completed Deposits', 'mhm-rentiva') . '</div>';
        echo '<div class="stat-trend">';
        echo '<span class="trend-text">' . esc_html(self::format_price($deposit_stats['completed_deposit_amount'])) . ' ' . esc_html__('total', 'mhm-rentiva') . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Deposit Rate
        $deposit_ratio = $deposit_stats['total_bookings'] > 0 ? 
            round(($deposit_stats['deposit_bookings'] / $deposit_stats['total_bookings']) * 100, 1) : 0;
        echo '<div class="stat-card stat-card-deposit-ratio">';
        echo '<div class="stat-icon">';
        echo '<span class="dashicons dashicons-chart-pie"></span>';
        echo '</div>';
        echo '<div class="stat-content">';
        echo '<div class="stat-number">' . esc_html($deposit_ratio) . '%</div>';
        echo '<div class="stat-label">' . esc_html__('Deposit Rate', 'mhm-rentiva') . '</div>';
        echo '<div class="stat-trend">';
        echo '<span class="trend-text">' . esc_html($deposit_stats['deposit_bookings']) . '/' . esc_html($deposit_stats['total_bookings']) . ' ' . esc_html__('bookings', 'mhm-rentiva') . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<div class="widget-footer">';
        echo '<a href="' . admin_url('edit.php?post_type=vehicle_booking&mhm_payment_type=deposit') . '" class="button button-secondary">' . esc_html__('Deposit Bookings', 'mhm-rentiva') . '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render pending payments widget
     */
    private static function render_pending_payments(): void
    {
        $pending_payments = self::get_pending_payments();
        
        echo '<div class="mhm-dashboard-widget">';
        echo '<h3>' . esc_html__('Pending Payments', 'mhm-rentiva') . '</h3>';
        echo '<div class="widget-content">';
        
        if (!empty($pending_payments)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__('Booking', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Customer', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Amount', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Due Date', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Status', 'mhm-rentiva') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($pending_payments as $payment) {
                $status_class = self::get_payment_status_class($payment['status']);
                $is_overdue = $payment['is_overdue'];
                $row_class = $is_overdue ? 'overdue' : '';
                
                echo '<tr class="' . esc_attr($row_class) . '">';
                echo '<td><strong>#' . esc_html($payment['booking_id']) . '</strong></td>';
                echo '<td>' . esc_html($payment['customer_name']) . '</td>';
                echo '<td>' . esc_html(self::format_price($payment['amount'])) . '</td>';
                echo '<td>' . esc_html($payment['deadline']) . '</td>';
                echo '<td><span class="status-badge ' . esc_attr($status_class) . '">' . esc_html($payment['status_label']) . '</span></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p class="no-data">' . esc_html__('No pending payments found.', 'mhm-rentiva') . '</p>';
        }
        
        echo '<div class="widget-footer">';
        echo '<a href="' . admin_url('edit.php?post_type=vehicle_booking') . '" class="button button-secondary">' . esc_html__('All Pending Payments', 'mhm-rentiva') . '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Clear cache when booking changes
     */
    public static function clear_cache_on_booking_change(int $post_id): void
    {
        if (get_post_type($post_id) === 'vehicle_booking') {
            self::clear_dashboard_cache();
        }
    }
    
    /**
     * Clear cache when booking is deleted
     */
    public static function clear_cache_on_booking_delete(int $post_id): void
    {
        if (get_post_type($post_id) === 'vehicle_booking') {
            self::clear_dashboard_cache();
        }
    }
    
    /**
     * Clear cache when vehicle changes
     */
    public static function clear_cache_on_vehicle_change(int $post_id): void
    {
        if (get_post_type($post_id) === 'vehicle') {
            self::clear_dashboard_cache();
        }
    }
    
    /**
     * Clear cache when message changes
     */
    public static function clear_cache_on_message_change(int $post_id): void
    {
        if (get_post_type($post_id) === 'mhm_message') {
            self::clear_dashboard_cache();
        }
    }
    
    /**
     * Clear all dashboard caches
     */
    public static function clear_dashboard_cache(): void
    {
        global $wpdb;
        
        // Clear global caches
        $cache_keys = [
            'mhm_dashboard_stats_global',
            'mhm_revenue_data_global',
            'mhm_vehicle_stats_',
            'mhm_customer_stats_',
            'mhm_message_stats_',
            'mhm_recent_messages_',
            'mhm_deposit_stats_',
            'mhm_pending_payments_'
        ];
        
        foreach ($cache_keys as $key_prefix) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $key_prefix . '%'
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . $key_prefix . '%'
            ));
        }
    }
    
    /**
     * AJAX handler for clearing dashboard cache
     */
    public static function ajax_clear_dashboard_cache(): void
    {
        if (!wp_verify_nonce(self::sanitize_text_field_safe($_POST['nonce'] ?? ''), 'mhm_clear_cache')) {
            wp_send_json_error(esc_html__('Security check failed', 'mhm-rentiva'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Unauthorized access', 'mhm-rentiva'));
            return;
        }
        
        self::clear_dashboard_cache();
        wp_send_json_success(esc_html__('Cache cleared successfully', 'mhm-rentiva'));
    }
    
    
}
