<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Cron;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron Monitor Admin Page (AJAX Handlers)
 */
final class CronMonitorPage
{
    public static function register(): void
    {
        add_action('wp_ajax_mhm_list_cron_jobs', [self::class, 'ajax_list_cron_jobs']);
        add_action('wp_ajax_mhm_run_cron_job', [self::class, 'ajax_run_cron_job']);
    }

    /**
     * AJAX - List all cron jobs
     */
    public static function ajax_list_cron_jobs(): void
    {
        check_ajax_referer('mhm_cron_monitor', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Permission denied', 'mhm-rentiva'));
        }
        
        $crons = CronMonitor::get_all_cron_jobs();
        
        wp_send_json_success([
            'crons' => $crons,
            'count' => count($crons)
        ]);
    }

    /**
     * AJAX - Run cron job manually
     */
    public static function ajax_run_cron_job(): void
    {
        check_ajax_referer('mhm_cron_monitor', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Permission denied', 'mhm-rentiva'));
        }
        
        $hook = isset($_POST['hook']) ? sanitize_text_field(wp_unslash($_POST['hook'])) : '';
        $args = isset($_POST['args']) && is_array($_POST['args']) ? array_map('sanitize_text_field', $_POST['args']) : [];
        
        if (empty($hook)) {
            wp_send_json_error(esc_html__('Cron hook is required', 'mhm-rentiva'));
        }
        
        $result = CronMonitor::run_cron_job($hook, $args);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
}

