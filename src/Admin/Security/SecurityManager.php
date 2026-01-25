<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Security;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Global Security Manager
 * 
 * Handles IP whitelist, blacklist, and country restrictions across the entire site
 * 
 * @since 4.0.0
 */
final class SecurityManager
{
    /**
     * Initialize security management
     */
    public static function init(): void
    {
        // Hook into early request processing
        add_action('template_redirect', [self::class, 'check_ip_access'], 1);
        add_action('wp_loaded', [self::class, 'check_ip_access'], 1);
    }

    /**
     * Check if current IP should be allowed access
     */
    public static function check_ip_access(): void
    {
        // Skip for admin pages to prevent lockout
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        $client_ip = self::get_client_ip();

        // Check blacklist first
        if (self::is_ip_blacklisted($client_ip)) {
            self::deny_access(__('Access denied: Your IP address is blocked.', 'mhm-rentiva'));
        }

        // Check whitelist if enabled
        if (self::is_whitelist_enabled()) {
            if (!self::is_ip_whitelisted($client_ip)) {
                self::deny_access(__('Access denied: Your IP address is not authorized.', 'mhm-rentiva'));
            }
        }

        // Check country restriction
        if (self::is_country_restriction_enabled()) {
            if (!self::is_country_allowed($client_ip)) {
                self::deny_access(__('Access denied: Your country is not authorized.', 'mhm-rentiva'));
            }
        }
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip(): string
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
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if IP blacklist is enabled
     */
    private static function is_blacklist_enabled(): bool
    {
        return SettingsCore::get('mhm_rentiva_ip_blacklist_enabled', '0') === '1';
    }

    /**
     * Check if IP is blacklisted
     */
    private static function is_ip_blacklisted(string $ip): bool
    {
        if (!self::is_blacklist_enabled()) {
            return false;
        }

        $blacklist = SettingsCore::get('mhm_rentiva_ip_blacklist', '');
        if (empty($blacklist)) {
            return false;
        }

        $ips = self::parse_ip_list($blacklist);

        foreach ($ips as $blocked_ip) {
            if (self::match_ip($ip, $blocked_ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if whitelist is enabled
     */
    private static function is_whitelist_enabled(): bool
    {
        return SettingsCore::get('mhm_rentiva_ip_whitelist_enabled', '0') === '1';
    }

    /**
     * Check if IP is whitelisted
     */
    private static function is_ip_whitelisted(string $ip): bool
    {
        $whitelist = SettingsCore::get('mhm_rentiva_ip_whitelist', '');
        if (empty($whitelist)) {
            return true; // Empty whitelist = allow all
        }

        $ips = self::parse_ip_list($whitelist);

        foreach ($ips as $allowed_ip) {
            if (self::match_ip($ip, $allowed_ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if country restriction is enabled
     */
    private static function is_country_restriction_enabled(): bool
    {
        return SettingsCore::get('mhm_rentiva_country_restriction_enabled', '0') === '1';
    }

    /**
     * Check if country is allowed
     */
    private static function is_country_allowed(string $ip): bool
    {
        $allowed_countries = SettingsCore::get('mhm_rentiva_allowed_countries', '');
        if (empty($allowed_countries)) {
            return true;
        }

        // Get country from IP (simplified - in production, use a proper GeoIP service)
        $country_code = self::get_country_from_ip($ip);
        if (empty($country_code)) {
            return true; // If we can't determine country, allow access
        }

        $allowed = array_map('trim', explode(',', strtoupper($allowed_countries)));
        return in_array(strtoupper($country_code), $allowed, true);
    }

    /**
     * Get country code from IP (placeholder - implement with GeoIP service)
     */
    /**
     * Get country code from IP (Cloudflare + IP-API fallback with caching)
     */
    private static function get_country_from_ip(string $ip): string
    {
        // 1. Cloudflare Check (Fastest & Most Reliable)
        if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return strtoupper(sanitize_text_field($_SERVER['HTTP_CF_IPCOUNTRY']));
        }

        // Skip private/local IPs to avoid unnecessary API calls
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '';
        }

        // 2. Cache Check
        $cache_key = 'mhm_geoip_' . md5($ip);

        // Try ObjectCache first if available
        if (class_exists(\MHMRentiva\Admin\Core\Utilities\ObjectCache::class)) {
            $country = \MHMRentiva\Admin\Core\Utilities\ObjectCache::get($cache_key, 'mhm_security');
            if ($country) return $country;
        } else {
            // Fallback to transient
            $country = get_transient($cache_key);
            if ($country) return $country;
        }

        // 3. IP-API.com Fallback (Free, Rate Limited 45/min)
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=countryCode", [
            'timeout' => 3 // Fail fast
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['countryCode'])) {
            $country = strtoupper(sanitize_text_field($data['countryCode']));

            // Cache result for 24 hours (long TTL is crucial for rate limiting)
            if (class_exists(\MHMRentiva\Admin\Core\Utilities\ObjectCache::class)) {
                \MHMRentiva\Admin\Core\Utilities\ObjectCache::set($cache_key, $country, 'mhm_security', 24 * HOUR_IN_SECONDS);
            } else {
                set_transient($cache_key, $country, 24 * HOUR_IN_SECONDS);
            }

            return $country;
        }

        return '';
    }

    /**
     * Parse IP list from textarea input
     */
    private static function parse_ip_list(string $list): array
    {
        $lines = explode("\n", $list);
        $ips = [];

        foreach ($lines as $line) {
            $ip = trim($line);
            if (!empty($ip)) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }

    /**
     * Match IP against pattern (supports CIDR notation)
     */
    private static function match_ip(string $ip, string $pattern): bool
    {
        // Direct match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation match
        if (strpos($pattern, '/') !== false) {
            return self::match_cidr($ip, $pattern);
        }

        return false;
    }

    /**
     * Match IP against CIDR notation
     */
    private static function match_cidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);

        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);

        if ($ip_long === false || $subnet_long === false) {
            return false;
        }

        $mask_long = -1 << (32 - (int)$mask);
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }

    /**
     * Deny access and send appropriate response
     */
    private static function deny_access(string $message): void
    {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => $message
            ]);
        } else {
            wp_die(
                esc_html($message),
                esc_html__('Access Denied', 'mhm-rentiva'),
                ['response' => 403]
            );
        }
    }
}
