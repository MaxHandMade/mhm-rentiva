<?php

/**
 * Settings Management Center
 *
 * Implements a Registry pattern for modular settings management.
 * Follows Open/Closed principle for extending settings tabs.
 *
 * @package MHMRentiva
 * @version 1.5.0
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\View\TabRendererRegistry;

if (! defined('ABSPATH')) {
	exit;
}

final class Settings
{

	/**
	 * Option key for central settings storage.
	 */
	public const OPTION_NAME = 'mhm_rentiva_settings';

	/**
	 * Registry for settings providers.
	 *
	 * @var array<string, string>
	 */
	private static array $providers = array();

	/**
	 * Register settings services and hooks.
	 *
	 * Required by MHMRentiva\Plugin class.
	 */
	public static function register(): void
	{
		self::init();

		// Initialize core settings registration
		SettingsCore::register();

		// Register action handler (Controller) for non-AJAX actions
		add_action('admin_init', array(SettingsHandler::class, 'handle'));
	}

	/**
	 * Initialize settings system and register registry-based hooks.
	 */
	public static function init(): void
	{
		// AJAX Actions
		add_action('wp_ajax_mhm_reset_settings_tab', array(self::class, 'ajax_reset_settings_tab'));

		// Register default providers from Groups
		self::register_provider('general', \MHMRentiva\Admin\Settings\Groups\GeneralSettings::class);
		self::register_provider('booking', \MHMRentiva\Admin\Settings\Groups\BookingSettings::class);
		self::register_provider('vehicle', \MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings::class);
		self::register_provider('security', \MHMRentiva\Admin\Settings\Groups\SecuritySettings::class);

		// Allow third-party extensions to register providers
		do_action('mhm_rentiva_register_settings_providers');
	}

	/**
	 * Register a new settings provider.
	 *
	 * @param string $tab        Tab slug.
	 * @param string $class_name Class name (must implement get_default_settings).
	 */
	public static function register_provider(string $tab, string $class_name): void
	{
		if (class_exists($class_name) && method_exists($class_name, 'get_default_settings')) {
			self::$providers[$tab] = $class_name;
		}
	}

	/**
	 * Get a setting value from the central store.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public static function get(string $key, $default = null)
	{
		return SettingsCore::get($key, $default);
	}

	/**
	 * Render the main settings page.
	 *
	 * Orchestrates the TabRendererRegistry and SettingsView.
	 */
	public static function render_settings_page(): void
	{
		$registry    = new TabRendererRegistry();
		$current_tab = sanitize_key($_GET['tab'] ?? 'general');
		$renderer    = $registry->get($current_tab) ?: $registry->get('general');

		// Prepare tab list for sidebar
		$tabs = array();
		foreach ($registry->get_all() as $slug => $tab_renderer) {
			$tabs[$slug] = $tab_renderer->get_label();
		}

		// Prepare header buttons
		$buttons = $renderer->get_header_actions();

		// Add Documentation button globally
		$buttons[] = array(
			'type' => 'documentation',
			'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
		);

		// Centralized header rendering
		ob_start();
		$settings_view = new class {
			use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait {
				render_admin_header as public;
			}
		};
		// Pass the current tab label as subtitle
		$settings_view->render_admin_header((string) get_admin_page_title(), $buttons, true, $renderer->get_label());
		$header_html = ob_get_clean();

		SettingsView::render_settings_page($current_tab, $tabs, $renderer, $header_html);
	}

	/**
	 * Reset a specific settings tab to defaults (AJAX).
	 */
	public static function ajax_reset_settings_tab(): void
	{
		// 1. Security Check
		check_ajax_referer('mhm_rentiva_settings_nonce', 'security');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions for this action.', 'mhm-rentiva')));
		}

		// 2. Parameter Validation
		$tab          = sanitize_key($_POST['tab'] ?? '');
		$redirect_url = esc_url_raw($_POST['redirect_url'] ?? admin_url('admin.php?page=mhm-rentiva-settings'));

		if (empty($tab)) {
			wp_send_json_error(array('message' => __('Invalid settings tab.', 'mhm-rentiva')));
		}

		// 3. Execute reset via Service
		$updated = \MHMRentiva\Admin\Settings\Services\SettingsService::reset_defaults($tab);

		if ($updated) {
			wp_send_json_success(
				array(
					'message'      => __('Settings successfully reset to defaults.', 'mhm-rentiva'),
					'redirect_url' => $redirect_url,
				)
			);
		}

		wp_send_json_error(array('message' => __('Settings are already at default values.', 'mhm-rentiva')));
	}

	/**
	 * Get all default values across all registered providers.
	 *
	 * Useful for API exports or system resets.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all_defaults(): array
	{
		$all_defaults = array();
		foreach (self::$providers as $tab => $class) {
			$all_defaults[$tab] = $class::get_default_settings();
		}
		return $all_defaults;
	}
}
