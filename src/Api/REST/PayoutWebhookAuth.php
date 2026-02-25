<?php

declare(strict_types=1);

namespace MHMRentiva\Api\REST;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * HMAC SHA256 webhook signature authenticator for payout callbacks.
 *
 * Headers required on every inbound callback request:
 *   X-MHM-Timestamp  Unix timestamp (integer string)
 *   X-MHM-Signature  sha256=<hex-encoded HMAC>
 *
 * Signature algorithm:
 *   $expected = hash_hmac('sha256', $timestamp . '.' . $raw_body, $secret)
 *
 * Replay attack guard:
 *   Request is rejected if |time() - timestamp| > 300 seconds (5 minutes).
 *
 * Secret storage:
 *   WP option key: 'mhm_rentiva_payout_webhook_secret'
 *   If empty, all callbacks are rejected.
 *
 * @since 4.21.0
 */
final class PayoutWebhookAuth
{
    /**
     * Tolerance window in seconds for replay attack prevention.
     */
    private const TIMESTAMP_TOLERANCE = 300;

    /**
     * WP option key holding the shared HMAC secret.
     */
    public const OPTION_SECRET = 'mhm_rentiva_payout_webhook_secret';

    /**
     * Verify an inbound webhook request.
     *
     * @param  \WP_REST_Request $request  Incoming REST request object.
     * @return bool  True if signature is valid and timestamp is within tolerance.
     */
    public static function verify(\WP_REST_Request $request): bool
    {
        $secret = (string) get_option(self::OPTION_SECRET, '');

        // An empty secret means HMAC is not configured — reject all callbacks.
        if ($secret === '') {
            return false;
        }

        $timestamp_header   = (string) $request->get_header('X-MHM-Timestamp');
        $signature_header   = (string) $request->get_header('X-MHM-Signature');

        // Both headers are mandatory.
        if ($timestamp_header === '' || $signature_header === '') {
            return false;
        }

        $timestamp = (int) $timestamp_header;

        // Replay attack guard: reject stale requests.
        if (abs(time() - $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        // Strip the 'sha256=' prefix if present (e.g. Stripe-style header format).
        $received_hash = str_starts_with($signature_header, 'sha256=')
            ? substr($signature_header, 7)
            : $signature_header;

        // Raw body must be read before WP_REST_Request parses it.
        $raw_body = $request->get_body();

        $expected_hash = hash_hmac('sha256', $timestamp_header . '.' . $raw_body, $secret);

        // Timing-safe comparison — prevents timing-based signature extraction.
        return hash_equals($expected_hash, $received_hash);
    }
}
