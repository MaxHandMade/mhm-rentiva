<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ 4. STAGE - Test Admin Page
 */
final class TestAdminPage
{
    /**
     * Register admin page and hooks
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_menu_page']);
        add_action('admin_post_mhm_run_tests', [self::class, 'handle_run_tests']);
        add_action('admin_post_mhm_download_test_report', [self::class, 'handle_download_report']);
    }

    /**
     * Add admin menu page
     */
    public static function add_menu_page(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __('Test Suite', 'mhm-rentiva'),
            __('🧪 Test Suite', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-tests',
            [self::class, 'render_page']
        );
    }

    /**
     * Render test page
     */
    public static function render_page(): void
    {
        // Permission check
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mhm-rentiva'));
        }

        // Get test results (if available)
        $test_results = get_transient('mhm_rentiva_test_results');
        
        ?>
        <div class="wrap mhm-test-page">
            <h1>🧪 <?php esc_html_e('MHM Rentiva Test Suite', 'mhm-rentiva'); ?></h1>
            
            <div class="mhm-test-controls">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mhm_run_tests', 'mhm_test_nonce'); ?>
                    <input type="hidden" name="action" value="mhm_run_tests">
                    
                    <div class="test-options">
                        <h2><?php esc_html_e('Test Options', 'mhm-rentiva'); ?></h2>
                        
