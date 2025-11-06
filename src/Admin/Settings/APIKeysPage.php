<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

use MHMRentiva\Admin\REST\APIKeyManager;
use MHMRentiva\Admin\REST\EndpointListHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ API KEYS PAGE - AJAX Handlers
 * 
 * AJAX endpoints for API Key management
 */
final class APIKeysPage
{
    /**
     * Register AJAX handlers
     */
    public static function register(): void
    {
        add_action('wp_ajax_mhm_create_api_key', [self::class, 'ajax_create_api_key']);
        add_action('wp_ajax_mhm_list_api_keys', [self::class, 'ajax_list_api_keys']);
        add_action('wp_ajax_mhm_revoke_api_key', [self::class, 'ajax_revoke_api_key']);
        add_action('wp_ajax_mhm_delete_api_key', [self::class, 'ajax_delete_api_key']);
        add_action('wp_ajax_mhm_list_endpoints', [self::class, 'ajax_list_endpoints']);
        add_action('wp_ajax_mhm_reset_rest_settings', [self::class, 'ajax_reset_rest_settings']);
    }
    
    /**
     * Create API Key AJAX handler
     */
    public static function ajax_create_api_key(): void
    {
        check_ajax_referer('mhm_rest_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'mhm-rentiva')]);
            return;
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) 
            ? array_map('sanitize_text_field', $_POST['permissions']) 
            : ['read'];
        $expires_at = !empty($_POST['expires_at']) ? (int) $_POST['expires_at'] : null;
        
        if (empty($name)) {
            wp_send_json_error(['message' => __('Key name is required.', 'mhm-rentiva')]);
            return;
        }
        
        $result = APIKeyManager::create_api_key($name, $permissions, $expires_at);
        
        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to create API key.', 'mhm-rentiva')]);
            return;
        }
        
        wp_send_json_success([
            'message' => __('API key created successfully.', 'mhm-rentiva'),
            'key' => $result
        ]);
    }
    
    /**
     * List API Keys AJAX handler
     */
    public static function ajax_list_api_keys(): void
    {
        check_ajax_referer('mhm_rest_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'mhm-rentiva')]);
            return;
        }
        
        $keys = APIKeyManager::list_api_keys();
        
        wp_send_json_success([
            'keys' => $keys,
            'count' => count($keys)
        ]);
    }
    
    /**
     * Revoke API Key AJAX handler
     */
    public static function ajax_revoke_api_key(): void
    {
        check_ajax_referer('mhm_rest_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'mhm-rentiva')]);
            return;
        }
        
        $key_id = sanitize_text_field($_POST['key_id'] ?? '');
        
        if (empty($key_id)) {
            wp_send_json_error(['message' => __('Key ID is required.', 'mhm-rentiva')]);
            return;
        }
        
        $result = APIKeyManager::revoke_api_key($key_id);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to revoke API key.', 'mhm-rentiva')]);
            return;
        }
        
        wp_send_json_success(['message' => __('API key revoked successfully.', 'mhm-rentiva')]);
    }
    
    /**
     * Delete API Key AJAX handler
     */
    public static function ajax_delete_api_key(): void
    {
        check_ajax_referer('mhm_rest_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'mhm-rentiva')]);
            return;
        }
        
        $key_id = sanitize_text_field($_POST['key_id'] ?? '');
        
        if (empty($key_id)) {
            wp_send_json_error(['message' => __('Key ID is required.', 'mhm-rentiva')]);
            return;
        }
        
        $result = APIKeyManager::delete_api_key($key_id);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to delete API key.', 'mhm-rentiva')]);
            return;
        }
        
        wp_send_json_success(['message' => __('API key deleted successfully.', 'mhm-rentiva')]);
    }
    
    /**
     * List Endpoints AJAX handler
     */
    public static function ajax_list_endpoints(): void
    {
        check_ajax_referer('mhm_rest_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'mhm-rentiva')]);
            return;
        }
        
        $endpoints = EndpointListHelper::get_all_endpoints();
        
        wp_send_json_success([
            'endpoints' => $endpoints,
            'count' => count($endpoints),
            'namespace' => EndpointListHelper::NAMESPACE
        ]);
    }
    
    /**
     * Reset REST Settings to Defaults AJAX handler
     */
    public static function ajax_reset_rest_settings(): void
    {
        check_ajax_referer('mhm_rest_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'mhm-rentiva')]);
            return;
        }
        
        if (!class_exists('\MHMRentiva\Admin\REST\Settings\RESTSettings')) {
            wp_send_json_error(['message' => __('RESTSettings class not found.', 'mhm-rentiva')]);
            return;
        }
        
        $result = \MHMRentiva\Admin\REST\Settings\RESTSettings::reset_to_defaults();
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to reset settings to defaults.', 'mhm-rentiva')]);
            return;
        }
        
        wp_send_json_success([
            'message' => __('Settings reset to defaults successfully.', 'mhm-rentiva'),
            'redirect' => admin_url('admin.php?page=mhm-rentiva-settings&tab=integration')
        ]);
    }
}

