<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

/**
 * Vehicle Comparison Shortcode
 *
 * Comparison table for multiple vehicles
 *
 * Usage: [rentiva_vehicle_comparison vehicle_ids="123,456,789" show_features="all" max_vehicles="4"]
 */
final class VehicleComparison extends AbstractShortcode {



	public const SHORTCODE = 'rentiva_vehicle_comparison';

	public static function register(): void {
		parent::register();

		// DEPRECATED: Standardizing on CompareService::ajax_toggle_compare to avoid split-brain.
	}

	protected static function get_shortcode_tag(): string {
		return 'rentiva_vehicle_comparison';
	}

	protected static function get_template_path(): string {
		return 'shortcodes/vehicle-comparison';
	}

	protected static function get_default_attributes(): array {
		return array(
			'vehicle_ids'   => '',
			'show_features' => 'all',
			'max_vehicles'  => '4',
			'class'         => '',
		);
	}

	protected static function get_css_filename(): string {
		return 'vehicle-comparison.css';
	}

	protected static function get_js_filename(): string {
		return 'vehicle-comparison.js';
	}

	/**
	 * Enqueue assets
	 */
	protected static function enqueue_assets( array $atts = array() ): void {
		// Call parent method (enqueue_styles and enqueue_scripts from AbstractShortcode)
		parent::enqueue_assets();

		// Localize script
		self::localize_script( self::get_asset_handle() );

		// Add inline script for configuration (WordPress way)
		add_action( 'wp_footer', array( self::class, 'add_configuration_script' ) );
	}

	protected static function get_asset_handle(): string {
		return 'mhm-rentiva-vehicle-comparison';
	}

	protected static function get_script_object_name(): string {
		return 'mhmRentivaVehicleComparison';
	}

	protected static function get_localized_data(): array {
		return array(
			'ajax_url'                   => admin_url( 'admin-ajax.php' ),
			'nonce'                      => wp_create_nonce( 'mhm_rentiva_vehicle_comparison_nonce' ),
			'toggle_nonce'               => wp_create_nonce( 'mhm_rentiva_toggle_compare' ),
			'loading'                    => __( 'Loading...', 'mhm-rentiva' ),
			'error'                      => __( 'An error occurred', 'mhm-rentiva' ),
			'vehicleAdded'               => __( 'Vehicle added to comparison', 'mhm-rentiva' ),
			'vehicleRemoved'             => __( 'Vehicle removed from comparison', 'mhm-rentiva' ),
			'maxVehiclesReached'         => __( 'Maximum vehicle count reached', 'mhm-rentiva' ),
			'noVehiclesToCompare'        => __( 'No vehicles to compare', 'mhm-rentiva' ),
			'addVehicle'                 => __( 'Add Vehicle', 'mhm-rentiva' ),
			'removeVehicle'              => __( 'Remove', 'mhm-rentiva' ),
			'bookNow'                    => __( 'Book Now', 'mhm-rentiva' ),
			'one_vehicle_compared'       => __( '1 vehicle being compared', 'mhm-rentiva' ),
			/* translators: %d: number of vehicles */
			'multiple_vehicles_compared' => __( '%d vehicles being compared', 'mhm-rentiva' ),
		);
	}

	public static function render( array $atts = array(), ?string $content = null ): string {
		// Manually enqueue assets
		self::enqueue_assets_once();

		$defaults = array(
			'vehicle_ids'          => '', // Vehicle IDs (comma-separated)
			'show_features'        => 'all', // Features to show: all, basic, detailed
			'max_vehicles'         => '4', // Maximum number of vehicles
			'show_add_vehicle'     => '1', // Show add vehicle button
			'show_remove_buttons'  => '1', // Show remove buttons
			'show_prices'          => '1', // Show prices
			'show_images'          => '1', // Show vehicle images
			'show_booking_buttons' => '1', // Show booking buttons
			'layout'               => 'table', // table, cards
			'title'                => '', // Custom title (translatable via shortcode attribute)
			'class'                => '', // Custom CSS class
		);

		$atts = shortcode_atts( $defaults, $atts, self::SHORTCODE );

		// Prepare template data
		$data = self::prepare_template_data( $atts );

		// Render template
		return Templates::render( 'shortcodes/vehicle-comparison', $data, true );
	}