                        <label>
                            <input type="checkbox" name="test_suites[]" value="activation" checked>
                            <?php esc_html_e('Activation Tests', 'mhm-rentiva'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="test_suites[]" value="security" checked>
                            <?php esc_html_e('Security Tests', 'mhm-rentiva'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="test_suites[]" value="functional" checked>
                            <?php esc_html_e('Functional Tests', 'mhm-rentiva'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="test_suites[]" value="performance" checked>
                            <?php esc_html_e('Performance Tests', 'mhm-rentiva'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="test_suites[]" value="integration" checked>
                            <?php esc_html_e('Integration Tests', 'mhm-rentiva'); ?>
                        </label>
                    </div>
                    
                    <div class="test-actions">
                        <button type="submit" class="button button-primary button-hero">
                            🚀 <?php esc_html_e('Run Tests', 'mhm-rentiva'); ?>
                        </button>
                        
                        <button type="button" class="button button-secondary" onclick="location.reload();">
                            🔄 <?php esc_html_e('Refresh Page', 'mhm-rentiva'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($test_results): ?>
                <div class="mhm-test-results-container">
                    <?php echo TestRunner::render_html_report($test_results); ?>
                    
                    <div class="test-export-options">
                        <h3><?php esc_html_e('Download Report', 'mhm-rentiva'); ?></h3>
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=mhm_download_test_report&format=html&_wpnonce=' . wp_create_nonce('mhm_download_report'))); ?>" 
                           class="button">
                            📄 <?php esc_html_e('Download HTML Report', 'mhm-rentiva'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=mhm_download_test_report&format=json&_wpnonce=' . wp_create_nonce('mhm_download_report'))); ?>" 
                           class="button">
                            📋 <?php esc_html_e('Download JSON Report', 'mhm-rentiva'); ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="mhm-test-welcome">
                    <div class="welcome-panel">
                        <h2>👋 <?php esc_html_e('Welcome!', 'mhm-rentiva'); ?></h2>
                        <p><?php esc_html_e('This page allows you to run comprehensive tests for the MHM Rentiva plugin.', 'mhm-rentiva'); ?></p>
                        <p><?php esc_html_e('The test suite checks the following:', 'mhm-rentiva'); ?></p>
                        <ul>
                            <li>✅ <?php esc_html_e('Plugin activation and deactivation', 'mhm-rentiva'); ?></li>
                            <li>🔒 <?php esc_html_e('Security: Nonce, sanitization, escaping', 'mhm-rentiva'); ?></li>
                            <li>⚙️ <?php esc_html_e('Functionality: Shortcode, AJAX, REST API', 'mhm-rentiva'); ?></li>
                            <li>⚡ <?php esc_html_e('Performance: Query time, cache, memory usage', 'mhm-rentiva'); ?></li>
                            <li>🔗 <?php esc_html_e('Integration: Settings, Email, Payment, Roles, i18n, Database, Error Handling', 'mhm-rentiva'); ?></li>
                        </ul>
                        <p><strong><?php esc_html_e('Click the "Run Tests" button above to get started.', 'mhm-rentiva'); ?></strong></p>
                    </div>
                </div>
            <?php endif; ?>

            <style>
                .mhm-test-page { max-width: 1400px; }
                .mhm-test-controls { background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
                .test-options { margin: 20px 0; }
                .test-options label { display: block; margin: 10px 0; font-size: 14px; }
                .test-options input[type="checkbox"] { margin-right: 8px; }
                .test-actions { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
                .test-actions button { margin-right: 10px; }
                .mhm-test-welcome { margin: 20px 0; }
                .welcome-panel { background: #fff; padding: 30px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
                .welcome-panel h2 { margin-top: 0; font-size: 24px; }
                .welcome-panel ul { margin: 20px 0; padding-left: 20px; }
                .welcome-panel ul li { margin: 8px 0; font-size: 14px; }
                .test-export-options { margin: 30px 0; padding: 20px; background: #f5f5f5; border-radius: 5px; }
                .test-export-options h3 { margin-top: 0; }
                .test-export-options .button { margin-right: 10px; }
            </style>
        </div>
        <?php
    }

    /**
     * Handle test execution
     */
    public static function handle_run_tests(): void
    {
        // Permission and nonce check
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mhm-rentiva'));
        }

        check_admin_referer('mhm_run_tests', 'mhm_test_nonce');

        // Get test suites
        $selected_suites = isset($_POST['test_suites']) ? array_map('sanitize_key', $_POST['test_suites']) : [];
        
        if (empty($selected_suites)) {
            $selected_suites = ['activation', 'security', 'functional', 'performance', 'integration'];
        }

        // Run tests
        $start_time = microtime(true);
        $results = [];

        foreach ($selected_suites as $suite) {
            switch ($suite) {
                case 'activation':
                    $results['activation'] = ActivationTest::run_all_tests();
                    break;
                case 'security':
                    $results['security'] = SecurityTest::run_all_tests();
                    break;
                case 'functional':
                    $results['functional'] = FunctionalTest::run_all_tests();
                    break;
                case 'performance':
                    $results['performance'] = PerformanceTest::run_all_tests();
                    break;
                case 'integration':
                    if (class_exists('MHMRentiva\\Admin\\Testing\\IntegrationTest')) {
                        $results['integration'] = IntegrationTest::run_all_tests();
                    }
                    break;
            }
        }

        $execution_time = microtime(true) - $start_time;

        // Analyze results
        $summary = TestRunner::analyze_results($results);

        $test_results = [
            'results' => $results,
            'summary' => $summary,
            'execution_time' => round($execution_time, 3),
            'timestamp' => current_time('mysql')
        ];

        // Save results to transient (1 hour)
        set_transient('mhm_rentiva_test_results', $test_results, HOUR_IN_SECONDS);

        // Redirect back
        wp_redirect(admin_url('admin.php?page=mhm-rentiva-tests&test_completed=1'));
        exit;
    }

    /**
     * Handle test report download
     */
    public static function handle_download_report(): void
    {
        // Permission and nonce check
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mhm-rentiva'));
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'mhm_download_report')) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
        }

        // Format check
        $format = isset($_GET['format']) ? sanitize_key($_GET['format']) : 'html';

        if (!in_array($format, ['html', 'json'], true)) {
            wp_die(esc_html__('Invalid format.', 'mhm-rentiva'));
        }

        // Get test results
        $test_results = get_transient('mhm_rentiva_test_results');

        if (!$test_results) {
            wp_die(esc_html__('Test results not found. Please run tests first.', 'mhm-rentiva'));
        }

        // Create content based on format
        if ($format === 'html') {
            $content = TestRunner::render_html_report($test_results);
            $filename = 'mhm-rentiva-test-report-' . date('Y-m-d-H-i-s') . '.html';
            $content_type = 'text/html';
        } else {
            $content = TestRunner::get_json_report($test_results);
            $filename = 'mhm-rentiva-test-report-' . date('Y-m-d-H-i-s') . '.json';
            $content_type = 'application/json';
        }

        // Send download headers
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        // Send content
        echo $content;
        exit;
    }

    // analyze_results and determine_overall_status methods removed
    // Using TestRunner::analyze_results() and TestRunner::determine_overall_status()
}
