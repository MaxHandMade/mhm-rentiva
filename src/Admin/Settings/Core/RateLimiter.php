<?php

/**
 * Rate Limiter Service
 *
 * Provides IP-based rate limiting using WordPress transients.
 * Supports both sliding and fixed window strategies with high-performance standards.
 *
 * @package MHMRentiva\Admin\Settings\Core
 * @since 2.0.1
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Core;

defined('ABSPATH') || exit;

/**
 * Class RateLimiter
 * 
 * Optimized for PHP 8.2+ and WordPress Coding Standards.
 */
final class RateLimiter
{
    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Transient prefix.
     */
    private string $prefix = 'mhm_rl_';

    /**
     * Default request limit.
     */
    private int $limit = 60;

    /**
     * Default expiration time in seconds (window).
     */
    private int $expiry = 60;

    /**
     * Get singleton instance.
     * Use this for global access while allowing constructor for DI in tests.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Public constructor for DI/Tests.
     */
    public function __construct() {}

    /**
     * Check if the current request is within limits.
     *
     * @return bool True if allowed, false if limit exceeded.
     */
    public function check_limit(): bool
    {
        $ip            = $this->get_client_ip_internal();
        $transient_key = $this->prefix . md5($ip);

        // Fetch current hits.
        $current_hits = get_site_transient($transient_key);

        // 1. Initial hit: Start a new window.
        if (false === $current_hits) {
            set_site_transient($transient_key, 1, $this->expiry);
            return true;
        }

        $hits = (int) $current_hits;

        // 2. Limit Check: If exceeded, log and block.
        if ($hits >= $this->limit) {
            $this->log_violation_internal($ip);
            return false;
        }

        // 3. Increment: Calculate remaining TTL to maintain the 'Fixed Window'.
        $timeout   = (int) get_site_option("_site_transient_timeout_{$transient_key}");
        $remaining = $timeout > 0 ? $timeout - time() : $this->expiry;

        // Fallback for edge cases where timeout is lost or invalid.
        if ($remaining <= 0) {
            $remaining = $this->expiry;
        }

        set_site_transient($transient_key, $hits + 1, $remaining);

        return true;
    }

    /**
     * Get client IP address securely (Internal).
     *
     * @return string Validated IP address.
     */
    private function get_client_ip_internal(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        /**
         * Filter the client IP. 
         * Use this to handle Cloudflare (HTTP_CF_CONNECTING_IP) or other proxies.
         */
        $ip = (string) apply_filters('mhm_rate_limiter_client_ip', $ip);

        // Validate IP format.
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '127.0.0.1';
        }

