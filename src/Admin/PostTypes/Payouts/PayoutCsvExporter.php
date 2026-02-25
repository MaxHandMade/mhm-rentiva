<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Payouts;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Streams a CSV of payout history records for admin download.
 *
 * Triggered via: GET /wp-admin/admin-post.php?action=mhm_export_payouts
 * Protected by: manage_options capability + _wpnonce verification.
 *
 * CSV columns:
 *   Payout ID, Vendor ID, Vendor Name, Amount, Currency,
 *   CPT Status, Processor Status, External Reference, Requested At
 *
 * @since 4.21.0
 */
final class PayoutCsvExporter
{
    /**
     * Register the admin-post.php action hook.
     */
    public static function register(): void
    {
        add_action('admin_post_' . self::action_name(), array(self::class, 'handle'));
    }

    /**
     * Action name constant to avoid magic strings.
     */
    public static function action_name(): string
    {
        return 'mhm_export_payouts';
    }

    /**
     * Handle the export request.
     * Validates nonce, streams CSV headers, outputs rows, exits.
     */
    public static function handle(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'mhm-rentiva'), 403);
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, self::action_name())) {
            wp_die(esc_html__('Nonce verification failed.', 'mhm-rentiva'), 403);
        }

        $posts = get_posts(array(
            'post_type'      => PostType::POST_TYPE,
            'post_status'    => array('pending', 'publish', 'trash'),
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'TRY';
        $filename = 'payouts-' . gmdate('Y-m-d') . '.csv';

        // Stream CSV headers — no output buffering before this point.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            wp_die(esc_html__('Failed to open output stream.', 'mhm-rentiva'), 500);
        }

        // BOM for Excel UTF-8 compatibility.
        fwrite($out, "\xEF\xBB\xBF");

        // Header row.
        fputcsv($out, array(
            'Payout ID',
            'Vendor ID',
            'Vendor Name',
            'Amount',
            'Currency',
            'CPT Status',
            'Processor Status',
            'External Reference',
            'Requested At (UTC)',
        ));

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $vendor_id  = (int) $post->post_author;
            $user       = get_userdata($vendor_id);
            $vendor_name = $user instanceof \WP_User
                ? ($user->display_name ?: $user->user_login)
                : 'Unknown';

            $amount           = (float) get_post_meta($post->ID, '_mhm_payout_amount', true);
            $processor_status = (string) get_post_meta($post->ID, '_mhm_payout_status', true);
            $external_ref     = (string) get_post_meta($post->ID, '_mhm_payout_external_ref', true);

            fputcsv($out, array(
                $post->ID,
                $vendor_id,
                $vendor_name,
                number_format($amount, 2, '.', ''),
                $currency,
                $post->post_status,
                $processor_status !== '' ? $processor_status : 'n/a',
                $external_ref !== '' ? $external_ref : 'n/a',
                $post->post_date_gmt,
            ));
        }

        fclose($out);
        exit;
    }

    /**
     * Returns a nonce-protected export URL for admin use.
     */
    public static function get_export_url(): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=' . self::action_name()),
            self::action_name()
        );
    }
}
