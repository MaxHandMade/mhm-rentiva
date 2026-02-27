<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Logging\StructuredLogger;
use MHMRentiva\Core\Services\Metrics\MetricCacheManager;
use MHMRentiva\Core\Financial\Events\DomainEventDispatcher;
use MHMRentiva\Core\Financial\Events\PayoutApprovedEvent;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Atomic payout approval — wraps ledger write + CPT status update in a DB transaction.
 *
 * This is the infrastructure layer over the domain-pure PayoutService.
 * PayoutListTable::process_bulk_approve() calls this class, NOT PayoutService directly.
 *
 * Transaction contract:
 *   Pre-flight validation (outside TX — avoids unnecessary lock acquisition)
 *   START TRANSACTION
 *     → Re-validate post_status inside TX (deadlock/concurrent guard)
 *     → Ledger::add_entry()   — throws RuntimeException on failure
 *     → rows_affected guard   — throws if insert did not land exactly 1 row
 *     → wp_update_post()      — throws on WP_Error or 0 return
 *   COMMIT   — only if all three steps succeed
 *   ROLLBACK — on any exception
 *
 * InnoDB required. LedgerMigration enforces ENGINE=InnoDB.
 * wp_update_post() fires internal WP hooks inside the transaction.
 * Those hooks must not open nested transactions — caller responsibility.
 *
 * Nested TX detection: We cannot reliably detect an open WP transaction (no WP native
 * flag). Callers must ensure AtomicPayoutService::approve() is never called from within
 * another transaction context. This is documented as a usage contract, not a runtime guard,
 * to avoid the overhead of per-call autocommit queries in bulk loops.
 *
 * @since 4.21.0
 */
final class AtomicPayoutService
{
    /**
     * Approve a payout atomically.
     *
     * @param  int $payout_id  mhm_payout CPT post ID.
     * @param  DomainEventDispatcher|null $dispatcher Optional forensic context
     * @return true|\WP_Error
     */
    public static function approve(int $payout_id, ?DomainEventDispatcher $dispatcher = null)
    {
        global $wpdb;

        // tx_uuid is generated here to uniquely represent the logical transaction
        $tx_uuid = wp_generate_uuid4();

        // ── Pre-flight validation (outside TX — keep lock window minimal) ──────
        $post = get_post($payout_id);
        if (! $post instanceof \WP_Post || $post->post_type !== PostType::POST_TYPE) {
            return new \WP_Error('invalid_payout', __('Invalid payout request ID.', 'mhm-rentiva'));
        }

        if ($post->post_status !== 'pending') {
            return new \WP_Error('invalid_status', __('Only pending payout requests can be approved.', 'mhm-rentiva'));
        }

        $amount = (float) get_post_meta($payout_id, '_mhm_payout_amount', true);
        if ($amount <= 0) {
            return new \WP_Error('invalid_amount', __('Payout amount is invalid or zero.', 'mhm-rentiva'));
        }

        $vendor_id = (int) $post->post_author;
        $uuid      = 'payout_' . $payout_id;
        $currency  = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'TRY';

        // ── SaaS Control Plane Guard (Quota & Status) ────────────────────────────
        $tenant_id = (int) \MHMRentiva\Core\Tenancy\TenantResolver::resolve()->get_id();
        try {
            \MHMRentiva\Core\Orchestration\ControlPlaneGuard::assert_operational_and_quota($tenant_id, 'payouts');
        } catch (\Exception $e) {
            return new \WP_Error('saas_block', $e->getMessage());
        }

        $entry = new LedgerEntry(
            $uuid,
            $vendor_id,
            null, // booking_id
            null, // order_id
            'payout_pending_debit',
            $amount * -1,
            null, // gross_amount
            null, // commission_amount
            null, // commission_rate
            $currency,
            'payout',
            'reserved'
        );

        // ── Atomic transaction block ─────────────────────────────────────────────
        $wpdb->query('START TRANSACTION');

        try {
            // ─── DEADLOCK / CONCURRENT-APPROVE GUARD ────────────────────────────
            // Re-read post_status directly from DB inside the transaction.
            // Prevents two concurrent admin approvals both reading 'pending'
            // outside the TX and both proceeding to write a payout_debit.
            // get_post() uses WP object cache — bypass with a direct DB read.
            $current_status = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    'SELECT post_status FROM %i WHERE ID = %d',
                    $wpdb->posts,
                    $payout_id
                )
            );

            if ($current_status !== 'pending') {
                throw new \RuntimeException(
                    sprintf(
                        'Payout #%d concurrent-approval guard: expected pending, got %s.',
                        $payout_id,
                        (string) $current_status
                    )
                );
            }

            // ─── LEDGER WRITE ────────────────────────────────────────────────────
            $rows_affected = Ledger::add_entry($entry);