        return $ip;
    }

    /**
     * Log rate limit violations for security monitoring (Internal).
     *
     * @param string $ip Client IP address.
     * @return void
     */
    private function log_violation_internal(string $ip): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : 'unknown';

        error_log(
            sprintf(
                '[MHM Rentiva] Rate limit exceeded. IP: %1$s | URI: %2$s',
                esc_html($ip),
                $uri
            )
        );
    }

    /**
     * Clean up expired rate limit data from the database.
     * Handles both Single-site and Multisite environments.
     *
     * @return void
     */
    public function cleanup_expired_limits(): void
    {
        if (wp_using_ext_object_cache()) {
            return;
        }

        global $wpdb;

        $table          = is_multisite() ? $wpdb->sitemeta : $wpdb->options;
        $key_col        = is_multisite() ? 'meta_key' : 'option_name';
        $val_col        = is_multisite() ? 'meta_value' : 'option_value';
        $prefix         = is_multisite() ? '_site_transient_' : '_transient_';
        $timeout_prefix = is_multisite() ? '_site_transient_timeout_' : '_transient_timeout_';

        // Optimized cleanup query to remove both data and timeout rows.
        $query = $wpdb->prepare(
            "DELETE a, b FROM {$table} a 
             INNER JOIN {$table} b ON b.{$key_col} = REPLACE(a.{$key_col}, %s, %s)
             WHERE a.{$key_col} LIKE %s 
             AND a.{$val_col} < %d",
            $timeout_prefix,
            $prefix,
            $wpdb->esc_like($timeout_prefix . $this->prefix) . '%',
            time()
        );

        $wpdb->query($query);
    }

    /**
     * Configure rate limiter settings.
     *
     * @param int $limit  Max requests.
     * @param int $expiry Time window in seconds.
     * @return self
     */
    public function configure(int $limit, int $expiry): self
    {
        $this->limit  = max(1, $limit);
        $this->expiry = max(1, $expiry);
        return $this;
    }

    /**
     * Get current limit setting.
     * 
     * @return int
     */
    public function get_limit(): int
    {
        return $this->limit;
    }

    /**
     * Get remaining requests for current window.
     * 
     * @param string $action
     * @param int|null $limit
     * @return int
     */
    public static function get_remaining_requests(string $action, ?int $limit = null): int
    {
        $instance = self::instance();
        if (null !== $limit) {
            $instance->configure($limit, $instance->expiry);
        }

        $ip            = $instance->get_client_ip_internal();
        $transient_key = $instance->prefix . md5($ip);
        $current_hits  = get_site_transient($transient_key);

        if (false === $current_hits) {
            return $instance->get_limit();
        }

        return max(0, $instance->get_limit() - (int) $current_hits);
    }

    // =========================================================================
    // STATIC WRAPPERS FOR BACKWARD COMPATIBILITY (SettingsCore.php etc.)
    // =========================================================================

    /**
     * Backward-compatible check if rate limiting is enabled.
     *
     * @return bool
     */
    public static function is_enabled(): bool
    {
        if (!class_exists('\MHMRentiva\Admin\REST\Settings\RESTSettings')) {
            return false;
        }
        $settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::get_rate_limit_settings();
        return !empty($settings['enabled']);
    }

    /**
     * Backward-compatible check for IP blocks.
     *
     * @return bool
     */
    public static function is_ip_blocked(): bool
    {
        $ip = self::instance()->get_client_ip_internal();
        $block_key = 'mhm_rl_block_' . md5($ip);
        return get_site_transient($block_key) !== false;
    }

    /**
     * Backward-compatible block function.
     *
     * @param int|null $duration Duration in seconds.
     * @return void
     */
    public static function block_ip(?int $duration = null): void
    {
        $ip = self::instance()->get_client_ip_internal();
        if (null === $duration) {
            $duration = self::get_block_duration() * 60;
        }

        $block_key = 'mhm_rl_block_' . md5($ip);
        set_site_transient($block_key, time(), max(1, $duration));
    }

    public static function is_allowed(string $action, ?int $limit = null, int $window = 60): bool
    {
        if (null === $limit) {
            switch ($action) {
                case 'booking':
                    $limit = self::get_booking_limit();
                    break;
                case 'payment':
                    $limit = self::get_payment_limit();
                    break;
                default:
                    $limit = self::get_general_limit();
                    break;
            }
        }

        return self::instance()
            ->configure($limit, $window)
            ->check_limit();
    }

    /**
     * Backward-compatible violation logger.
     *
     * @param string $action
     * @param string $ip
     * @param int    $limit
     */
    public static function log_violation(string $action, string $ip, int $limit): void
    {
        self::instance()->log_violation_internal($ip);
    }

    /**
     * Backward-compatible IP fetcher.
     *
     * @return string
     */
    public static function get_client_ip(): string
    {
        return self::instance()->get_client_ip_internal();
    }

    /**
     * Backward-compatible block duration.
     *
     * @return int
     */
    public static function get_block_duration(): int
    {
        return (int) get_option('mhm_rentiva_rate_limit_block_duration', 15);
    }

    /**
     * Static helper for booking limits.
     *
     * @return int
     */
    public static function get_booking_limit(): int
    {
        if (!class_exists('\MHMRentiva\Admin\REST\Settings\RESTSettings')) {
            return 5;
        }
        $settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::get_rate_limit_settings();
        return (int) ($settings['strict_limit'] ?? 5);
    }

    /**
     * Static helper for payment limits.
     *
     * @return int
     */
    public static function get_payment_limit(): int
    {
        if (!class_exists('\MHMRentiva\Admin\REST\Settings\RESTSettings')) {
            return 3;
        }
        $settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::get_rate_limit_settings();
        return (int) ($settings['strict_limit'] ?? 3);
    }

    /**
     * Static helper for general limits.
     *
     * @return int
     */
    public static function get_general_limit(): int
    {
        if (!class_exists('\MHMRentiva\Admin\REST\Settings\RESTSettings')) {
            return 60;
        }
        $settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::get_rate_limit_settings();
        return (int) ($settings['default_limit'] ?? 60);
    }
}
