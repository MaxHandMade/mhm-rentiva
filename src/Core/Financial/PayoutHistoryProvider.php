<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\PostTypes\Payouts\PostType;



/**
 * Retrieves a vendor's payout history from the mhm_payout CPT.
 *
 * Maps CPT post_status and _mhm_payout_status meta to a unified
 * display status used by the vendor dashboard template:
 *
 *   pending  → 'pending'   (awaiting admin approval)
 *   publish  + no meta     → 'approved'   (ledger debit written, no processor yet)
 *   publish  + 'confirmed' → 'confirmed'  (external processor confirmed)
 *   publish  + 'failed'    → 'failed'     (payout_reversal entry in ledger)
 *   trash                  → 'rejected'   (admin declined, no ledger impact)
 *
 * @since 4.21.0
 */
final class PayoutHistoryProvider {

    /**
     * Get payout history for a vendor.
     *
     * @param  int $vendor_id    WordPress user ID.
     * @param  int $limit        Max records to return. -1 for all.
     * @return array<int, array<string, mixed>>
     */
    public static function get_for_vendor(int $vendor_id, int $limit = 20): array
    {
        if ($vendor_id <= 0) {
            return array();
        }

        $posts = get_posts(array(
            'post_type'      => PostType::POST_TYPE,
            'author'         => $vendor_id,
            'post_status'    => array( 'pending', 'publish', 'trash' ),
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $rows = array();

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $rows[] = array(
                'id'                 => $post->ID,
                'amount'             => (float) get_post_meta($post->ID, '_mhm_payout_amount', true),
                'status'             => self::resolve_display_status($post),
                'external_reference' => (string) get_post_meta($post->ID, '_mhm_payout_external_ref', true),
                'rejection_reason'   => (string) get_post_meta($post->ID, '_mhm_payout_rejection_reason', true),
                'requested_at'       => $post->post_date_gmt, // UTC
            );
        }

        return $rows;
    }

    /**
     * Map CPT status + meta to a single display status string.
     *
     * @param  \WP_Post $post
     * @return string  One of: pending | approved | confirmed | failed | rejected
     */
    private static function resolve_display_status(\WP_Post $post): string
    {
        if ($post->post_status === 'trash') {
            return 'rejected';
        }

        if ($post->post_status === 'pending') {
            return 'pending';
        }

        // post_status === 'publish' — check processor meta for sub-status.
        $processor_status = (string) get_post_meta($post->ID, '_mhm_payout_status', true);

        if ($processor_status === 'confirmed') {
            return 'confirmed';
        }

        if ($processor_status === 'failed') {
            return 'failed';
        }

        return 'approved'; // Admin approved, no external processor response yet.
    }
}
