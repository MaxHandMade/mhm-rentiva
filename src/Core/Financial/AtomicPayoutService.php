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
 *   START TRANSACTION
 *     → Ledger::add_entry()     (throws RuntimeException on failure)
 *     → wp_update_post()        (returns WP_Error or 0 on failure)
 *   COMMIT   — only if both succeed
 *   ROLLBACK — if either fails
 *
 * InnoDB is required (enforced by LedgerMigration ENGINE=InnoDB).
 * Note: wp_update_post() uses $wpdb internally and respects the transaction.
 * WP object cache is flushed post-COMMIT only (no cache mutations on ROLLBACK).
 *
 * @since 4.21.0
 */
final class AtomicPayoutService
{
    /**
     * Approve a payout atomically.
     *
     * Validates CPT state first (outside transaction to avoid locking on validation errors).
     * Opens transaction only when ready to write — minimizes lock duration.
     *
     * @param  int $payout_id  mhm_payout CPT post ID.
     * @return true|\WP_Error
     */
    public static function approve(int $payout_id)
    {
        global $wpdb;

        // ── Pre-flight validation (no transaction yet) ──────────────────────────
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

        // ── Atomic transaction block ────────────────────────────────────────────
        $wpdb->query('START TRANSACTION');

        try {
            // 1. Ledger write — throws RuntimeException on DB failure.
            Ledger::add_entry($entry);

            // 2. CPT status update — runs inside the same connection/transaction.
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
                        : 'wp_update_post returned 0 — CPT status update failed.'
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

        // ── Post-COMMIT side effects (outside transaction) ──────────────────────
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
