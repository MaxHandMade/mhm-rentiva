<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\REST\Settings\RESTSettings;

/**
 * ✅ RATE LIMITER - API Endpoint Security
 * 
 * Protects API endpoints against brute force attacks
 */
final class RateLimiter
{
    /**
     * Rate limit configurations
     */
    private static function get_rate_limits(): array
    {
        $settings = RESTSettings::get_rate_limit_settings();
        $default_limit = (int) ($settings['default_limit'] ?? 60);
        $strict_limit = (int) ($settings['strict_limit'] ?? 10);

        return [
            'general' => [
                'max_per_minute' => $default_limit,
                'max_per_hour' => $default_limit * 60, // Estimate based on minute limit
                'max_per_day' => $default_limit * 60 * 24
            ],
            'booking_creation' => [
                'max_per_minute' => $strict_limit, // Use strict limit for critical actions
                'max_per_hour' => $strict_limit * 10,
                'max_per_day' => $strict_limit * 100
            ],
            'payment_processing' => [
                'max_per_minute' => $strict_limit, // Use strict limit for payments
                'max_per_hour' => $strict_limit * 6,
                'max_per_day' => $strict_limit * 33
            ],

            'file_upload' => [
                'max_per_minute' => 5,
                'max_per_hour' => 30,
                'max_per_day' => 100
            ],
            'webhook_processing' => [
                'max_per_minute' => 20,
                'max_per_hour' => 200,
                'max_per_day' => 1000
            ],
            'admin_actions' => [
                'max_per_minute' => 30,
                'max_per_hour' => 300,
                'max_per_day' => 2000
            ]
        ];
    }

    /**
     * Check rate limit
     * 
     * @param string $identifier Client identifier (IP, user_id, etc.)
     * @param string $action Action type
     * @return bool Is rate limit exceeded?
     */
    public static function check(string $identifier, string $action = 'general'): bool
    {
        // Return true always if rate limiter is not enabled
        $settings = RESTSettings::get_rate_limit_settings();
        if (empty($settings['enabled'])) {
            return true;
        }

        $rate_limits = self::get_rate_limits();
        $limits = $rate_limits[$action] ?? $rate_limits['general'];

        // Check for each timeframe
        $checks = [
            'minute' => self::checkTimeframe($identifier, $action, 'minute', $limits['max_per_minute'], MINUTE_IN_SECONDS),
            'hour' => self::checkTimeframe($identifier, $action, 'hour', $limits['max_per_hour'], HOUR_IN_SECONDS),
            'day' => self::checkTimeframe($identifier, $action, 'day', $limits['max_per_day'], DAY_IN_SECONDS)
        ];

        // Return false if limit exceeded in any timeframe
        return !in_array(false, $checks, true);
    }

    /**
     * Rate limit check for a specific timeframe
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @param string $timeframe Timeframe (minute, hour, day)
     * @param int $max_requests Max request count
     * @param int $duration Duration in seconds
     * @return bool Is limit exceeded?
     */
    private static function checkTimeframe(string $identifier, string $action, string $timeframe, int $max_requests, int $duration): bool
    {
        $cache_key = self::getCacheKey($identifier, $action, $timeframe);
        $current_requests = (int) get_transient($cache_key);

        if ($current_requests >= $max_requests) {
            // Rate limit exceeded - log it
            self::logRateLimitExceeded($identifier, $action, $timeframe, $current_requests, $max_requests);
            return false;
        }

        // Increment request count
        set_transient($cache_key, $current_requests + 1, $duration);

        return true;
    }

    /**
     * Create rate limit cache key 
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @param string $timeframe Timeframe
     * @return string Cache key
     */
    private static function getCacheKey(string $identifier, string $action, string $timeframe): string
    {
        $hash = hash('sha256', $identifier);
        return "rate_limit_{$action}_{$timeframe}_{$hash}";
    }

    /**
     * Log rate limit exceedance
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @param string $timeframe Timeframe
     * @param int $current_requests Current request count
     * @param int $max_requests Max request count
     */
    private static function logRateLimitExceeded(string $identifier, string $action, string $timeframe, int $current_requests, int $max_requests): void
    {
        // Log to PHP error log
        $message = sprintf(
            '[MHM Rate Limit] Exceeded: %s | Action: %s | Timeframe: %s | Count: %d/%d | IP: %s',
            $identifier,
            $action,
            $timeframe,
            $current_requests,
            $max_requests,
            self::getClientIP()
        );

        error_log($message);
    }

