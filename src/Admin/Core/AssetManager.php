<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.







/**
 * Asset Manager - Manages CSS and JS files centrally
 */
final class AssetManager {

	/**
	 * Guard to prevent duplicate core script localization in same request.
	 */
	private static bool $core_scripts_localized = false;
	/**
	 * Guard to prevent duplicate admin global localization in same request.
	 */
	private static bool $admin_globals_localized = false;
	/**
	 * Guard to prevent duplicate REST nonce middleware injection in same request.
	 */
	private static bool $react_nonce_added = false;




	/**
	 * Core CSS files
	 */
	private static array $core_css = array(
		'mhm-rentiva-css-variables'         => array(
			'url'  => 'assets/css/core/css-variables.css',
			// Depends on the LOCALLY BUNDLED webfont (not a CDN) -- see
			// register_vendor_assets(). css-variables.css declares
			// --mhm-font-display as "Plus Jakarta Sans", so the face has to be
			// defined before this stylesheet's consumers render.
			'deps' => array( 'mhm-rentiva-fonts' ),
		),
		'mhm-rentiva-golden-ratio-contract' => array(
			'url'  => 'assets/css/core/golden-ratio-contract.css',
			'deps' => array( 'mhm-rentiva-css-variables' ),
		),
		'mhm-rentiva-core-css'              => array(
			'url'  => 'assets/css/core/core.css',
			'deps' => array( 'mhm-rentiva-css-variables', 'mhm-rentiva-golden-ratio-contract' ),
		),
		'mhm-rentiva-animations'            => array(
			'url'  => 'assets/css/core/animations.css',
			'deps' => array( 'mhm-rentiva-css-variables' ),
		),
		'mhm-rentiva-notifications'         => array(
			'url'  => 'assets/css/frontend/notifications.css',
			'deps' => array( 'mhm-rentiva-css-variables' ),
		),
	);

	/**
	 * Component CSS files
	 */
	private static array $component_css = array(
		'mhm-rentiva-stats-cards' => array(
			'url'  => 'assets/css/components/stats-cards.css',
			'deps' => array( 'mhm-rentiva-core-css' ),
		),
		'mhm-rentiva-calendars'   => array(
			'url'  => 'assets/css/components/calendars.css',
			'deps' => array( 'mhm-rentiva-core-css' ),
		),
	);

	/**
	 * Core JS files
	 */
	private static array $core_js = array(
		'mhm-rentiva-core-js'       => array(
			'url'       => 'assets/js/core/core.js',
			'deps'      => array( 'jquery' ),
			'in_footer' => true,
		),
		'mhm-rentiva-utilities'     => array(
			'url'       => 'assets/js/core/utilities.js',
			'deps'      => array( 'jquery', 'mhm-rentiva-core-js' ),
			'in_footer' => true,
		),
		'mhm-rentiva-i18n'          => array(
			'url'       => 'assets/js/core/i18n.js',
			'deps'      => array( 'jquery', 'mhm-rentiva-core-js' ),
			'in_footer' => true,
		),
		'mhm-rentiva-performance'   => array(
			'url'       => 'assets/js/core/performance.js',
			'deps'      => array( 'jquery', 'mhm-rentiva-utilities' ),
			'in_footer' => true,
		),
		'mhm-rentiva-module-loader' => array(
			'url'       => 'assets/js/core/module-loader.js',
			'deps'      => array( 'jquery', 'mhm-rentiva-core-js' ),
			'in_footer' => true,
		),
	);

