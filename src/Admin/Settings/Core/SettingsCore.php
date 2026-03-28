<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Core;

if (!defined('ABSPATH')) {
    exit;
}

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * SettingsCore Class
 *
 * Handles core settings registration, asset management, and configuration routing.
 * Refactored for SOLID principles and high-performance.
 *
 * @package MHMRentiva\Admin\Settings\Core
 */
final class SettingsCore
{



	/**
	 * Settings Group and Page constants
	 */
	public const GROUP       = 'mhm_rentiva_settings';
	public const PAGE        = 'mhm_rentiva_settings';
	public const OPTION_NAME = 'mhm_rentiva_settings';

	/**
	 * Register all core settings hooks
	 */
	public static function register(): void
	{
		// Enqueue admin assets
		add_action('admin_enqueue_scripts', array(self::class, 'enqueue_assets'));

		// Dark Mode Logic
		add_action('admin_head', array(self::class, 'inject_dark_mode_styles'));
		add_action('wp_head', array(self::class, 'inject_dark_mode_styles'));
		add_filter('body_class', array(self::class, 'add_dark_mode_body_class'));

		// AJAX Handlers
		add_action('wp_ajax_mhm_save_dark_mode', array(self::class, 'ajax_save_dark_mode'));
		add_action('wp_ajax_mhm_run_settings_tests', array(self::class, 'ajax_run_settings_tests'));

		// Service Initializers (Delegated to specialized managers)
		add_action('init', array(self::class, 'initialize_services'));

		// Core Registration
		add_action('admin_init', array(self::class, 'init_settings_registration'));

		// Performance: Flush rewrite rules only when necessary
		add_action('update_option_' . self::OPTION_NAME, array(self::class, 'handle_rewrite_flushing'), 10, 3);
	}

	/**
	 * Initialize related services
	 */
	public static function initialize_services(): void
	{
		// Session and Security Management (Delegated)
		if (class_exists(\MHMRentiva\Admin\Auth\SessionManager::class)) {
			\MHMRentiva\Admin\Auth\SessionManager::init();
		}

		// Rate Limiting Logic (Separated Service)
		if (class_exists(RateLimiter::class) && RateLimiter::is_enabled()) {
			self::setup_rate_limiting_hooks();
		}
	}

	/**
	 * Centralized settings registration
	 */
	public static function init_settings_registration(): void
	{
		register_setting(
			self::GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array(\MHMRentiva\Admin\Settings\Core\SettingsSanitizer::class, 'sanitize'),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::GROUP,
			'mhm_rentiva_dark_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => array(\MHMRentiva\Admin\Settings\Core\SettingsSanitizer::class, 'sanitize_dark_mode_option'),
				'default'           => 'auto',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'mhm_rentiva_addon_settings',
			'mhm_rentiva_addon_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array(\MHMRentiva\Admin\Settings\Core\SettingsSanitizer::class, 'sanitize_addon_settings_option'),
				'default'           => \MHMRentiva\Admin\Addons\AddonSettings::defaults(),
				'show_in_rest'      => false,
			)
		);