    /**
     * Get rate limit statistics
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @return array Rate limit statistics
     */
    public static function getStats(string $identifier, string $action = 'general'): array
    {
        $rate_limits = self::get_rate_limits();
        $limits = $rate_limits[$action] ?? $rate_limits['general'];

        return [
            'minute' => [
                'current' => (int) get_transient(self::getCacheKey($identifier, $action, 'minute')),
                'limit' => $limits['max_per_minute']
            ],
            'hour' => [
                'current' => (int) get_transient(self::getCacheKey($identifier, $action, 'hour')),
                'limit' => $limits['max_per_hour']
            ],
            'day' => [
                'current' => (int) get_transient(self::getCacheKey($identifier, $action, 'day')),
                'limit' => $limits['max_per_day']
            ]
        ];
    }

    /**
     * Clear rate limit (for admin)
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @return bool Success status
     */
    public static function clear(string $identifier, string $action = 'general'): bool
    {
        $timeframes = ['minute', 'hour', 'day'];
        $success = true;

        foreach ($timeframes as $timeframe) {
            $cache_key = self::getCacheKey($identifier, $action, $timeframe);
            if (!delete_transient($cache_key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Reset rate limit (for a specific duration)
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @param int $duration_seconds Reset duration (seconds)
     * @return bool Success status
     */
    public static function reset(string $identifier, string $action = 'general', int $duration_seconds = 3600): bool
    {
        $timeframes = ['minute', 'hour', 'day'];
        $success = true;

        foreach ($timeframes as $timeframe) {
            $cache_key = self::getCacheKey($identifier, $action, $timeframe);
            if (!set_transient($cache_key, 0, $duration_seconds)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get Client IP address
     * 
     * @return string Client IP
     */
    public static function getClientIP(): string
    {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Rate limit middleware (for REST API)
     * 
     * @param string $action Action type
     * @return bool|WP_Error Rate limit status
     */
    public static function middleware(string $action = 'general')
    {
        $identifier = self::getClientIP();

        if (!self::check($identifier, $action)) {
            $stats = self::getStats($identifier, $action);

            return new \WP_Error(
                'rate_limit_exceeded',
                __('Too many requests. Please try again later.', 'mhm-rentiva'),
                [
                    'status' => 429,
                    'retry_after' => 60, // 1 dakika
                    'rate_limit_stats' => $stats
                ]
            );
        }

        return true;
    }




    /**
     * Add rate limit info to response header
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     */
    public static function addResponseHeaders(string $identifier, string $action = 'general'): void
    {
        $stats = self::getStats($identifier, $action);

        header("X-RateLimit-Limit-Minute: {$stats['minute']['limit']}");
        header("X-RateLimit-Remaining-Minute: " . max(0, $stats['minute']['limit'] - $stats['minute']['current']));
        header("X-RateLimit-Reset-Minute: " . (time() + MINUTE_IN_SECONDS));

        header("X-RateLimit-Limit-Hour: {$stats['hour']['limit']}");
        header("X-RateLimit-Remaining-Hour: " . max(0, $stats['hour']['limit'] - $stats['hour']['current']));
        header("X-RateLimit-Reset-Hour: " . (time() + HOUR_IN_SECONDS));

        header("X-RateLimit-Limit-Day: {$stats['day']['limit']}");
        header("X-RateLimit-Remaining-Day: " . max(0, $stats['day']['limit'] - $stats['day']['current']));
        header("X-RateLimit-Reset-Day: " . (time() + DAY_IN_SECONDS));
    }

    /**
     * Get rate limit configuration
     * 
     * @param string $action Action type
     * @return array Rate limit configuration
     */
    public static function getConfig(string $action = 'general'): array
    {
        $rate_limits = self::get_rate_limits();
        return $rate_limits[$action] ?? $rate_limits['general'];
    }

    /**
     * Update rate limit configuration
     * 
     * @param string $action Action type
     * @param array $config New configuration
     * @return bool Success status
     */
    public static function updateConfig(string $action, array $config): bool
    {
        $rate_limits = self::get_rate_limits();
        if (!isset($rate_limits[$action])) {
            return false;
        }

        // This method is for changing config at runtime
        // In real implementation should be read from config file
        return true;
    }

    /**
     * Check rate limit status (for admin dashboard)
     * 
     * @return array Global rate limit status
     */
    public static function getGlobalStats(): array
    {
        global $wpdb;

        $transients = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_rate_limit_%' 
             AND option_value > 0",
            ARRAY_A
        );

        $stats = [
            'total_active_limits' => count($transients),
            'actions' => [],
            'top_offenders' => []
        ];

        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient['option_name']);
            $parts = explode('_', $key);

            if (count($parts) >= 4) {
                $action = $parts[2];
                $timeframe = $parts[3];
                $identifier_hash = $parts[4];

                if (!isset($stats['actions'][$action])) {
                    $stats['actions'][$action] = [];
                }

                if (!isset($stats['actions'][$action][$timeframe])) {
                    $stats['actions'][$action][$timeframe] = 0;
                }

                $stats['actions'][$action][$timeframe]++;
            }
        }

        return $stats;
    }
}
