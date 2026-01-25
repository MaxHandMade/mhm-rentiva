<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

use MHMRentiva\Admin\REST\APIKeyManager;
use MHMRentiva\Admin\REST\EndpointListHelper;
use MHMRentiva\Admin\REST\Settings\RESTSettings;

/**
 * Class APIKeysPage
 *
 * REST API Keys management static AJAX controller.
 * Refactored using a dispatcher pattern for cleaner request handling.
 * 
 * @package MHMRentiva\Admin\Settings
 * @since 4.0.0
 */
final class APIKeysPage
{
    /**
     * Nonce action for REST settings operations.
     */
    private const ACTION_NONCE = 'mhm_rest_settings';

    /**
     * Required capability for REST settings operations.
     */
    private const REQUIRED_CAP = 'manage_options';

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * Register AJAX handlers.
     *
     * @return void
     */
    public static function register(): void
    {
        $actions = [
            'create_api_key',
            'list_api_keys',
            'revoke_api_key',
            'delete_api_key',
            'list_endpoints',
            'reset_rest_settings',
        ];

        foreach ($actions as $action) {
            add_action("wp_ajax_mhm_{$action}", [self::class, 'handle_request']);
        }
    }

    /**
     * Main dispatcher for all AJAX requests in this context.
     *
     * @return void
     */
    public static function handle_request(): void
    {
        // 1. Security Check (Compatibility with rest-api-keys.js using 'nonce' or 'security' param)
        $nonce_param = isset($_POST['nonce']) ? 'nonce' : 'security';
        check_ajax_referer(self::ACTION_NONCE, $nonce_param);

        if (! current_user_can(self::REQUIRED_CAP)) {
            wp_send_json_error([
                'message' => __('Insufficient permissions to perform this action.', 'mhm-rentiva')
            ], 403);
        }

        $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';

        // 2. Dispatching (PHP 8.0+ Match)
        try {
            match ($action) {
                'mhm_create_api_key'     => self::ajax_create_api_key(),
                'mhm_list_api_keys'      => self::ajax_list_api_keys(),
                'mhm_revoke_api_key'     => self::ajax_revoke_api_key(),
                'mhm_delete_api_key'     => self::ajax_delete_api_key(),
                'mhm_list_endpoints'     => self::ajax_list_endpoints(),
                'mhm_reset_rest_settings' => self::ajax_reset_rest_settings(),
                default                  => throw new \Exception(__('Invalid operation.', 'mhm-rentiva')),
            };
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Create API Key AJAX handler.
     */
    private static function ajax_create_api_key(): void
    {
        $name        = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $permissions = isset($_POST['permissions']) && is_array($_POST['permissions'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['permissions']))
            : ['read'];
        $expires_at  = ! empty($_POST['expires_at']) ? (int) $_POST['expires_at'] : null;

        if (empty($name)) {
            throw new \Exception(__('API key name is required.', 'mhm-rentiva'));
        }

        $result = APIKeyManager::create_api_key($name, $permissions, $expires_at);

        if (false === $result) {
            throw new \Exception(__('Failed to create API key.', 'mhm-rentiva'));
        }

        wp_send_json_success([
            'message' => __('API key created successfully.', 'mhm-rentiva'),
            'key'     => $result
        ]);
    }

    /**
     * List API Keys AJAX handler.
     */
    private static function ajax_list_api_keys(): void
    {
        $keys = APIKeyManager::list_api_keys();

        wp_send_json_success([
            'keys'  => $keys,
            'count' => count($keys)
        ]);
    }

    /**
     * Revoke API Key AJAX handler.
     */
    private static function ajax_revoke_api_key(): void
    {
        $key_id = isset($_POST['key_id']) ? sanitize_text_field(wp_unslash($_POST['key_id'])) : '';

        if (empty($key_id)) {
            throw new \Exception(__('API key ID is required to revoke.', 'mhm-rentiva'));
        }

        if (! APIKeyManager::revoke_api_key($key_id)) {
            throw new \Exception(__('Failed to revoke API key.', 'mhm-rentiva'));
        }

        wp_send_json_success(['message' => __('API key revoked successfully.', 'mhm-rentiva')]);
    }

    /**
     * Delete API Key AJAX handler.
     */
    private static function ajax_delete_api_key(): void
    {
        $key_id = isset($_POST['key_id']) ? sanitize_text_field(wp_unslash($_POST['key_id'])) : '';

        if (empty($key_id)) {
            throw new \Exception(__('API key ID is required to delete.', 'mhm-rentiva'));
        }

        if (! APIKeyManager::delete_api_key($key_id)) {
            throw new \Exception(__('Failed to delete API key.', 'mhm-rentiva'));
        }

        wp_send_json_success(['message' => __('API key deleted successfully.', 'mhm-rentiva')]);
    }

    /**
     * List Endpoints AJAX handler.
     */
    private static function ajax_list_endpoints(): void
    {
        $endpoints = EndpointListHelper::get_all_endpoints();

        wp_send_json_success([
            'endpoints' => $endpoints,
            'count'     => count($endpoints),
            'namespace' => EndpointListHelper::NAMESPACE
        ]);
    }

    /**
     * Reset REST Settings to Defaults AJAX handler.
     */
    private static function ajax_reset_rest_settings(): void
    {
        if (! class_exists(RESTSettings::class)) {
            throw new \Exception(__('REST configuration system is not available.', 'mhm-rentiva'));
        }

        if (! RESTSettings::reset_to_defaults()) {
            throw new \Exception(__('Failed to reset REST API settings to defaults.', 'mhm-rentiva'));
        }

        wp_send_json_success([
            'message'  => __('REST API settings reset to defaults successfully.', 'mhm-rentiva'),
            'redirect' => admin_url('admin.php?page=mhm-rentiva-settings&tab=integration')
        ]);
    }
}
