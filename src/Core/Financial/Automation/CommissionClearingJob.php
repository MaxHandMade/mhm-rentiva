<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Automation;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Core\Services\Metrics\MetricCacheManager;

/**
 * Background worker to clear matured pending commission credits.
 * Scheduled via WP-Cron.
 *
 * @since 4.64.0
 */
final class CommissionClearingJob {

    /**
     * Batch limit to prevent memory exhaustion and long-running execution.
     */
    private const BATCH_LIMIT = 50;

    /**
     * Fixed hold period: a commission credit is only clearable once this many
     * days have passed since it was created.
     */
    private const CLEARING_DELAY_DAYS = 7;

    /**
     * Register the cron schedule.
     */
    public static function register(): void
    {
        if (! wp_next_scheduled('mhm_rentiva_process_commission_clearing')) {
            wp_schedule_event(time(), 'hourly', 'mhm_rentiva_process_commission_clearing');
        }

        add_action('mhm_rentiva_process_commission_clearing', [ self::class, 'run' ]);
    }

    /**
     * Execution entrypoint.
     */
    public static function run(): void
    {
        global $wpdb;

        $table  = $wpdb->prefix . 'mhm_rentiva_ledger';
        $cutoff = gmdate('Y-m-d H:i:s', time() - ( self::CLEARING_DELAY_DAYS * DAY_IN_SECONDS ));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Worker must scan the live pending commission queue before processing.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, vendor_id, booking_id, order_id, amount, currency
                 FROM %i
                 WHERE type = %s AND status = %s AND created_at <= %s
                 LIMIT %d',
                $table,
                'commission_credit',
                'pending',
                $cutoff,
                self::BATCH_LIMIT
            )
        );

        if (empty($rows)) {
            return;
        }

        $cleared_count = 0;
        $skipped_count = 0;

        foreach ($rows as $row) {
            $id = (int) $row->id;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Guarded, idempotent transition; safe under concurrent workers.
            $updated = $wpdb->update(
                $table,
                [ 'status' => 'cleared' ],
                [
                    'id'     => $id,
                    'status' => 'pending',
                ],
                [ '%s' ],
                [ '%d', '%s' ]
            );

            if ($updated !== 1) {
                // Already processed by another worker in the meantime — not a failure.
                ++$skipped_count;
                continue;
            }

            // Invalidate the vendor's cached dashboard metrics (e.g. "Kullanılabilir
            // Bakiye") so it reflects the newly cleared balance immediately, instead
            // of showing a stale pre-clearing value until the cache naturally expires.
            if (class_exists(MetricCacheManager::class)) {
                MetricCacheManager::flush_subject_all_metrics( (string) $row->vendor_id);
            }

            do_action(
                'mhm_rentiva_commission_cleared',
                (int) $row->vendor_id,
                (float) $row->amount,
                (string) $row->currency,
                $row->booking_id !== null ? (int) $row->booking_id : 0,
                $row->order_id !== null ? (int) $row->order_id : 0
            );

            ++$cleared_count;
        }

        if ($cleared_count > 0 || $skipped_count > 0) {
            AdvancedLogger::info(
                'Commission clearing batch processing complete.',
                [
                    'cleared' => $cleared_count,
                    'skipped' => $skipped_count,
                ],
                'commission_automation'
            );
        }
    }
}
