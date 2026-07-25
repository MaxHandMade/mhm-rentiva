<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

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
use MHMRentiva\Admin\Core\Security\VerifiedRequest;

final class APIKeysPage {



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
			'list_endpoints',
			'reset_rest_settings',
		);

		foreach ($actions as $action) {
			add_action("wp_ajax_mhm_rentiva_{$action}", array( self::class, 'handle_request' ));
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
		$nonce_value = '';
		if (isset($_REQUEST['nonce'])) {
			$nonce_value = sanitize_text_field(wp_unslash( (string) $_REQUEST['nonce']));
		} elseif (isset($_REQUEST['security'])) {
			$nonce_value = sanitize_text_field(wp_unslash( (string) $_REQUEST['security']));
		}
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

		$req = VerifiedRequest::from($_POST);

		$action = $req->text('action');

		// 2. Dispatching (PHP 8.0+ Match)
		try {
			match ($action) {
				'mhm_rentiva_list_endpoints'      => self::ajax_list_endpoints(),
				'mhm_rentiva_reset_rest_settings' => self::ajax_reset_rest_settings(),
				default                  => throw new \Exception(esc_html__('Invalid operation.', 'mhm-rentiva')),
			};
		} catch (\Throwable $e) {
			$e_class = get_class($e);
			if (str_contains($e_class, 'WPAjaxDie') || str_contains($e_class, 'WPDie')) {
				throw $e;
			}
			wp_send_json_error(array( 'message' => esc_html($e->getMessage()) ));
		}
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
}
