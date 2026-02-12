<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified Search Shortcode
 *
 * Merges Vehicle and Transfer search into a single tabbed widget.
 *
 * @since 4.0.0
 */
final class UnifiedSearch extends AbstractShortcode {

	/**
	 * @return string Shortcode tag.
	 */
	protected static function get_shortcode_tag(): string {
		return 'rentiva_unified_search';
	}

	/**
	 * @return string Path to template.
	 */
	protected static function get_template_path(): string {
		return 'shortcodes/unified-search';
	}

	/**
	 * Define default attributes.
	 */
	protected static function get_default_attributes(): array {
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
	protected static function prepare_template_data( array $atts ): array {
		// Initial service type depends on default_tab
		$initial_service_type = $atts['default_tab'] === 'transfer' ? 'transfer' : 'rental';

		// Fetch locations based on initial service type
		$locations = self::get_all_locations( $initial_service_type );

		// Normalize boolean attributes (accept '1', 'true', true, 1)
		$bool = fn( $v ) => filter_var( $v, FILTER_VALIDATE_BOOLEAN );

		// Resolve initial visibility
		$show_rental   = self::resolve_bool( $atts['show_rental_tab'], 'mhm_rentiva_show_rental_tab', true );
		$show_transfer = self::resolve_bool( $atts['show_transfer_tab'], 'mhm_rentiva_show_transfer_tab', true );

		// Override based on Service Mode (Master Switch)
		if ( $atts['service_type'] === 'rental' ) {
			$show_transfer = false;
			$show_rental   = true;
		} elseif ( $atts['service_type'] === 'transfer' ) {
			$show_rental   = false;
			$show_transfer = true;
		}

		// Resolve layout: Check search_layout first (Block), then layout (Shortcode)
		$layout = ! empty( $atts['search_layout'] ) ? $atts['search_layout'] : $atts['layout'];

		return array(
			'locations'             => $locations,
			'default_tab'           => self::resolve_default( $atts['default_tab'], 'mhm_rentiva_default_search_tab', 'rental' ),
			'wrapper_id'            => uniqid( 'rv_unified_' ),
			'nonce'                 => wp_create_nonce( 'mhm_rentiva_unified_search' ),

			// Visibility controls
			'show_rental_tab'       => $show_rental,
			'show_transfer_tab'     => $show_transfer,
			'show_location_select'  => self::resolve_bool( $atts['show_location_select'], 'mhm_rentiva_enable_location_select', true ),
			'show_time_select'      => self::resolve_bool( $atts['show_time_select'], 'mhm_rentiva_enable_time_select', true ),
			'show_date_picker'      => self::resolve_bool( $atts['show_date_picker'], 'mhm_rentiva_enable_date_picker', true ),
			'show_dropoff_location' => self::resolve_bool( $atts['show_dropoff_location'], 'mhm_rentiva_enable_dropoff', true ),
			'show_pax'              => self::resolve_bool( $atts['show_pax'], 'mhm_rentiva_enable_pax', true ),
			'show_luggage'          => self::resolve_bool( $atts['show_luggage'], 'mhm_rentiva_enable_luggage', true ),

			// Query filters
			'service_type'          => $atts['service_type'],
			'filter_categories'     => $atts['filter_categories'],
			'redirect_page'         => self::resolve_default( $atts['redirect_page'], 'mhm_rentiva_search_results_page' ),
			'layout'                => $layout,
			'style'                 => $atts['style'] ?? 'glass',
		);
	}

	/**
	 * Enqueue specific assets.
	 */
	protected static function enqueue_assets( array $atts = array() ): void {
		// Enqueue unified JS/CSS
		wp_enqueue_style(
			'mhm-rentiva-unified-search',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/unified-search.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-rentiva-unified-search',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/unified-search.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			MHM_RENTIVA_VERSION,
			true
		);

		// Ensure datepicker assets are loaded
		wp_enqueue_style(
			'mhm-rentiva-datepicker',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/datepicker-custom.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// Fetch Routes for Frontend Filtering
		$routes = self::get_all_routes();

		// Consolidate Localize script with combined data
		// We use 'rentiva_transfer_nonce' because it's what TransferShortcodes AJAX handler expects.
		wp_localize_script(
			'mhm-rentiva-unified-search',
			'mhmUnifiedSearch',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'restUrl'         => get_rest_url( null, 'mhm-rentiva/v1/locations' ),
				'nonce'           => wp_create_nonce( 'rentiva_transfer_nonce' ),
				'restNonce'       => wp_create_nonce( 'wp_rest' ),
				'initial_service' => $atts['default_tab'] === 'transfer' ? 'transfer' : 'rental',
				'routes'          => $routes,
				'settings'        => array(
					'minRentalDays'     => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_min_rental_days', 1 ),
					'defaultRentalDays' => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_default_rental_days', 1 ),
				),
				'i18n'            => array(
					'same_location_error' => __( 'Pick-up and Drop-off locations cannot be the same.', 'mhm-rentiva' ),
					'no_route_error'      => __( 'No transfer route available between selected locations.', 'mhm-rentiva' ),
					'searching_text'      => __( 'Searching...', 'mhm-rentiva' ),
					'error_text'          => __( 'An error occurred. Please try again.', 'mhm-rentiva' ),
					'server_error'        => __( 'Server communication error!', 'mhm-rentiva' ),
				),
			)
		);
	}

	/**
	 * Helper to get all routes for frontend validation
	 *
	 * @return array
	 */
	private static function get_all_routes(): array {
		global $wpdb;
		$table_routes = $wpdb->prefix . 'rentiva_transfer_routes';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_routes'" ) != $table_routes ) {
			$table_routes = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
		}

		// Check if table exists before querying
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_routes'" ) != $table_routes ) {
			return array();
		}

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( "SELECT origin_id, destination_id FROM {$table_routes}" );
	}

	/**
	 * Helper to get locations from Transfer database
	 *
	 * @param string $service_type rental|transfer|both
	 * @return array
	 */
	private static function get_all_locations( string $service_type = 'both' ): array {
		global $wpdb;
		$table_locations = $wpdb->prefix . 'rentiva_transfer_locations';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_locations'" ) != $table_locations ) {
			$table_locations = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
		}

		// Check if table exists before querying
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_locations'" ) != $table_locations ) {
			return array();
		}

		$query = "SELECT id, name, type FROM {$table_locations} WHERE is_active = 1";

		if ( $service_type === 'rental' ) {
			$query .= ' AND allow_rental = 1';
		} elseif ( $service_type === 'transfer' ) {
			$query .= ' AND allow_transfer = 1';
		}

		$query .= ' ORDER BY priority ASC, name ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $query );
	}

	/**
	 * Resolve attribute value: If 'default', fetch from Global Settings.
	 */
	private static function resolve_default( mixed $value, string $global_key, mixed $fallback = null ): mixed {
		if ( $value === 'default' || $value === '' || $value === null ) {
			$global_val = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( $global_key );

			if ( $global_val === null || $global_val === '' ) {
				return $fallback;
			}
			return $global_val;
		}
		return $value;
	}

	/**
	 * Resolve boolean attribute with default fallback.
	 */
	private static function resolve_bool( mixed $value, string $global_key, bool $default_val = false ): bool {
		if ( $value === 'default' || $value === '' || $value === null ) {
			// Get from settings
			$global_val = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( $global_key );

			// If setting refers to a non-existent key, SettingsCore might return null (or its own default)
			// If strictly null/empty, use our provided fallback
			if ( $global_val === null || $global_val === '' ) {
				return $default_val;
			}

			return filter_var( $global_val, FILTER_VALIDATE_BOOLEAN );
		}
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}
}
