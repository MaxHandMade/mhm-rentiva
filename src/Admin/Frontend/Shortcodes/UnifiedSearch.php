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
 * Rental vehicle search widget. Lite ships rental-only (Task A10) -- transfer
 * search is the separate `rentiva_transfer_search` shortcode/block (add-on).
 *
 * @since 4.0.0
 */
final class UnifiedSearch extends AbstractShortcode {


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
			'default_tab'           => 'default', // Rental-only; kept for BC, has no other effect.
			'default_tab_alias'     => 'defaultTab',

			// Visibility controls (boolean as string for shortcode compatibility)
			'show_rental_tab'       => 'default',
			'show_location_select'  => 'default',
			'show_time_select'      => 'default',
			'show_date_picker'      => 'default',
			'show_dropoff_location' => 'default',
			'location_required'     => 'default', // Whether pickup_location select is required
			'fields_required'       => 'default', // Whether date fields are required (false = browse all vehicles)

			// Query filters
			'service_type'          => 'rental', // Lite ships rental-only; transfer is a separate add-on shortcode/block.
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
		// Lite is rental-only (Task A10): the unified-search widget no longer
		// offers a transfer tab -- transfer search is the separate
		// `rentiva_transfer_search` shortcode/block (add-on). Locations are still
		// requested for 'rental': the same filter also feeds an add-on's
		// pickup/dropoff branch selects on the rental form itself, so it must
		// stay live even though the transfer tab is gone.
		$locations = apply_filters('mhm_rentiva_search_locations', array(), 'rental');

		// Resolve initial visibility
		$show_rental = self::resolve_bool($atts['show_rental_tab'], 'mhm_rentiva_show_rental_tab', true);

		// Resolve layout: Check search_layout first (Block), then layout (Shortcode)
		$layout = ! empty($atts['search_layout']) ? $atts['search_layout'] : $atts['layout'];

		return array(
			'locations'             => $locations,
			'default_tab'           => 'rental',
			'wrapper_id'            => uniqid('rv_unified_'),
			'nonce'                 => wp_create_nonce('mhm_rentiva_unified_search'),

			// Visibility controls
			'show_rental_tab'       => $show_rental,
			// Never show a location select with nothing to select: the setting
			// defaults to true, so without this the Lite rental form would render an
			// empty (and, when location_required, unsubmittable) picker.
			'show_location_select'  => $locations !== array()
				&& self::resolve_bool($atts['show_location_select'], 'mhm_rentiva_enable_location_select', true),
			'show_time_select'      => self::resolve_bool($atts['show_time_select'], 'mhm_rentiva_enable_time_select', true),
			'show_date_picker'      => self::resolve_bool($atts['show_date_picker'], 'mhm_rentiva_enable_date_picker', true),
			'show_dropoff_location' => self::resolve_bool($atts['show_dropoff_location'], 'mhm_rentiva_enable_dropoff', true),
			'location_required'     => self::resolve_bool($atts['location_required'], 'mhm_rentiva_location_required', true),
			'fields_required'       => self::resolve_bool($atts['fields_required'], 'mhm_rentiva_fields_required', true),

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
			array( 'mhm-rentiva-unified-search-base' ),
			MHM_RENTIVA_VERSION
		);

		// Lite is rental-only (Task A10): the search-enqueue action and
		// script-deps filter this method used to fire/apply existed solely to let
		// an add-on's extra (transfer) search tab enqueue its own assets and
		// script dependency for that tab's now-removed panel. Neither has any
		// rental-side consumer, so both are gone with the tab.
		$search_deps = array( 'jquery', 'jquery-ui-datepicker' );

		wp_enqueue_script(
			'mhm-rentiva-unified-search',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/unified-search.js',
			$search_deps,
			MHM_RENTIVA_VERSION,
			true
		);

		// Ensure datepicker assets are loaded via centralized helper
		DatepickerAssets::enqueue();

		// Consolidate Localize script with combined data.
		// ajaxUrl/nonce('rentiva_transfer_nonce')/routes/i18n (same_location_error,
		// no_route_error, searching_text, error_text, server_error) were dropped:
		// they fed the TransferShortcodes AJAX handler and route-validation table,
		// neither of which Lite ships (Task A10 -- transfer is a separate add-on
		// shortcode/block). Nothing in unified-search.js reads them.
		wp_localize_script(
			'mhm-rentiva-unified-search',
			'mhmUnifiedSearch',
			array(
				'restUrl'         => get_rest_url(null, 'mhm-rentiva/v1/locations'),
				'restNonce'       => wp_create_nonce('wp_rest'),
				'initial_service' => $atts['default_tab'] === 'transfer' ? 'transfer' : 'rental',
				'settings'        => array(
					'minRentalDays'     => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_min_rental_days', 1),
					'defaultRentalDays' => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_default_rental_days', 1),
				),
			)
		);
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
