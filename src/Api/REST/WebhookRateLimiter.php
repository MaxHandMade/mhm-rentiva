<?php

declare(strict_types=1);

namespace MHMRentiva\Api\REST;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Transient-based sliding window rate limiter for webhook endpoints.
 *
 * Uses a WP transient to count requests per identifier within a rolling time window.
 * Identifiers are hashed (SHA256) before storage — no raw IP or PII persisted.
 *
 * Usage:
 *   if (! WebhookRateLimiter::check('some-identifier', 10, 60)) {
 *       return new WP_Error('rate_limited', 'Too many requests.', ['status' => 429]);
 *   }
 *
 * Transient key pattern: mhm_rentiva_rl_{sha256(identifier)}
 * TTL = $window_seconds (resets the window on first request in each window)
 *
 * @since 4.21.0
 */
final class WebhookRateLimiter
{
    /**
     * Check if identifier is within rate limit. Increments counter on each call.
     *
     * @param  string $identifier      Unique identifier (e.g. signature prefix, vendor ID).
     *                                 Hashed before storage — never stored raw.
     * @param  int    $max_requests    Maximum allowed requests per window.
     * @param  int    $window_seconds  Rolling window length in seconds.
     * @return bool   True = within limit. False = rate exceeded → caller should return 429.
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
            // Limit exceeded — do NOT increment (avoids integer overflow on flood).
            return false;
        }

        // Within limit — increment counter. Re-set with same TTL is intentional:
        // WP transients don't support atomic increment, so we accept the
        // minor race window (two near-simultaneous requests might both read 0).
        // This is acceptable for webhook throttling (not a security-critical counter).
        set_transient($key, $count + 1, $window_seconds);
        return true;
    }

    /**
     * Manually reset the rate limit for an identifier (e.g. for testing or admin override).
     *
     * @param string $identifier  Raw identifier (will be hashed).
     */
    public static function reset(string $identifier): void
    {
        delete_transient('mhm_rentiva_rl_' . hash('sha256', $identifier));
    }

    /**
     * Returns the current request count for an identifier (for monitoring).
     *
     * @param  string $identifier  Raw identifier.
     * @return int
     */
    public static function get_count(string $identifier): int
    {
        return (int) get_transient('mhm_rentiva_rl_' . hash('sha256', $identifier));
    }
}
