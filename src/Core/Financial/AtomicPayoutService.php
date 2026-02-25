<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Logging\StructuredLogger;
use MHMRentiva\Core\Services\Metrics\MetricCacheManager;

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
     * @return true|\WP_Error
     */
    public static function approve(int $payout_id)
    {
        global $wpdb;

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

        $entry = new LedgerEntry(
            $uuid,
            $vendor_id,
            null, // booking_id
            null, // order_id
            'payout_debit',
            $amount * -1,
            null, // gross_amount
            null, // commission_amount
            null, // commission_rate
            $currency,
            'payout',
            'cleared'
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
            Ledger::add_entry($entry);

            // rows_affected guard: ensure the insert physically landed.
            // Ledger::add_entry() throws RuntimeException on error, but an empty
            // affected-rows (e.g. duplicate uuid on UNIQUE KEY) must also be caught.
            if ((int) $wpdb->rows_affected !== 1) {
                throw new \RuntimeException(
                    sprintf(
                        'Ledger insert for payout #%d did not affect exactly 1 row (rows_affected=%d). Possible duplicate UUID.',
                        $payout_id,
                        (int) $wpdb->rows_affected
                    )
                );
            }

            // ─── CPT STATUS UPDATE ───────────────────────────────────────────────
            $updated = wp_update_post(
                array(
                    'ID'          => $payout_id,
                    'post_status' => 'publish',
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

            $wpdb->query('COMMIT');
        } catch (\RuntimeException $e) {
            $wpdb->query('ROLLBACK');

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
}
