<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Unified search intentionally composes bounded vehicle/transfer filters and lookup queries.



use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\Assets\DatepickerAssets;



/**
 * Unified Search Shortcode
 *
 * Merges Vehicle and Transfer search into a single tabbed widget.
 *
 * @since 4.0.0
 */
final class UnifiedSearch extends AbstractShortcode
{

	/**
	 * @return string Shortcode tag.
	 */
	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_unified_search';
	}

	/**
	 * @return string Path to template.
	 */
	protected static function get_template_path(): string
	{
		return 'shortcodes/unified-search';
	}

	/**
	 * Define default attributes.
	 */
	protected static function get_default_attributes(): array
	{
		return array(
			// Tab controls
			'default_tab'           => 'default', // 'rental', 'transfer', or 'default'
			'default_tab_alias'     => 'defaultTab',

			// Visibility controls (boolean as string for shortcode compatibility)
			'show_rental_tab'       => 'default',
			'show_transfer_tab'     => 'default',
			'show_location_select'  => 'default',
			'show_time_select'      => 'default',
			'show_date_picker'      => 'default',
			'show_dropoff_location' => 'default',
			'location_required'     => 'default', // Whether pickup_location select is required
			'fields_required'       => 'default', // Whether date fields are required (false = browse all vehicles)
			'show_pax'              => 'default', // Adults/Children
			'show_luggage'          => 'default', // Luggage inputs

			// Query filters
			'service_type'          => 'both', // 'rental', 'transfer', 'both'
			'filter_categories'     => '',
			'redirect_page'         => 'default',

			// Layout & Styling
			'layout'                => 'horizontal', // 'horizontal', 'vertical', 'compact'
			'search_layout'         => '',           // Block editor uses this
			'style'                 => 'glass',      // 'glass', 'solid'
			'class'                 => '',
		);
	}

	/**
	 * Prepare data for the template.
	 */
	protected static function prepare_template_data(array $atts): array
	{
		// Initial service type depends on default_tab
		$initial_service_type = $atts['default_tab'] === 'transfer' ? 'transfer' : 'rental';

		// Fetch locations based on initial service type
		$locations = \MHMRentiva\Admin\Transfer\Engine\LocationProvider::get_locations($initial_service_type);

		// Normalize boolean attributes (accept '1', 'true', true, 1)
		$bool = fn($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN);

		// Resolve initial visibility
		$show_rental   = self::resolve_bool($atts['show_rental_tab'], 'mhm_rentiva_show_rental_tab', true);
		$show_transfer = self::resolve_bool($atts['show_transfer_tab'], 'mhm_rentiva_show_transfer_tab', true);

		// Override based on Service Mode (Master Switch)
		if ($atts['service_type'] === 'rental') {
			$show_transfer = false;
			$show_rental   = true;
		} elseif ($atts['service_type'] === 'transfer') {
			$show_rental   = false;
			$show_transfer = true;
		}

		// Resolve layout: Check search_layout first (Block), then layout (Shortcode)
		$layout = ! empty($atts['search_layout']) ? $atts['search_layout'] : $atts['layout'];

		return array(
			'locations'             => $locations,
			'default_tab'           => self::resolve_default($atts['default_tab'], 'mhm_rentiva_default_search_tab', 'rental'),
			'wrapper_id'            => uniqid('rv_unified_'),
			'nonce'                 => wp_create_nonce('mhm_rentiva_unified_search'),

			// Visibility controls
			'show_rental_tab'       => $show_rental,
			'show_transfer_tab'     => $show_transfer,
			'show_location_select'  => self::resolve_bool($atts['show_location_select'], 'mhm_rentiva_enable_location_select', true),
			'show_time_select'      => self::resolve_bool($atts['show_time_select'], 'mhm_rentiva_enable_time_select', true),
			'show_date_picker'      => self::resolve_bool($atts['show_date_picker'], 'mhm_rentiva_enable_date_picker', true),
			'show_dropoff_location' => self::resolve_bool($atts['show_dropoff_location'], 'mhm_rentiva_enable_dropoff', true),
			'location_required'     => self::resolve_bool($atts['location_required'], 'mhm_rentiva_location_required', true),
			'fields_required'       => self::resolve_bool($atts['fields_required'], 'mhm_rentiva_fields_required', true),
			'show_pax'              => self::resolve_bool($atts['show_pax'], 'mhm_rentiva_enable_pax', true),
			'show_luggage'          => self::resolve_bool($atts['show_luggage'], 'mhm_rentiva_enable_luggage', true),

			// Query filters
			'service_type'          => $atts['service_type'],
			'filter_categories'     => $atts['filter_categories'],
			'redirect_page'         => self::resolve_default($atts['redirect_page'], 'mhm_rentiva_search_results_page'),
			'layout'                => $layout,
			'style'                 => $atts['style'] ?? 'glass',
		);
	}

	/**
	 * Enqueue specific assets.
	 */
	protected static function enqueue_assets(array $atts = array()): void
	{
		// Base unified-search styles (layout and component foundations).
		wp_enqueue_style(
			'mhm-rentiva-unified-search-base',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/unified-search.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// Premium search overlay styles.
		wp_enqueue_style(
			'mhm-rentiva-search-premium',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/search-premium.css',
			array('mhm-rentiva-unified-search-base'),
			MHM_RENTIVA_VERSION
		);

		// Ensure Transfer JS logic is loaded for the Transfer tab (Parity)
		\MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes::enqueue_assets();

		wp_enqueue_script(
			'mhm-rentiva-unified-search',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/unified-search.js',
			array('jquery', 'jquery-ui-datepicker', 'rentiva-transfer'),
			MHM_RENTIVA_VERSION,
			true
		);

		// Ensure datepicker assets are loaded via centralized helper
		DatepickerAssets::enqueue();

		// Fetch Routes for Frontend Filtering
		$routes = self::get_all_routes();

		// Consolidate Localize script with combined data
		// We use 'rentiva_transfer_nonce' because it's what TransferShortcodes AJAX handler expects.
		wp_localize_script(
			'mhm-rentiva-unified-search',
			'mhmUnifiedSearch',
			array(
				'ajaxUrl'         => admin_url('admin-ajax.php'),
				'restUrl'         => get_rest_url(null, 'mhm-rentiva/v1/locations'),
				'nonce'           => wp_create_nonce('rentiva_transfer_nonce'),
				'restNonce'       => wp_create_nonce('wp_rest'),
				'initial_service' => $atts['default_tab'] === 'transfer' ? 'transfer' : 'rental',
				'routes'          => $routes,
				'settings'        => array(
					'minRentalDays'     => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_min_rental_days', 1),
					'defaultRentalDays' => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_default_rental_days', 1),
				),
				'i18n'            => array(
					'same_location_error' => __('Pick-up and Drop-off locations cannot be the same.', 'mhm-rentiva'),
					'no_route_error'      => __('No transfer route available between selected locations.', 'mhm-rentiva'),
					'searching_text'      => __('Searching...', 'mhm-rentiva'),
					'error_text'          => __('An error occurred. Please try again.', 'mhm-rentiva'),
					'server_error'        => __('Server communication error!', 'mhm-rentiva'),
				),
			)
		);
	}

	/**
	 * Helper to get all routes for frontend validation
	 *
	 * @return array
	 */
	private static function get_all_routes(): array
	{
		static $routes_cache = null;
		if (null !== $routes_cache) {
			return $routes_cache;
		}

		global $wpdb;
		$table_routes = $wpdb->prefix . 'rentiva_transfer_routes';
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_routes));
		if ($table_exists !== $table_routes) {
			$table_routes = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
		}

		// Check if table exists before querying
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_routes));
		if ($table_exists !== $table_routes) {
			$routes_cache = array();
			return $routes_cache;
		}

		$query = "SELECT origin_id, destination_id FROM {$table_routes}";

		// %i identifier placeholder is used for dynamic table names.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results($query);
		$routes_cache = is_array($results) ? $results : array();

		return $routes_cache;
	}


	/**
	 * Resolve attribute value: If 'default', fetch from Global Settings.
	 * Priority: Attribute > SettingsCore (if exists) > Fallback
	 */
	private static function resolve_default(mixed $value, string $global_key, mixed $fallback = null): mixed
	{
		// 1. Attribute Priority (if not 'default' and not empty)
		if ('default' !== $value && '' !== $value && null !== $value) {
			return $value;
		}

		// 2. SettingsCore Priority (if exists)
		if (\MHMRentiva\Admin\Settings\Core\SettingsCore::has($global_key)) {
			$global_val = \MHMRentiva\Admin\Settings\Core\SettingsCore::get($global_key);
			if (null !== $global_val && '' !== $global_val) {
				return $global_val;
			}
		}

		// 3. Fallback priority
		return $fallback;
	}

	/**
	 * Resolve boolean attribute with default fallback.
	 * Priority: Attribute > SettingsCore (if exists) > Fallback
	 */
	private static function resolve_bool(mixed $value, string $global_key, bool $default_val = false): bool
	{
		// 1. Attribute Priority (if not 'default' and not empty)
		if ('default' !== $value && '' !== $value && null !== $value) {
			return filter_var($value, FILTER_VALIDATE_BOOLEAN);
		}

		// 2. SettingsCore Priority (if exists)
		if (\MHMRentiva\Admin\Settings\Core\SettingsCore::has($global_key)) {
			$global_val = \MHMRentiva\Admin\Settings\Core\SettingsCore::get($global_key);
			if (null !== $global_val && '' !== $global_val) {
				return filter_var($global_val, FILTER_VALIDATE_BOOLEAN);
			}
		}

		// 3. Fallback priority
		return $default_val;
	}
}
