<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

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
	private const ACTION_NONCE = 'mhm_rest_api_keys_nonce';

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
		$actions = array(
			'create_api_key',
			'list_api_keys',
			'revoke_api_key',
			'delete_api_key',
			'list_endpoints',
			'reset_rest_settings',
		);

		foreach ($actions as $action) {
			add_action("wp_ajax_mhm_{$action}", array(self::class, 'handle_request'));
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
		$nonce_value = isset($_REQUEST['nonce']) ? (string) wp_unslash($_REQUEST['nonce']) : (isset($_REQUEST['security']) ? (string) wp_unslash($_REQUEST['security']) : '');
		if (! wp_verify_nonce($nonce_value, self::ACTION_NONCE)) {
			wp_send_json_error(
				array(
					'message' => esc_html__('Invalid security nonce.', 'mhm-rentiva'),
				),
				403
			);
		}

		if (! current_user_can(self::REQUIRED_CAP)) {
			wp_send_json_error(
				array(
					'message' => esc_html__('Insufficient permissions to perform this action.', 'mhm-rentiva'),
				),
				403
			);
		}

		$action = self::post_text('action');

		// 2. Dispatching (PHP 8.0+ Match)
		try {
			match ($action) {
				'mhm_create_api_key'     => self::ajax_create_api_key(),
				'mhm_list_api_keys'      => self::ajax_list_api_keys(),
				'mhm_revoke_api_key'     => self::ajax_revoke_api_key(),
				'mhm_delete_api_key'     => self::ajax_delete_api_key(),
				'mhm_list_endpoints'     => self::ajax_list_endpoints(),
				'mhm_reset_rest_settings' => self::ajax_reset_rest_settings(),
				default                  => throw new \Exception(esc_html__('Invalid operation.', 'mhm-rentiva')),
			};
		} catch (\Throwable $e) {
			wp_send_json_error(array('message' => esc_html($e->getMessage())));
		}
	}

	/**
	 * Create API Key AJAX handler.
	 */
	private static function ajax_create_api_key(): void
	{
		$name        = self::post_text('name');
		$permissions = self::post_array('permissions');
		$permissions = ! empty($permissions) ? array_map('sanitize_text_field', $permissions) : array('read');
		$expires_at  = self::post_int('expires_at');
		$expires_at  = $expires_at > 0 ? $expires_at : null;

		if (empty($name)) {
			throw new \Exception(esc_html__('API key name is required.', 'mhm-rentiva'));
		}

		$result = APIKeyManager::create_api_key($name, $permissions, $expires_at);

		if (false === $result) {
			throw new \Exception(esc_html__('Failed to create API key.', 'mhm-rentiva'));
		}

		wp_send_json_success(
			array(
				'message' => esc_html__('API key created successfully.', 'mhm-rentiva'),
				'key'     => $result,
			)
		);
	}

	/**
	 * List API Keys AJAX handler.
	 */
	private static function ajax_list_api_keys(): void
	{
		$keys = APIKeyManager::list_api_keys();

		wp_send_json_success(
			array(
				'keys'  => $keys,
				'count' => count($keys),
			)
		);
	}

	/**
	 * Revoke API Key AJAX handler.
	 */
	private static function ajax_revoke_api_key(): void
	{
		$key_id = self::post_text('key_id');

		if (empty($key_id)) {
			throw new \Exception(esc_html__('API key ID is required to revoke.', 'mhm-rentiva'));
		}

		if (! APIKeyManager::revoke_api_key($key_id)) {
			throw new \Exception(esc_html__('Failed to revoke API key.', 'mhm-rentiva'));
		}

		wp_send_json_success(array('message' => esc_html__('API key revoked successfully.', 'mhm-rentiva')));
	}

	/**
	 * Delete API Key AJAX handler.
	 */
	private static function ajax_delete_api_key(): void
	{
		$key_id = self::post_text('key_id');

		if (empty($key_id)) {
			throw new \Exception(esc_html__('API key ID is required to delete.', 'mhm-rentiva'));
		}

		if (! APIKeyManager::delete_api_key($key_id)) {
			throw new \Exception(esc_html__('Failed to delete API key.', 'mhm-rentiva'));
		}

		wp_send_json_success(array('message' => esc_html__('API key deleted successfully.', 'mhm-rentiva')));
	}

	/**
	 * List Endpoints AJAX handler.
	 */
	private static function ajax_list_endpoints(): void
	{
		$endpoints = EndpointListHelper::get_all_endpoints();

		wp_send_json_success(
			array(
				'endpoints' => $endpoints,
				'count'     => count($endpoints),
				'namespace' => EndpointListHelper::NAMESPACE,
			)
		);
	}

	/**
	 * Reset REST Settings to Defaults AJAX handler.
	 */
	private static function ajax_reset_rest_settings(): void
	{
		if (! class_exists(RESTSettings::class)) {
			throw new \Exception(esc_html__('REST configuration system is not available.', 'mhm-rentiva'));
		}

		if (! RESTSettings::reset_to_defaults()) {
			throw new \Exception(esc_html__('Failed to reset REST API settings to defaults.', 'mhm-rentiva'));
		}

		wp_send_json_success(
			array(
				'message'  => esc_html__('REST API settings reset to defaults successfully.', 'mhm-rentiva'),
				'redirect' => esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=integration')),
			)
		);
	}

	/**
	 * Safely read text value from $_POST.
	 */
	private static function post_text(string $key, string $fallback = ''): string
	{
		$post = $_POST ?? array();
		if (! isset($post[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_request().
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_request().
		return sanitize_text_field(wp_unslash((string) $post[$key]));
	}

	/**
	 * Safely read integer value from $_POST.
	 */
	private static function post_int(string $key, int $fallback = 0): int
	{
		$value = self::post_text($key, '');
		return '' === $value ? $fallback : (int) $value;
	}

	/**
	 * Safely read array value from $_POST.
	 *
	 * @return array<int|string,mixed>
	 */
	private static function post_array(string $key): array
	{
		$post = $_POST ?? array();
		if (! isset($post[$key]) || ! is_array($post[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_request().
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified in handle_request(); array items are sanitized by caller.
		return wp_unslash($post[$key]);
	}
}
