<?php
declare(strict_types=1);

namespace MHMRentiva\Integrations\WooCommerce;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\CommissionResolver;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use MHMRentiva\Core\Services\Metrics\MetricCacheManager;



/**
 * Event-Driven boundary parsing WooCommerce semantics and executing immutable Ledger append transactions.
 */
final class CommissionBridge {

    /**
     * Boot and bind WooCommerce hooks.
     */
    public static function boot(): void
    {
        add_action('woocommerce_payment_complete', array( self::class, 'on_payment_complete' ));
        add_action('woocommerce_order_status_completed', array( self::class, 'on_order_completed' ));
        add_action('woocommerce_order_refunded', array( self::class, 'on_order_refunded' ), 10, 2);
    }

    public static function on_payment_complete(int $order_id): void
    {
        self::process_order_capture($order_id);
    }

    public static function on_order_completed(int $order_id): void
    {
        self::process_order_capture($order_id);
    }

    /**
     * Executes core ledger injection for successful order captures.
     */
    private static function process_order_capture(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (! $order instanceof \WC_Order) {
            return;
        }

        // Ensure mapping is explicitly deterministic parsing booking meta
        $booking_id = (int) $order->get_meta('_mhm_booking_id');
        if ($booking_id <= 0) {
            return; // Payment was completely unrelated to a specific Rentiva Booking
        }

        $vendor_id = (int) get_post_field('post_author', $booking_id);
        if ($vendor_id <= 0) {
            return; // Invalid booking architecture, ghost author.
        }

        $payment_amount = (float) $order->get_total();
        $currency       = $order->get_currency();

        try {
            $commission_logic = CommissionResolver::calculate($payment_amount, $vendor_id);
        } catch (\InvalidArgumentException $e) {
            return; // Safely abort negative parsing inside isolated module resolving
        }

        $transaction_uuid = 'pay_cmp_' . $order_id . '_' . $booking_id; // Ensures idempotency per successful order capture

        $entry = new LedgerEntry(
            $transaction_uuid,
            $vendor_id,
            $booking_id,
            $order_id,
            'commission_credit',
            $commission_logic->get_vendor_net_amount(),
            $commission_logic->get_gross_amount(),
            $commission_logic->get_commission_amount(),
            $commission_logic->get_commission_rate_snapshot(),
            $currency,
            'vendor',
            'pending', // Status is pending until manual administrative payout or further hooks mature clearing
            null, // created_at (auto)
            $commission_logic->get_policy_id(),
            $commission_logic->get_policy_version_hash()
        );

        try {
            Ledger::add_entry($entry);
        } catch (\RuntimeException $e) {
            // Catch exception: Silent ignore for race condition Idempotent Duplicate Events as mandated in the prompt.
            return;
        }

        // Push to cache manager forcing instant dashboard refresh
        if (class_exists(MetricCacheManager::class)) {
            MetricCacheManager::flush_subject_all_metrics( (string) $vendor_id);
            $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
            if ($vehicle_id > 0) {
                MetricCacheManager::flush_subject_metric('vehicle', 'perf', (string) $vehicle_id);
            }
        }
    }

    /**
     * Creates a reverse record whenever an order completes a refund to ensure the ledger retains audit parity.
     */
    public static function on_order_refunded(int $order_id, int $refund_id): void
    {
        global $wpdb;

        $order = wc_get_order($order_id);
        if (! $order instanceof \WC_Order) {
            return;
        }

        $refund = wc_get_order($refund_id);
        if (! $refund instanceof \WC_Order_Refund) {
            return;
        }

        $booking_id = (int) $order->get_meta('_mhm_booking_id');
        if ($booking_id <= 0) {
            return;
        }

        $vendor_id = (int) get_post_field('post_author', $booking_id);
        if ($vendor_id <= 0) {
            return;
        }

        $refund_amount = (float) $refund->get_amount(); // Often positive integer inside `get_amount` for Refunds, inverse manually
        $currency      = $order->get_currency();

        try {
            $commission_logic = CommissionResolver::calculate($refund_amount, $vendor_id);
        } catch (\InvalidArgumentException $e) {
            return;
        }

        // Ensure strictly negative transaction values
        $net_deduction = -abs($commission_logic->get_vendor_net_amount());

        // Void the original pending credit FIRST (if still pending). Its rows-affected
        // count tells us, atomically, whether this is an early refund (credit was still
        // pending — nothing was ever released to the vendor) or a late refund (credit
        // already cleared — real money was already released and must be clawed back).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Guarded, idempotent void of the matching pending credit.
        $voided_rows = $wpdb->update(
            $wpdb->prefix . 'mhm_rentiva_ledger',
            array( 'status' => 'voided' ),
            array(
                'transaction_uuid' => 'pay_cmp_' . $order_id . '_' . $booking_id,
                'status'           => 'pending',
            ),
            array( '%s' ),
            array( '%s', '%s' )
        );

        // Early refund (credit voided just now, never released): the debit must NOT
        // also subtract from the balance, or the vendor ends up with a phantom negative
        // for a booking whose commission was never paid out. 'reversed' is excluded from
        // every Ledger:: balance query, so the net effect on this booking is zero.
        // Late refund (credit already cleared, $voided_rows === 0): the debit must be
        // 'cleared' so it actually claws back the already-released balance.
        $debit_status = ( $voided_rows === 1 ) ? 'reversed' : 'cleared';

        $transaction_uuid = 'pay_ref_' . $refund_id . '_' . $order_id;

        $entry = new LedgerEntry(
            $transaction_uuid,
            $vendor_id,
            $booking_id,
            $order_id,
            'commission_refund',
            $net_deduction,
            -$commission_logic->get_gross_amount(),
            -$commission_logic->get_commission_amount(),
            $commission_logic->get_commission_rate_snapshot(),
            $currency,
            'vendor',
            $debit_status,
            null, // created_at (auto)
            $commission_logic->get_policy_id(),
            $commission_logic->get_policy_version_hash()
        );

        try {
            Ledger::add_entry($entry);
        } catch (\RuntimeException $e) {
            return;
        }

        if (class_exists(MetricCacheManager::class)) {
            MetricCacheManager::flush_subject_all_metrics( (string) $vendor_id);
            $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
            if ($vehicle_id > 0) {
                MetricCacheManager::flush_subject_metric('vehicle', 'perf', (string) $vehicle_id);
            }
        }
    }
}