	/**
	 * Initialize Asset Manager
	 */
	public static function init(): void
	{
		add_action('wp_enqueue_scripts', array( self::class, 'enqueue_frontend_assets' ));
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_admin_assets' ));
		// Correct way to load assets specifically for the block editor (parent and iframe)
		add_action('enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ));

		// Register core styles FIRST, at an early `init` priority.
		//
		// This must beat every other `init`-time registration of the shared
		// handles (notably BlockRegistry::register_blocks()), because
		// WP_Dependencies::add() refuses to re-add an already-registered
		// handle: the FIRST registration wins and any later src/deps/version
		// are discarded SILENTLY. If something else registered
		// `mhm-css-variables` first with empty deps, the `mhm-rentiva-fonts`
		// dependency below would vanish without warning and the bundled
		// webfont would never load. See register_core_styles().
		add_action('init', array( self::class, 'register_core_styles' ), 1);

		// Register Vendor Assets
		add_action('init', array( self::class, 'register_vendor_assets' ));
		// Register Common Assets (Shared but not Global)
		add_action('init', array( self::class, 'register_common_assets' ));
		// Attached on the *_enqueue_scripts hooks (not wp_head/admin_head):
		// wp_print_styles runs at wp_head priority 8, and admin_print_styles
		// fires before admin_head altogether, so by the time wp_head/admin_head
		// executes the style queue for this request has already been printed.
		// wp_add_inline_style() only has an effect if it runs before that print,
		// which the *_enqueue_scripts hooks guarantee.
		add_action('wp_enqueue_scripts', array( self::class, 'add_inline_styles' ), 20);
		add_action('admin_enqueue_scripts', array( self::class, 'add_inline_styles' ), 20);
	}

	/**
	 * Register the bundled webfont and the core stylesheets.
	 *
	 * This is the SINGLE canonical registration point for the shared core CSS
	 * handles. Nothing else in the plugin may call wp_register_style() for
	 * them -- other call sites must either call this method (it is idempotent
	 * and safe to call repeatedly) or simply enqueue the handle by name.
	 *
	 * Why this is centralised: several call sites enqueue `mhm-css-variables`
	 * by handle alone (BlockRegistry::enqueue_block_assets(),
	 * DatepickerAssets, shortcode deps). They all inherit whatever deps the
	 * first registration declared, so the `mhm-rentiva-fonts` dependency has
	 * to be attached exactly once, as early as possible.
	 *
	 * Runs on `init` priority 1 (see init()) and is additionally called
	 * directly by the other former registration sites, so the correct deps
	 * win regardless of hook ordering.
	 */
	public static function register_core_styles(): void
	{
		// Plus Jakarta Sans -- bundled locally, NOT loaded from Google Fonts.
		//
		// This stylesheet used to be a Google Fonts URL, which meant every
		// front-end page load sent the visitor's IP address to Google: the same
		// privacy defect as the removed geolocation lookups, and WordPress.org
		// does not permit loading assets from external CDNs. (The host name
		// itself is deliberately not spelled out here: a scanner looking for
		// third-party hosts cannot tell a live URL from a comment about a
		// deleted one, and G-B's const-external-host probe flagged this line.)
		// Shipping the woff2 files inside the plugin restores the designed
		// typography with zero third-party requests, which is what
		// readme.txt promises ("Every asset it loads, including its webfont,
		// is served from your own site").
		//
		// Registered as a dependency of `mhm-css-variables` (see $core_css),
		// because that stylesheet declares --mhm-font-display as
		// "Plus Jakarta Sans". The @font-face rules reference the woff2 files
		// by relative URL, so they resolve against this stylesheet's own
		// directory.
		if (! wp_style_is('mhm-rentiva-fonts', 'registered')) {
			wp_register_style(
				'mhm-rentiva-fonts',
				MHM_RENTIVA_PLUGIN_URL . 'assets/vendor/fonts/plus-jakarta-sans.css',
				array(),
				self::get_file_version('assets/vendor/fonts/plus-jakarta-sans.css')
			);
		}

		foreach (self::$core_css as $handle => $asset) {
			if (wp_style_is($handle, 'registered')) {
				continue;
			}

			wp_register_style(
				$handle,
				self::get_asset_url($asset['url']),
				$asset['deps'],
				self::get_file_version($asset['url'])
			);
		}
	}

	/**
	 * Register Third-Party Vendor Assets
	 */
	public static function register_vendor_assets(): void
	{
		// Swiper JS
		if (! wp_script_is('mhm-rentiva-swiper', 'registered')) {
			wp_register_script(
				'mhm-rentiva-swiper',
				MHM_RENTIVA_PLUGIN_URL . 'assets/vendor/swiper-bundle.min.js',
				array(),
				'11.0.0',
				true
			);
		}

		// Swiper CSS
		if (! wp_style_is('mhm-rentiva-swiper-css', 'registered')) {
			wp_register_style(
				'mhm-rentiva-swiper-css',
				MHM_RENTIVA_PLUGIN_URL . 'assets/vendor/swiper-bundle.min.css',
				array(),
				'11.0.0'
			);
		}
		// The bundled Plus Jakarta Sans webfont is registered by
		// register_core_styles(), which owns every shared core handle.
	}

	/**
	 * Register Common Assets (Shared but not Global)
	 * These are registered so they can be added as dependencies, but not enqueued globally.
	 */
	public static function register_common_assets(): void
	{
		// Vehicle Card CSS
		if (! wp_style_is('mhm-rentiva-vehicle-card-css', 'registered')) {
			wp_register_style(
				'mhm-rentiva-vehicle-card-css',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/vehicle-card.css',
				array( 'mhm-rentiva-css-variables', 'mhm-rentiva-core-css' ),
				self::get_file_version('assets/css/core/vehicle-card.css')
			);
		}

		// Datepicker Custom CSS
		if (! wp_style_is('mhm-rentiva-datepicker-custom', 'registered')) {
			wp_register_style(
				'mhm-rentiva-datepicker-custom',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/datepicker-custom.css',
				array( 'mhm-rentiva-css-variables' ),
				self::get_file_version('assets/css/frontend/datepicker-custom.css')
			);
		}

		// mhm-core-js must be registered before mhm-vehicle-interactions (WP 6.9.1 strict dep check).
		if (! wp_script_is('mhm-rentiva-core-js', 'registered')) {
			wp_register_script(
				'mhm-rentiva-core-js',
				self::get_asset_url('assets/js/core/core.js'),
				array( 'jquery' ),
				self::get_file_version('assets/js/core/core.js'),
				true
			);
		}

		// Vehicle Interactions JS (Favorites & Compare)
		if (! wp_script_is('mhm-rentiva-vehicle-interactions', 'registered')) {
			wp_register_script(
				'mhm-rentiva-vehicle-interactions',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/vehicle-interactions.js',
				array( 'jquery', 'mhm-rentiva-core-js', 'mhm-rentiva-toast' ),
				self::get_file_version('assets/js/frontend/vehicle-interactions.js'),
				true
			);
		}

		// Centralized Toast System
		if (! wp_script_is('mhm-rentiva-toast', 'registered')) {
			wp_register_script(
				'mhm-rentiva-toast',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/toast.js',
				array(),
				self::get_file_version('assets/js/frontend/toast.js'),
				true
			);
		}
	}

	/**
	 * Load frontend assets
	 * Note: Frontend shortcode assets are now handled by AbstractShortcode
	 */
	public static function enqueue_frontend_assets(): void
	{
		// Conditional Loading: Only load if necessary
		if (! self::should_load_assets()) {
			return;
		}

		// Load core CSS files
		self::enqueue_core_css();

		// Load core JS files
		self::enqueue_core_js();

		// Only load non-shortcode frontend assets
		self::enqueue_frontend_specific_assets();

		// Enqueue Vehicle Interactions (Favorites & Compare)
		if (wp_script_is('mhm-rentiva-vehicle-interactions', 'registered')) {
			wp_enqueue_script('mhm-rentiva-vehicle-interactions');
			wp_localize_script(
				'mhm-rentiva-vehicle-interactions',
				'mhm_rentiva_vars',
				array(
					'ajax_url'           => admin_url('admin-ajax.php'),
					'nonce'              => wp_create_nonce('mhm_rentiva_toggle_favorite'), // Fallback generic
					'fav_nonce'          => wp_create_nonce('mhm_rentiva_toggle_favorite'),
					'compare_nonce'      => wp_create_nonce('mhm_rentiva_toggle_compare'),
					'compare_page_url'   => \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicle_comparison'),
					'favorites_page_url' => \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_my_favorites'),
					'i18n'               => array(
						'add_favorite'         => __('Add to Favorites', 'mhm-rentiva'),
						'remove_favorite'      => __('Remove from Favorites', 'mhm-rentiva'),
						'adding_favorite'      => __('Adding to favorites...', 'mhm-rentiva'),
						'removing_favorite'    => __('Removing from favorites...', 'mhm-rentiva'),
						'added_favorite'       => __('Added to favorites.', 'mhm-rentiva'),
						'removed_favorite'     => __('Removed from favorites.', 'mhm-rentiva'),
						'add_compare'          => __('Compare', 'mhm-rentiva'),
						'remove_compare'       => __('Remove Compare', 'mhm-rentiva'),
						'adding_compare'       => __('Adding to comparison...', 'mhm-rentiva'),
						'removing_compare'     => __('Removing from comparison...', 'mhm-rentiva'),
						'added_to_compare'     => __('Added to comparison.', 'mhm-rentiva'),
						'removed_from_compare' => __('Removed from comparison.', 'mhm-rentiva'),
						/* translators: %d: maximum number of vehicles allowed in compare list. */
						'max_compare'          => __('You can compare up to %d vehicles', 'mhm-rentiva'),
						'view_comparison'      => __('View comparison', 'mhm-rentiva'),
						'go_to_favorites'      => __('Go to My Favorites', 'mhm-rentiva'),
						'add_one_more'         => __('Add one more vehicle to compare', 'mhm-rentiva'),
						'need_at_least_two'    => __('Comparison needs at least 2 vehicles', 'mhm-rentiva'),
						'compare_page_missing' => __('Comparison page not configured', 'mhm-rentiva'),
					),
				)
			);
		}
	}

	/**
	 * Load block editor assets
	 */
	public static function enqueue_editor_assets(): void
	{
		self::enqueue_core_css();
	}

	/**
	 * Load admin assets
	 */
	public static function enqueue_admin_assets(): void
	{
		// Only load on Rentiva admin pages — no need to pollute other admin screens.
		if ( ! self::is_rentiva_admin_page() ) {
			return;
		}

		// Load core CSS files
		self::enqueue_core_css();

		// JS Kill-switch: allow disabling plugin admin JS for debugging
		$disableJs = isset($_GET['mhm_admin_no_js']) && sanitize_text_field(wp_unslash($_GET['mhm_admin_no_js'])) === '1';
		if ($disableJs) {
			return; // Do not enqueue any JS if kill-switch is enabled
		}

		// Load core JS files
		self::enqueue_core_js();

		// Admin-specific assets
		self::enqueue_admin_specific_assets();
	}

	/**
	 * Detect whether the current admin page belongs to this plugin.
	 */
	private static function is_rentiva_admin_page(): bool
	{
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// Plugin's custom post types that don't carry the 'mhm' prefix in their slug.
		$plugin_post_types = array( 'vehicle', 'vehicle_booking', 'vehicle_addon', 'mhm_vendor_app' );

		return (
			str_contains( $screen->id, 'mhm' ) ||
			str_contains( $screen->post_type ?? '', 'mhm' ) ||
			in_array( $screen->post_type ?? '', $plugin_post_types, true )
		);
	}

	/**
	 * Load core CSS files
	 */
	public static function enqueue_core_css(): void
	{
		// Make sure the handles exist even if `init` never ran (direct calls,
		// unit tests). Idempotent.
		self::register_core_styles();

		// Enqueue BY HANDLE ONLY. Passing $src/$deps here would be a lie: the
		// handles are already registered by this point, and
		// WP_Dependencies::add() silently ignores the arguments for an
		// existing handle -- which is exactly how the `mhm-rentiva-fonts`
		// dependency was previously lost.
		foreach (array_keys(self::$core_css) as $handle) {
			wp_enqueue_style($handle);
		}
	}

	/**
	 * Load core JS files
	 */
	public static function enqueue_core_js(): void
	{
		foreach (self::$core_js as $handle => $asset) {
			wp_enqueue_script(
				$handle,
				self::get_asset_url($asset['url']),
				$asset['deps'],
				self::get_file_version($asset['url']),
				$asset['in_footer'] ?? true
			);
		}

		// Localize JavaScript configuration
		self::localize_scripts();
	}

	/**
	 * Load component CSS file
	 *
	 * @param string $component - Component name
	 */
	public static function enqueue_component_css(string $component): void
	{
		if (isset(self::$component_css[ $component ])) {
			$asset = self::$component_css[ $component ];
			wp_enqueue_style(
				$component,
				self::get_asset_url($asset['url']),
				$asset['deps'],
				self::get_file_version($asset['url'])
			);
		}
	}

	/**
	 * Load component JS file
	 *
	 * @param string $component - Component name
	 */
	public static function enqueue_component_js(string $component): void
	{
		$components = array(
			'addon-booking'      => array(
				'url'  => 'assets/js/components/addon-booking.js',
				'deps' => array( 'jquery' ),
			),
			'vehicle-meta'       => array(
				'url'  => 'assets/js/components/vehicle-meta.js',
				'deps' => array( 'jquery', 'jquery-ui-sortable' ),
			),
			'vehicle-quick-edit' => array(
				'url'  => 'assets/js/components/vehicle-quick-edit.js',
				'deps' => array( 'jquery', 'inline-edit-post' ),
			),
		);

		if (isset($components[ $component ])) {
			$asset = $components[ $component ];
			wp_enqueue_script(
				'mhm-rentiva-' . $component,
				self::get_asset_url($asset['url']),
				$asset['deps'],
				self::get_file_version($asset['url']),
				true
			);

			// Component-specific localization
			self::localize_component_script($component);
		}
	}

	/**
	 * Localize component script
	 */
	private static function localize_component_script(string $component): void
	{
		switch ($component) {
			case 'addon-booking':
				wp_localize_script(
					'mhm-rentiva-addon-booking',
					'mhmAddonBooking',
					array(
						'currency' => CurrencyHelper::get_currency_symbol(),
						'locale'   => \MHMRentiva\Admin\Core\LanguageHelper::get_current_js_locale(),
						'strings'  => array(
							'totalAddons'      => __('Total Add-ons', 'mhm-rentiva'),
							'noAddonsSelected' => __('No add-ons selected', 'mhm-rentiva'),
						),
					)
				);
				break;

			case 'vehicle-meta':
				wp_localize_script(
					'mhm-rentiva-vehicle-meta',
					'mhmVehicleMeta',
					array(
						'ajaxUrl' => admin_url('admin-ajax.php'),
						'nonce'   => wp_create_nonce('mhm_vehicle_meta_nonce'),
						'strings' => array(
							'orderUpdated'           => __('Order updated!', 'mhm-rentiva'),
							'orderSaveError'         => __('Failed to save order', 'mhm-rentiva'),
							'ajaxError'              => __('AJAX error: Failed to save order', 'mhm-rentiva'),
							'enterNewFeature'        => __('Enter new feature name:', 'mhm-rentiva'),
							'enterNewEquipment'      => __('Enter new equipment name:', 'mhm-rentiva'),
							'enterNewDetail'         => __('Enter new detail name:', 'mhm-rentiva'),
							'confirmRemoveFeature'   => __('Are you sure you want to remove this feature?', 'mhm-rentiva'),
							'confirmRemoveEquipment' => __('Are you sure you want to remove this equipment?', 'mhm-rentiva'),
							'enterValue'             => __('Enter value', 'mhm-rentiva'),
							'remove'                 => __('Remove', 'mhm-rentiva'),
							'available'              => __('Available', 'mhm-rentiva'),
							'notAvailable'           => __('Not Available', 'mhm-rentiva'),
							'validFormat'            => __('Valid format', 'mhm-rentiva'),
							'invalidFormat'          => __('Invalid format', 'mhm-rentiva'),
							'depositFormatHelp'      => __('Only percentage values are allowed (e.g. 10)', 'mhm-rentiva'),
							'depositPlaceholder'     => __('10', 'mhm-rentiva'),
							'comingSoonCustomAdd'    => __('Coming soon! Use the Custom Add button for now.', 'mhm-rentiva'),
							'redirectingToSettings'  => __('Redirecting to Vehicle Settings...', 'mhm-rentiva'),
						),
					)
				);
				break;

			case 'vehicle-quick-edit':
				wp_localize_script(
					'mhm-rentiva-vehicle-quick-edit',
					'mhmVehicleQuickEdit',
					array(
						'labels' => array(
							'manual'      => __('Manual', 'mhm-rentiva'),
							'diesel'      => __('Diesel', 'mhm-rentiva'),
							'hybrid'      => __('Hybrid', 'mhm-rentiva'),
							'electric'    => __('Electric', 'mhm-rentiva'),
							'passive'     => __('Passive', 'mhm-rentiva'),
							'maintenance' => __('Maintenance', 'mhm-rentiva'),
							'yes'         => __('Yes', 'mhm-rentiva'),
						),
					)
				);
				break;
		}
	}

	/**
	 * Load stats cards CSS
	 */
	public static function enqueue_stats_cards(): void
	{
		self::enqueue_component_css('mhm-stats-cards');
	}

	/**
	 * Load calendars CSS
	 */
	public static function enqueue_calendars(): void
	{
		self::enqueue_component_css('mhm-calendars');
	}

	/**
	 * Load frontend-specific assets
	 */
	private static function enqueue_frontend_specific_assets(): void
	{
		// Frontend shortcode assets are now handled by AbstractShortcode
		// Only non-shortcode frontend assets should be loaded here
	}

	/**
	 * Load admin-specific assets
	 */
	private static function enqueue_admin_specific_assets(): void
	{
		$screen = get_current_screen();

		if (! $screen) {
			return;
		}

		// Global admin scripts
		self::enqueue_admin_global_scripts();

		// Screen-specific scripts
		self::enqueue_screen_specific_scripts($screen);
	}

	/**
	 * Load global admin scripts
	 */
	private static function enqueue_admin_global_scripts(): void
	{
		if (self::$admin_globals_localized) {
			return;
		}

		// General settings for admin
		wp_localize_script(
			'mhm-rentiva-dark-mode',
			'mhmDarkMode',
			array(
				'ajaxUrl'     => admin_url('admin-ajax.php'),
				'nonce'       => wp_create_nonce('mhm_dark_mode_nonce'),
				'currentMode' => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_dark_mode', 'auto'),
			)
		);
		wp_localize_script(
			'mhm-rentiva-core-js',
			'mhmRentivaAdmin',
			array(
				'ajaxUrl'          => admin_url('admin-ajax.php'),
				'nonce'            => wp_create_nonce('mhm_admin_nonce'),
				'locale'           => get_locale(),
				'currency'         => get_option('mhm_rentiva_currency', 'USD'),
				'currencySymbol'   => CurrencyHelper::get_currency_symbol(),
				'currencyPosition' => CurrencyHelper::get_currency_position(),
				'decimalSep'       => function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : ',',
				'thousandSep'      => function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : '.',
				'numDecimals'      => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
				'dateFormat'       => get_option('date_format', 'Y-m-d'),
				'timeFormat'       => get_option('time_format', 'H:i'),
				'strings'          => array(
					'loading'              => __('Loading...', 'mhm-rentiva'),
					'error'                => __('An error occurred', 'mhm-rentiva'),
					'success'              => __('Operation successful', 'mhm-rentiva'),
					'confirm'              => __('Are you sure?', 'mhm-rentiva'),
					'cancel'               => __('Cancel', 'mhm-rentiva'),
					'save'                 => __('Save', 'mhm-rentiva'),
					'delete'               => __('Delete', 'mhm-rentiva'),
					'processing'           => __('Processing...', 'mhm-rentiva'),
					'confirmRefund'        => __('Are you sure you want to refund this payment?', 'mhm-rentiva'),
					'processingRefund'     => __('Processing refund...', 'mhm-rentiva'),
					'refundError'          => __('Refund error', 'mhm-rentiva'),
					'refundCompleted'      => __('Refund completed (or queued). Please refresh the page.', 'mhm-rentiva'),
					'required_field'       => __('This field is required', 'mhm-rentiva'),
					'invalid_email'        => __('Please enter a valid email address', 'mhm-rentiva'),
					'invalid_phone'        => __('Please enter a valid phone number', 'mhm-rentiva'),
					'value_range'          => __('Value must be between %min and %max', 'mhm-rentiva'),
					'dropoff_after_pickup' => __('Dropoff date must be after pickup date', 'mhm-rentiva'),
					'pickup_not_past'      => __('Pickup date cannot be in the past', 'mhm-rentiva'),
					'dismiss'              => __('Dismiss this notice', 'mhm-rentiva'),
					/* translators: %s: status label. */
					'confirmStatusChange'  => sprintf(__('Are you sure you want to change the booking status to "%s"?', 'mhm-rentiva'), '%s'),
					'changing'             => __('Changing status...', 'mhm-rentiva'),
				),
				'statusLabels'     => array(
					'pending'     => __('Pending', 'mhm-rentiva'),
					'confirmed'   => __('Confirmed', 'mhm-rentiva'),
					'in_progress' => __('In Progress', 'mhm-rentiva'),
					'completed'   => __('Completed', 'mhm-rentiva'),
					'cancelled'   => __('Cancelled', 'mhm-rentiva'),
				),
			)
		);
		self::$admin_globals_localized = true;
	}

	/**
	 * Load screen-specific scripts
	 */
	private static function enqueue_screen_specific_scripts($screen): void
	{
		// Booking Edit Meta
		// Deposit Management
		// Vehicle Gallery & Meta
		if ($screen->id === 'vehicle' || $screen->post_type === 'vehicle') {
			wp_enqueue_media();
			wp_enqueue_script(
				'mhm-rentiva-vehicle-gallery',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-gallery.js',
				array( 'jquery', 'jquery-ui-sortable' ),
				self::get_file_version('assets/js/admin/vehicle-gallery.js'),
				true
			);

			// ⭐ Get max gallery images from settings (default: 50)
			$max_gallery_images = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
				'mhm_rentiva_vehicle_max_gallery_images',
				50
			);

			wp_localize_script(
				'mhm-rentiva-vehicle-gallery',
				'mhmVehicleGallery',
				array(
					'ajaxUrl'   => admin_url('admin-ajax.php'),
					'nonce'     => wp_create_nonce('mhm_gallery_nonce'),
					'maxImages' => $max_gallery_images,
					'strings'   => array(
						/* translators: %d: maximum number of images allowed. */
						'maxImages'     => sprintf(__('Maximum %d images allowed', 'mhm-rentiva'), $max_gallery_images),
						'selectImages'  => __('Select Images', 'mhm-rentiva'),
						'addImages'     => __('Add to Gallery', 'mhm-rentiva'),
						'uploadError'   => __('Error uploading image', 'mhm-rentiva'),
						'confirmRemove' => __('Are you sure you want to remove this image?', 'mhm-rentiva'),
						'removeImage'   => __('Remove Image', 'mhm-rentiva'),
						'addError'      => __('Error adding image', 'mhm-rentiva'),
						'removeError'   => __('Error removing image', 'mhm-rentiva'),
						'reorderError'  => __('Error reordering images', 'mhm-rentiva'),
					),
				)
			);

			// Vehicle Meta Component
			self::enqueue_component_js('vehicle-meta');
		}

		// Vehicle Quick Edit
		if ($screen->id === 'edit-vehicle' || $screen->post_type === 'vehicle') {
			self::enqueue_component_js('vehicle-quick-edit');
		}

		// Messages Settings enqueue used to live here, guarded on a screen id
		// ('mhm-rentiva_page_mhm-rentiva-messages-settings') that no page has
		// registered since the tabbed settings refactor -- the real Messages
		// Settings screen is `page=mhm-rentiva-settings&tab=messages`, which
		// \MHMRentiva\Admin\Settings\View\Tabs\MessagesSettingsRenderer (the add-on)
		// owns and enqueues its own assets for. This block never fired; removed as
		// part of the add-on asset carve-out (WP.org T4 Phase B, Task B-A1).

		// Booking Calendar
		// Bookings List (CPT list) - ensure bulk actions work reliably
		if ($screen->id === 'edit-vehicle_booking') {
			wp_enqueue_script(
				'mhm-rentiva-booking-bulk-actions',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/booking-bulk-actions.js',
				array( 'jquery' ),
				self::get_file_version('assets/js/admin/booking-bulk-actions.js'),
				true
			);
			wp_localize_script(
				'mhm-rentiva-booking-bulk-actions',
				'mhmBookingBulkActions',
				array(
					'strings' => array(
						'no_items_selected'     => __('Please select at least one item to perform this action on.', 'mhm-rentiva'),
						/* translators: %d: number of selected bookings. */
						'confirm_bulk_trash'    => sprintf(__('Move %d selected bookings to Trash?', 'mhm-rentiva'), '%d'),
						/* translators: %d: number of selected bookings. */
						'confirm_bulk_delete'   => sprintf(__('Permanently delete %d selected bookings?', 'mhm-rentiva'), '%d'),
						'confirm_single_trash'  => __('Move this booking to Trash?', 'mhm-rentiva'),
						'confirm_single_delete' => __('Permanently delete this booking?', 'mhm-rentiva'),
						'confirm_empty_trash'   => __('Empty Trash for all bookings?', 'mhm-rentiva'),
					),
				)
			);
		}

		// Settings
		if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-settings') {
			wp_enqueue_script(
				'mhm-rentiva-settings',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/settings.js',
				array( 'jquery' ),
				time(), // Force fresh load with current timestamp
				true
			);

			wp_enqueue_style(
				'mhm-rentiva-notifications',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/notifications.css',
				array(),
				MHM_RENTIVA_VERSION . '.toast2'
			);

			wp_localize_script(
				'mhm-rentiva-settings',
				'mhmRentivaSettings',
				array(
					'ajaxUrl'    => admin_url('admin-ajax.php'),
					'nonce'      => wp_create_nonce('mhm_rentiva_settings'),
					'resetNonce' => wp_create_nonce('mhm_rentiva_reset_defaults'),
					'strings'    => array(
						'categoryEmpty'         => __('Category name cannot be empty', 'mhm-rentiva'),
						'categoryExists'        => __('This category already exists', 'mhm-rentiva'),
						'confirmDeleteCategory' => __('Are you sure you want to delete this category?', 'mhm-rentiva'),
						'statusEmpty'           => __('Status name cannot be empty', 'mhm-rentiva'),
						'statusExists'          => __('This status already exists', 'mhm-rentiva'),
						'confirmDeleteStatus'   => __('Are you sure you want to delete this status?', 'mhm-rentiva'),
						'validAdminEmail'       => __('Enter a valid admin email address', 'mhm-rentiva'),
						'validFromEmail'        => __('Enter a valid sender email address', 'mhm-rentiva'),
						'validEmail'            => __('Enter a valid email address', 'mhm-rentiva'),
						'maxMessagesRange'      => __('Widget max messages must be between 1-20', 'mhm-rentiva'),
						'duplicateCategory'     => __('Duplicate category names are not allowed', 'mhm-rentiva'),
						'duplicateStatus'       => __('Duplicate status names are not allowed', 'mhm-rentiva'),
						'formErrors'            => __('Form errors', 'mhm-rentiva'),
						'saving'                => __('Saving...', 'mhm-rentiva'),
						'delete'                => __('Delete', 'mhm-rentiva'),
						'templatePreview'       => __('Template Preview', 'mhm-rentiva'),
						'templateEmpty'         => __('Template content is empty', 'mhm-rentiva'),
						'templateResetSuccess'  => __('Template reset to default', 'mhm-rentiva'),
						'confirmResetTab'       => __('Are you sure you want to reset this tab\'s settings to default values? This action cannot be undone.', 'mhm-rentiva'),
						'resetting'             => __('Resetting...', 'mhm-rentiva'),
						'resetSuccess'          => __('Settings reset to defaults successfully. Page will reload...', 'mhm-rentiva'),
						'resetFailed'           => __('Failed to reset settings to defaults.', 'mhm-rentiva'),
						'errorOccurred'         => __('An error occurred. Please try again.', 'mhm-rentiva'),
						'adminEmailRequired'    => __('Admin email address is required', 'mhm-rentiva'),
						'fromNameRequired'      => __('Sender name is required', 'mhm-rentiva'),
						'fromEmailRequired'     => __('Sender email address is required', 'mhm-rentiva'),
						'minOneCategory'        => __('At least one category must be defined', 'mhm-rentiva'),
						'minOneStatus'          => __('At least one status must be defined', 'mhm-rentiva'),
						'confirmResetTemplate'  => __('Are you sure you want to reset this template to default?', 'mhm-rentiva'),
						'defaultNewMessage'     => __('New message received: {{subject}}', 'mhm-rentiva'),
						'defaultReply'          => __('Reply to your message: {{subject}}', 'mhm-rentiva'),
						'defaultStatusChange'   => __('Message status changed: {{subject}}', 'mhm-rentiva'),
						'defaultAutoReply'      => __('Your message received: {{subject}}', 'mhm-rentiva'),
					),
				)
			);

			// Enqueue Email Templates JS for Preview Tab
			if (isset($_GET['tab']) && sanitize_text_field(wp_unslash($_GET['tab'])) === 'email_preview') {
				wp_enqueue_script(
					'mhm-rentiva-email-templates',
					MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/email-templates.js',
					array( 'jquery' ),
					self::get_file_version('assets/js/admin/email-templates.js'),
					true
				);

				wp_localize_script(
					'mhm-rentiva-email-templates',
					'mhm_rentiva_email_templates_vars',
					array(
						'ajax_url'          => admin_url('admin-ajax.php'),
						'nonce'             => wp_create_nonce('mhm_email_templates_nonce'),
						'preview_email'     => __('Email Preview', 'mhm-rentiva'),
						'send_test'         => __('Send Test Email', 'mhm-rentiva'),
						'processing'        => __('Processing...', 'mhm-rentiva'),
						'test_email_sent'   => __('Test email sent successfully', 'mhm-rentiva'),
						'test_email_failed' => __('Failed to send test email', 'mhm-rentiva'),
						'error_occurred'    => __('An error occurred', 'mhm-rentiva'),
						'strings'           => array(
							'sendTestEmail' => __('Send Test Email', 'mhm-rentiva'),
							'emailAddress'  => __('Email Address', 'mhm-rentiva'),
							'cancel'        => __('Cancel', 'mhm-rentiva'),
							'enterEmail'    => __('Please enter email address', 'mhm-rentiva'),
							'editTemplate'  => __('Edit Template', 'mhm-rentiva'),
							'subject'       => __('Subject', 'mhm-rentiva'),
							'content'       => __('Content', 'mhm-rentiva'),
							'save'          => __('Save', 'mhm-rentiva'),
							'templateSaved' => __('Template saved successfully!', 'mhm-rentiva'),
							'templateReset' => __('Template reset to default!', 'mhm-rentiva'),
						),
					)
				);
			}

			// Enqueue the Integration tab's script: the endpoint reference list and
			// the settings reset. It used to drive an API-key manager too; that
			// surface was removed because no endpoint ever validated the keys it
			// issued.
			if (isset($_GET['tab']) && sanitize_text_field(wp_unslash($_GET['tab'])) === 'integration') {
				wp_enqueue_script(
					'mhm-rentiva-rest-integration',
					MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/rest-integration.js',
					array( 'jquery' ),
					self::get_file_version('assets/js/admin/rest-integration.js'),
					true
				);

				wp_localize_script(
					'mhm-rentiva-rest-integration',
					'mhmRestIntegration',
					array(
						'ajax_url' => admin_url('admin-ajax.php'),
						'nonce'    => wp_create_nonce('mhm_rest_api_keys_nonce'),
						'strings'  => array(
							'error_occurred' => __('An error occurred. Please try again.', 'mhm-rentiva'),
							'confirm_reset'  => __('Are you sure you want to reset all REST API settings to default values? This action cannot be undone.', 'mhm-rentiva'),
							'resetting'      => __('Resetting...', 'mhm-rentiva'),
							'reset_success'  => __('Settings reset to defaults successfully. Page will reload...', 'mhm-rentiva'),
							'reset_failed'   => __('Failed to reset settings to defaults.', 'mhm-rentiva'),
						),
					)
				);
			}
		}