		// Register Sub-groups dynamically
		self::register_sub_groups();
	}

	/**
	 * Register external setting groups
	 */
	private static function register_sub_groups(): void
	{
		$groups = array(
			\MHMRentiva\Admin\Settings\Groups\GeneralSettings::class,
			\MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings::class,
			\MHMRentiva\Admin\Settings\Groups\BookingSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CustomerManagementSettings::class,
			\MHMRentiva\Admin\Settings\Groups\EmailSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CoreSettings::class,
			\MHMRentiva\Admin\Settings\Groups\FrontendSettings::class,
			\MHMRentiva\Admin\Settings\Groups\AddonSettings::class,
			\MHMRentiva\Admin\Settings\Groups\SecuritySettings::class,
			\MHMRentiva\Admin\Settings\Groups\PaymentSettings::class,
			\MHMRentiva\Admin\Settings\Groups\MaintenanceSettings::class,
			\MHMRentiva\Admin\Settings\Groups\LicenseSettings::class,
			\MHMRentiva\Admin\Settings\Groups\LogsSettings::class,
			\MHMRentiva\Admin\Settings\Groups\TransferSettings::class,
			\MHMRentiva\Admin\Settings\Groups\VendorMarketplaceSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CommentsSettingsGroup::class,
			\MHMRentiva\Admin\REST\Settings\RESTSettings::class,
		);

		foreach ($groups as $group) {
			if (class_exists($group) && method_exists($group, 'register')) {
				$group::register();
			}
		}
	}

	/**
	 * Get all plugin settings from database.
	 *
	 * @return array
	 */
	public static function get_all(): array
	{
		return (array) get_option(self::OPTION_NAME, array());
	}

	/**
	 * Check if a setting exists in the database.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function has(string $key): bool
	{
		$settings = get_option(self::OPTION_NAME, array());
		return array_key_exists($key, $settings);
	}

	/**
	 * Get value from settings with safe fallback
	 */
	public static function get(string $key, mixed $default = null): mixed
	{
		$settings = get_option(self::OPTION_NAME, array());
		$defaults = self::get_defaults();

		if (array_key_exists($key, $settings)) {
			$value = $settings[$key];

			// Handle empty strings or specific numeric fallbacks
			if ('' === $value || (null === $value)) {
				return $defaults[$key] ?? $default;
			}

			return $value;
		}

		// Final safety fallback: If not in DB and not in defaults
		$val = $defaults[$key] ?? $default;

		// If still null/empty and looks like a boolean/checkbox field, force '0'
		if (null === $val || '' === $val) {
			$boolean_indicators = array('_enabled', '_protection', '_active', 'is_', '_enabled_');
			foreach ($boolean_indicators as $indicator) {
				if (str_contains($key, $indicator)) {
					return '0';
				}
			}
		}

		return $val;
	}

	/**
	 * Set a specific setting value
	 */
	public static function set(string $key, mixed $value): bool
	{
		$settings         = self::get_all();
		$settings[$key] = $value;
		return update_option(self::OPTION_NAME, $settings);
	}

	/**
	 * Delete a specific setting
	 */
	public static function delete(string $key): bool
	{
		$settings = self::get_all();
		if (array_key_exists($key, $settings)) {
			unset($settings[$key]);
			return update_option(self::OPTION_NAME, $settings);
		}
		return true;
	}

	/**
	 * Get company website URL
	 */
	public static function get_company_website(): string
	{
		return 'https://maxhandmade.com';
	}

	/**
	 * Get support email address
	 */
	public static function get_support_email(): string
	{
		return (string) self::get('mhm_rentiva_support_email', get_option('admin_email'));
	}

	/**
	 * Optimized defaults merging
	 */
	public static function get_defaults(): array
	{
		static $merged_defaults = null;

		if (null !== $merged_defaults) {
			return $merged_defaults;
		}

		$merged_defaults = array(
			'mhm_rentiva_endpoint_bookings'        => 'rentiva-bookings',
			'mhm_rentiva_endpoint_favorites'       => 'rentiva-favorites',
			'mhm_rentiva_endpoint_payment_history' => 'rentiva-payment-history',
			'mhm_rentiva_endpoint_edit_account'    => 'rentiva-edit-account',
			'mhm_rentiva_endpoint_messages'        => 'rentiva-messages',
			'mhm_rentiva_vehicle_base_price'       => 1.0,
			'mhm_rentiva_brand_name'               => get_bloginfo('name'),
		);

		// Collect defaults from other modules
		$sub_modules = array(
			\MHMRentiva\Admin\Settings\Groups\GeneralSettings::class,
			\MHMRentiva\Admin\Settings\Groups\BookingSettings::class,
			\MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings::class,
			\MHMRentiva\Admin\Settings\Groups\FrontendSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CustomerManagementSettings::class,
			\MHMRentiva\Admin\Settings\Groups\EmailSettings::class,
			\MHMRentiva\Admin\Settings\Groups\TransferSettings::class,
			\MHMRentiva\Admin\Settings\Groups\VendorMarketplaceSettings::class,
			\MHMRentiva\Admin\Settings\Groups\MaintenanceSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CoreSettings::class,
			\MHMRentiva\Admin\Settings\Groups\SecuritySettings::class,
			\MHMRentiva\Admin\Settings\Groups\AddonSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CommentsSettingsGroup::class,
			\MHMRentiva\Admin\Settings\Groups\LicenseSettings::class,
			\MHMRentiva\Admin\Settings\Groups\LogsSettings::class,
			\MHMRentiva\Admin\Settings\Groups\PaymentSettings::class,
		);

		foreach ($sub_modules as $module) {
			if (class_exists($module) && method_exists($module, 'get_default_settings')) {
				$merged_defaults = array_merge($merged_defaults, $module::get_default_settings());
			}
		}

		return $merged_defaults;
	}

	/**
	 * Inject Dark Mode CSS using wp_add_inline_style for better performance
	 */
	/**
	 * Inject Dark Mode CSS using wp_add_inline_style for better performance
	 */
	public static function inject_dark_mode_styles(): void
	{
		// Use central getter to respect settings page saves
		$mode = self::get('mhm_rentiva_dark_mode', 'auto');

		if ('auto' !== $mode) {
			return;
		}

		// Using a variable to avoid multiple echo statements
		$css = '
            @media (prefers-color-scheme: dark) {
                .mhm-auto-dark-mode .mhm-quick-actions { background: #1e1e1e !important; color: #fff; }
                .mhm-auto-dark-mode .quick-action-card { background: #2d2d2d !important; border-color: #3c3c3c !important; }
            }
        ';

		printf('<style id="mhm-rentiva-dynamic-dark-mode">%s</style>', esc_html(wp_strip_all_tags($css)));
	}

	/**
	 * AJAX: Save Dark Mode Preference
	 */
	public static function ajax_save_dark_mode(): void
	{
		if (! check_ajax_referer('mhm_dark_mode_nonce', 'nonce', false)) {
			wp_send_json_error(__('Invalid nonce', 'mhm-rentiva'), 403);
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Permission denied', 'mhm-rentiva'));
		}

		$raw_mode = wp_unslash($_POST['mode'] ?? 'auto');

		$sanitized_settings = \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::sanitize(
			array(
				'current_active_tab' => 'general',
				'mhm_rentiva_dark_mode' => $raw_mode,
			)
		);

		$mode = (string)($sanitized_settings['mhm_rentiva_dark_mode'] ?? 'auto');

		// 1. Update standalone option (for quick frontend access)
		update_option('mhm_rentiva_dark_mode', $mode);

		// 2. Sync with Main Settings Array (so the Settings Form reflects the change)
		update_option(self::OPTION_NAME, $sanitized_settings);

		wp_send_json_success(array('message' => __('Settings updated', 'mhm-rentiva')));
	}

	/**
	 * AJAX: Run settings diagnostic tests
	 */
	public static function ajax_run_settings_tests(): void
	{
		if (! check_ajax_referer('mhm_settings_test_nonce', 'nonce', false)) {
			wp_send_json_error(__('Invalid nonce', 'mhm-rentiva'), 403);
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Permission denied', 'mhm-rentiva'));
		}

		if (! class_exists(\MHMRentiva\Admin\Settings\Testing\SettingsTester::class)) {
			wp_send_json_error(__('Diagnostic testing engine not found.', 'mhm-rentiva'));
		}

		$report = \MHMRentiva\Admin\Settings\Testing\SettingsTester::generate_report();
		wp_send_json_success($report);
	}

	/**
	 * Handle rewrite rules flushing only if slugs changed
	 */
	public static function handle_rewrite_flushing(mixed $old_value, mixed $new_value): void
	{
		$slug_keys = array(
			'mhm_rentiva_vehicle_url_base',
			'mhm_rentiva_endpoint_bookings',
			'mhm_rentiva_endpoint_favorites',
			'mhm_rentiva_endpoint_payment_history',
			'mhm_rentiva_endpoint_messages',
		);

		$changed = false;
		foreach ($slug_keys as $key) {
			if (($old_value[$key] ?? '') !== ($new_value[$key] ?? '')) {
				$changed = true;
				break;
			}
		}

		if ($changed) {
			flush_rewrite_rules();
			update_option('mhm_rentiva_woocommerce_endpoints_flushed', false);
		}
	}

	/**
	 * Enqueue Admin Assets
	 */
	public static function enqueue_assets(): void
	{
		$screen = get_current_screen();
		if (! $screen || ! str_contains($screen->id, 'mhm-rentiva-settings')) {
			return;
		}

		wp_enqueue_style(
			'mhm-rentiva-settings',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/settings.css',
			array(),
			\MHM_RENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-rentiva-dark-mode',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/dark-mode.css',
			array(),
			\MHM_RENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-rentiva-dark-mode',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/dark-mode.js',
			array('jquery'),
			\MHM_RENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-rentiva-dark-mode',
			'mhmDarkMode',
			array(
				'ajaxUrl'     => admin_url('admin-ajax.php'),
				'nonce'       => wp_create_nonce('mhm_dark_mode_nonce'),
				'currentMode' => self::get('mhm_rentiva_dark_mode', 'auto'),
			)
		);
	}

	/**
	 * Private helper for rate limit hooks to keep register() clean
	 */
	private static function setup_rate_limiting_hooks(): void
	{
		$actions = array('mhm_booking_request', 'mhm_payment_request');
		foreach ($actions as $action) {
			add_action("wp_ajax_{$action}", array(self::class, 'enforce_rate_limit'), 1);
			add_action("wp_ajax_nopriv_{$action}", array(self::class, 'enforce_rate_limit'), 1);
		}
	}

	/**
	 * Enforce Rate Limiting Logic
	 */
	public static function enforce_rate_limit(): void
	{
		if (current_user_can('manage_options')) {
			return;
		}

		// Logic handled by RateLimiter service
		if (class_exists(RateLimiter::class) && ! RateLimiter::is_allowed('general')) {
			wp_send_json_error(
				array(
					'message' => __('Too many requests. Please wait.', 'mhm-rentiva'),
					'retry'   => 3600,
				),
				429
			);
		}
	}
}
