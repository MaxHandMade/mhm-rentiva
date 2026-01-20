<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Uninstall;

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Uninstaller
 * 
 * Handles complete removal of all plugin data from database
 */
final class Uninstaller
{
    /**
     * Get uninstall statistics (what will be deleted)
     */
    public static function get_uninstall_stats(): array
    {
        global $wpdb;

        $stats = [
            'options' => 0,
            'post_types' => [
                'vehicles' => 0,
                'bookings' => 0,
            ],
            'postmeta' => 0,
            'custom_tables' => [],
            'cron_jobs' => 0,
            'transients' => 0,
            'backup_files' => 0,
        ];

        // Count options - using prepare for LIKE patterns
        $options = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->options}
            WHERE option_name LIKE %s
            OR option_name LIKE %s",
            'mhm_rentiva%',
            '_mhm_rentiva%'
        ));
        $stats['options'] = (int) $options;

        // Count vehicles
        $vehicles = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts}
            WHERE post_type = %s
        ", 'vehicle'));
        $stats['post_types']['vehicles'] = (int) $vehicles;

        // Count bookings
        $bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts}
            WHERE post_type = %s
        ", 'vehicle_booking'));
        $stats['post_types']['bookings'] = (int) $bookings;

        // Count postmeta - using prepare for LIKE pattern
        $postmeta = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->postmeta}
            WHERE meta_key LIKE %s",
            '_mhm%'
        ));
        $stats['postmeta'] = (int) $postmeta;

        // Count custom tables
        $custom_tables = [
            $wpdb->prefix . 'mhm_rentiva_queue',
            $wpdb->prefix . 'mhm_rentiva_ratings',
            $wpdb->prefix . 'mhm_rentiva_report_queue',
            $wpdb->prefix . 'mhm_message_logs',
            $wpdb->prefix . 'mhm_rentiva_background_jobs',
            $wpdb->prefix . 'mhm_backup_records',
        ];

        foreach ($custom_tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($exists) {
                // Table name is from internal array, sanitize with esc_sql for safety
                $safe_table = esc_sql($table);
                $rows = $wpdb->get_var("SELECT COUNT(*) FROM `{$safe_table}`");
                $stats['custom_tables'][$table] = (int) $rows;
            }
        }

        // Count cron jobs
        $crons = _get_cron_array();
        $plugin_crons = [
            'mhm_rentiva_auto_cancel_event',
            'mhm_rentiva_reconcile_event',
            'mhm_data_retention_cleanup',
            'mhm_send_scheduled_notifications',
            'mhm_rentiva_license_daily',
            'mhm_email_log_retention',
            'mhm_log_retention',
        ];

        $cron_count = 0;
        if (!empty($crons)) {
            foreach ($crons as $timestamp => $cron) {
                foreach ($cron as $hook => $dings) {
                    if (in_array($hook, $plugin_crons, true)) {
                        $cron_count += count($dings);
                    }
                }
            }
        }
        $stats['cron_jobs'] = $cron_count;

        // Count transients - using prepare for LIKE patterns
        $transients = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->options}
            WHERE (option_name LIKE %s 
            OR option_name LIKE %s)",
            '_transient_mhm_rentiva%',
            '_transient_timeout_mhm_rentiva%'
        ));
        $stats['transients'] = (int) $transients;

        // Count backup files
        $backup_dir = WP_CONTENT_DIR . '/mhm-rentiva-backups';
        $backup_files = 0;
        if (file_exists($backup_dir)) {
            $files = glob($backup_dir . '/*.sql');
            $backup_files = $files ? count($files) : 0;
        }
        $stats['backup_files'] = $backup_files;

        return $stats;
    }

    /**
     * Perform complete uninstall (delete all plugin data)
     */
    public static function uninstall(bool $delete_backups = false): array
    {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'message' => __('Permission denied', 'mhm-rentiva')
            ];
        }

        return self::uninstall_direct($delete_backups);
    }

    /**
     * Direct uninstall (bypasses permission check - for use in uninstall.php)
     */
    public static function uninstall_direct(bool $delete_backups = false): array
    {
        global $wpdb;

        $results = [
            'options_deleted' => 0,
            'posts_deleted' => 0,
            'postmeta_deleted' => 0,
            'tables_dropped' => 0,
            'cron_jobs_cleared' => 0,
            'transients_deleted' => 0,
            'backup_files_deleted' => 0,
            'errors' => [],
        ];

        // 1. Delete all options - using prepare for LIKE patterns
        $options = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name
            FROM {$wpdb->options}
            WHERE option_name LIKE %s
            OR option_name LIKE %s",
            'mhm_rentiva%',
            '_mhm_rentiva%'
        ));

        foreach ($options as $option_name) {
            if (delete_option($option_name)) {
                $results['options_deleted']++;
            }
        }

        // 2. Delete all vehicles
        $vehicles = $wpdb->get_col($wpdb->prepare("
            SELECT ID
            FROM {$wpdb->posts}
            WHERE post_type = %s
        ", 'vehicle'));

        foreach ($vehicles as $post_id) {
            wp_delete_post($post_id, true);
            $results['posts_deleted']++;
        }

        // 3. Delete all bookings
        $bookings = $wpdb->get_col($wpdb->prepare("
            SELECT ID
            FROM {$wpdb->posts}
            WHERE post_type = %s
        ", 'vehicle_booking'));

        foreach ($bookings as $post_id) {
            wp_delete_post($post_id, true);
            $results['posts_deleted']++;
        }

        // 4. Delete all postmeta - using prepare for LIKE pattern
        $postmeta_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta}
            WHERE meta_key LIKE %s",
            '_mhm%'
        ));
        $results['postmeta_deleted'] = (int) $postmeta_deleted;

        // 5. Drop custom tables
        $custom_tables = [
            $wpdb->prefix . 'mhm_rentiva_queue',
            $wpdb->prefix . 'mhm_rentiva_ratings',
            $wpdb->prefix . 'mhm_rentiva_report_queue',
            $wpdb->prefix . 'mhm_message_logs',
            $wpdb->prefix . 'mhm_rentiva_background_jobs',
            $wpdb->prefix . 'mhm_backup_records',
        ];

        foreach ($custom_tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($exists) {
                // Table name is from internal array, sanitize with esc_sql for safety
                $safe_table = esc_sql($table);
                $wpdb->query("DROP TABLE IF EXISTS `{$safe_table}`");
                $results['tables_dropped']++;
            }
        }

        // 6. Clear all cron jobs
        $plugin_crons = [
            'mhm_rentiva_auto_cancel_event',
            'mhm_rentiva_reconcile_event',
            'mhm_data_retention_cleanup',
            'mhm_send_scheduled_notifications',
            'mhm_rentiva_license_daily',
            'mhm_email_log_retention',
            'mhm_log_retention',
        ];

        foreach ($plugin_crons as $hook) {
            $timestamp = wp_next_scheduled($hook);
            while ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
                $timestamp = wp_next_scheduled($hook);
                $results['cron_jobs_cleared']++;
            }
        }

        // 7. Delete all transients - using prepare for LIKE patterns
        $transients = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name
            FROM {$wpdb->options}
            WHERE option_name LIKE %s
            OR option_name LIKE %s",
            '_transient_mhm_rentiva%',
            '_transient_timeout_mhm_rentiva%'
        ));

        foreach ($transients as $transient_name) {
            $name = str_replace(['_transient_', '_transient_timeout_'], '', $transient_name);
            if (delete_transient($name)) {
                $results['transients_deleted']++;
            }
        }

        // 8. Delete backup files (optional)
        if ($delete_backups) {
            $backup_dir = WP_CONTENT_DIR . '/mhm-rentiva-backups';
            if (file_exists($backup_dir)) {
                $files = glob($backup_dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && unlink($file)) {
                        $results['backup_files_deleted']++;
                    }
                }

                // Delete .htaccess and index.php
                @unlink($backup_dir . '/.htaccess');
                @unlink($backup_dir . '/index.php');

                // Try to remove directory (may fail if not empty)
                @rmdir($backup_dir);
            }
        }

        // 9. Delete taxonomies and terms
        $taxonomies = ['vehicle_category', 'vehicle_cat']; // vehicle_cat is deprecated
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ]);

            foreach ($terms as $term) {
                wp_delete_term($term->term_id, $taxonomy);
            }
        }

        return [
            'success' => empty($results['errors']),
            'results' => $results,
            'message' => empty($results['errors'])
                ? __('All plugin data has been removed successfully', 'mhm-rentiva')
                : __('Uninstall completed with some errors', 'mhm-rentiva')
        ];
    }
}
