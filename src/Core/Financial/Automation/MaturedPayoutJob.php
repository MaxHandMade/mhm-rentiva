<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Automation;

use MHMRentiva\Core\Financial\AtomicPayoutService;
use MHMRentiva\Core\Financial\ApprovalStateMachine;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Background worker to process matured time-locked payouts.
 * Scheduled via WP-Cron.
 *
 * @since 4.23.0
 */
final class MaturedPayoutJob
{
    /**
     * Batch limit to prevent memory exhaustion and long-running execution.
     */
    private const BATCH_LIMIT = 50;

    /**
     * Register the cron schedule.
     */
    public static function register(): void
    {
        if (! wp_next_scheduled('mhm_rentiva_process_matured_payouts')) {
            wp_schedule_event(time(), 'hourly', 'mhm_rentiva_process_matured_payouts');
        }

        add_action('mhm_rentiva_process_matured_payouts', [self::class, 'run']);
    }

    /**
     * Execution entrypoint.
     */
    public static function run(): void
    {
        global $wpdb;

        // 1. Fetch matured payout IDs (strictly those in TIME_LOCKED state and date passed)
        // Using gmdate for UTC strict comparison as per Chief Engineer.
        $now_utc = gmdate('Y-m-d H:i:s');

        $payout_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID 
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = '_mhm_workflow_state'
                 JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = '_mhm_release_after'
                 JOIN {$wpdb->postmeta} m3 ON p.ID = m3.post_id AND m3.meta_key = '_mhm_lock_status'
                 WHERE m1.meta_value = %s
                 AND m2.meta_value <= %s
                 AND m3.meta_value IN ('LOCKED', 'MATURED')
                 LIMIT %d",
                ApprovalStateMachine::STATE_TIME_LOCKED,
                $now_utc,
                self::BATCH_LIMIT
            )
        );

        if (empty($payout_ids)) {
            return;
        }

        $success_count = 0;
        $fail_count    = 0;

        // 2. Process each payout in its own transaction (Atomic isolation)
        foreach ($payout_ids as $payout_id) {
            $payout_id = (int) $payout_id;

            // ─── SaaS Control Plane Guard (Worker Skip) ───────────────────────
            // Resolve current tenant context first
            \MHMRentiva\Core\Tenancy\TenantResolver::reset();
            $tenant_id = (int) \MHMRentiva\Core\Tenancy\TenantResolver::resolve()->get_id();

            if (class_exists('\\MHMRentiva\\Core\\Orchestration\\ControlPlaneGuard')) {
                if (! \MHMRentiva\Core\Orchestration\ControlPlaneGuard::is_operational($tenant_id)) {
                    // Skip suspended/terminated tenants silently per Chief Engineer
                    continue;
                }
            }

            // finalize_time_locked_payout() handles its own idempotency and DB transaction
            $result = AtomicPayoutService::finalize_time_locked_payout($payout_id);

            if (is_wp_error($result)) {
                // If it's just idempotency_abort, it's not a failure, just already processed
                if ($result->get_error_code() !== 'idempotency_abort') {
                    $fail_count++;
                    AdvancedLogger::error(
                        'Failed to finalize matured payout.',
                        ['payout_id' => $payout_id, 'error' => $result->get_error_message()],
                        'payout_automation'
                    );
                }
                continue;
            }

            $success_count++;
        }

        if ($success_count > 0 || $fail_count > 0) {
            AdvancedLogger::info(
                'Matured payout batch processing complete.',
                ['success' => $success_count, 'failed' => $fail_count],
                'payout_automation'
            );
        }
    }
}