	// ...
	protected static function prepare_template_data( array $atts ): array {
		$vehicle_ids  = self::parse_vehicle_ids( $atts['vehicle_ids'] ?? '' );
		$max_vehicles = intval( $atts['max_vehicles'] ?? 3 );

		// Canonical Source of Truth: Get from CompareService if shortcode IDs are empty
		if ( empty( $vehicle_ids ) && class_exists( '\MHMRentiva\Admin\Services\CompareService' ) ) {
			$vehicle_ids = \MHMRentiva\Admin\Services\CompareService::get_list();
		}

		// Enforce maximum number of vehicles
		if ( count( $vehicle_ids ) > $max_vehicles ) {
			$vehicle_ids = array_slice( $vehicle_ids, 0, $max_vehicles );
		}

		$vehicles     = self::get_vehicles_data( $vehicle_ids );
		$features     = self::get_comparison_features( $atts['show_features'] ?? 'all', $vehicles );
		$all_vehicles = ( $atts['manual_add'] ?? '0' ) === '1' ? self::get_all_available_vehicles() : array();

		return array(
			'atts'             => $atts,
			'vehicles'         => $vehicles,
			'features'         => $features,
			'all_vehicles'     => $all_vehicles,
			'max_vehicles'     => $max_vehicles,
			'has_vehicles'     => count( $vehicles ) >= 2, // At least 2 vehicles needed for comparison UX
			'can_add_more'     => count( $vehicles ) < $max_vehicles,
			'show_add_vehicle' => ( $atts['manual_add'] ?? '0' ) === '1' && count( $vehicles ) < $max_vehicles,
		);
	}
	// ...
	// ...
	/**
	 * Add vehicle via AJAX
	 *
	 * @return void
	 */
	public static function ajax_add_vehicle(): void {
		try {
			// Security check
			if ( ! check_ajax_referer( 'mhm_rentiva_vehicle_comparison_nonce', 'nonce', false ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
				return;
			}

			$vehicle_id = intval( isset( $_POST['vehicle_id'] ) ? wp_unslash( $_POST['vehicle_id'] ) : 0 );

			if ( $vehicle_id <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'Invalid vehicle ID.', 'mhm-rentiva' ) ) );
			}

			// Use Service
			if ( class_exists( '\MHMRentiva\Admin\Services\CompareService' ) ) {
				try {
					\MHMRentiva\Admin\Services\CompareService::add( $vehicle_id );
				} catch ( \Exception $e ) {
					wp_send_json_error( array( 'message' => $e->getMessage() ) );
				}
			}

			// Get vehicle data
			$vehicle_data = self::get_vehicle_data( $vehicle_id );
			if ( ! $vehicle_data ) {
				wp_send_json_error( array( 'message' => __( 'Vehicle not found.', 'mhm-rentiva' ) ) );
			}

