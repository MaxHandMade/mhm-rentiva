<?php declare(strict_types=1);

namespace MHMRentiva\Admin\REST\Helpers;

use WP_Error;
use WP_REST_Request;
use MHMRentiva\Admin\REST\Helpers\SecureToken;
use MHMRentiva\Admin\REST\Settings\RESTSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central authorization helper class for REST API
 * 
 * This class meets the common authorization needs of all REST endpoints
 * and prevents code duplication.
 */
final class AuthHelper
{
    /**
     * Admin permission check
     * 
     * @param WP_REST_Request $request REST request object
     * @return bool Does admin permission exist?
     */
    public static function adminPermissionsCheck(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }
    
    /**
     * REST API request validation
     * 
     * This method supports two different authorization methods:
     * 1. WordPress REST nonce (X-WP-Nonce header)
     * 2. MHM custom nonce (mhm_nonce in request body)
     * 
     * @param WP_REST_Request $request REST request object
     * @param int $booking_id Booking ID (for nonce validation)
     * @param string $gateway_prefix Gateway prefix (e.g. 'offline')
     * @return bool|WP_Error True if successful, WP_Error if error
     */
    public static function verifyAuth(WP_REST_Request $request, int $booking_id = 0, string $gateway_prefix = ''): bool|WP_Error
    {
        // ✅ Security checks (IP blacklist, HTTPS, User Agent)
        if (!RESTSettings::check_security($request)) {
            // Detailed logging in development mode
            if (RESTSettings::is_development_mode()) {
                $dev_settings = RESTSettings::get_development_settings();
                if ($dev_settings['verbose_logging']) {
                    error_log('[MHM REST API] Security check failed: ' . wp_json_encode([
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'user_agent' => $request->get_header('User-Agent'),
                        'https' => is_ssl(),
                        'url' => $request->get_route(),
                        'method' => $request->get_method()
                    ]));
                }
            }
            
            return new WP_Error(
                'security_check_failed',
                __('Security check failed. Access denied.', 'mhm-rentiva'),
                ['status' => 403]
            );
        }

        // 1. WordPress REST nonce check (logged-in users)
        $wpNonce = $request->get_header('X-WP-Nonce');
        if ($wpNonce && wp_verify_nonce($wpNonce, 'wp_rest')) {
            return true;
        }
        
        // 2. MHM custom nonce check (guest users)
        if ($booking_id > 0 && !empty($gateway_prefix)) {
            $body = $request->get_json_params();
            $mhmNonce = is_array($body) ? (string) ($body['mhm_nonce'] ?? '') : '';
            
            if ($mhmNonce && wp_verify_nonce($mhmNonce, 'mhm_' . $gateway_prefix . '_' . $booking_id)) {
                return true;
            }
        }
        
        return new WP_Error(
            'forbidden', 
            __('Authorization failed. Please refresh the page and try again.', 'mhm-rentiva'), 
            ['status' => 403]
        );
    }
    
    /**
     * Customer token validation
     * 
     * @param string $token Customer token
     * @param string $post_type Post type to check (default: 'vehicle_booking')
     * @param string $email_meta_key Email meta key (default: '_booking_customer_email')
     * @return array|null Customer information or null
     */
    public static function verifyCustomerToken(string $token, string $post_type = 'vehicle_booking', string $email_meta_key = '_booking_customer_email'): ?array
    {
        // ✅ Use secure token validation system
        return SecureToken::verify_customer_token($token, $post_type, $email_meta_key);
    }
    
    /**
     * ✅ Dynamic Rate limiting check
     * 
     * @param string $identifier User identifier (IP, user_id, etc.)
     * @param string $type Rate limit type (default, strict, burst)
     * @return bool Is rate limit exceeded?
     */
    public static function checkRateLimit(string $identifier, string $type = 'default'): bool
    {
        // ✅ Get dynamic settings from RESTSettings
        return RESTSettings::check_rate_limit($identifier, $type);
    }

    /**
     * @deprecated Use dynamic checkRateLimit
     * Old hardcoded rate limiting system
     */
    public static function checkRateLimitLegacy(string $identifier, int $limit = 60, int $window = 60): bool
    {
        $cache_key = 'mhm_rate_limit_' . md5($identifier);
        $requests = get_transient($cache_key) ?: [];
        
        $now = time();
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        // Limit check
        if (count($requests) >= $limit) {
            return false; // Rate limit exceeded
        }
        
        // Save new request
        $requests[] = $now;
        set_transient($cache_key, $requests, $window);
        
        return true; // Rate limit not exceeded
    }
    
    /**
     * API key validation
     * 
     * @param string $api_key API key
     * @param string $key_type API key type
     * @return bool Is API key valid?
     */
    public static function verifyApiKey(string $api_key, string $key_type = 'general'): bool
    {
        if (empty($api_key)) {
            return false;
        }
        
        $valid_keys = get_option('mhm_rentiva_api_keys', []);
        
        if (!is_array($valid_keys) || !isset($valid_keys[$key_type])) {
            return false;
        }
        
        return hash_equals($valid_keys[$key_type], $api_key);
    }
    
    /**
     * IP whitelist check
     * 
     * @param string $ip IP address to check
     * @return bool Is IP in whitelist?
     */
    public static function isIpWhitelisted(string $ip): bool
    {
        $whitelist = get_option('mhm_rentiva_ip_whitelist', []);
        
        if (empty($whitelist) || !is_array($whitelist)) {
            return true; // Allow all IPs if no whitelist
        }
        
        return in_array($ip, $whitelist, true);
    }
    
    /**
     * Create secure token
     * 
     * @param array $data Data to store in token
     * @param int $expiration Token duration (seconds)
     * @return string Secure token
     */
    public static function createSecureToken(array $data, int $expiration = 3600): string
    {
        $payload = [
            'data' => $data,
            'exp' => time() + $expiration,
            'nonce' => wp_generate_password(32, false)
        ];
        
        $encoded = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $encoded, wp_salt());
        
        return base64_encode(json_encode([
            'payload' => $encoded,
            'signature' => $signature
        ]));
    }
    
    /**
     * Secure token validation
     * 
     * @param string $token Token to validate
     * @return array|null Token data or null
     */
    public static function verifySecureToken(string $token): ?array
    {
        try {
            $decoded = json_decode(base64_decode($token), true);
            
            if (!$decoded || !isset($decoded['payload']) || !isset($decoded['signature'])) {
                return null;
            }
            
            // Signature check
            $expected_signature = hash_hmac('sha256', $decoded['payload'], wp_salt());
            if (!hash_equals($expected_signature, $decoded['signature'])) {
                return null;
            }
            
            // Payload decode et
            $payload = json_decode(base64_decode($decoded['payload']), true);
            
            if (!$payload || !isset($payload['data']) || !isset($payload['exp'])) {
                return null;
            }
            
            // Duration check
            if (time() > $payload['exp']) {
                return null;
            }
            
            return $payload['data'];
            
        } catch (Exception $e) {
            return null;
        }
    }
}
