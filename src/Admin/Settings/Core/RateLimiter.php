<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate Limiter Implementation
 * 
 * Implements rate limiting functionality for the plugin
 * 
 * @since 4.0.0
 */
final class RateLimiter
{
    private const CACHE_GROUP = 'mhm_rate_limit';
    private const CACHE_EXPIRATION = 3600; // 1 hour

    /**
     * Check if request is within rate limit
     * 
     * @param string $key    Rate limit key (e.g., 'booking', 'payment', 'general')
     * @param int    $limit  Rate limit (requests per window)
     * @param int    $window Time window in seconds (default: 60)
     * @return bool True if allowed, false if rate limit exceeded
     */
    public static function is_allowed(string $key, int $limit, int $window = 60): bool
    {
        if (!self::is_enabled()) {
            return true;
        }

        // Get current user role for role-based rate limiting
        $user_role = null;
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_role = $user->roles[0] ?? null;
        }

        // Allow bypass for administrators (optional, can be filtered)
        /**
         * Filter: Allow bypassing rate limits for specific roles
         * 
         * @param bool        $bypass    Whether to bypass rate limit
         * @param string      $key       Rate limit key
         * @param string|null $user_role Current user role
         * @return bool Modified bypass status
         */
        $bypass = apply_filters('mhm_rentiva_rate_limit_bypass', false, $key, $user_role);
        if ($bypass && $user_role === 'administrator') {
            return true;
        }

        $ip = self::get_client_ip();
        $cache_key = self::get_cache_key($key, $ip);
        
        $requests = get_transient($cache_key);
        
        if ($requests === false) {
            $requests = 0;
        }
        
        if ($requests >= $limit) {
            return false;
        }
        
        $requests++;
        set_transient($cache_key, $requests, $window);
        
        return true;
    }

    /**
     * Get remaining requests for a key
     */
    public static function get_remaining_requests(string $key, int $limit, int $window = 60): int
    {
        if (!self::is_enabled()) {
            return $limit;
        }

        $ip = self::get_client_ip();
        $cache_key = self::get_cache_key($key, $ip);
        
        $requests = get_transient($cache_key);
        
        if ($requests === false) {
            return $limit;
        }
        
        return max(0, $limit - $requests);
    }

    /**
     * Reset rate limit for a key
     */
    public static function reset(string $key): void
    {
        $ip = self::get_client_ip();
        $cache_key = self::get_cache_key($key, $ip);
        
        delete_transient($cache_key);
    }

    /**
     * Check if rate limiting is enabled
     */
    public static function is_enabled(): bool
    {
        return (bool) SettingsCore::get('mhm_rentiva_rate_limit_enabled', '1');
    }

    /**
     * Get general rate limit
     * 
     * @param string|null $user_role Optional user role for role-based limits
     * @return int Rate limit per minute
     */
    public static function get_general_limit(?string $user_role = null): int
    {
        $base_limit = (int) SettingsCore::get('mhm_rentiva_rate_limit_requests_per_minute', 60);
        
        /**
         * Filter: Allow customization of general rate limit based on user role or other factors
         * 
         * @param int         $base_limit Base rate limit from settings
         * @param string|null $user_role  Current user role (if provided)
         * @return int Modified rate limit
         * 
         * @example
         * add_filter('mhm_rentiva_rate_limit_general', function($limit, $role) {
         *     if ($role === 'administrator') {
         *         return $limit * 2; // Admins get double limit
         *     }
         *     return $limit;
         * }, 10, 2);
         */
        return (int) apply_filters('mhm_rentiva_rate_limit_general', $base_limit, $user_role);
    }

    /**
     * Get booking rate limit
     * 
     * @param string|null $user_role Optional user role for role-based limits
     * @return int Rate limit per minute
     */
    public static function get_booking_limit(?string $user_role = null): int
    {
        $base_limit = (int) SettingsCore::get('mhm_rentiva_rate_limit_booking_per_minute', 5);
        
        /**
         * Filter: Allow customization of booking rate limit
         * 
         * @param int         $base_limit Base rate limit from settings
         * @param string|null $user_role  Current user role (if provided)
         * @return int Modified rate limit
         */
        return (int) apply_filters('mhm_rentiva_rate_limit_booking', $base_limit, $user_role);
    }

    /**
     * Get payment rate limit
     * 
     * @param string|null $user_role Optional user role for role-based limits
     * @return int Rate limit per minute
     */
    public static function get_payment_limit(?string $user_role = null): int
    {
        $base_limit = (int) SettingsCore::get('mhm_rentiva_rate_limit_payment_per_minute', 3);
        
        /**
         * Filter: Allow customization of payment rate limit
         * 
         * @param int         $base_limit Base rate limit from settings
         * @param string|null $user_role  Current user role (if provided)
         * @return int Modified rate limit
         */
        return (int) apply_filters('mhm_rentiva_rate_limit_payment', $base_limit, $user_role);
    }

    /**
     * Get block duration
     */
    public static function get_block_duration(): int
    {
        return (int) SettingsCore::get('mhm_rentiva_rate_limit_block_duration', 15);
    }

    /**
     * Check if IP is blocked
     */
    public static function is_ip_blocked(string $ip = null): bool
    {
        if (!$ip) {
            $ip = self::get_client_ip();
        }

        $block_key = 'mhm_rate_limit_block_' . md5($ip);
        return get_transient($block_key) !== false;
    }

    /**
     * Block IP address
     */
    public static function block_ip(string $ip = null, int $duration = null): void
    {
        if (!$ip) {
            $ip = self::get_client_ip();
        }

        if (!$duration) {
            $duration = self::get_block_duration() * 60; // Convert to seconds
        }

        $block_key = 'mhm_rate_limit_block_' . md5($ip);
        set_transient($block_key, time(), $duration);
    }

    /**
     * Unblock IP address
     */
    public static function unblock_ip(string $ip = null): void
    {
        if (!$ip) {
            $ip = self::get_client_ip();
        }

        $block_key = 'mhm_rate_limit_block_' . md5($ip);
        delete_transient($block_key);
    }

    /**
     * Get client IP address
     */
    public static function get_client_ip(): string
    {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get cache key for rate limiting
     */
    private static function get_cache_key(string $key, string $ip): string
    {
        return self::CACHE_GROUP . '_' . $key . '_' . md5($ip);
    }

    /**
     * Log rate limit violation
     */
    public static function log_violation(string $key, string $ip, int $limit): void
    {
        if (!self::is_enabled()) {
            return;
        }

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'key' => $key,
            'ip' => $ip,
            'limit' => $limit,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        ];

        // Log to WordPress error log
        error_log('MHM Rate Limit Violation: ' . json_encode($log_entry));

        // Log to custom log file if available
        if (function_exists('mhm_log')) {
            mhm_log('rate_limit', $log_entry);
        }
    }

    /**
     * Get rate limit statistics
     */
    public static function get_stats(): array
    {
        global $wpdb;

        $stats = [
            'total_requests' => 0,
            'blocked_requests' => 0,
            'active_blocks' => 0,
        ];

        // Get active blocks count
        $blocks = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_mhm_rate_limit_block_%' 
             AND option_value > " . (time() - 3600)
        );
        
        $stats['active_blocks'] = count($blocks);

        return $stats;
    }

    /**
     * Clean up expired rate limit data
     */
    public static function cleanup(): void
    {
        global $wpdb;

        // Clean up expired transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_mhm_rate_limit_%' 
             AND option_value < " . time()
        );
    }
}
