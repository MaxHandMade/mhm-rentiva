<?php declare(strict_types=1);

namespace MHMRentiva\Admin\REST\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ REST SETTINGS - Dynamic REST API Settings
 * 
 * Moves hardcoded values to central settings
 */
final class RESTSettings
{
    const OPTION_NAME = 'mhm_rentiva_rest_settings';
    
    /**
     * Default settings
     */
    public static function get_default_settings(): array
    {
        return [
            // Rate limiting settings
            'rate_limiting' => [
                'enabled' => true,
                'default_limit' => 60,        // Maximum requests per minute
                'default_window' => 60,       // Time window (seconds)
                'strict_limit' => 10,         // Strict limit (non-admin)
                'strict_window' => 60,        // Strict time window
                'burst_limit' => 100,         // Burst limit (short-term)
                'burst_window' => 300         // Burst time window (5 minutes)
            ],
            
            // Token settings
            'tokens' => [
                'default_expiry_hours' => 24,     // Default token duration
                'max_expiry_hours' => 168,        // Maximum token duration (7 days)
                'refresh_enabled' => true,        // Token refresh
                'auto_refresh_threshold' => 2     // Refresh when X hours remaining
            ],
            
            // Security settings
            'security' => [
                'require_https' => true,          // HTTPS requirement
                'ip_whitelist_enabled' => false,  // IP whitelist
                'ip_whitelist' => [],             // IP list
                'ip_blacklist_enabled' => true,   // IP blacklist
                'ip_blacklist' => [],             // Blocked IP list
                'user_agent_validation' => true,  // User agent validation
                'blocked_user_agents' => [        // Blocked user agents
                    'curl', 'wget', 'python', 'bot', 'spider', 'crawler'
                ]
            ],
            
            // API settings
            'api' => [
                'version' => 'v1',
                'base_namespace' => 'mhm-rentiva/v1',
                'cors_enabled' => true,
                'cors_origins' => [],             // CORS origins
                'request_logging' => true,        // Request logging
                'response_compression' => true    // Response compression
            ],
            
            // Cache settings
            'cache' => [
                'enabled' => true,
                'duration_seconds' => 300,        // 5 minutes
                'long_duration_seconds' => 1800,  // 30 minutes
                'cache_headers' => true,          // Cache headers
                'etag_enabled' => true            // ETag support
            ],
            
            // Development mode settings
            'development' => [
                'debug_mode' => false,            // Debug mode
                'cors_all_origins' => false,      // CORS permission for all origins
                'rate_limit_bypass' => false,     // Rate limit bypass
                'security_bypass' => false,       // Bypass security checks
                'verbose_logging' => false,       // Detailed logging
                'auto_enable_on_debug' => true    // Auto-enable when WP_DEBUG is active
            ]
        ];
    }
    
