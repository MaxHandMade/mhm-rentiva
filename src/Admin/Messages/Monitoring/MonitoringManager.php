<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Monitoring;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance and log monitoring manager
 */
final class MonitoringManager
{
    private static bool $initialized = false;

    /**
     * Start monitoring system
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Start logger
        MessageLogger::init();

        // Start performance monitor
        add_action('wp_ajax_mhm_get_performance_report', [PerformanceMonitor::class, 'ajax_get_performance_data']);
        add_action('wp_ajax_mhm_clear_performance_data', [self::class, 'ajax_clear_performance_data']);

        // Add dashboard widgets
        add_action('wp_dashboard_setup', [self::class, 'add_dashboard_widgets']);

        // Add admin menu
        add_action('admin_menu', [self::class, 'add_admin_menu']);

        // Start performance monitoring
        add_action('init', [self::class, 'start_performance_monitoring'], 1);

        // Check system health
        add_action('wp_ajax_mhm_get_system_health', [self::class, 'ajax_get_system_health']);

        self::$initialized = true;
    }

    /**
     * Start performance monitoring
     */
    public static function start_performance_monitoring(): void
    {
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'mhm-rentiva') !== false) {
            PerformanceMonitor::start_monitoring();
        }
    }

    /**
     * Add dashboard widgets
     */
    public static function add_dashboard_widgets(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'mhm_messages_performance_widget',
            __('Message System Performance', 'mhm-rentiva'),
            [PerformanceMonitor::class, 'render_performance_widget']
        );

        wp_add_dashboard_widget(
            'mhm_messages_logs_widget',
            __('Message System Logs', 'mhm-rentiva'),
            [MessageLogger::class, 'render_log_widget']
        );
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu(): void
    {
        add_submenu_page(
            'mhm-rentiva-messages',
            __('System Monitoring', 'mhm-rentiva'),     
            __('System Monitoring', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-messages-monitoring',
            [self::class, 'render_monitoring_page']
        );

        add_submenu_page(
            'mhm-rentiva-messages',
            __('Log View', 'mhm-rentiva'),
            __('Log View', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-messages-logs',
            [self::class, 'render_logs_page']
        );
    }

    /**
     * Monitoring page render
     */
    public static function render_monitoring_page(): void
    {
        $performance_stats = PerformanceMonitor::get_performance_stats();
        $log_stats = MessageLogger::get_log_stats();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Message System Monitoring', 'mhm-rentiva'); ?></h1>
            
            <div class="mhm-monitoring-dashboard">
                <div class="monitoring-cards">
                    <!-- Performance Card -->
                    <div class="monitoring-card">
                        <div class="card-header">
                            <h3><?php _e('Performance Status', 'mhm-rentiva'); ?></h3>
                            <button type="button" class="button button-small" id="refresh-performance-btn">
                                <?php _e('Refresh', 'mhm-rentiva'); ?>
                            </button>
                        </div>
                        
                        <div class="card-content">
                            <div class="stat-grid">
                                <div class="stat-item">
                                    <span class="stat-label"><?php _e('Active Timer:', 'mhm-rentiva'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($performance_stats['active_timers']); ?></span>
                                </div>
                                
                                <div class="stat-item">
                                    <span class="stat-label"><?php _e('Query Count:', 'mhm-rentiva'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($performance_stats['query_count']); ?></span>
                                </div>
                                
                                <div class="stat-item">
                                    <span class="stat-label"><?php _e('Memory Usage:', 'mhm-rentiva'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(size_format($performance_stats['current_memory'])); ?></span>
                                </div>
                                
                                <div class="stat-item">
                                    <span class="stat-label"><?php _e('Peak Memory:', 'mhm-rentiva'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(size_format($performance_stats['peak_memory'])); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <button type="button" class="button" id="generate-performance-report-btn">
                                <?php _e('Generate Detailed Report', 'mhm-rentiva'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Log Card -->
                    <div class="monitoring-card">
                        <div class="card-header">
                            <h3><?php _e('Log Status', 'mhm-rentiva'); ?></h3>
                            <button type="button" class="button button-small" id="refresh-log-data-btn">
                                <?php _e('Refresh', 'mhm-rentiva'); ?>
                            </button>
                        </div>
                        
                        <div class="card-content">
                            <div class="stat-grid">
                                <div class="stat-item">
                                    <span class="stat-label"><?php _e('Total Logs:', 'mhm-rentiva'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(number_format($log_stats['total_logs'])); ?></span>
                                </div>
                                
                                <?php foreach ($log_stats['level_stats'] as $level_stat): ?>
                                <div class="stat-item">
                                    <span class="stat-label">
                                        <?php echo esc_html(strtoupper($level_stat['level']) . ':'); ?>
                                    </span>
                                    <span class="stat-value log-count-<?php echo esc_attr($level_stat['level']); ?>">
                                        <?php echo esc_html($level_stat['count']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <button type="button" class="button" id="view-logs-btn">
                                <?php _e('View Logs', 'mhm-rentiva'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- System Health Card -->
                    <div class="monitoring-card">
                        <div class="card-header">
                            <h3><?php _e('System Health', 'mhm-rentiva'); ?></h3>
                            <button type="button" class="button button-small" id="check-system-health-btn">
                                <?php _e('Check', 'mhm-rentiva'); ?>
                            </button>
                        </div>
                        
                        <div class="card-content">
                            <div id="system-health-content">
                                <p><?php _e('Checking system health...', 'mhm-rentiva'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Charts -->
                <div class="monitoring-section">
                    <h3><?php _e('Performance Trends', 'mhm-rentiva'); ?></h3>
                    <div class="chart-container">
                        <canvas id="performance-chart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- System Recommendations -->
                <div class="monitoring-section">
                    <h3><?php _e('System Recommendations', 'mhm-rentiva'); ?></h3>
                    <div id="system-recommendations">
                        <p><?php _e('Loading recommendations...', 'mhm-rentiva'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CSS will be moved to a separate file -->
        
        <!-- JavaScript will be moved to a separate file -->
        <?php
    }

    /**
     * Log page render
     */
    public static function render_logs_page(): void
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Message System Logs', 'mhm-rentiva'); ?></h1>
            
            <div class="mhm-logs-page">
                <!-- Log Filters -->
                <div class="log-filters">
                    <form id="log-filters-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="log-level"><?php _e('Log Level:', 'mhm-rentiva'); ?></label>
                                <select id="log-level" name="level">
                                    <option value=""><?php _e('All', 'mhm-rentiva'); ?></option>
                                    <option value="debug"><?php _e('Debug', 'mhm-rentiva'); ?></option>
                                    <option value="info"><?php _e('Info', 'mhm-rentiva'); ?></option>
                                    <option value="warning"><?php _e('Warning', 'mhm-rentiva'); ?></option>
                                    <option value="error"><?php _e('Error', 'mhm-rentiva'); ?></option>
                                    <option value="critical"><?php _e('Critical', 'mhm-rentiva'); ?></option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="log-search"><?php _e('Search:', 'mhm-rentiva'); ?></label>
                                <input type="text" id="log-search" name="search" placeholder="<?php _e('Message or context search...', 'mhm-rentiva'); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <button type="submit" class="button button-primary">
                                    <?php _e('Filter', 'mhm-rentiva'); ?>
                                </button>
                                
                                <button type="button" class="button" id="clear-log-filters-btn">
                                    <?php _e('Clear', 'mhm-rentiva'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Log List -->
                <div class="log-list-container">
                    <div id="log-list">
                        <p><?php _e('Loading logs...', 'mhm-rentiva'); ?></p>
                    </div>
                    
                    <div class="log-pagination">
                        <button type="button" class="button" id="prev-page" disabled>
                            <?php _e('Previous', 'mhm-rentiva'); ?>
                        </button>
                        
                        <span id="page-info"><?php _e('Page 1', 'mhm-rentiva'); ?></span>
                        
                        <button type="button" class="button" id="next-page">
                            <?php _e('Next', 'mhm-rentiva'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Log Operations -->
                <div class="log-actions">
                    <button type="button" class="button" id="clear-old-logs-btn">
                        <?php _e('Clear Logs Older Than 7 Days', 'mhm-rentiva'); ?>
                    </button>
                    
                    <button type="button" class="button" id="export-logs-btn">
                        <?php _e('Export Logs', 'mhm-rentiva'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- CSS will be moved to a separate file -->
        
        <!-- JavaScript will be moved to a separate file -->
        <?php
    }

    /**
     * AJAX - Performance data clear
     */
    public static function ajax_clear_performance_data(): void
    {
        check_ajax_referer('mhm_messages_performance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
        }

        PerformanceMonitor::clear_performance_data();
        wp_send_json_success(__('Performance data cleared', 'mhm-rentiva'));
    }

    /**
     * AJAX - Check system health
     */
    public static function ajax_get_system_health(): void
    {
        check_ajax_referer('mhm_messages_performance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
        }

        $checks = [];

        // WordPress version check
        global $wp_version;
        if (version_compare($wp_version, '5.0', '>=')) {
            /* translators: %s placeholder. */
            $checks[] = ['status' => 'ok', 'message' => sprintf(__('WordPress version is compatible: %s', 'mhm-rentiva'), $wp_version)];
        } else {
            /* translators: %s placeholder. */
            $checks[] = ['status' => 'warning', 'message' => sprintf(__('WordPress version is outdated: %s (5.0+ recommended)', 'mhm-rentiva'), $wp_version)];
        }

        // PHP version check
        if (version_compare(PHP_VERSION, '7.4', '>=')) {
            /* translators: %s placeholder. */
            $checks[] = ['status' => 'ok', 'message' => sprintf(__('PHP version is compatible: %s', 'mhm-rentiva'), PHP_VERSION)];
        } else {
            /* translators: %s placeholder. */
            $checks[] = ['status' => 'warning', 'message' => sprintf(__('PHP version is outdated: %s (7.4+ recommended)', 'mhm-rentiva'), PHP_VERSION)];
        }

        // Memory limit check
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = wp_convert_hr_to_bytes($memory_limit);
        if ($memory_bytes >= 256 * 1024 * 1024) { // 256MB
            /* translators: %s placeholder. */
            $checks[] = ['status' => 'ok', 'message' => sprintf(__('Memory limit is sufficient: %s', 'mhm-rentiva'), $memory_limit)];
        } else {
            /* translators: %s placeholder. */
            $checks[] = ['status' => 'warning', 'message' => sprintf(__('Memory limit is low: %s (256MB+ recommended)', 'mhm-rentiva'), $memory_limit)];
        }

        // Database connection check
        global $wpdb;
        $db_check = $wpdb->get_var("SELECT 1");
        if ($db_check === '1') {
            $checks[] = ['status' => 'ok', 'message' => __('Database connection active', 'mhm-rentiva')];
        } else {
            $checks[] = ['status' => 'error', 'message' => __('Database connection issue', 'mhm-rentiva')];
        }

        // Plugin files check
        $plugin_files = [
            'src/Admin/Messages/Settings/MessagesSettings.php',
            'src/Admin/Messages/Core/MessageCache.php',
            'src/Admin/Messages/Core/MessageQueryHelper.php'
        ];

        foreach ($plugin_files as $file) {
            $file_path = MHM_RENTIVA_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                /* translators: %s placeholder. */
                $checks[] = ['status' => 'ok', 'message' => sprintf(__('File exists: %s', 'mhm-rentiva'), basename($file))];
            } else {
                /* translators: %s placeholder. */
                $checks[] = ['status' => 'error', 'message' => sprintf(__('File not found: %s', 'mhm-rentiva'), basename($file))];
            }
        }

        // Cache status check
        if (class_exists('MHM\\Rentiva\\Admin\\Messages\\Core\\MessageCache')) {
            $checks[] = ['status' => 'ok', 'message' => __('Cache system active', 'mhm-rentiva')];
        } else {
            $checks[] = ['status' => 'error', 'message' => __('Cache system not found', 'mhm-rentiva')];
        }

        wp_send_json_success(['checks' => $checks]);
    }
}