		// Vehicle Settings
		if ($screen->id === 'mhm-rentiva_page_vehicle-settings') {
			// Dedicated CSS for Vehicle Settings
			wp_enqueue_style(
				'mhm-rentiva-vehicle-settings-css',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/vehicle-settings.css',
				array( 'mhm-rentiva-core-css' ),
				self::get_file_version('assets/css/admin/vehicle-settings.css')
			);

			$vs_is_v2 = \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::is_v2_ui();

			if ( $vs_is_v2 ) {
				// Redesigned UI is self-contained: it does NOT load the legacy scripts, so it can
				// own the whole page (including the Reset button) without double-bound handlers.
				wp_enqueue_style(
					'mhm-rentiva-vehicle-settings-v2',
					MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/vehicle-settings-v2.css',
					array( 'mhm-rentiva-vehicle-settings-css' ),
					self::get_file_version('assets/css/admin/vehicle-settings-v2.css')
				);

				wp_enqueue_script(
					'mhm-rentiva-vehicle-settings-v2',
					MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-settings-v2.js',
					array( 'jquery', 'jquery-ui-sortable' ),
					self::get_file_version('assets/js/admin/vehicle-settings-v2.js'),
					true
				);
			} else {
				wp_enqueue_script(
					'mhm-rentiva-vehicle-card-fields',
					MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-card-fields.js',
					array( 'jquery', 'jquery-ui-sortable' ),
					self::get_file_version('assets/js/admin/vehicle-card-fields.js'),
					true
				);

				// Settings-page behavior (reset, display tab, definitions tab + rename modal).
				// Replaces the three inline <script> blocks in VehicleSettings render methods.
				wp_enqueue_script(
					'mhm-rentiva-vehicle-settings',
					MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-settings.js',
					array( 'jquery', 'jquery-ui-sortable' ),
					self::get_file_version('assets/js/admin/vehicle-settings.js'),
					true
				);
			}

			$vs_active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'definitions';
			wp_localize_script(
				$vs_is_v2 ? 'mhm-rentiva-vehicle-settings-v2' : 'mhm-rentiva-vehicle-settings',
				'mhmVehicleSettings',
				array(
					'nonce'     => wp_create_nonce('vehicle_settings_nonce'),
					'activeTab' => $vs_active_tab,
					'state'     => \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::build_settings_state(),
					'i18n'      => array(
						'confirmResetAll'        => __('Are you sure you want to reset all vehicle settings to defaults? Custom field definitions will NOT be deleted.', 'mhm-rentiva'),
						'saved'                  => __('Settings saved successfully!', 'mhm-rentiva'),
						'errorSaving'            => __('Error saving settings.', 'mhm-rentiva'),
						'select'                 => __('Select', 'mhm-rentiva'),
						'number'                 => __('Number', 'mhm-rentiva'),
						'errPrefix'              => __('Error:', 'mhm-rentiva'),
						'genericError'           => __('An error occurred!', 'mhm-rentiva'),
						'remove'                 => __('Remove', 'mhm-rentiva'),
						'detailAdded'            => __('Custom detail added successfully!', 'mhm-rentiva'),
						'featureAdded'           => __('Custom feature added successfully!', 'mhm-rentiva'),
						'equipmentAdded'         => __('Custom equipment added successfully!', 'mhm-rentiva'),
						'confirmRemoveDetail'    => __('Are you sure you want to remove this custom detail?', 'mhm-rentiva'),
						'detailRemoved'          => __('Custom detail removed successfully!', 'mhm-rentiva'),
						'confirmRemoveFeature'   => __('Are you sure you want to remove this custom feature?', 'mhm-rentiva'),
						'featureRemoved'         => __('Custom feature removed successfully!', 'mhm-rentiva'),
						'confirmRemoveEquipment' => __('Are you sure you want to remove this custom equipment?', 'mhm-rentiva'),
						'equipmentRemoved'       => __('Custom equipment removed successfully!', 'mhm-rentiva'),
						'editFieldNames'         => __('Edit Field Names', 'mhm-rentiva'),
						'cancel'                 => __('Cancel', 'mhm-rentiva'),
						'save'                   => __('Save', 'mhm-rentiva'),
						'fieldNamesSaved'        => __('Field names updated and saved!', 'mhm-rentiva'),
						'fieldNamesError'        => __('Error: Field names could not be saved!', 'mhm-rentiva'),

						// --- Redesigned (v2) UI strings (assets/js/admin/vehicle-settings-v2.js) ---
						'title'                  => __('Vehicle Settings', 'mhm-rentiva'),
						'subtitle'               => __('Define fields and manage where they appear', 'mhm-rentiva'),
						'tabFields'              => __('1 · Field Definitions', 'mhm-rentiva'),
						'tabDisplay'             => __('2 · Display & Preview', 'mhm-rentiva'),
						'fieldsHint'             => __('Choose which fields are collected. Passive fields do not appear on vehicle forms or in the preview. You can also add your own custom field.', 'mhm-rentiva'),
						'titleDetail'            => __('Vehicle Details', 'mhm-rentiva'),
						'titleFeature'           => __('Vehicle Features', 'mhm-rentiva'),
						'titleEquipment'         => __('Vehicle Equipment', 'mhm-rentiva'),
						'active'                 => __('Active', 'mhm-rentiva'),
						'passive'                => __('Passive', 'mhm-rentiva'),
						'activeLower'            => __('active', 'mhm-rentiva'),
						'coreLocked'             => __('Core fields cannot be disabled', 'mhm-rentiva'),
						'badgeRequired'          => __('REQUIRED', 'mhm-rentiva'),
						'badgeCustom'            => __('CUSTOM', 'mhm-rentiva'),
						/* translators: %s: field name */
						'removeConfirm'          => __('The field "%s" will be permanently deleted. Are you sure?', 'mhm-rentiva'),
						'removed'                => __('Field deleted.', 'mhm-rentiva'),
						'removeFailed'           => __('Could not delete the field.', 'mhm-rentiva'),
						'selectAll'              => __('Select All', 'mhm-rentiva'),
						'selectNone'             => __('Deselect All', 'mhm-rentiva'),
						'editNames'              => __('Edit Names', 'mhm-rentiva'),
						'addCustom'              => __('Add custom field:', 'mhm-rentiva'),
						'fieldNamePlaceholder'   => __('Field name (e.g. Boot Size)', 'mhm-rentiva'),
						'groupDetail'            => __('Detail', 'mhm-rentiva'),
						'groupFeature'           => __('Feature', 'mhm-rentiva'),
						'groupEquipment'         => __('Equipment', 'mhm-rentiva'),
						'typeText'               => __('Text', 'mhm-rentiva'),
						'typeNumber'             => __('Number', 'mhm-rentiva'),
						'typeSelect'             => __('Select', 'mhm-rentiva'),
						'optionsPlaceholder'     => __('Options (comma separated: S, M, L)', 'mhm-rentiva'),
						'add'                    => __('Add', 'mhm-rentiva'),
						'nameRequired'           => __('Please enter a field name.', 'mhm-rentiva'),
						'addFailed'              => __('Could not add the field.', 'mhm-rentiva'),
						'addedOk'                => __('Field added. Press Save to persist the selection state.', 'mhm-rentiva'),
						'genericFail'            => __('Operation failed. Your session may have expired — reload the page.', 'mhm-rentiva'),
						'saving'                 => __('Saving…', 'mhm-rentiva'),
						'dirtyTitle'             => __('You have unsaved changes', 'mhm-rentiva'),
						'savedOk'                => __('Settings saved.', 'mhm-rentiva'),
						'saveErr'                => __('Could not save. Your session may have expired — reload the page and try again.', 'mhm-rentiva'),
						'netErr'                 => __('Could not save. Check your connection and try again.', 'mhm-rentiva'),
						'template'               => __('Template:', 'mhm-rentiva'),
						'presetMinimal'          => __('Minimal', 'mhm-rentiva'),
						'presetStandard'         => __('Standard', 'mhm-rentiva'),
						'presetDetailed'         => __('Detailed', 'mhm-rentiva'),
						'filterAll'              => __('All', 'mhm-rentiva'),
						'filterDetail'           => __('Details', 'mhm-rentiva'),
						'filterFeature'          => __('Features', 'mhm-rentiva'),
						'filterEquipment'        => __('Equipment', 'mhm-rentiva'),
						'colField'               => __('Field', 'mhm-rentiva'),
						'colCard'                => __('Card', 'mhm-rentiva'),
						'colDetail'              => __('Detail', 'mhm-rentiva'),
						'colCompare'             => __('Comp.', 'mhm-rentiva'),
						'toggleOn'               => __('On', 'mhm-rentiva'),
						'toggleOff'              => __('Off', 'mhm-rentiva'),
						'emptyCategory'          => __('No active fields in this category.', 'mhm-rentiva'),
						'dragHint'               => __('Switch to "All" to reorder', 'mhm-rentiva'),
						'gripTitle'              => __('Drag to reorder', 'mhm-rentiva'),
						'livePreview'            => __('Live preview', 'mhm-rentiva'),
						'previewImage'           => __('vehicle image', 'mhm-rentiva'),
						'previewName'            => __('Toyota Corolla Hybrid', 'mhm-rentiva'),
						'previewPrice'           => __('$1,850 / day', 'mhm-rentiva'),
						'previewLink'            => __('View →', 'mhm-rentiva'),
						'detailHighlights'       => __('Detail — Highlights', 'mhm-rentiva'),
						'noCard'                 => __('No fields selected for the card', 'mhm-rentiva'),
						'noDetail'               => __('No fields highlighted in the detail view', 'mhm-rentiva'),
						'countCard'              => __('Card', 'mhm-rentiva'),
						'countDetail'            => __('Detail', 'mhm-rentiva'),
						'countCompare'           => __('Comparison', 'mhm-rentiva'),
						'renameSuffix'           => __(' — Edit Names', 'mhm-rentiva'),
						'renameSaved'            => __('Names updated.', 'mhm-rentiva'),
						'renameFailed'           => __('Could not update names.', 'mhm-rentiva'),
						'resetConfirm'           => __('Reset this tab to defaults?', 'mhm-rentiva'),
						'resetFailed'            => __('Could not reset.', 'mhm-rentiva'),
					),
				)
			);
		}

