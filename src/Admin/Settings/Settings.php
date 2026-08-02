<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.

/**
 * Settings Management Center
 *
 * Implements a Registry pattern for modular settings management.
 * Follows Open/Closed principle for extending settings tabs.
 *
 * @package MHMRentiva
 * @version 1.5.0
 */





use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\View\TabRendererRegistry;



final class Settings {



	/**
	 * Option key for central settings storage.
	 */
	public const OPTION_NAME = 'mhmrentiva_settings';

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
		add_action('admin_init', array( SettingsHandler::class, 'handle' ));
	}

	/**
	 * Initialize settings system and register registry-based hooks.
	 */
	public static function init(): void
	{
		// AJAX Actions

		// Register default providers from Groups
		self::register_provider('general', \MHMRentiva\Admin\Settings\Groups\GeneralSettings::class);
		self::register_provider('booking', \MHMRentiva\Admin\Settings\Groups\BookingSettings::class);
		self::register_provider('vehicle', \MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings::class);
		// 'vendor-marketplace' (add-on) is no longer hardcoded here (extension
		// point) -- the add-on registers it via the do_action() below, the same
		// extension point it already had.

		// Allow third-party extensions to register providers
		do_action('mhmrentiva_register_settings_providers');
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
			self::$providers[ $tab ] = $class_name;
		}
	}

	/**
	 * Look up a previously-registered provider class for a tab, if any.
	 *
	 * Used by SettingsService::match() to resolve
	 * the provider class for the add-on-owned tabs (transfer/vendor-marketplace/
	 * messages) Lite no longer hardcodes by name -- the add-on registers its class
	 * via the existing `mhmrentiva_register_settings_providers` action
	 * (see self::init()), and this reads that same registry instead of a
	 * second, parallel extension point.
	 *
	 * @param string $tab Tab slug.
	 * @return string|null Registered class name, or null if nothing registered.
	 */
	public static function get_provider(string $tab): ?string
	{
		return self::$providers[ $tab ] ?? null;
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
		$current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash( (string) $_GET['tab'])) : 'general';
		$renderer    = $registry->get($current_tab) ?: $registry->get('general');

		// Prepare tab list for sidebar
		$tabs = array();
		foreach ($registry->get_all() as $slug => $tab_renderer) {
			$tabs[ $slug ] = $tab_renderer->get_label();
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
		$settings_view = new class() {
			use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait {
				render_admin_header as public;
			}
		};
		// Pass the current tab label as subtitle
		$settings_view->render_admin_header( (string) get_admin_page_title(), $buttons, true, $renderer->get_label());
		$header_html = ob_get_clean();

		SettingsView::render_settings_page($current_tab, $tabs, $renderer, $header_html);
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
			$all_defaults[ $tab ] = $class::get_default_settings();
		}
		return $all_defaults;
	}
}
