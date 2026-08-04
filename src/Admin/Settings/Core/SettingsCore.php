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
final class SettingsCore {




	/**
	 * Settings Group and Page constants
	 */
	public const GROUP       = 'mhmrentiva_settings';
	public const PAGE        = 'mhmrentiva_settings';
	public const OPTION_NAME = 'mhmrentiva_settings';

	/**
	 * Register all core settings hooks
	 */
	public static function register(): void
	{
		// Enqueue admin assets
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_assets' ));

		// Dark Mode Logic
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_dark_mode_styles' ));
		add_action('wp_enqueue_scripts', array( self::class, 'enqueue_dark_mode_styles' ));

		// AJAX Handlers
		add_action('wp_ajax_mhmrentiva_save_dark_mode', array( self::class, 'ajax_save_dark_mode' ));

		// Service Initializers (Delegated to specialized managers)
		add_action('init', array( self::class, 'initialize_services' ));

		// Core Registration
		add_action('admin_init', array( self::class, 'init_settings_registration' ));

		// Performance: Flush rewrite rules only when necessary
		add_action('update_option_' . self::OPTION_NAME, array( self::class, 'handle_rewrite_flushing' ), 10, 2);
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
				'sanitize_callback' => array( \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::class, 'sanitize' ),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::GROUP,
			'mhmrentiva_dark_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::class, 'sanitize_dark_mode_option' ),
				'default'           => 'auto',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'mhmrentiva_addon_settings',
			'mhmrentiva_addon_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::class, 'sanitize_addon_settings_option' ),
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
			// CustomerManagementSettings::class was removed here (WP.org T8
			// Görev 10b, row F2): the class was a hollow stub (0 fields, 0
			// keys, register() empty) with no reachable tab slot (D3).
			\MHMRentiva\Admin\Settings\Groups\EmailSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CoreSettings::class,
			\MHMRentiva\Admin\Settings\Groups\FrontendSettings::class,
			\MHMRentiva\Admin\Settings\Groups\AddonSettings::class,
			\MHMRentiva\Admin\Settings\Groups\PaymentSettings::class,
			\MHMRentiva\Admin\Settings\Groups\MaintenanceSettings::class,
			\MHMRentiva\Admin\Settings\Groups\LogsSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CommentsSettingsGroup::class,
			\MHMRentiva\Admin\REST\Settings\RESTSettings::class,
		);

