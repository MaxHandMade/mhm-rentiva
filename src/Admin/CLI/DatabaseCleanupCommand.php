<?php declare(strict_types=1);

namespace MHMRentiva\Admin\CLI;

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ WP-CLI Database Cleanup Command
 * 
 * Usage:
 *   wp mhm-rentiva cleanup analyze
 *   wp mhm-rentiva cleanup orphaned --dry-run
 *   wp mhm-rentiva cleanup orphaned --execute
 *   wp mhm-rentiva cleanup transients --execute
 *   wp mhm-rentiva cleanup full --execute
 */
class DatabaseCleanupCommand
{
    /**
     * Default days for log cleanup
     */
    public const DEFAULT_LOG_CLEANUP_DAYS = 30;

    /**
     * Analyze database for cleanup opportunities
     * 
     * ## EXAMPLES
     * 
     *     wp mhm-rentiva cleanup analyze
     * 
     * @when after_wp_load
     */
    public function analyze($args, $assoc_args)
    {
        \WP_CLI::log(__('Analyzing MHM Rentiva database...', 'mhm-rentiva'));
        
        $analysis = DatabaseCleaner::analyze_database();
        
        \WP_CLI::success(__('Analysis complete!', 'mhm-rentiva'));
        \WP_CLI::line('');
        
        // Orphaned Postmeta
        \WP_CLI::log(sprintf(
            /* translators: 1: %d; 2: %s. */
            __('Orphaned Postmeta: %1$d records (~%2$s)', 'mhm-rentiva'),
            $analysis['orphaned_postmeta']['count'],
            size_format($analysis['orphaned_postmeta']['total_size_estimate'])
        ));
        
        // Expired Transients
        \WP_CLI::log(sprintf(
            /* translators: 1: %d; 2: %s. */
            __('Expired Transients: %1$d records (~%2$s)', 'mhm-rentiva'),
            $analysis['expired_transients']['count'],
            size_format($analysis['expired_transients']['total_size_estimate'])
        ));
        
        // Autoload Options
        \WP_CLI::log(sprintf(
            /* translators: 1: %d; 2: %s. */
            __('Autoload Options: %1$d options (%2$s)', 'mhm-rentiva'),
            $analysis['unused_options']['autoload_options'],
            size_format($analysis['unused_options']['autoload_size'])
        ));
        
        // Invalid Meta Keys
        \WP_CLI::log(sprintf(
            /* translators: %d placeholder. */
            __('Invalid Meta Keys: %d unique keys', 'mhm-rentiva'),
            $analysis['invalid_meta_keys']['count']
        ));
        
        // Table Stats
        \WP_CLI::line('');
        \WP_CLI::log(__('Custom Tables:', 'mhm-rentiva'));
        foreach ($analysis['table_stats'] as $key => $stats) {
            if ($stats['exists']) {
                \WP_CLI::log(sprintf(
                    /* translators: 1: %s; 2: %d; 3: %.2f. */
                    __('  - %1$s: %2$d rows (%3$.2f MB)', 'mhm-rentiva'),
                    $key,
                    $stats['rows'],
                    $stats['size_mb']
                ));
            } else {
                /* translators: %s placeholder. */
                \WP_CLI::warning(sprintf(__('  - %s: NOT EXISTS', 'mhm-rentiva'), $key));
            }
        }
    }

    /**
     * Clean orphaned postmeta
     * 
     * ## OPTIONS
     * 
     * [--dry-run]
     * : Show what would be deleted without actually deleting
     * 
     * [--execute]
     * : Actually execute the cleanup
     * 
     * ## EXAMPLES
     * 
     *     wp mhm-rentiva cleanup orphaned --dry-run
     *     wp mhm-rentiva cleanup orphaned --execute
     * 
     * @when after_wp_load
     */
    public function orphaned($args, $assoc_args)
    {
        $dry_run = !isset($assoc_args['execute']);
        
        if ($dry_run) {
            \WP_CLI::log(__('DRY RUN MODE - No changes will be made', 'mhm-rentiva'));
        } else {
            \WP_CLI::warning(__('EXECUTE MODE - Changes will be permanent!', 'mhm-rentiva'));
            \WP_CLI::confirm(__('Continue with cleanup?', 'mhm-rentiva'));
        }
        
        $result = DatabaseCleaner::cleanup_orphaned_postmeta($dry_run);
        
        if ($dry_run) {
            \WP_CLI::success(sprintf(
                /* translators: %d placeholder. */
                __('Would delete %d orphaned meta records', 'mhm-rentiva'),
                $result['would_delete']
            ));
        } else {
            \WP_CLI::success(sprintf(
                /* translators: 1: %d; 2: %s. */
                __('Deleted %1$d orphaned meta records. Backup: %2$s', 'mhm-rentiva'),
                $result['deleted'],
                $result['backup_table']
            ));
        }
    }

    /**
     * Clean expired transients
     * 
     * ## OPTIONS
     * 
     * [--dry-run]
     * : Show what would be deleted
     * 
     * [--execute]
     * : Execute the cleanup
     * 
     * @when after_wp_load
     */
    public function transients($args, $assoc_args)
    {
        $dry_run = !isset($assoc_args['execute']);
        
        if (!$dry_run) {
            \WP_CLI::confirm(__('Continue with transient cleanup?', 'mhm-rentiva'));
        }
        
        $result = DatabaseCleaner::cleanup_expired_transients($dry_run);
        
        if ($dry_run) {
            /* translators: %d placeholder. */
            \WP_CLI::success(sprintf(__('Would delete %d expired transients', 'mhm-rentiva'), $result['would_delete']));
        } else {
            /* translators: %d placeholder. */
            \WP_CLI::success(sprintf(__('Deleted %d expired transients', 'mhm-rentiva'), $result['deleted']));
        }
    }

    /**
     * Full cleanup (all operations)
     * 
     * ## OPTIONS
     * 
     * [--dry-run]
     * : Show what would be done
     * 
     * [--execute]
     * : Execute all cleanup operations
     * 
     * [--days=<days>]
     * : Clean logs older than X days (default: 30)
     * 
     * @when after_wp_load
     */
    public function full($args, $assoc_args)
    {
        $dry_run = !isset($assoc_args['execute']);
        $days = isset($assoc_args['days']) ? (int) $assoc_args['days'] : self::DEFAULT_LOG_CLEANUP_DAYS;
        
        if (!$dry_run) {
            \WP_CLI::warning(__('FULL CLEANUP MODE - This will run all cleanup operations!', 'mhm-rentiva'));
            \WP_CLI::confirm(__('Continue?', 'mhm-rentiva'));
        }
        
        \WP_CLI::log(__('Running full database cleanup...', 'mhm-rentiva'));
        
        $result = DatabaseCleaner::full_cleanup($dry_run, [
            'old_logs_days' => $days,
            'optimize_tables' => !$dry_run
        ]);
        
        \WP_CLI::line('');
        \WP_CLI::log(__('Results:', 'mhm-rentiva'));
        
        foreach ($result['operations'] as $operation => $op_result) {
            \WP_CLI::log(sprintf('  - %s: %s', $operation, json_encode($op_result)));
        }
        
        if ($dry_run) {
            \WP_CLI::success(__('Dry run complete. Use --execute to apply changes.', 'mhm-rentiva'));
        } else {
            \WP_CLI::success(__('Full cleanup complete!', 'mhm-rentiva'));
        }
    }
}

// WP-CLI Command Registration
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('mhm-rentiva cleanup', DatabaseCleanupCommand::class);
}