			wp_send_json_success(
				array(
					'vehicle' => $vehicle_data,
					'message' => __( 'Vehicle added to comparison.', 'mhm-rentiva' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'An error occurred while adding vehicle.', 'mhm-rentiva' ) ) );
		}
	}

	/**
	 * Remove vehicle via AJAX
	 *
	 * @return void
	 */
	public static function ajax_remove_vehicle(): void {
		try {
			// Security check
			if ( ! check_ajax_referer( 'mhm_rentiva_vehicle_comparison_nonce', 'nonce', false ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
				return;
			}

			$vehicle_id = intval( isset( $_POST['vehicle_id'] ) ? wp_unslash( $_POST['vehicle_id'] ) : 0 );

			if ( $vehicle_id <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'Invalid vehicle ID.', 'mhm-rentiva' ) ) );
			}

			// Use Service
			if ( class_exists( '\MHMRentiva\Admin\Services\CompareService' ) ) {
				\MHMRentiva\Admin\Services\CompareService::remove( $vehicle_id );
			}

			wp_send_json_success(
				array(
					'vehicle_id' => $vehicle_id,
					'message'    => __( 'Vehicle removed from comparison.', 'mhm-rentiva' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'An error occurred while removing vehicle.', 'mhm-rentiva' ) ) );
		}
	}

	/**
	 * Create dynamic feature list — returns FLAT associative array.
	 * Template iterates this directly: foreach ($features as $key => $label)
	 */
	private static function get_dynamic_features( array $vehicles = array() ): array {
		// Get selected fields from settings
		$settings            = get_option( 'mhm_rentiva_settings', array() );
		$selected_fields_map = $settings['comparison_fields'] ?? array();

		// If no selection is made in the settings, return empty flat array
		if ( empty( $selected_fields_map ) ) {
			return array();
		}

		// Flatten all selected fields from all categories (details, features, equipment)
		$all_selected_keys = array();
		foreach ( $selected_fields_map as $category => $fields ) {
			if ( is_array( $fields ) ) {
				$all_selected_keys = array_merge( $all_selected_keys, $fields );
			}
		}
		$all_selected_keys = array_unique( $all_selected_keys );

		// Define preferred sort order for common fields
		$field_order = array(
			'price_per_day',
			'brand',
			'model',
			'availability',
			'available',
			'fuel_type',
			'transmission',
			'seats',
			'doors',
			'engine_size',
			'year',
			'color',
			'deposit',
			'mileage',
			'license_plate',
			'rating_average',
			'rating_count',
		);

		$features = array();

		// 1. Add fields that are in our preferred sort order
		foreach ( $field_order as $field_key ) {
			if ( in_array( $field_key, $all_selected_keys, true ) ) {
				$features[ $field_key ] = self::get_feature_label( $field_key );
			}
		}

		// 2. Add remaining fields (custom fields, taxonomies, etc.)
		foreach ( $all_selected_keys as $field_key ) {
			if ( ! isset( $features[ $field_key ] ) ) {
				$features[ $field_key ] = self::get_feature_label( $field_key );
			}
		}

		return $features;
	}

	/**
	 * Default features
	 */
	private static function get_default_features(): array {
		// Remove default fields - only use fields selected from admin settings
		return array();
	}

	/**
	 * Get feature label
	 */
	private static function get_feature_label( string $feature_key ): string {
		// 1. Try to get label from centralized VehicleFeatureHelper (Dynamic/Custom Fields)
		if ( class_exists( '\MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper' ) ) {
			$available_map = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_available_fields_map();
			foreach ( $available_map as $type => $group_fields ) {
				if ( isset( $group_fields[ $feature_key ]['label'] ) ) {
					return $group_fields[ $feature_key ]['label'];
				}
			}
		}

		// 2. Fallback: Normalize the key and use hardcoded map
		$normalized_key = strtolower( str_replace( ' ', '_', trim( $feature_key ) ) );

		$labels = array(
			'availability'     => __( 'Availability', 'mhm-rentiva' ),
			'available'        => __( 'Available', 'mhm-rentiva' ),
			'brand'            => __( 'Brand', 'mhm-rentiva' ),
			'model'            => __( 'Model', 'mhm-rentiva' ),
			'price_per_day'    => __( 'Daily Price', 'mhm-rentiva' ),
			'fuel_type'        => __( 'Fuel Type', 'mhm-rentiva' ),
			'transmission'     => __( 'Transmission', 'mhm-rentiva' ),
			'seats'            => __( 'Seats', 'mhm-rentiva' ),
			'doors'            => __( 'Doors', 'mhm-rentiva' ),
			'engine_size'      => __( 'Engine Size', 'mhm-rentiva' ),
			'year'             => __( 'Model Year', 'mhm-rentiva' ),
			'mileage'          => __( 'Mileage', 'mhm-rentiva' ),
			'color'            => __( 'Color', 'mhm-rentiva' ),
			'deposit'          => __( 'Deposit', 'mhm-rentiva' ),
			'license_plate'    => __( 'License Plate', 'mhm-rentiva' ),
			'rating_average'   => __( 'Rating Average', 'mhm-rentiva' ),
			'rating_count'     => __( 'Rating Count', 'mhm-rentiva' ),
			'gallery_images'   => __( 'Gallery Images', 'mhm-rentiva' ),
			'air_conditioning' => __( 'Air Conditioning', 'mhm-rentiva' ),
			'gps'              => __( 'GPS', 'mhm-rentiva' ),
			'bluetooth'        => __( 'Bluetooth', 'mhm-rentiva' ),
			'usb_port'         => __( 'USB Port', 'mhm-rentiva' ),
			'sunroof'          => __( 'Sunroof', 'mhm-rentiva' ),
			// Common vehicle features
			'power_steering'   => __( 'Power Steering', 'mhm-rentiva' ),
			'central_locking'  => __( 'Central Locking', 'mhm-rentiva' ),
			'cruise_control'   => __( 'Cruise Control', 'mhm-rentiva' ),
			'airbags'          => __( 'Airbags', 'mhm-rentiva' ),
			'abs_brakes'       => __( 'ABS Brakes', 'mhm-rentiva' ),
			'abs'              => __( 'ABS Brakes', 'mhm-rentiva' ), // Fallback
			'fog_lights'       => __( 'Fog Lights', 'mhm-rentiva' ),
			'parking_sensors'  => __( 'Parking Sensors', 'mhm-rentiva' ),
			'backup_camera'    => __( 'Backup Camera', 'mhm-rentiva' ),
			'leather_seats'    => __( 'Leather Seats', 'mhm-rentiva' ),
			'heated_seats'     => __( 'Heated Seats', 'mhm-rentiva' ),
			'electric_windows' => __( 'Electric Windows', 'mhm-rentiva' ),
			'electric_mirrors' => __( 'Electric Mirrors', 'mhm-rentiva' ), // Fallback
			'power_mirrors'    => __( 'Power Mirrors', 'mhm-rentiva' ),
			'alloy_wheels'     => __( 'Alloy Wheels', 'mhm-rentiva' ),
			'roof_rack'        => __( 'Roof Rack', 'mhm-rentiva' ),
			'navigation'       => __( 'Navigation', 'mhm-rentiva' ),
		);

		return $labels[ $normalized_key ] ?? ucfirst( str_replace( '_', ' ', $normalized_key ) );
	}

	/**
	 * Add configuration script to footer (WordPress way)
	 */
	public static function add_configuration_script(): void {
		// Only add if shortcode is used on current page
		if ( ! self::is_shortcode_used() ) {
			return;
		}

		$features     = array();
		$all_vehicles = self::get_all_available_vehicles();

		echo '<script type="text/javascript">
					';
		echo 'window.mhmRentivaVehicleComparison = {';
		echo 'ajax_url: "' . esc_url( admin_url( 'admin-ajax.php' ) ) .
			'",';
		echo 'nonce: "' . esc_js( wp_create_nonce( 'mhm_rentiva_vehicle_comparison_nonce' ) ) .
			'",';
		echo 'strings: {';
		echo 'loading: "' . esc_js( __( 'Loading...', 'mhm-rentiva' ) ) .
			'",';
		echo 'error: "' . esc_js( __( 'An error occurred.', 'mhm-rentiva' ) ) .
			'",';
		echo 'vehicleAdded: "' . esc_js( __( 'Vehicle added to comparison', 'mhm-rentiva' ) ) .
			'",';
		echo 'vehicleRemoved: "' . esc_js( __( 'Vehicle removed from comparison', 'mhm-rentiva' ) ) .
			'",';
		echo 'maxVehiclesReached: "' . esc_js( __( 'Maximum number of vehicles reached', 'mhm-rentiva' ) ) .
			'",';
		echo 'noVehiclesToCompare: "' . esc_js( __( 'No vehicles found to compare', 'mhm-rentiva' ) ) .
			'",';
		echo 'addVehicle: "' . esc_js( __( 'Add Vehicle', 'mhm-rentiva' ) ) .
			'",';
		echo 'removeVehicle: "' . esc_js( __( 'Remove', 'mhm-rentiva' ) ) .
			'",';
		echo 'bookNow: "' . esc_js( __( 'Make Reservation', 'mhm-rentiva' ) ) .
			'",';
		echo 'one_vehicle_compared: "' . esc_js( __( '1 vehicle being compared', 'mhm-rentiva' ) ) .
			'",';
		/* translators: %d: number of vehicles */
		echo 'multiple_vehicles_compared: "' . esc_js( __( '%d vehicles being compared', 'mhm-rentiva' ) ) .
			'"';
		echo '},';
		echo 'features: ' . wp_json_encode( $features, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ) .
			',';
		echo 'availableVehicles: ' . wp_json_encode( $all_vehicles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT );
		echo '};';
		echo '
				</script>';
	}

	/**
	 * Check if shortcode is used on current page
	 */
	private static function is_shortcode_used(): bool {
		global $post;
		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, self::SHORTCODE );
	}
	/**
	 * Parse vehicle IDs string
	 */
	private static function parse_vehicle_ids( string $ids_string ): array {
		if ( empty( $ids_string ) ) {
			return array();
		}

		$ids = explode( ',', $ids_string );
		$ids = array_map( 'intval', $ids );
		$ids = array_filter(
			$ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		return array_unique( $ids );
	}

	/**
	 * Get data for multiple vehicles
	 */
	private static function get_vehicles_data( array $vehicle_ids ): array {
		if ( empty( $vehicle_ids ) ) {
			return array();
		}

		$vehicles = array();
		foreach ( $vehicle_ids as $id ) {
			$data = self::get_vehicle_data( $id );
			if ( $data ) {
				$vehicles[] = $data;
			}
		}

		return $vehicles;
	}

	/**
	 * Get data for a single vehicle.
	 * Dynamically fetches ALL admin-selected comparison fields.
	 */
	private static function get_vehicle_data( int $vehicle_id ): ?array {
		$post = get_post( $vehicle_id );
		if ( ! $post || $post->post_type !== 'vehicle' || $post->post_status !== 'publish' ) {
			return null;
		}

		$price           = get_post_meta( $post->ID, '_mhm_rentiva_price_per_day', true );
		$currency_symbol = get_option( 'mhm_rentiva_currency_symbol', '$' );

		// Canonical data structure expected by template
		$data = array(
			'id'           => $post->ID,
			'title'        => get_the_title( $post ),
			'permalink'    => get_permalink( $post ),
			'image_url'    => get_the_post_thumbnail_url( $post, 'medium' ) ?: '',
			'features'     => array(
				'price_per_day'   => (float) $price,
				'currency_symbol' => $currency_symbol,
			),
			'availability' => array(
				'is_available' => true,
				'text'         => __( 'Available', 'mhm-rentiva' ),
			),
			'meta'         => array(
				'available' => true,
			),
		);

		// Dynamically fetch ALL admin-selected comparison fields
		$settings            = get_option( 'mhm_rentiva_settings', array() );
		$selected_fields_map = $settings['comparison_fields'] ?? array();

		// Flatten selected keys from all categories
		$all_selected_keys = array();
		foreach ( $selected_fields_map as $category => $fields ) {
			if ( is_array( $fields ) ) {
				$all_selected_keys = array_merge( $all_selected_keys, $fields );
			}
		}
		$all_selected_keys = array_unique( $all_selected_keys );

		// Pre-fetch features and equipment arrays for this vehicle
		$features_array = get_post_meta( $post->ID, '_mhm_rentiva_features', true );
		$features_array = is_array( $features_array ) ? $features_array : maybe_unserialize( $features_array );
		$features_array = is_array( $features_array ) ? $features_array : array();

		$equipment_array = get_post_meta( $post->ID, '_mhm_rentiva_equipment', true );
		$equipment_array = is_array( $equipment_array ) ? $equipment_array : maybe_unserialize( $equipment_array );
		$equipment_array = is_array( $equipment_array ) ? $equipment_array : array();

		foreach ( $all_selected_keys as $key ) {
			// Skip price — already handled above
			if ( $key === 'price_per_day' || $key === 'currency_symbol' ) {
				continue;
			}

			// 1. Direct meta check (Details)
			$meta_key = '_mhm_rentiva_' . $key;
			$value    = get_post_meta( $post->ID, $meta_key, true );

			// 2. If empty or not found as direct meta, check Features/Equipment arrays
			if ( empty( $value ) || $value === false ) {
				if ( in_array( $key, $features_array, true ) || in_array( $key, $equipment_array, true ) ) {
					$value = true; // Found in category array
				}
			}

			$data['features'][ $key ] = self::normalize_value( $value );
		}

		return $data;
	}

	/**
	 * Get comparison features based on vehicles.
	 * Returns flat associative array: [ 'brand' => 'Brand', 'model' => 'Model', ... ]
	 */
	private static function get_comparison_features( string $show_features, array $vehicles ): array {
		return self::get_dynamic_features( $vehicles );
	}

	/**
	 * Normalize any value to a safe display string.
	 * Prevents raw "Array" output and handles all types.
	 */
	private static function normalize_value( $value ): string {
		// Serialized data (stored by WP)
		if ( is_string( $value ) && is_serialized( $value ) ) {
			$unserialized = maybe_unserialize( $value );
			if ( is_array( $unserialized ) ) {
				return implode( ', ', array_filter( array_map( 'strval', $unserialized ) ) );
			}
			$value = $unserialized;
		}

		// Array values (gallery, multi-select, etc.)
		if ( is_array( $value ) ) {
			$flat = array();
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					// Nested arrays (e.g., gallery [{id, url}]) — skip
					continue;
				}
				$flat[] = (string) $item;
			}
			$joined = implode( ', ', array_filter( $flat ) );
			return $joined !== '' ? $joined : '–';
		}

		// Boolean values
		if ( is_bool( $value ) ) {
			return $value ? '✓' : '✗';
		}

		// Boolean-like strings (common in WP meta)
		if ( in_array( $value, array( '1', 'yes', 'true', 'on' ), true ) ) {
			return '✓';
		}
		if ( in_array( $value, array( '0', 'no', 'false', 'off' ), true ) ) {
			return '✗';
		}

		// Empty / null
		if ( $value === '' || $value === null || $value === false ) {
			return '–';
		}

		return (string) $value;
	}

	/**
	 * Page Validity Guard.
	 * Returns the comparison page URL ONLY if the page contains
	 * the [rentiva_vehicle_comparison] shortcode in its content.
	 *
	 * @return string URL or empty string if no valid page found.
	 */
	public static function get_comparison_page_url(): string {
		$settings = get_option( 'mhm_rentiva_settings', array() );

		// 1. Try to get from setting first (Direct Link / Most Stable)
		if ( ! empty( $settings['comparison_page_id'] ) ) {
			$url = get_permalink( $settings['comparison_page_id'] );
			if ( $url ) {
				return $url;
			}
		}

		// 2. Fallback to automated search (checks content for shortcode)
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				's'              => '[rentiva_vehicle_comparison',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $pages ) ) {
			return '';
		}

		$page_id = $pages[0];
		$content = get_post_field( 'post_content', $page_id );

		// Strict check: shortcode must actually exist in post_content
		if ( strpos( $content, '[rentiva_vehicle_comparison' ) === false ) {
			return '';
		}

		return get_permalink( $page_id );
	}

	/**
	 * Get all available vehicles for dropdown
	 */
	private static function get_all_available_vehicles(): array {
		$args = array(
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$query    = new \WP_Query( $args );
		$vehicles = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $id ) {
				$vehicles[] = array(
					'id'   => $id,
					'text' => get_the_title( $id ),
				);
			}
		}

		return $vehicles;
	}
}
