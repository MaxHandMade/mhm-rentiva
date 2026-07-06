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

        $transaction_uuid = 'pay_ref_' . $refund_id . '_' . $order_id;

        // Always 'cleared' (immediate, real debit) regardless of whether the original
        // commission_credit for this booking has cleared yet. The original credit is
        // NEVER voided or amount-adjusted — it clears normally, at its original
        // full-order-gross value, on its own 7-day schedule. Addition is commutative:
        // whenever both entries have landed, original_credit + this_debit always equals
        // the correct commission on the amount the vendor actually retained, whether the
        // refund is partial or full, and whether it arrives before or after clearing.
        // (A refund arriving before the original clears will show a temporary balance
        // dip until the original credit's own 7-day hold elapses — expected and correct,
        // not a bug: that reflects money not yet released being provisionally offset.)
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
            'cleared',
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
