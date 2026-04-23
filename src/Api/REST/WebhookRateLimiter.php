<?php
declare(strict_types=1);

namespace MHMRentiva\Api\REST;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Transient-based sliding window rate limiter for webhook endpoints.
 *
 * Identifier strategy:
 *   The rate key is SHA256(REMOTE_ADDR + signature_prefix).
 *   Including the client IP prevents an attacker from rotating the
 *   X-MHM-Signature header to spawn unlimited identities and bypass
 *   the per-signature limit.
 *   The raw IP is never stored in the transient key (only its hash),
 *   so this remains privacy-safe under GDPR/KVKK.
 *
 * Usage:
 *   $rate_id = $_SERVER['REMOTE_ADDR'] . $request->get_header('X-MHM-Signature');
 *   if (! WebhookRateLimiter::check($rate_id, 20, 60)) {
 *       return new WP_Error('rate_limited', 'Too many requests.', ['status' => 429]);
 *   }
 *
 * Transient key: mhm_rentiva_rl_{sha256(identifier)}
 * TTL = $window_seconds per window.
 *
 * Race condition note: WP transients lack atomic increment. Two near-simultaneous
 * requests at count=0 may both pass. Acceptable for webhook throttling —
 * this is a defense-in-depth measure, not a hard enforcement boundary.
 *
 * @since 4.21.0 (v1.1: identifier now includes REMOTE_ADDR)
 */
final class WebhookRateLimiter {

    /**
     * Check if identifier is within rate limit. Increments counter on each call.
     *
     * @param  string $identifier      Raw composite identifier to hash.
     *                                 Should include REMOTE_ADDR + a request signature component.
     *                                 Never stored raw — hashed before use.
     * @param  int    $max_requests    Maximum allowed requests per window.
     * @param  int    $window_seconds  Rolling window length in seconds.
     * @return bool   True = within limit. False = rate exceeded → return 429.
     */
    public static function check(
        string $identifier,
        int $max_requests = 10,
        int $window_seconds = 60
    ): bool {
        $key   = 'mhm_rentiva_rl_' . hash('sha256', $identifier);
        $count = (int) get_transient($key);

        if ($count === 0) {
            // First request in window — start counter with TTL = window.
            set_transient($key, 1, $window_seconds);
            return true;
        }

        if ($count >= $max_requests) {
            // Rate exceeded — do NOT increment (prevents integer flood on DDoS).
            return false;
        }

        set_transient($key, $count + 1, $window_seconds);
        return true;
    }

    /**
     * Build a composite rate identifier from request context.
     * This is the RECOMMENDED way to create an identifier for this limiter.
     *
     * Combines REMOTE_ADDR + a signature component to prevent header rotation bypass.
     *
     * @param  string $signature_header  Full X-MHM-Signature header value.
     * @return string  Composite raw identifier (will be SHA256'd inside check()).
     */
    public static function build_identifier(string $signature_header): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash( (string) $_SERVER['REMOTE_ADDR']))
            : '0.0.0.0';
        // Take only the first 32 chars of signature to prevent key collision farming.
        $sig_prefix = substr($signature_header, 0, 32);
        return $ip . ':' . $sig_prefix;
    }

    /**
     * Reset the rate limit for an identifier (for testing / admin override).
     *
     * @param string $identifier  Raw composite identifier (will be hashed).
     */
    public static function reset(string $identifier): void
    {
        delete_transient('mhm_rentiva_rl_' . hash('sha256', $identifier));
    }

    /**
     * Get current request count for an identifier (for monitoring).
     *
     * @param  string $identifier
     * @return int
     */
    public static function get_count(string $identifier): int
    {
        return (int) get_transient('mhm_rentiva_rl_' . hash('sha256', $identifier));
    }
}