            // rows_affected guard: ensure the insert physically landed.
            // Ledger::add_entry() returns 1 if new row inserted, 0 if duplicate.
            // This return value is isolated from potential side-effects like metering increments.
            if ($rows_affected !== 1) {
                throw new \RuntimeException(
                    sprintf(
                        'Ledger insert for payout #%d did not affect exactly 1 row (rows_affected=%d). Possible duplicate UUID.',
                        $payout_id,
                        (int) $rows_affected
                    )
                );
            }

            // ─── CPT STATUS UPDATE ───────────────────────────────────────────────
            $updated = wp_update_post(
                array(
                    'ID'          => $payout_id,
                    'post_status' => 'publish', // Maintain technical 'publish' but workflow state logic will dominate
                ),
                true // return WP_Error on failure
            );

            if (is_wp_error($updated) || (int) $updated === 0) {
                throw new \RuntimeException(
                    is_wp_error($updated)
                        ? $updated->get_error_message()
                        : sprintf('wp_update_post returned 0 for payout #%d.', $payout_id)
                );
            }

            if ($dispatcher !== null) {
                $dispatcher->dispatch(new PayoutApprovedEvent($payout_id, $tx_uuid));
                $dispatcher->flush(); // Execute event callbacks inside the transaction window
            }

            $wpdb->query('COMMIT');
        } catch (\RuntimeException $e) {
            $wpdb->query('ROLLBACK');

            if ($dispatcher !== null) {
                $dispatcher->discard(); // Clear buffer to ensure no audit trace escapes rollback
            }

            StructuredLogger::error(
                'Atomic payout approve failed — transaction rolled back.',
                array(
                    'payout_id' => $payout_id,
                    'vendor_id' => $vendor_id,
                    'amount'    => $amount,
                    'error'     => $e->getMessage(),
                ),
                'payout'
            );

            return new \WP_Error('atomic_approve_failed', $e->getMessage());
        }

        // ── Post-COMMIT side effects (outside transaction) ───────────────────────
        // Capture SaaS Metering
        \MHMRentiva\Core\Orchestration\MeteredUsageTracker::increment($tenant_id, 'payouts');

        MetricCacheManager::flush_subject_all_metrics((string) $vendor_id);

        StructuredLogger::info(
            'Payout approved atomically.',
            array(
                'payout_id' => $payout_id,
                'vendor_id' => $vendor_id,
                'amount'    => $amount,
                'uuid'      => $uuid,
            ),
            'payout'
        );

        return true;
    }

    /**
     * Finalizes a time-locked payout by moving it to the 'EXECUTED' lock status.
     * Implements the strict idempotency guard required for concurrent workers.
     *
     * @param int $payout_id
     * @return true|\WP_Error
     */
    public static function finalize_time_locked_payout(int $payout_id)
    {
        global $wpdb;

        // 1. Strict SQL-level Idempotency Guard
        // We update the lock status from LOCKED (or MATURED) to EXECUTED.
        // If another process already did this, rows_affected will be 0.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} 
                 SET meta_value = %s 
                 WHERE post_id = %d 
                 AND meta_key = %s 
                 AND meta_value IN ('LOCKED', 'MATURED')",
                'EXECUTED',
                $payout_id,
                '_mhm_lock_status'
            )
        );

        if ($updated !== 1) {
            // Already processed by another worker or invalid state
            return new \WP_Error(
                'idempotency_abort',
                sprintf('Payout #%d finalization aborted: already executed or invalid lock status.', $payout_id)
            );
        }

        // 2. Clear the Ledger Reservation
        // Instead of adding a new entry (0-amount finalize entry Prohibited by Chief Engineer),
        // we update the existing 'payout_pending_debit' entry from 'reserved' to 'cleared'.
        $payout_uuid = 'payout_' . $payout_id;
        $wpdb->update(
            $wpdb->prefix . 'mhm_rentiva_ledger',
            ['status' => 'cleared'],
            [
                'transaction_uuid' => $payout_uuid,
                'status'           => 'reserved'
            ],
            ['%s'],
            ['%s', '%s']
        );

        // 3. Update Workflow State to EXECUTED
        try {
            ApprovalStateMachine::atomic_update_state($wpdb, $payout_id, ApprovalStateMachine::STATE_TIME_LOCKED, ApprovalStateMachine::STATE_EXECUTED);
        } catch (\Exception $e) {
            // Log but don't fail the whole engine if meta update succeeded
            StructuredLogger::error(
                'Time-lock finalized but state transition failed.',
                ['payout_id' => $payout_id, 'error' => $e->getMessage()],
                'payout'
            );
        }

        // 3. Mark Ledger entry as 'cleared' (optional, but good for consistency)
        // Since we reserved it, we now officially clear it.
        $wpdb->update(
            $wpdb->prefix . 'mhm_rentiva_ledger',
            ['status' => 'cleared'],
            ['transaction_uuid' => 'payout_' . $payout_id, 'status' => 'reserved'],
            ['%s'],
            ['%s', '%s']
        );

        return true;
    }
}
