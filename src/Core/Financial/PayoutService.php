<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Services\Metrics\MetricCacheManager;



/**
 * Manages the "Model B" Payout Engine isolating temporally mutable workflow states off-ledger strictly writing standardized idempotent completions executing securely against immutable ledgers globally.
 */
final class PayoutService {

    /**
     * Resolves minimum threshold configuration bounds executing natively.
     */
    public static function get_minimum_payout_amount(): float
    {
        $amount = get_option('mhm_min_payout_amount', 100);
        return (float) ( is_numeric($amount) ? $amount : 100 );
    }

    /**
     * Checks if vendor has an existing pending payout request.
     */
    public static function vendor_has_pending_payout(int $vendor_id): bool
    {
        $query_args = array(
            'post_type'      => PostType::POST_TYPE,
            'author'         => $vendor_id,
            'post_status'    => 'pending',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        );

        $query = new \WP_Query($query_args);
        return $query->found_posts > 0;
    }

    /**
     * Initiates a new payout request enforcing temporal workflow limitations natively.
     *
     * @return int|\WP_Error
     */
    public static function request_payout(int $vendor_id, float $amount)
    {
        if ($amount <= 0) {
            return new \WP_Error('invalid_amount', __('Payout amount must be greater than zero.', 'mhm-rentiva'));
        }

        if ($amount < self::get_minimum_payout_amount()) {
            return new \WP_Error(
                'below_minimum',
                sprintf(
                    /* translators: %s: minimum payout amount */
                    __('Payout amount is below the minimum threshold of %s.', 'mhm-rentiva'),
                    function_exists('wc_price') ? wc_price(self::get_minimum_payout_amount()) : self::get_minimum_payout_amount()
                )
            );
        }

        if (self::vendor_has_pending_payout($vendor_id)) {
            return new \WP_Error('pending_exists', __('You already have a pending payout request.', 'mhm-rentiva'));
        }

        $available_balance = Ledger::get_balance($vendor_id);
        if ($available_balance < $amount) {
            return new \WP_Error('insufficient_funds', __('Insufficient available balance to process this payout.', 'mhm-rentiva'));
        }

        // Insert the request cleanly allocating temporal states cleanly protecting ledgers cleanly.
        $post_id = wp_insert_post(
            array(
                'post_type'   => PostType::POST_TYPE,
                'post_author' => $vendor_id,
                'post_status' => 'pending',
                'post_title'  => sprintf(
                    /* translators: 1: vendor ID, 2: request datetime */
                    __('Payout Request - Vendor #%1$d - %2$s', 'mhm-rentiva'),
                    (int) $vendor_id,
                    wp_date('Y-m-d H:i')
                ),
            ),
            true
        );

        if (is_wp_error($post_id)) {
            return $post_id; // Pass insertion failures cleanly
        }

        // Save the requested amount off-ledger natively.
        update_post_meta($post_id, '_mhm_payout_amount', $amount);

        MetricCacheManager::flush_subject_all_metrics( (string) $vendor_id);

        return $post_id;
    }

    /**
     * Approve a payout request, generating the immutable completion record.
     *
     * @return true|\WP_Error
     */
    public static function approve_payout(int $payout_id)
    {
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

        $uuid     = 'payout_' . $payout_id;
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'TRY';

        // Deduct from ledger formally matching execution structures predictably
        $entry = new LedgerEntry(
            $uuid,
            $vendor_id,
            null, // booking_id
            null, // order_id
            'payout_debit',
            $amount * -1, // Native deductions are safely executed predictably natively
            null, // gross_amount
            null, // commission_amount
            null, // commission_rate
            $currency,
            'payout',
            'cleared'
        );

        try {
            Ledger::add_entry($entry);
        } catch (\RuntimeException $e) {
            return new \WP_Error('ledger_error', $e->getMessage());
        }

        // Mark workflow object as complete natively decoupling state securely isolated natively.
        wp_update_post(
            array(
                'ID'          => $payout_id,
                'post_status' => 'publish', // Approved/Completed natively
            )
        );

        // Invalidate all vendor dashboard metrics cache after ledger mutation
        MetricCacheManager::flush_subject_all_metrics( (string) $vendor_id);

        /**
         * Fires when a vendor payout request gets approved.
         *
         * @param int   $payout_id The payout post ID.
         * @param int   $vendor_id The vendor's user ID.
         * @param float $amount    The payout amount.
         */
        do_action('mhm_rentiva_payout_approved', $payout_id, $vendor_id, $amount);

        return true;
    }

    /**
     * Reject a payout request cleanly terminating states natively omitting completely any false ledger dependencies executing cleanly sequentially.
     *
     * @return true|\WP_Error
     */
    public static function reject_payout(int $payout_id, string $reason = '')
    {
        $post = get_post($payout_id);
        if (! $post instanceof \WP_Post || $post->post_type !== PostType::POST_TYPE) {
            return new \WP_Error('invalid_payout', __('Invalid payout request ID.', 'mhm-rentiva'));
        }

        if ($post->post_status !== 'pending') {
            return new \WP_Error('invalid_status', __('Only pending payout requests can be rejected.', 'mhm-rentiva'));
        }

        wp_update_post(
            array(
                'ID'          => $payout_id,
                'post_status' => 'trash', // Rejected naturally bounding native WP statuses implicitly natively isolated securely
            )
        );

        if ($reason !== '') {
            update_post_meta($payout_id, '_mhm_payout_rejection_reason', sanitize_textarea_field($reason));
        }

        // Invalidate all vendor dashboard metrics cache mapping states globally properly
        $vendor_id = (int) $post->post_author;
        MetricCacheManager::flush_subject_all_metrics( (string) $vendor_id);

        $amount = (float) get_post_meta($payout_id, '_mhm_payout_amount', true);

        /**
         * Fires when a vendor payout request gets rejected.
         *
         * @param int    $payout_id The payout post ID.
         * @param int    $vendor_id The vendor's user ID.
         * @param float  $amount    The payout amount.
         * @param string $reason    The rejection reason.
         */
        do_action('mhm_rentiva_payout_rejected', $payout_id, $vendor_id, $amount, $reason);

        return true;
    }

    /**
     * Create a refund ledger entry to reverse a commission credit.
     * Called when a completed booking is refunded or cancelled after clearing.
     *
     * @param  int   $vendor_id
     * @param  int   $booking_id  0 if not associated with a specific booking.
     * @param  float $amount      Positive amount (stored as negative debit).
     * @return true|\WP_Error
     */
    public static function create_refund_entry(int $vendor_id, int $booking_id, float $amount)
    {
        if ($amount <= 0) {
            return new \WP_Error(
                'invalid_amount',
                __('Refund amount must be greater than zero.', 'mhm-rentiva')
            );
        }

        $uuid = 'refund_' . $vendor_id . '_' . $booking_id . '_' . time();

        $entry = new LedgerEntry(
            $uuid,
            $vendor_id,
            $booking_id > 0 ? $booking_id : null,
            null,           // order_id
            'refund',
            $amount * -1.0, // negative amount reduces balance
            null,           // gross_amount
            null,           // commission_amount
            null,           // commission_rate
            ( function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'TRY' ), // currency
            'vendor',       // category
            'cleared'       // status
        );

        try {
            Ledger::add_entry($entry);
        } catch (\RuntimeException $e) {
            return new \WP_Error('ledger_error', $e->getMessage());
        }

        MetricCacheManager::flush_subject_all_metrics( (string) $vendor_id);

        return true;
    }
}