    /**
     * Get setting
     */
    public static function get_setting(string $key, $default = null)
    {
        $settings = get_option(self::OPTION_NAME, self::get_default_settings());
        
        $keys = explode('.', $key);
        $value = $settings;
        
        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Get rate limiting settings
     */
    public static function get_rate_limit_settings(): array
    {
        return self::get_setting('rate_limiting', [
            'enabled' => true,
            'default_limit' => 60,
            'default_window' => 60,
            'strict_limit' => 10,
            'strict_window' => 60,
            'burst_limit' => 100,
            'burst_window' => 300
        ]);
    }
    
    /**
     * Get token settings
     */
    public static function get_token_settings(): array
    {
        return self::get_setting('tokens', [
            'default_expiry_hours' => 24,
            'max_expiry_hours' => 168,
            'refresh_enabled' => true,
            'auto_refresh_threshold' => 2
        ]);
    }
    
    /**
     * Get security settings
     */
    public static function get_security_settings(): array
    {
        return self::get_setting('security', [
            'require_https' => true,
            'ip_whitelist_enabled' => false,
            'ip_whitelist' => [],
            'ip_blacklist_enabled' => true,
            'ip_blacklist' => [],
            'user_agent_validation' => true,
            'blocked_user_agents' => ['curl', 'wget', 'python', 'bot', 'spider', 'crawler']
        ]);
    }
    
    /**
     * Get API settings
     */
    public static function get_api_settings(): array
    {
        return self::get_setting('api', [
            'version' => 'v1',
            'base_namespace' => 'mhm-rentiva/v1',
            'cors_enabled' => true,
            'cors_origins' => [],
            'request_logging' => true,
            'response_compression' => true
        ]);
    }
    
    /**
     * Get cache settings
     */
    public static function get_cache_settings(): array
    {
        return self::get_setting('cache', [
            'enabled' => true,
            'duration_seconds' => 300,
            'long_duration_seconds' => 1800,
            'cache_headers' => true,
            'etag_enabled' => true
        ]);
    }
    
    /**
     * Get development mode settings
     */
    public static function get_development_settings(): array
    {
        $settings = self::get_setting('development', [
            'debug_mode' => false,
            'cors_all_origins' => false,
            'rate_limit_bypass' => false,
            'security_bypass' => false,
            'verbose_logging' => false,
            'auto_enable_on_debug' => true
        ]);
        
        // ✅ Auto-enable development mode if WP_DEBUG is active
        if ($settings['auto_enable_on_debug'] && self::is_wp_debug_enabled()) {
            $settings['debug_mode'] = true;
            $settings['verbose_logging'] = true;
        }
        
        return $settings;
    }
    
    /**
     * Is WordPress debug mode active?
     */
    public static function is_wp_debug_enabled(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }
    
    /**
     * Is development mode active?
     */
    public static function is_development_mode(): bool
    {
        $dev_settings = self::get_development_settings();
        return $dev_settings['debug_mode'] || self::is_wp_debug_enabled();
    }
    
    /**
     * Perform rate limit check
     */
    public static function check_rate_limit(string $identifier, string $type = 'default'): bool
    {
        $rate_settings = self::get_rate_limit_settings();
        $dev_settings = self::get_development_settings();
        
        // ✅ Rate limit bypass in development mode
        if ($dev_settings['rate_limit_bypass'] && self::is_development_mode()) {
            return true; // Rate limiting bypassed
        }
        
        if (!$rate_settings['enabled']) {
            return true; // Rate limiting disabled
        }
        
        switch ($type) {
            case 'strict':
                $limit = $rate_settings['strict_limit'];
                $window = $rate_settings['strict_window'];
                break;
            case 'burst':
                $limit = $rate_settings['burst_limit'];
                $window = $rate_settings['burst_window'];
                break;
            default:
                $limit = $rate_settings['default_limit'];
                $window = $rate_settings['default_window'];
        }
        
        $cache_key = 'mhm_rate_limit_' . $type . '_' . md5($identifier);
        $requests = get_transient($cache_key) ?: [];
        
        $now = time();
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        // Check limit
        if (count($requests) >= $limit) {
            return false; // Rate limit exceeded
        }
        
        // Save new request
        $requests[] = $now;
        set_transient($cache_key, $requests, $window);
        
        return true; // Rate limit not exceeded
    }
    
    /**
     * Perform security check
     */
    public static function check_security(\WP_REST_Request $request): bool
    {
        $security_settings = self::get_security_settings();
        $dev_settings = self::get_development_settings();
        
        // ✅ Security bypass in development mode
        if ($dev_settings['security_bypass'] && self::is_development_mode()) {
            return true; // Security checks bypassed
        }
        
        // HTTPS check
        if ($security_settings['require_https'] && !is_ssl()) {
            return false;
        }
        
        $client_ip = self::get_client_ip();
        
        // IP blacklist check (check before whitelist)
        if ($security_settings['ip_blacklist_enabled'] && !empty($security_settings['ip_blacklist'])) {
            if (in_array($client_ip, $security_settings['ip_blacklist'])) {
                return false; // IP in blacklist, access denied
            }
        }
        
        // IP whitelist check
        if ($security_settings['ip_whitelist_enabled']) {
            if (!in_array($client_ip, $security_settings['ip_whitelist'])) {
                return false; // IP not in whitelist, access denied
            }
        }
        
        // User agent check
        if ($security_settings['user_agent_validation']) {
            $user_agent = $request->get_header('User-Agent') ?: '';
            foreach ($security_settings['blocked_user_agents'] as $blocked) {
                if (stripos($user_agent, $blocked) !== false) {
                    return false; // User agent blocked
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip(): string
    {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
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
                $ip = $_SERVER[$header];
                
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Initialize settings
     */
    public static function init(): void
    {
        register_setting(
            'mhm_rentiva_rest_settings',
            self::OPTION_NAME,
            [
                'sanitize_callback' => [self::class, 'sanitize_settings'],
                'default' => self::get_default_settings()
            ]
        );
    }
    
    /**
     * Reset settings to defaults
     * 
     * @return bool Success status
     */
    public static function reset_to_defaults(): bool
    {
        $defaults = self::get_default_settings();
        return update_option(self::OPTION_NAME, $defaults) !== false;
    }
    
    /**
     * Sanitize settings
     */
    public static function sanitize_settings(array $input): array
    {
        $sanitized = [];
        
        // Rate limiting
        if (isset($input['rate_limiting'])) {
            $rl = $input['rate_limiting'];
            $sanitized['rate_limiting'] = [
                'enabled' => (bool) ($rl['enabled'] ?? true),
                'default_limit' => max(1, (int) ($rl['default_limit'] ?? 60)),
                'default_window' => max(1, (int) ($rl['default_window'] ?? 60)),
                'strict_limit' => max(1, (int) ($rl['strict_limit'] ?? 10)),
                'strict_window' => max(1, (int) ($rl['strict_window'] ?? 60)),
                'burst_limit' => max(1, (int) ($rl['burst_limit'] ?? 100)),
                'burst_window' => max(1, (int) ($rl['burst_window'] ?? 300))
            ];
        }
        
        // Tokens
        if (isset($input['tokens'])) {
            $tokens = $input['tokens'];
            $sanitized['tokens'] = [
                'default_expiry_hours' => max(1, (int) ($tokens['default_expiry_hours'] ?? 24)),
                'max_expiry_hours' => max(1, (int) ($tokens['max_expiry_hours'] ?? 168)),
                'refresh_enabled' => (bool) ($tokens['refresh_enabled'] ?? true),
                'auto_refresh_threshold' => max(1, (int) ($tokens['auto_refresh_threshold'] ?? 2))
            ];
        }
        
        // Security
        if (isset($input['security'])) {
            $security = $input['security'];
            
            // IP whitelist handling
            $ip_whitelist = [];
            if (isset($security['ip_whitelist'])) {
                if (is_string($security['ip_whitelist'])) {
                    // String'den array'e çevir
                    $ips = array_map('trim', explode(',', $security['ip_whitelist']));
                    $ip_whitelist = array_filter($ips);
                } elseif (is_array($security['ip_whitelist'])) {
                    $ip_whitelist = $security['ip_whitelist'];
                }
            }
            
            // IP blacklist handling
            $ip_blacklist = [];
            if (isset($security['ip_blacklist'])) {
                if (is_string($security['ip_blacklist'])) {
                    // String'den array'e çevir
                    $ips = array_map('trim', explode(',', $security['ip_blacklist']));
                    $ip_blacklist = array_filter($ips);
                } elseif (is_array($security['ip_blacklist'])) {
                    $ip_blacklist = $security['ip_blacklist'];
                }
            }
            
            $sanitized['security'] = [
                'require_https' => (bool) ($security['require_https'] ?? true),
                'ip_whitelist_enabled' => (bool) ($security['ip_whitelist_enabled'] ?? false),
                'ip_whitelist' => array_map('sanitize_text_field', $ip_whitelist),
                'ip_blacklist_enabled' => (bool) ($security['ip_blacklist_enabled'] ?? true),
                'ip_blacklist' => array_map('sanitize_text_field', $ip_blacklist),
                'user_agent_validation' => (bool) ($security['user_agent_validation'] ?? true),
                'blocked_user_agents' => array_map('sanitize_text_field', $security['blocked_user_agents'] ?? [])
            ];
        }
        
        // Development
        if (isset($input['development'])) {
            $development = $input['development'];
            $sanitized['development'] = [
                'debug_mode' => (bool) ($development['debug_mode'] ?? false),
                'cors_all_origins' => (bool) ($development['cors_all_origins'] ?? false),
                'rate_limit_bypass' => (bool) ($development['rate_limit_bypass'] ?? false),
                'security_bypass' => (bool) ($development['security_bypass'] ?? false),
                'verbose_logging' => (bool) ($development['verbose_logging'] ?? false),
                'auto_enable_on_debug' => (bool) ($development['auto_enable_on_debug'] ?? true)
            ];
        }
        
        return $sanitized;
    }

    /**
     * Render settings section
     */
    public static function render_settings_section(): void
    {
        echo '<div class="mhm-rest-settings-header">';
        echo '<div>';
        echo '<h2>' . esc_html__('REST API Settings', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('Configure REST API security, performance and behavior settings.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        echo '<button type="button" id="mhm-reset-rest-settings-btn" class="button button-secondary">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Reset to Defaults', 'mhm-rentiva');
        echo '</button>';
        echo '</div>';
        
        // Rate Limiting
        echo '<table class="form-table">';
        echo '<tr><th scope="row">' . esc_html__('Rate Limiting', 'mhm-rentiva') . '</th><td>';
        
        $rate_settings = self::get_rate_limit_settings();
        
        echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[rate_limiting][enabled]" value="1"' . checked($rate_settings['enabled'], true, false) . '> ' . esc_html__('Rate limiting enabled', 'mhm-rentiva') . '</label><br><br>';
        
        echo '<label for="rest_default_limit">' . esc_html__('Default Limit (Requests/Minute)', 'mhm-rentiva') . '</label><br>';
        echo '<input type="number" id="rest_default_limit" name="mhm_rentiva_rest_settings[rate_limiting][default_limit]" value="' . esc_attr($rate_settings['default_limit']) . '" min="1" max="1000" style="width: 150px;">';
        echo '<p class="description">' . esc_html__('Maximum number of requests per minute for normal users.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label for="rest_strict_limit">' . esc_html__('Strict Limit (Requests/Minute)', 'mhm-rentiva') . '</label><br>';
        echo '<input type="number" id="rest_strict_limit" name="mhm_rentiva_rest_settings[rate_limiting][strict_limit]" value="' . esc_attr($rate_settings['strict_limit']) . '" min="1" max="100" style="width: 150px;">';
        echo '<p class="description">' . esc_html__('Strict limit for non-admin users.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label for="rest_burst_limit">' . esc_html__('Burst Limit (Requests/5 Minutes)', 'mhm-rentiva') . '</label><br>';
        echo '<input type="number" id="rest_burst_limit" name="mhm_rentiva_rest_settings[rate_limiting][burst_limit]" value="' . esc_attr($rate_settings['burst_limit']) . '" min="1" max="1000" style="width: 150px;">';
        echo '<p class="description">' . esc_html__('Burst limit for short-term intensive usage.', 'mhm-rentiva') . '</p>';
        
        echo '</td></tr>';
        
        echo '<tr><th scope="row">' . esc_html__('Token Settings', 'mhm-rentiva') . '</th><td>';
        
        $token_settings = self::get_token_settings();
        
        echo '<label for="rest_token_expiry">' . esc_html__('Default Token Duration (Hours)', 'mhm-rentiva') . '</label><br>';
        echo '<input type="number" id="rest_token_expiry" name="mhm_rentiva_rest_settings[tokens][default_expiry_hours]" value="' . esc_attr($token_settings['default_expiry_hours']) . '" min="1" max="168" style="width: 150px;">';
        echo '<p class="description">' . esc_html__('Default token validity period.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[tokens][refresh_enabled]" value="1"' . checked($token_settings['refresh_enabled'], true, false) . '> ' . esc_html__('Token refresh enabled', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Allow users to refresh their tokens.', 'mhm-rentiva') . '</p>';
        
        echo '</td></tr>';
        
        echo '<tr><th scope="row">' . esc_html__('Security Settings', 'mhm-rentiva') . '</th><td>';
        
        $security_settings = self::get_security_settings();
        
        echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[security][require_https]" value="1"' . checked($security_settings['require_https'], true, false) . '> ' . esc_html__('HTTPS required', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Require HTTPS for all REST API requests.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[security][user_agent_validation]" value="1"' . checked($security_settings['user_agent_validation'], true, false) . '> ' . esc_html__('User Agent Validation', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Block suspicious user agents (bots, curl, etc.).', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label for="rest_ip_whitelist">' . esc_html__('IP Whitelist (Comma separated)', 'mhm-rentiva') . '</label><br>';
        echo '<textarea id="rest_ip_whitelist" name="mhm_rentiva_rest_settings[security][ip_whitelist]" rows="3" cols="50" placeholder="192.168.1.1, 10.0.0.1">' . esc_textarea(implode(', ', $security_settings['ip_whitelist'])) . '</textarea>';
        echo '<p class="description">' . esc_html__('Only allow requests from these IPs. If left blank, all IPs are allowed.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[security][ip_blacklist_enabled]" value="1"' . checked($security_settings['ip_blacklist_enabled'], true, false) . '> ' . esc_html__('IP Blacklist enabled', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Block requests from specified IPs.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label for="rest_ip_blacklist">' . esc_html__('IP Blacklist (Comma separated)', 'mhm-rentiva') . '</label><br>';
        echo '<textarea id="rest_ip_blacklist" name="mhm_rentiva_rest_settings[security][ip_blacklist]" rows="3" cols="50" placeholder="192.168.1.100, 10.0.0.50, 172.16.1.200">' . esc_textarea(implode(', ', $security_settings['ip_blacklist'])) . '</textarea>';
        echo '<p class="description">' . esc_html__('Block all requests from these IPs. Example: 192.168.1.100, 10.0.0.50', 'mhm-rentiva') . '</p>';
        
        echo '</td></tr>';
        
        echo '<tr><th scope="row">' . esc_html__('Cache Settings', 'mhm-rentiva') . '</th><td>';
        
        $cache_settings = self::get_cache_settings();
        
        echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[cache][enabled]" value="1"' . checked($cache_settings['enabled'], true, false) . '> ' . esc_html__('API Cache enabled', 'mhm-rentiva') . '</label><br><br>';
        
        echo '<label for="rest_cache_duration">' . esc_html__('Cache Duration (Seconds)', 'mhm-rentiva') . '</label><br>';
        echo '<input type="number" id="rest_cache_duration" name="mhm_rentiva_rest_settings[cache][duration_seconds]" value="' . esc_attr($cache_settings['duration_seconds']) . '" min="60" max="3600" style="width: 150px;">';
        echo '<p class="description">' . esc_html__('Duration for which API responses are cached.', 'mhm-rentiva') . '</p>';
        
        echo '</td></tr>';
        
        echo '<tr><th scope="row">' . esc_html__('Developer Mode', 'mhm-rentiva') . '</th><td>';
        
        $dev_settings = self::get_development_settings();
        $wp_debug_status = self::is_wp_debug_enabled() ? 
            '<span style="color: #46b450;">✓ Active</span>' : 
            '<span style="color: #dc3232;">✗ Inactive</span>';
        
        echo '<p><strong>WordPress Debug Status:</strong> ' . $wp_debug_status . '</p>';
        
        echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[development][debug_mode]" value="1"' . checked($dev_settings['debug_mode'], true, false) . '> ' . esc_html__('Debug mode enabled', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Enable REST API debug information.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][auto_enable_on_debug]" value="1"' . checked($dev_settings['auto_enable_on_debug'], true, false) . '> ' . esc_html__('Auto enable if WP_DEBUG is active', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Automatically enable developer features if WordPress debug mode is active.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][rate_limit_bypass]" value="1"' . checked($dev_settings['rate_limit_bypass'], true, false) . '> ' . esc_html__('Rate limit bypass', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Bypass rate limiting in developer mode.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][security_bypass]" value="1"' . checked($dev_settings['security_bypass'], true, false) . '> ' . esc_html__('Security bypass', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Bypass security checks in developer mode (WARNING: Use only in development environment!).', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][cors_all_origins]" value="1"' . checked($dev_settings['cors_all_origins'], true, false) . '> ' . esc_html__('Allow CORS from all origins', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Allow CORS requests from all origins in developer mode.', 'mhm-rentiva') . '</p>';
        
        echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][verbose_logging]" value="1"' . checked($dev_settings['verbose_logging'], true, false) . '> ' . esc_html__('Verbose logging', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Detailed logging for all REST API requests and responses.', 'mhm-rentiva') . '</p>';
        
        echo '</td></tr>';
        
        echo '</table>';
        
        // SettingsView::render_api_keys_section() and SettingsView::render_endpoints_section()
        // should be called from SettingsView or moved here too. 
        // For now, I will keep them effectively available via class checks or just return null if not needed here.
        // But since I'm moving the main tab logic, I should probably also consider if these need to enter here.
        // SettingsView calls them sequentially.
    }
}
