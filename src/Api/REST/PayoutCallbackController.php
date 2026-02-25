<?php

declare(strict_types=1);

namespace MHMRentiva\Api\REST;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use MHMRentiva\Core\Logging\StructuredLogger;
use MHMRentiva\Core\Services\Metrics\MetricCacheManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * REST endpoint: POST /mhm-rentiva/v1/payouts/{id}/callback
 *
 * Called by an external payment processor to report the final outcome
 * of a payout that was already approved by admin (payout_debit in ledger).
 *
 * Authentication: HMAC SHA256 via PayoutWebhookAuth (see that class for spec).
 *
 * Request payload (JSON):
 * {
 *   "status": "confirmed" | "failed",
 *   "external_reference": "PROC_TRX_XYZ" // optional
 * }
 *
 * Ledger rules (immutable â€” NO UPDATE EVER):
 *   confirmed â†’ CPT meta _mhm_payout_status = 'confirmed'. Ledger unchanged.
 *   failed     â†’ New LedgerEntry (type='payout_reversal', amount=+abs, cleared).
 *                CPT meta _mhm_payout_status = 'failed'.
 *
 * Idempotency guard:
 *   If _mhm_payout_status is already set, returns 200 OK
 *   without re-processing. Prevents double-reversal on processor retry.
 *
 * @since 4.21.0
 */
final class PayoutCallbackController
{
    private const REST_NAMESPACE = 'mhm-rentiva/v1';
    private const ROUTE          = '/payouts/(?P<id>\d+)/callback';

    /**
     * Register REST route. Called on rest_api_init.
     */
    public static function register(): void
    {
        add_action('rest_api_init', array(self::class, 'register_route'));
    }

    /**
     * Register the route with the WP REST API.
     */
    public static function register_route(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::ROUTE,
            array(
                'methods'             => \WP_REST_Server::CREATABLE, // POST only
                'callback'            => array(self::class, 'handle'),
                'permission_callback' => array(self::class, 'authenticate'),
                'args'                => array(
                    'id' => array(
                        'type'     => 'integer',
                        'required' => true,
                        'minimum'  => 1,
                    ),
                ),
            )
        );
    }

    /**
     * Permission callback: HMAC signature verification.
     *
     * @param  \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public static function authenticate(\WP_REST_Request $request)
    {
        // Rate limit: 20 callbacks per 60 seconds per signature identity.
        $rate_id = (string) $request->get_header('X-MHM-Signature');
        if (! WebhookRateLimiter::check($rate_id, 20, 60)) {
            StructuredLogger::warning(
                'Webhook rate limit exceeded.',
                array('signature_prefix' => substr($rate_id, 0, 16) . '...'),
                'payout'
            );

            return new \WP_Error(
                'mhm_rentiva_rate_limited',
                __('Too many requests.', 'mhm-rentiva'),
                array('status' => 429)
            );
        }

        if (PayoutWebhookAuth::verify($request)) {
            return true;
        }

        return new \WP_Error(
            'mhm_rentiva_unauthorized',
            __('Invalid or missing webhook signature.', 'mhm-rentiva'),
            array('status' => 401)
        );
    }

    /**
     * Handle the callback.
     *
     * @param  \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function handle(\WP_REST_Request $request)
    {
        $payout_id  = (int) $request->get_param('id');
        $status_raw = sanitize_key((string) ($request->get_param('status') ?? ''));
        $ext_ref    = sanitize_text_field((string) ($request->get_param('external_reference') ?? ''));

        // Validate status value.
        if (! in_array($status_raw, array('confirmed', 'failed'), true)) {
            return new \WP_Error(
                'mhm_rentiva_invalid_status',
                __('Status must be "confirmed" or "failed".', 'mhm-rentiva'),
                array('status' => 400)
            );
        }

        // Load CPT post.
        $post = get_post($payout_id);
        if (! $post instanceof \WP_Post || $post->post_type !== PostType::POST_TYPE) {
            return new \WP_Error(
                'mhm_rentiva_not_found',
                __('Payout not found.', 'mhm-rentiva'),
                array('status' => 404)
            );
        }

        // Only approved (publish) payouts can receive processor callbacks.
        if ($post->post_status !== 'publish') {
            return new \WP_Error(
                'mhm_rentiva_invalid_state',
                __('Payout is not in an approved state.', 'mhm-rentiva'),
                array('status' => 409)
            );
        }

        // â”€â”€â”€ IDEMPOTENCY GUARD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // If processor status already set, this is a duplicate callback.
        // Return 200 OK silently â€” do not re-process.
        $existing_status = (string) get_post_meta($payout_id, '_mhm_payout_status', true);
        if ($existing_status !== '') {
            return new \WP_REST_Response(
                array(
                    'success'   => true,
                    'idempotent' => true,
                    'status'    => $existing_status,
                    'message'   => __('Callback already processed.', 'mhm-rentiva'),
                ),
                200
            );
        }
        // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

        $vendor_id = (int) $post->post_author;

        if ($status_raw === 'confirmed') {
            // Confirmed: update CPT meta only. Ledger stays as-is (payout_debit cleared).
            update_post_meta($payout_id, '_mhm_payout_status', 'confirmed');
            if ($ext_ref !== '') {
                update_post_meta($payout_id, '_mhm_payout_external_ref', $ext_ref);
            }

            MetricCacheManager::flush_subject_all_metrics((string) $vendor_id);

            return new \WP_REST_Response(
                array(
                    'success' => true,
                    'status'  => 'confirmed',
                    'message' => __('Payout confirmed.', 'mhm-rentiva'),
                ),
                200
            );
        }

        // â”€â”€â”€ FAILED: Append reversal entry to ledger (NEVER update existing entry) â”€â”€
        $original_amount = abs((float) get_post_meta($payout_id, '_mhm_payout_amount', true));
        $currency        = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'TRY';
        $reversal_uuid   = 'payout_reversal_' . $payout_id . '_' . time();

        $reversal_entry = new LedgerEntry(
            $reversal_uuid,
            $vendor_id,
            null, // booking_id
            null, // order_id
            'payout_reversal',
            $original_amount, // Positive: re-credits the vendor balance
            null, // gross_amount
            null, // commission_amount
            null, // commission_rate
            $currency,
            'payout',
            'cleared'
        );

        try {
            Ledger::add_entry($reversal_entry);
        } catch (\RuntimeException $e) {
            return new \WP_Error(
                'mhm_rentiva_ledger_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }

        // Mark CPT with failed status.
        update_post_meta($payout_id, '_mhm_payout_status', 'failed');
        if ($ext_ref !== '') {
            update_post_meta($payout_id, '_mhm_payout_external_ref', $ext_ref);
        }

        MetricCacheManager::flush_subject_all_metrics((string) $vendor_id);

        return new \WP_REST_Response(
            array(
                'success'          => true,
                'status'           => 'failed',
                'reversal_uuid'    => $reversal_uuid,
                'message'          => __('Payout failed. Reversal entry written to ledger.', 'mhm-rentiva'),
            ),
            200
        );
    }
}