		// Add-on-owned settings groups (extension point): Lite no longer
		// names the Transfer / Vendor-Marketplace settings-group classes here.
		// The add-on adds its own group class(es) back via this filter.
		$groups = (array) apply_filters( 'mhmrentiva_settings_groups', $groups );

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
		if (! is_array($settings)) {
			$settings = array();
		}
		return array_key_exists($key, $settings);
	}

	/**
	 * Get value from settings with safe fallback
	 */
	/**
	 * One-time cleanup for installations hit by the v4.27.1-era
	 * Settings Testing "Run All Diagnostics" pollution bug.
	 *
	 * Root cause: {@see \MHMRentiva\Admin\Settings\Testing\SettingsTester::test_settings_save()}
	 * flipped empty strings to '1' to generate a "changed" test payload, then
	 * fed that through the REAL sanitizer (which rewrites the whole target
	 * tab, not just the keys asked about), and finally restored ONLY the
	 * tested keys. The collateral tab writes stayed in the options table, so
	 * a fresh install that clicked "Run All Diagnostics" once ended up with
	 * Brand Name = "1", Cancellation Deadline = 1, Payment Deadline = 1 etc.
	 *
	 * This migration removes entries whose value is literally the '1' / '0'
	 * flip-pollution fingerprint on fields that can never legitimately hold
	 * those values (free-text labels, emails, URLs). Number fields are left
	 * alone because a user may have deliberately saved 1 there. The harness
	 * fix in v4.27.2 prevents the pollution from recurring; this migration
	 * just cleans up the already-affected installs.
	 *
	 * Idempotent via the `mhmrentiva_v4272_test_pollution_cleaned` flag.
	 */
	public static function migrate_clean_test_pollution(): void
	{
		$flag = 'mhmrentiva_v4272_test_pollution_cleaned';
		if (get_option($flag)) {
			return;
		}

		self::strip_pollution_fingerprint();

		update_option($flag, '1', true);
	}

	/**
	 * Second pass of the v4.27.2 cleanup, for installs where the flag above
	 * was already stamped "done" (e.g. the migration ran once on a clean
	 * install before pollution occurred, or the pollution reappeared via the
	 * v4.64.0-era {@see self::ajax_save_dark_mode()} clobber bug) so the
	 * original migration never re-ran to catch the collateral '1' values.
	 *
	 * Reuses the same fingerprint check as {@see self::migrate_clean_test_pollution()}.
	 * Idempotent via its own `mhmrentiva_v4641_test_pollution_recleaned` flag.
	 */
	public static function migrate_reclean_test_pollution(): void
	{
		$flag = 'mhmrentiva_v4641_test_pollution_recleaned';
		if (get_option($flag)) {
			return;
		}

		self::strip_pollution_fingerprint();

		update_option($flag, '1', true);
	}

	/**
	 * Removes the '1' / '0' flip-pollution fingerprint from free-text,
	 * email, URL and currency fields that can never legitimately hold those
	 * values. Number fields are left alone because a user may have
	 * deliberately saved 1 there.
	 */
	private static function strip_pollution_fingerprint(): void
	{
		$settings = get_option(self::OPTION_NAME, null);

		if (! is_array($settings) || empty($settings)) {
			return;
		}

		$polluted_keys = array(
			// Free-text labels that can never legitimately equal '0' or '1'.
			'mhmrentiva_brand_name',
			'mhmrentiva_email_from_name',
			'mhmrentiva_contact_phone',
			'mhmrentiva_contact_hours',
			'mhmrentiva_email_footer_text',
			// Email fields.
			'mhmrentiva_email_from_address',
			'mhmrentiva_support_email',
			'mhmrentiva_email_reply_to',
			// URL fields.
			'mhmrentiva_booking_url',
			'mhmrentiva_login_url',
			'mhmrentiva_register_url',
			'mhmrentiva_vehicles_list_url',
			'mhmrentiva_my_bookings_url',
			'mhmrentiva_brand_logo_url',
			'mhmrentiva_email_header_image',
			// Currency codes — three-letter ISO; '0' / '1' is pollution.
			'mhmrentiva_currency',
		);

		$changed = false;
		foreach ($polluted_keys as $key) {
			if (! array_key_exists($key, $settings)) {
				continue;
			}
			$value = $settings[ $key ];
			if ('0' === $value || '1' === $value || 0 === $value || 1 === $value) {
				unset($settings[ $key ]);
				$changed = true;
			}
		}

		if ($changed) {
			update_option(self::OPTION_NAME, $settings);
			wp_cache_delete(self::OPTION_NAME, 'options');
		}
	}

	public static function get(string $key, mixed $default = null): mixed
	{
		$settings = get_option(self::OPTION_NAME, array());
		if (! is_array($settings)) {
			$settings = array();
		}
		$defaults = self::get_defaults();

		if (array_key_exists($key, $settings)) {
			$value = $settings[ $key ];

			// Handle empty strings or specific numeric fallbacks
			if ('' === $value || ( null === $value )) {
				return $defaults[ $key ] ?? $default;
			}

			return $value;
		}

		// Final safety fallback: If not in DB and not in defaults
		$val = $defaults[ $key ] ?? $default;

		// If still null/empty and looks like a boolean/checkbox field, force '0'
		if (null === $val || '' === $val) {
			$boolean_indicators = array( '_enabled', '_protection', '_active', 'is_', '_enabled_' );
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
		$settings[ $key ] = $value;
		return update_option(self::OPTION_NAME, $settings);
	}

	/**
	 * Delete a specific setting
	 */
	public static function delete(string $key): bool
	{
		$settings = self::get_all();
		if (array_key_exists($key, $settings)) {
			unset($settings[ $key ]);
			return update_option(self::OPTION_NAME, $settings);
		}
		return true;
	}

	/**
	 * Get company website URL
	 */
	public static function get_company_website(): string
	{
		return 'https://wpalemi.com';
	}

	/**
	 * Get support email address
	 */
	public static function get_support_email(): string
	{
		return (string) self::get('mhmrentiva_support_email', 'support@wpalemi.com');
	}

	/**
	 * Read the per-tab registration state for the settings tabs Lite carved
	 * out (transfer, vendor-marketplace, messages).
	 *
	 * Lite's own default is an empty array -- a missing key means "not
	 * registered", not "visible". Only an active extension's SettingsExtensions
	 * subscribes, reporting its own per-tab availability state
	 * (extension point). Shared by SettingsService::reset_defaults()
	 * and SettingsSanitizer::sanitize() so both fail-closed gates read the
	 * exact same source instead of duplicating the filter call.
	 *
	 * @return array<string, bool>
	 */
	public static function settings_tabs(): array
	{
		return (array) apply_filters('mhmrentiva_settings_tabs', array());
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
			'mhmrentiva_endpoint_bookings'        => 'rentiva-bookings',
			'mhmrentiva_endpoint_favorites'       => 'rentiva-favorites',
			'mhmrentiva_endpoint_payment_history' => 'rentiva-payment-history',
			'mhmrentiva_endpoint_edit_account'    => 'rentiva-edit-account',
			'mhmrentiva_endpoint_messages'        => 'rentiva-messages',
			'mhmrentiva_vehicle_base_price'       => 1.0,
			'mhmrentiva_brand_name'               => get_bloginfo('name'),
		);

		// Collect defaults from other modules
		$sub_modules = array(
			\MHMRentiva\Admin\Settings\Groups\GeneralSettings::class,
			\MHMRentiva\Admin\Settings\Groups\BookingSettings::class,
			\MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings::class,
			\MHMRentiva\Admin\Settings\Groups\FrontendSettings::class,
			// CustomerManagementSettings::class was removed here (WP.org T8
			// Görev 10b, row F2): see the identical note above.
			\MHMRentiva\Admin\Settings\Groups\EmailSettings::class,
			\MHMRentiva\Admin\Settings\Groups\MaintenanceSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CoreSettings::class,
			\MHMRentiva\Admin\Settings\Groups\AddonSettings::class,
			\MHMRentiva\Admin\Settings\Groups\CommentsSettingsGroup::class,
			\MHMRentiva\Admin\Settings\Groups\LogsSettings::class,
			\MHMRentiva\Admin\Settings\Groups\PaymentSettings::class,
		);

		// Add-on-owned settings groups (extension point): Lite no longer
		// names the Transfer / Vendor-Marketplace settings-group classes here.
		// The add-on adds its own group class(es) back via this filter (same filter as
		// register_sub_groups() above -- both lists carried the same two
		// classes before the carve).
		$sub_modules = (array) apply_filters( 'mhmrentiva_settings_groups', $sub_modules );

		foreach ($sub_modules as $module) {
			if (class_exists($module) && method_exists($module, 'get_default_settings')) {
				$merged_defaults = array_merge($merged_defaults, $module::get_default_settings());
			}
		}

		return $merged_defaults;
	}

	/**
	 * Enqueue Dark Mode CSS (static file) when auto dark-mode is active
	 */
	public static function enqueue_dark_mode_styles(): void
	{
		// Use central getter to respect settings page saves
		$mode = self::get('mhmrentiva_dark_mode', 'auto');

		if ('auto' !== $mode) {
			return;
		}

		wp_enqueue_style(
			'mhm-rentiva-auto-dark-mode',
			\MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/auto-dark-mode.css',
			array(),
			\MHMRENTIVA_VERSION
		);
	}

	/**
	 * AJAX: Save Dark Mode Preference
	 */
	public static function ajax_save_dark_mode(): void
	{
		if (! check_ajax_referer('mhmrentiva_dark_mode_nonce', 'nonce', false)) {
			wp_send_json_error(__('Invalid nonce', 'mhm-rentiva'), 403);
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Permission denied', 'mhm-rentiva'));
		}

		$raw_mode = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : 'auto';
		$mode     = \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::sanitize_dark_mode_option($raw_mode);

		// 1. Update standalone option (for quick frontend access)
		update_option('mhmrentiva_dark_mode', $mode);

		// 2. Sync with Main Settings Array (so the Settings Form reflects the change).
		// Only touch the dark mode key here — routing this through the full
		// SettingsSanitizer::sanitize() with an input array that only contains
		// 'mhmrentiva_dark_mode' used to re-run the entire General/Site-Info
		// sanitizer, which silently blanked contact_phone/contact_hours/
		// support_email and reset brand_name to get_bloginfo('name') on every
		// dark mode toggle.
		$settings                         = self::get_all();
		$settings['mhmrentiva_dark_mode'] = $mode;
		update_option(self::OPTION_NAME, $settings);

		wp_send_json_success(array( 'message' => __('Settings updated', 'mhm-rentiva') ));
	}

	/**
	 * Handle rewrite rules flushing only if slugs changed
	 */
	public static function handle_rewrite_flushing(mixed $old_value, mixed $new_value): void
	{
		$slug_keys = array(
			'mhmrentiva_vehicle_url_base',
			'mhmrentiva_endpoint_bookings',
			'mhmrentiva_endpoint_favorites',
			'mhmrentiva_endpoint_payment_history',
			'mhmrentiva_endpoint_messages',
		);

		$changed = false;
		foreach ($slug_keys as $key) {
			if (( $old_value[ $key ] ?? '' ) !== ( $new_value[ $key ] ?? '' )) {
				$changed = true;
				break;
			}
		}

		if ($changed) {
			flush_rewrite_rules();
			update_option('mhmrentiva_woocommerce_endpoints_flushed', false);
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

		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_style(
			'mhm-rentiva-settings',
			\MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/settings.css',
			array(),
			\MHMRENTIVA_VERSION . '.toast2'
		);

		wp_enqueue_style(
			'mhm-rentiva-dark-mode',
			\MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/dark-mode.css',
			array(),
			\MHMRENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-rentiva-dark-mode',
			\MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/dark-mode.js',
			array( 'jquery' ),
			\MHMRENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-rentiva-dark-mode',
			'mhmDarkMode',
			array(
				'ajaxUrl'     => admin_url('admin-ajax.php'),
				'nonce'       => wp_create_nonce('mhmrentiva_dark_mode_nonce'),
				'currentMode' => self::get('mhmrentiva_dark_mode', 'auto'),
			)
		);

			// Media (image) settings field picker — replaces the former inline block
			// in SettingsHelper::render_media_field_html().
			wp_enqueue_script(
				'mhm-rentiva-settings-media-field',
				\MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/settings-media-field.js',
				array( 'media-editor' ),
				\MHMRENTIVA_VERSION,
				true
			);

			wp_localize_script(
				'mhm-rentiva-settings-media-field',
				'mhmMediaField',
				array(
					'i18n' => array(
						'selectImage' => __( 'Select image', 'mhm-rentiva' ),
					),
				)
			);
	}
}