		// Email Templates
		if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-email-templates') {
			wp_enqueue_script(
				'mhm-rentiva-email-templates',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/email-templates.js',
				array( 'jquery' ),
				self::get_file_version('assets/js/admin/email-templates.js'),
				true
			);

			wp_localize_script(
				'mhm-rentiva-email-templates',
				'mhm_rentiva_email_templates_vars',
				array(
					'ajax_url'          => admin_url('admin-ajax.php'),
					'nonce'             => wp_create_nonce('mhm_email_templates_nonce'),
					'preview_email'     => __('Email Preview', 'mhm-rentiva'),
					'send_test'         => __('Send Test Email', 'mhm-rentiva'),
					'processing'        => __('Processing...', 'mhm-rentiva'),
					'test_email_sent'   => __('Test email sent successfully', 'mhm-rentiva'),
					'test_email_failed' => __('Failed to send test email', 'mhm-rentiva'),
					'error_occurred'    => __('An error occurred', 'mhm-rentiva'),
					'strings'           => array(
						'sendTestEmail' => __('Send Test Email', 'mhm-rentiva'),
						'emailAddress'  => __('Email Address', 'mhm-rentiva'),
						'cancel'        => __('Cancel', 'mhm-rentiva'),
						'enterEmail'    => __('Please enter email address', 'mhm-rentiva'),
						'editTemplate'  => __('Edit Template', 'mhm-rentiva'),
						'subject'       => __('Subject', 'mhm-rentiva'),
						'content'       => __('Content', 'mhm-rentiva'),
						'save'          => __('Save', 'mhm-rentiva'),
						'templateSaved' => __('Template saved successfully!', 'mhm-rentiva'),
						'templateReset' => __('Template reset to default!', 'mhm-rentiva'),
					),
				)
			);
		}

		// Message List enqueue used to live here, guarded on
		// `$screen->post_type === 'message'` -- but the `message` CPT the add-on
		// actually registers is `mhm_message` (see \MHMRentiva\Admin\PostTypes\Message\Message
		// ::POST_TYPE in the add-on), so this guard never matched and the block
		// never fired. Removed as part of the add-on asset carve-out (WP.org T4
		// Phase B, Task B-A1) along with the file it enqueued.

		// Export Page enqueue (legacy 'mhm-export' handle/CSS/JS) used to live
		// here. The Export admin screen belongs to the add-on; the add-on's own
		// \MHMRentiva\Admin\Utilities\Export\Export::enqueue_scripts() now enqueues
		// this handle itself from its own plugin directory, alongside the React
		// bundle it already served (WP.org T4 Phase B, Task B-A1).
	}





	/**
	 * Localize scripts
	 */
	private static function localize_scripts(): void
	{
		if (self::$core_scripts_localized) {
			return;
		}

		// Configuration for Core JS
		wp_localize_script(
			'mhm-rentiva-core-js',
			'mhm_rentiva_config',
			array(
				'ajax_url'    => admin_url('admin-ajax.php'),
				'nonce'       => wp_create_nonce('mhm_ajax_nonce'),
				'baseUrl'     => MHM_RENTIVA_PLUGIN_URL, // Fixed as camelCase
				'base_url'    => MHM_RENTIVA_PLUGIN_URL, // backward compatibility
				'locale'      => get_locale(),
				'currency'    => get_option('mhm_rentiva_currency', 'USD'),
				'date_format' => get_option('date_format', 'd/m/Y'),
				'time_format' => get_option('time_format', 'H:i'),
				'debug'       => defined('WP_DEBUG') && WP_DEBUG,
			)
		);

		// Localize translations for i18n
		wp_localize_script(
			'mhm-rentiva-i18n',
			'mhm_rentiva_i18n_translations',
			array(
				'mhm-rentiva' => self::get_translations(),
			)
		);
		self::$core_scripts_localized = true;
	}

	/**
	 * Get translations
	 *
	 * @return array
	 */
	private static function get_translations(): array
	{
		return array(
			'Loading...'                       => __('Loading...', 'mhm-rentiva'),
			'Error'                            => __('Error', 'mhm-rentiva'),
			'Success'                          => __('Success', 'mhm-rentiva'),
			'Warning'                          => __('Warning', 'mhm-rentiva'),
			'Info'                             => __('Info', 'mhm-rentiva'),
			'Yes'                              => __('Yes', 'mhm-rentiva'),
			'No'                               => __('No', 'mhm-rentiva'),
			'Cancel'                           => __('Cancel', 'mhm-rentiva'),
			'Confirm'                          => __('Confirm', 'mhm-rentiva'),
			'Save'                             => __('Save', 'mhm-rentiva'),
			'Delete'                           => __('Delete', 'mhm-rentiva'),
			'Edit'                             => __('Edit', 'mhm-rentiva'),
			'Add'                              => __('Add', 'mhm-rentiva'),
			'Update'                           => __('Update', 'mhm-rentiva'),
			'Search'                           => __('Search', 'mhm-rentiva'),
			'Filter'                           => __('Filter', 'mhm-rentiva'),
			'Reset'                            => __('Reset', 'mhm-rentiva'),
			'Close'                            => __('Close', 'mhm-rentiva'),
			'Back'                             => __('Back', 'mhm-rentiva'),
			'Next'                             => __('Next', 'mhm-rentiva'),
			'Previous'                         => __('Previous', 'mhm-rentiva'),
			'Submit'                           => __('Submit', 'mhm-rentiva'),
			'Clear'                            => __('Clear', 'mhm-rentiva'),
			'Select All'                       => __('Select All', 'mhm-rentiva'),
			'Select None'                      => __('Select None', 'mhm-rentiva'),
			'No data found'                    => __('No data found', 'mhm-rentiva'),
			'No results found'                 => __('No results found', 'mhm-rentiva'),
			'Please wait...'                   => __('Please wait...', 'mhm-rentiva'),
			'Processing...'                    => __('Processing...', 'mhm-rentiva'),
			'An error occurred'                => __('An error occurred', 'mhm-rentiva'),
			'Please try again'                 => __('Please try again', 'mhm-rentiva'),
			'Operation completed successfully' => __('Operation completed successfully', 'mhm-rentiva'),
			'Operation failed'                 => __('Operation failed', 'mhm-rentiva'),
			'Are you sure?'                    => __('Are you sure?', 'mhm-rentiva'),
			'This action cannot be undone'     => __('This action cannot be undone', 'mhm-rentiva'),
			'Invalid input'                    => __('Invalid input', 'mhm-rentiva'),
			'Required field'                   => __('Required field', 'mhm-rentiva'),
			'Please fill all required fields'  => __('Please fill all required fields', 'mhm-rentiva'),
		);
	}

	/**
	 * Add inline styles
	 */
	public static function add_inline_styles(): void
	{
		// Add CSS variables inline. get_css_variables() has already validated
		// every interpolated value against the grammar of its declaration, so
		// the block is safe by construction -- no post-hoc filtering here.
		$css_variables = self::get_css_variables();
		if ($css_variables) {
			wp_add_inline_style( 'mhm-rentiva-css-variables', $css_variables );
		}
	}

	/**
	 * Get CSS variables
	 *
	 * @return string
	 */
	private static function get_css_variables(): string
	{
		$primary_color   = self::sanitize_css_declaration_value( (string) get_option('mhm_rentiva_primary_color', '#2271b1') ) ?: '#2271b1';
		$secondary_color = self::sanitize_css_declaration_value( (string) get_option('mhm_rentiva_secondary_color', '#00a32a') ) ?: '#00a32a';

		return "
        :root {
            --mhm-primary: {$primary_color};
            --mhm-secondary: {$secondary_color};
        }
        ";
	}

	/**
	 * Validate one CSS declaration value against the grammar it lands in.
	 *
	 * A value interpolated into a stylesheet cannot be made safe by escaping:
	 * WordPress has no CSS-context escaper, and the HTML escapers leave `;`
	 * and `}` untouched -- so `#fff; } body { background: url(//evil) ` walks
	 * straight through esc_attr() or wp_strip_all_tags() and closes the rule
	 * it was supposed to live inside. Validation is the only correct
	 * instrument, so anything that does not match a known value type is
	 * DROPPED and the caller falls back to its default.
	 *
	 * @param string $value Raw value, typically read from an option.
	 * @return string The value if it is of a known type, otherwise ''.
	 */
	private static function sanitize_css_declaration_value( string $value ): string
	{
		$value = trim( $value );

		// #rgb / #rrggbb.
		if ( '' !== ( sanitize_hex_color( $value ) ?? '' ) ) {
			return $value;
		}

		// rgb()/rgba() with integer channels and an optional 0-1 alpha.
		if ( preg_match( '/^rgba?\(\s*\d{1,3}(\s*,\s*\d{1,3}){2}(\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $value ) ) {
			return $value;
		}

		// Number with an optional length/percentage unit.
		if ( preg_match( '/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)?$/', $value ) ) {
			return $value;
		}

		// Bare keyword (sans-serif, bold, inherit...).
		if ( preg_match( '/^[a-zA-Z-]{1,32}$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Check if asset is loaded
	 *
	 * @param string $handle - Asset handle
	 * @return bool
	 */
	public static function is_asset_loaded(string $handle): bool
	{
		return wp_style_is($handle, 'enqueued') || wp_script_is($handle, 'enqueued');
	}

	/**
	 * Remove asset
	 *
	 * @param string $handle - Asset handle
	 */
	public static function dequeue_asset(string $handle): void
	{
		wp_dequeue_style($handle);
		wp_dequeue_script($handle);
	}

	/**
	 * Remove all core assets
	 */
	public static function dequeue_all_core_assets(): void
	{
		foreach (array_keys(self::$core_css) as $handle) {
			wp_dequeue_style($handle);
		}

		foreach (array_keys(self::$core_js) as $handle) {
			wp_dequeue_script($handle);
		}
	}

	/**
	 * Get file version based on file modification time
	 * This ensures browser cache is invalidated only when files actually change
	 *
	 * @param string $file_path Relative path from plugin root (e.g., 'assets/css/core/core.css')
	 * @return string|int File modification timestamp or plugin version as fallback
	 */
	public static function get_file_version(string $file_path): string
	{
		$full_path = MHM_RENTIVA_PLUGIN_DIR . $file_path;

		// If file exists, use file modification time for cache busting
		if (file_exists($full_path)) {
			$filemtime = filemtime($full_path);
			if ($filemtime !== false) {
				return (string) $filemtime;
			}
		}

		// Fallback to plugin version if file doesn't exist or filemtime fails
		return MHM_RENTIVA_VERSION;
	}

	/**
	 * Check if assets should be loaded on current page
	 * Returns true if any MHM Rentiva shortcode or content is present
	 */
	private static function should_load_assets(): bool
	{

		// Always load if filtering enabled via hook
		if (apply_filters('mhm_rentiva_force_load_assets', false)) {
			return true;
		}

		// Load assets on WooCommerce account pages (v1.3.3)
		if (function_exists('is_account_page') && is_account_page()) {
			return true;
		}

		global $post;
		if (! is_a($post, 'WP_Post')) {
			return false;
		}

		$content = (string) $post->post_content;
		return strpos($content, '[rentiva_') !== false;
	}

	/**
	 * Enqueue a React admin page bundle with its wp-api-fetch nonce middleware.
	 *
	 * @param string      $page_handle Basename of the bundle under build/admin/ (e.g. 'dashboard').
	 * @param array       $extra_deps  Optional array of additional script dependencies.
	 * @param string|null $base_dir    Absolute plugin dir the bundle lives under (trailing slash).
	 *                                 Defaults to Lite's own MHM_RENTIVA_PLUGIN_DIR. The add-on's admin
	 *                                 pages pass MHM_RENTIVA_PRO_PATH here for the 5 bundles that
	 *                                 moved to the add-on's build/admin/ (Task A11a, WP.org T4 seam
	 *                                 inversion) -- this Lite class stays the single call site for
	 *                                 the shared wp-api-fetch nonce middleware + wp_enqueue_script
	 *                                 boilerplate, it just no longer assumes the bundle is Lite's own.
	 * @param string|null $base_url    Absolute plugin URL counterpart to $base_dir. Defaults to
	 *                                 MHM_RENTIVA_PLUGIN_URL.
	 * @param string|null $text_domain Text domain the bundle's __() calls use, for
	 *                                 wp_set_script_translations(). Defaults to Lite's 'mhm-rentiva'.
	 *                                 This was hardcoded until 2026-07-28, which meant the add-on's 5
	 *                                 relocated bundles asked WordPress for 'mhm-rentiva' JSON inside
	 *                                 the add-on's own languages/ directory -- a lookup that can never
	 *                                 succeed, because the add-on compiles its catalogues under
	 *                                 'mhm-rentiva-pro'. $base_dir was already a parameter; the domain
	 *                                 has to travel with it or the pair is inconsistent.
	 */
	public static function enqueue_react_page( string $page_handle, array $extra_deps = [], ?string $base_dir = null, ?string $base_url = null, ?string $text_domain = null ): void
	{
		if ( ! self::$react_nonce_added ) {
			wp_add_inline_script(
				'wp-api-fetch',
				sprintf(
					'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( "%s" ) );',
					esc_js( wp_create_nonce( 'wp_rest' ) )
				),
				'after'
			);
			self::$react_nonce_added = true;
		}

		wp_enqueue_style( 'wp-components' );

		$base_dir = $base_dir ?? MHM_RENTIVA_PLUGIN_DIR;
		$base_url = $base_url ?? MHM_RENTIVA_PLUGIN_URL;

		$asset_file = $base_dir . "build/admin/{$page_handle}.asset.php";
		$asset      = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array(),
				'version'      => MHM_RENTIVA_VERSION,
			);

		wp_enqueue_script(
			"mhm-rentiva-react-{$page_handle}",
			$base_url . "build/admin/{$page_handle}.js",
			array_merge( $asset['dependencies'], $extra_deps ),
			$asset['version'],
			true
		);

		wp_set_script_translations(
			"mhm-rentiva-react-{$page_handle}",
			$text_domain ?? 'mhm-rentiva',
			$base_dir . 'languages/'
		);
	}

	/**
	 * Get minified asset URL if enabled and exists
	 */
	public static function get_asset_url(string $path): string
	{
		$use_min = ! ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG );

		if ($use_min) {
			$path_parts = pathinfo($path);
			$extension  = $path_parts['extension'] ?? '';
			$dirname    = $path_parts['dirname'] ?? '';
			$filename   = $path_parts['filename'] ?? '';

			if ($extension && $dirname && $filename) {
				$min_path = $dirname . '/' . $filename . '.min.' . $extension;
				if (file_exists(MHM_RENTIVA_PLUGIN_DIR . $min_path)) {
					return MHM_RENTIVA_PLUGIN_URL . $min_path;
				}
			}
		}

		return MHM_RENTIVA_PLUGIN_URL . $path;
	}
}
