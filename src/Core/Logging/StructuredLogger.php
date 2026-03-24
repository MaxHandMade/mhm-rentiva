<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Logging;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * PSR-3 inspired structured logger for the MHM Rentiva financial engine.
 *
 * Levels: debug < info < warning < error
 *
 * Behaviour:
 *   - WP_DEBUG = true  → all levels written
 *   - WP_DEBUG = false → only 'warning' and 'error' written
 *   - Output target: PHP error_log() (formatted as JSON) + optional WP transient accumulator
 *
 * Log entry format (single line JSON):
 *   {"level":"error","channel":"payout","message":"Ledger write failed",
 *    "context":{"payout_id":123},"ts":"2026-02-25T18:00:00Z"}
 *
 * Transient accumulator key: mhm_rentiva_log_{channel} (last 50 entries, 1h TTL)
 * This enables lightweight in-dashboard log viewing without a custom table.
 *
 * @since 4.21.0
 */
final class StructuredLogger
{
    public const LEVEL_DEBUG   = 'debug';
    public const LEVEL_INFO    = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR   = 'error';

    private const LEVEL_WEIGHTS = array(
        self::LEVEL_DEBUG   => 0,
        self::LEVEL_INFO    => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR   => 3,
    );

    private const TRANSIENT_MAX_ENTRIES = 50;
    private const TRANSIENT_TTL         = HOUR_IN_SECONDS;

    /**
     * Log at DEBUG level (dev/test only — filtered in production when WP_DEBUG=false).
     *
     * @param string               $message
     * @param array<string, mixed> $context
     * @param string               $channel
     */
    public static function debug(string $message, array $context = array(), string $channel = 'rentiva'): void
    {
        self::write(self::LEVEL_DEBUG, $message, $context, $channel);
    }

    /**
     * Log at INFO level.
     *
     * @param string               $message
     * @param array<string, mixed> $context
     * @param string               $channel
     */
    public static function info(string $message, array $context = array(), string $channel = 'rentiva'): void
    {
        self::write(self::LEVEL_INFO, $message, $context, $channel);
    }

    /**
     * Log at WARNING level.
     *
     * @param string               $message
     * @param array<string, mixed> $context
     * @param string               $channel
     */
    public static function warning(string $message, array $context = array(), string $channel = 'rentiva'): void
    {
        self::write(self::LEVEL_WARNING, $message, $context, $channel);
    }

    /**
     * Log at ERROR level. Always written regardless of WP_DEBUG.
     *
     * @param string               $message
     * @param array<string, mixed> $context
     * @param string               $channel
     */
    public static function error(string $message, array $context = array(), string $channel = 'rentiva'): void
    {
        self::write(self::LEVEL_ERROR, $message, $context, $channel);
    }

    /**
     * Core write dispatcher.
     *
     * @param string               $level
     * @param string               $message
     * @param array<string, mixed> $context
     * @param string               $channel
     */
    private static function write(string $level, string $message, array $context, string $channel): void
    {
        // Filter by WP_DEBUG: only warning+ in production.
        if (! self::should_write($level)) {
            return;
        }

        $entry = array(
            'level'   => $level,
            'channel' => sanitize_key($channel),
            'message' => $message,
            'context' => $context,
            'ts'      => gmdate('Y-m-d\TH:i:s\Z'),
        );

        $line = wp_json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = '{"level":"error","message":"StructuredLogger: json_encode failed"}';
        }

        // Primary: PHP error log (always available).
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[MHM-Rentiva] ' . $line);

        // Secondary: transient accumulator for in-dashboard viewing.
        self::accumulate($channel, $entry);
    }

    /**
     * Determine if this level should be written given current WP_DEBUG state.
     */
    private static function should_write(string $level): bool
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true; // All levels in debug mode.
        }

        // Production: only warning and above.
        return (self::LEVEL_WEIGHTS[$level] ?? 0) >= self::LEVEL_WEIGHTS[self::LEVEL_WARNING];
    }

    /**
     * Accumulate last N entries in a transient for lightweight retrieval.
     *
     * @param string               $channel
     * @param array<string, mixed> $entry
     */
    private static function accumulate(string $channel, array $entry): void
    {
        $key      = 'mhm_rentiva_log_' . sanitize_key($channel);
        $existing = get_transient($key);
        $log      = is_array($existing) ? $existing : array();

        // Prepend newest entry and cap at max.
        array_unshift($log, $entry);
        $log = array_slice($log, 0, self::TRANSIENT_MAX_ENTRIES);

        set_transient($key, $log, self::TRANSIENT_TTL);
    }

    /**
     * Retrieve recent log entries for a channel (for admin log viewers).
     *
     * @param  string $channel
     * @param  int    $limit   Max entries to return.
     * @return array<int, array<string, mixed>>
     */
    public static function get_recent(string $channel = 'rentiva', int $limit = 20): array
    {
        $key = 'mhm_rentiva_log_' . sanitize_key($channel);
        $log = get_transient($key);

        if (! is_array($log)) {
            return array();
        }

        return array_slice($log, 0, $limit);
    }
}
