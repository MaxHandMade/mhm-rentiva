<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Cron;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron Job Monitor
 * 
 * Monitors and manages all plugin-related cron jobs
 */
final class CronMonitor
{
    /**
     * Get all plugin-related cron jobs
     */
    public static function get_all_cron_jobs(): array
    {
        // Ensure all plugin hooks are loaded (some hooks register on 'init')
        if (!did_action('init')) {
            do_action('init');
        }
        
        $crons = _get_cron_array();
        $plugin_crons = [];
        
        if (empty($crons)) {
            return [];
        }
        
        // Define all plugin cron hooks with verification info
        $plugin_hooks = [
            'mhm_rentiva_auto_cancel_event' => [
                'name' => __('Auto Cancel Bookings', 'mhm-rentiva'),
                'description' => __('Automatically cancels unpaid bookings after payment deadline', 'mhm-rentiva'),
                'schedule' => 'mhm_rentiva_15min',
            ],
            'mhm_rentiva_reconcile_event' => [
                'name' => __('Payment Reconciliation', 'mhm-rentiva'),
                'description' => __('Reconciles payment statuses with payment gateways (PayTR, Stripe)', 'mhm-rentiva'),
                'schedule' => 'mhm_rentiva_10min',
            ],
            'mhm_data_retention_cleanup' => [
                'name' => __('Data Retention Cleanup', 'mhm-rentiva'),
                'description' => __('Cleans up expired data according to retention policies', 'mhm-rentiva'),
                'schedule' => 'daily',
            ],
            'mhm_send_scheduled_notifications' => [
                'name' => __('Scheduled Notifications', 'mhm-rentiva'),
                'description' => __('Sends scheduled email notifications', 'mhm-rentiva'),
                'schedule' => 'hourly', // Can be hourly, daily, or weekly
            ],
            'mhm_rentiva_license_daily' => [
                'name' => __('License Validation', 'mhm-rentiva'),
                'description' => __('Validates plugin license daily', 'mhm-rentiva'),
                'schedule' => 'daily',
            ],
            'mhm_rentiva_email_log_purge_event' => [
                'name' => __('Email Log Retention', 'mhm-rentiva'),
                'description' => __('Cleans up old email logs', 'mhm-rentiva'),
                'schedule' => 'daily',
            ],
            'mhm_rentiva_log_purge_event' => [
                'name' => __('Log Retention', 'mhm-rentiva'),
                'description' => __('Cleans up old system logs', 'mhm-rentiva'),
                'schedule' => 'daily',
            ],
        ];
        
        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $dings) {
                if (!isset($plugin_hooks[$hook])) {
                    continue;
                }
                
                foreach ($dings as $sig => $data) {
                    $info = $plugin_hooks[$hook];
                    $schedule_name = wp_get_schedule($hook);
                    $next_run = wp_next_scheduled($hook);
                    
                    $plugin_crons[] = [
                        'hook' => $hook,
                        'name' => $info['name'],
                        'description' => $info['description'],
                        'schedule' => $schedule_name ?: __('Not scheduled', 'mhm-rentiva'),
                        'next_run' => $next_run ?: 0,
                        'next_run_formatted' => $next_run ? date_i18n('Y-m-d H:i:s', $next_run) : __('Not scheduled', 'mhm-rentiva'),
                        'is_scheduled' => $next_run > 0,
                        'timestamp' => $timestamp,
                        'signature' => $sig,
                        'args' => $data['args'] ?? [],
                        'is_registered' => has_action($hook) !== false,
                    ];
                }
            }
        }
        
        // Add unscheduled hooks (hooks that should exist but don't)
        foreach ($plugin_hooks as $hook => $info) {
            $found = false;
            foreach ($plugin_crons as $cron) {
                if ($cron['hook'] === $hook) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $plugin_crons[] = [
                    'hook' => $hook,
                    'name' => $info['name'],
                    'description' => $info['description'],
                    'schedule' => __('Not scheduled', 'mhm-rentiva'),
                    'next_run' => 0,
                    'next_run_formatted' => __('Not scheduled', 'mhm-rentiva'),
                    'is_scheduled' => false,
                    'timestamp' => 0,
                    'signature' => '',
                    'args' => [],
                    'is_registered' => has_action($hook) !== false,
                ];
            }
        }
        
        // Sort by hook name
        usort($plugin_crons, function($a, $b) {
            return strcmp($a['hook'], $b['hook']);
        });
        
        return $plugin_crons;
    }
    
    /**
     * Manually run a cron job
     */
    public static function run_cron_job(string $hook, array $args = []): array
    {
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'message' => __('Permission denied', 'mhm-rentiva')
            ];
        }
        
        // Check if hook is a plugin hook
        $plugin_hooks = [
            'mhm_rentiva_auto_cancel_event',
            'mhm_rentiva_reconcile_event',
            'mhm_data_retention_cleanup',
            'mhm_send_scheduled_notifications',
            'mhm_rentiva_license_daily',
            'mhm_rentiva_email_log_purge_event',
            'mhm_rentiva_log_purge_event',
        ];
        
        if (!in_array($hook, $plugin_hooks, true)) {
            return [
                'success' => false,
                'message' => __('Invalid cron hook', 'mhm-rentiva')
            ];
        }
        
        // Verify hook is registered before running
        $hook_exists = has_action($hook);
        
        if (!$hook_exists) {
            return [
                'success' => false,
                /* translators: Dynamic value. */
                'message' => sprintf(__('Cron hook "%s" is not registered. The function may not be active.', 'mhm-rentiva'), $hook)
            ];
        }
        
        // Ensure all plugin hooks are loaded (some hooks register on 'init')
        // Trigger 'init' if not already fired to ensure hooks are registered
        if (!did_action('init')) {
            do_action('init');
        }
        
        // Verify hook is registered again after init
        $hook_exists_after_init = has_action($hook);
        
        if (!$hook_exists_after_init) {
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: Dynamic value. */
                    __('Cron hook "%s" is not registered. The associated class may not be loaded. Ensure the plugin is fully activated.', 'mhm-rentiva'),
                    $hook
                )
            ];
        }
        
        // Run the hook
        try {
            $start_time = microtime(true);
            do_action($hook, ...$args);
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            return [
                'success' => true,
                /* translators: 1: cron hook name; 2: execution time in milliseconds. */
                'message' => sprintf(__('Cron job "%1$s" executed successfully in %2$s ms', 'mhm-rentiva'), $hook, $execution_time),
                'execution_time' => $execution_time
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                /* translators: %s placeholder. */
                'message' => sprintf(__('Error executing cron job: %s', 'mhm-rentiva'), $e->getMessage())
            ];
        }
    }
    
    /**
     * Get cron schedule information
     */
    public static function get_schedule_info(string $schedule_name): ?array
    {
        $schedules = wp_get_schedules();
        
        if (!isset($schedules[$schedule_name])) {
            return null;
        }
        
        $schedule = $schedules[$schedule_name];
        return [
            'name' => $schedule['display'] ?? $schedule_name,
            'interval' => $schedule['interval'] ?? 0,
            'interval_formatted' => human_time_diff(0, $schedule['interval'] ?? 0),
        ];
    }
}

