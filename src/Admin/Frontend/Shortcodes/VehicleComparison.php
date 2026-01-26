<?php

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

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

		add_action( 'wp_ajax_mhm_rentiva_add_vehicle_to_comparison', array( self::class, 'ajax_add_vehicle' ) );
		add_action( 'wp_ajax_nopriv_mhm_rentiva_add_vehicle_to_comparison', array( self::class, 'ajax_add_vehicle' ) );
		add_action( 'wp_ajax_mhm_rentiva_remove_vehicle_from_comparison', array( self::class, 'ajax_remove_vehicle' ) );
		add_action( 'wp_ajax_nopriv_mhm_rentiva_remove_vehicle_from_comparison', array( self::class, 'ajax_remove_vehicle' ) );
		add_action( 'wp_ajax_mhm_rentiva_get_available_vehicles', array( self::class, 'ajax_get_available_vehicles' ) );
		add_action( 'wp_ajax_nopriv_mhm_rentiva_get_available_vehicles', array( self::class, 'ajax_get_available_vehicles' ) );
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
	protected static function enqueue_assets(): void {
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
			'vehicle_ids'          => '',         // Vehicle IDs (comma-separated)
			'show_features'        => 'all',      // Features to show: all, basic, detailed
			'max_vehicles'         => '4',        // Maximum number of vehicles
			'show_add_vehicle'     => '1',        // Show add vehicle button
			'show_remove_buttons'  => '1',      // Show remove buttons
			'show_prices'          => '1',        // Show prices
			'show_images'          => '1',        // Show vehicle images
			'show_booking_buttons' => '1',     // Show booking buttons
			'layout'               => 'table',    // table, cards
			'class'                => '',         // Custom CSS class
		);

		$atts = shortcode_atts( $defaults, $atts, self::SHORTCODE );

		// Prepare template data
		$data = self::prepare_template_data( $atts );

		// Render template
		return Templates::render( 'shortcodes/vehicle-comparison', $data, true );
	}

	protected static function prepare_template_data( array $atts ): array {

		$vehicle_ids  = self::parse_vehicle_ids( $atts['vehicle_ids'] );
		$max_vehicles = intval( $atts['max_vehicles'] );

		// Check maximum number of vehicles
		if ( count( $vehicle_ids ) > $max_vehicles ) {
			$vehicle_ids = array_slice( $vehicle_ids, 0, $max_vehicles );
		}

		$vehicles     = self::get_vehicles_data( $vehicle_ids );
		$features     = self::get_comparison_features( $atts['show_features'], $vehicles );
		$all_vehicles = self::get_all_available_vehicles();

		return array(
			'atts'             => $atts,
			'vehicles'         => $vehicles,
			'features'         => $features,
			'all_vehicles'     => $all_vehicles,
			'max_vehicles'     => $max_vehicles,
			'has_vehicles'     => ! empty( $vehicles ),
			'can_add_more'     => count( $vehicles ) < $max_vehicles,
			'show_add_vehicle' => count( $vehicles ) < $max_vehicles,
		);
	}

	private static function parse_vehicle_ids( string $vehicle_ids ): array {
		if ( empty( $vehicle_ids ) ) {
			return array();
		}

		$ids = array_map( 'intval', explode( ',', $vehicle_ids ) );
		return array_filter(
			$ids,
			function ( $id ) {
				return $id > 0;
			}
		);
	}

	private static function get_vehicles_data( array $vehicle_ids ): array {
		if ( empty( $vehicle_ids ) ) {
			return array();
		}

		$vehicles = array();
		foreach ( $vehicle_ids as $vehicle_id ) {
			$vehicle_data = self::get_vehicle_data( $vehicle_id );
			if ( $vehicle_data ) {
				$vehicles[] = $vehicle_data;
			}
		}

		return $vehicles;
	}

	private static function get_vehicle_data( int $vehicle_id ): ?array {
		$vehicle = get_post( $vehicle_id );
		if ( ! $vehicle || $vehicle->post_type !== 'vehicle' ) {
			return null;
		}

		$price_per_day   = floatval( get_post_meta( $vehicle_id, '_mhm_rentiva_price_per_day', true ) ?: 0 );
		$currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

		// Vehicle image
		$image_id  = get_post_thumbnail_id( $vehicle_id );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

		// Vehicle features - Use correct meta fields
		$color_value   = get_post_meta( $vehicle_id, '_mhm_rentiva_custom_1759176716159', true ) ?: '-';
		$features_data = get_post_meta( $vehicle_id, '_mhm_rentiva_features', true );
		$features_list = array();

		// Process serialized features
		if ( is_string( $features_data ) && self::is_serialized( $features_data ) ) {
			$features_list = unserialize( $features_data ) ?: array();
		} elseif ( is_array( $features_data ) ) {
			$features_list = $features_data;
		}

		// Create dynamic feature list
		$dynamic_features = self::get_dynamic_features( array( $vehicle_id ) );
		$selected_fields  = $dynamic_features['all'] ?? array();

		$features = array(
			'price_per_day'   => $price_per_day,
			'currency_symbol' => $currency_symbol,
		);

		// Add only selected fields
		foreach ( $selected_fields as $field_key => $field_label ) {
			if ( $field_key === 'price_per_day' ) {
				continue; // Already added
			}

			if ( $field_key === 'features' ) {
				$features[ $field_key ] = $features_list;
				continue;
			}

			if ( ! empty( $features_list ) && is_array( $features_list ) && in_array( $field_key, $features_list, true ) ) {
				$features[ $field_key ] = __( 'Yes', 'mhm-rentiva' );
				continue;
			}

			// Fix for Deposit Display (Show calculated amount instead of % value)
			if ( $field_key === 'deposit' ) {
				$raw_deposit = get_post_meta( $vehicle_id, '_mhm_rentiva_deposit', true );
				$deposit_val = trim( (string) $raw_deposit );

				if ( $deposit_val !== '' && class_exists( '\MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator' ) ) {
					$calc = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::calculate_deposit( $deposit_val, $price_per_day, 1 );

					if ( $calc['deposit_amount'] > 0 ) {
						// Format price
						$formatted_price = number_format( $calc['deposit_amount'], 0, ',', '.' );
						$position        = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_currency_position', 'right_space' );

						switch ( $position ) {
							case 'left':
								$val = $currency_symbol . $formatted_price;
								break;
							case 'left_space':
								$val = $currency_symbol . ' ' . $formatted_price;
								break;
							case 'right':
								$val = $formatted_price . $currency_symbol;
								break;
							default:
								$val = $formatted_price . ' ' . $currency_symbol;
								break;
						}
						$features[ $field_key ] = $val;
					} else {
						$features[ $field_key ] = '-';
					}
					continue;
				}
			}

			$meta_value = self::resolve_feature_value( $vehicle_id, $field_key );

			// Try to use VehicleFeatureHelper for better formatting (Label mapping)
			$formatted_helper = null;
			if ( class_exists( '\MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper' ) ) {
				$formatted_helper = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::format_detail_value( $field_key, $meta_value );
			}

			if ( $formatted_helper && isset( $formatted_helper['text'] ) ) {
				$features[ $field_key ] = $formatted_helper['text'];
			} else {
				$features[ $field_key ] = self::format_feature_value( $meta_value );
			}
		}

		return array(
			'id'           => $vehicle_id,
			'title'        => $vehicle->post_title,
			'excerpt'      => $vehicle->post_excerpt,
			'image_url'    => $image_url,
			'permalink'    => get_permalink( $vehicle_id ) ?: '',
			'features'     => $features,
			'availability' => self::check_vehicle_availability( $vehicle_id ),
		);
	}

	/**
	 * Checks vehicle availability
	 */
	private static function check_vehicle_availability( int $vehicle_id ): array {
		$status = get_post_meta( $vehicle_id, '_mhm_vehicle_status', true );

		// Fallback for older data or if status is not set
		if ( empty( $status ) ) {
			$old_availability = get_post_meta( $vehicle_id, '_mhm_vehicle_availability', true );
			// Handle legacy values
			if ( $old_availability === '0' || $old_availability === 'passive' || $old_availability === 'inactive' ) {
				$status = 'inactive';
			} elseif ( $old_availability === '1' || $old_availability === 'active' ) {
				$status = 'active';
			} elseif ( $old_availability === 'maintenance' ) {
				$status = 'maintenance';
			} else {
				$status = 'active'; // Default
			}
		}

		$is_available = ( $status === 'active' );

		return array(
			'is_available' => $is_available,
			'status'       => $status,
			'text'         => $is_available ? __( 'Available', 'mhm-rentiva' ) : __( 'Out of Order', 'mhm-rentiva' ),
		);
	}

	private static function get_comparison_features( string $show_features, array $vehicles = array() ): array {
		// Create dynamic feature list
		$all_features = self::get_dynamic_features( $vehicles );

		// Single category structure (all)
		return $all_features['all'] ?? array();
	}

	/**
	 * Check for serialized string
	 */
	private static function is_serialized( $data ): bool {
		return is_string( $data ) && preg_match( '/^[aOs]:\d+:/', $data );
	}



	private static function resolve_feature_value( int $vehicle_id, string $field_key ) {
		$special_map = array(
			'availability'   => '_mhm_vehicle_availability',
			'available'      => '_mhm_vehicle_availability',
			'gallery_images' => '_mhm_rentiva_gallery_images',
			'rating_average' => '_mhm_rentiva_rating_average',
			'rating_count'   => '_mhm_rentiva_rating_count',
		);

		if ( isset( $special_map[ $field_key ] ) ) {
			$value = get_post_meta( $vehicle_id, $special_map[ $field_key ], true );
			if ( $value !== '' && $value !== null ) {
				$value = maybe_unserialize( $value );
				if ( is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					if ( json_last_error() === JSON_ERROR_NONE ) {
						return $decoded;
					}
				}

				return $value;
			}
		}

		$fallback_map = array(
			'year'            => array( '_mhm_rentiva_year', 'year' ),
			'fuel_type'       => array( '_mhm_rentiva_fuel_type', 'fuel_type' ),
			'transmission'    => array( '_mhm_rentiva_transmission', 'transmission' ),
			'seats'           => array( '_mhm_rentiva_seats', 'seats' ),
			'doors'           => array( '_mhm_rentiva_doors', 'doors' ),
			'mileage'         => array( '_mhm_rentiva_mileage', 'mileage' ),
			'brand'           => array( '_mhm_rentiva_brand', 'brand' ),
			'model'           => array( '_mhm_rentiva_model', 'model' ),
			'engine_size'     => array( '_mhm_rentiva_engine_size', 'engine_size' ),
			'color'           => array( '_mhm_rentiva_color', 'color' ),
			'license_plate'   => array( '_mhm_rentiva_license_plate', 'license_plate' ),
			'price_per_week'  => array( '_mhm_rentiva_price_per_week' ),
			'price_per_month' => array( '_mhm_rentiva_price_per_month' ),
		);

		$possible_keys = array_merge(
			$fallback_map[ $field_key ] ?? array(),
			array(
				'_mhm_rentiva_' . $field_key,
				'_mhm_rentiva' . $field_key,
				'_mhm_vehicle_' . $field_key,
				'_mhm_' . $field_key,
				'mhm_rentiva_' . $field_key,
				$field_key,
			)
		);

		$possible_keys = array_unique( array_filter( $possible_keys ) );

		foreach ( $possible_keys as $meta_key ) {
			$value = get_post_meta( $vehicle_id, $meta_key, true );

			if ( $value === '' || $value === null || $value === array() ) {
				continue;
			}

			$value = maybe_unserialize( $value );

			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					return $decoded;
				}
			}

			return $value;
		}

		return null;
	}



	private static function format_feature_value( $value ) {

		if ( is_array( $value ) ) {

			$flattened = array();

			foreach ( $value as $item ) {

				if ( is_array( $item ) ) {

					$flattened[] = implode( ', ', array_filter( array_map( 'strval', $item ) ) );
				} elseif ( is_bool( $item ) ) {

					$flattened[] = $item ? __( 'Yes', 'mhm-rentiva' ) : __( 'No', 'mhm-rentiva' );
				} elseif ( $item !== '' && $item !== null ) {

					$flattened[] = (string) $item;
				}
			}

			$flattened = array_filter(
				$flattened,
				static function ( $entry ) {

					return $entry !== '';
				}
			);

			return ! empty( $flattened ) ? implode( ', ', $flattened ) : '-';
		}

		if ( is_bool( $value ) ) {

			return $value ? __( 'Yes', 'mhm-rentiva' ) : __( 'No', 'mhm-rentiva' );
		}

		if ( $value === 0 || $value === '0' ) {

			return '0';
		}

		if ( $value === null || $value === '' || $value === array() ) {

			return '-';
		}

		return is_scalar( $value ) ? (string) $value : '-';
	}



	private static function get_all_available_vehicles(): array {
		$vehicles = get_posts(
			array(
				'post_type'      => 'vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$result = array();
		foreach ( $vehicles as $vehicle ) {
			$result[] = array(
				'id'      => $vehicle->ID,
				'title'   => $vehicle->post_title,
				'excerpt' => wp_trim_words( $vehicle->post_excerpt, 10 ),
			);
		}
		return $result;
	}

	/**
	 * Get vehicle list via AJAX
	 */
	public static function ajax_get_available_vehicles(): void {
		// Nonce check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_rentiva_vehicle_comparison_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'mhm-rentiva' ) );
			return;
		}

		$vehicles = self::get_all_available_vehicles();

		wp_send_json_success( $vehicles );
	}

	/**
	 * Add vehicle via AJAX
	 */
	public static function ajax_add_vehicle(): void {
		try {
			// Nonce check
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
			if ( ! wp_verify_nonce( $nonce, 'mhm_rentiva_vehicle_comparison_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			}

			$vehicle_id       = intval( $_POST['vehicle_id'] ?? 0 );
			$current_vehicles = array_map( 'intval', $_POST['current_vehicles'] ?? array() );
			$max_vehicles     = intval( $_POST['max_vehicles'] ?? 4 );

			if ( $vehicle_id <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'Invalid vehicle ID.', 'mhm-rentiva' ) ) );
			}

			// Check maximum number of vehicles
			if ( count( $current_vehicles ) >= $max_vehicles ) {
				wp_send_json_error( array( 'message' => __( 'Maximum vehicle count reached.', 'mhm-rentiva' ) ) );
			}

			// Check if vehicle is already added
			if ( in_array( $vehicle_id, $current_vehicles ) ) {
				wp_send_json_error( array( 'message' => __( 'This vehicle is already in comparison.', 'mhm-rentiva' ) ) );
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
	 */
	public static function ajax_remove_vehicle(): void {
		try {
			// Nonce check
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
			if ( ! wp_verify_nonce( $nonce, 'mhm_rentiva_vehicle_comparison_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			}

			$vehicle_id = intval( $_POST['vehicle_id'] ?? 0 );

			if ( $vehicle_id <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'Invalid vehicle ID.', 'mhm-rentiva' ) ) );
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
	 * Create dynamic feature list
	 */
	private static function get_dynamic_features( array $vehicles = array() ): array {
		// Get selected fields from settings
		$settings            = get_option( 'mhm_rentiva_settings', array() );
		$selected_fields_map = $settings['comparison_fields'] ?? array();

		// If no selection is made in the settings, return default empty structure
		if ( empty( $selected_fields_map ) ) {
			return array(
				'all' => array(),
			);
		}

		// Flatten all selected fields from all categories (details, features, equipment, etc.)
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
			'gallery_images',
			'features',
		);

		$features = array(
			'all' => array(),
		);

		// 1. Add fields that are in our preferred sort order
		foreach ( $field_order as $field_key ) {
			if ( in_array( $field_key, $all_selected_keys, true ) ) {
				$features['all'][ $field_key ] = self::get_feature_label( $field_key );
			}
		}

		// 2. Add remaining fields (custom fields, taxonomies, etc.)
		foreach ( $all_selected_keys as $field_key ) {
			if ( ! isset( $features['all'][ $field_key ] ) ) {
				$features['all'][ $field_key ] = self::get_feature_label( $field_key );
			}
		}

		return $features;
	}

	/**
	 * Default features
	 */
	private static function get_default_features(): array {
		// Remove default fields - only use fields selected from admin settings
		return array(
			'all' => array(),
		);
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

		echo '<script type="text/javascript">';
		echo 'window.mhmRentivaVehicleComparison = {';
		echo 'ajax_url: "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '",';
		echo 'nonce: "' . esc_js( wp_create_nonce( 'mhm_rentiva_vehicle_comparison_nonce' ) ) . '",';
		echo 'strings: {';
		echo 'loading: "' . esc_js( __( 'Loading...', 'mhm-rentiva' ) ) . '",';
		echo 'error: "' . esc_js( __( 'An error occurred.', 'mhm-rentiva' ) ) . '",';
		echo 'vehicleAdded: "' . esc_js( __( 'Vehicle added to comparison', 'mhm-rentiva' ) ) . '",';
		echo 'vehicleRemoved: "' . esc_js( __( 'Vehicle removed from comparison', 'mhm-rentiva' ) ) . '",';
		echo 'maxVehiclesReached: "' . esc_js( __( 'Maximum number of vehicles reached', 'mhm-rentiva' ) ) . '",';
		echo 'noVehiclesToCompare: "' . esc_js( __( 'No vehicles found to compare', 'mhm-rentiva' ) ) . '",';
		echo 'addVehicle: "' . esc_js( __( 'Add Vehicle', 'mhm-rentiva' ) ) . '",';
		echo 'removeVehicle: "' . esc_js( __( 'Remove', 'mhm-rentiva' ) ) . '",';
		echo 'bookNow: "' . esc_js( __( 'Make Reservation', 'mhm-rentiva' ) ) . '",';
		echo 'one_vehicle_compared: "' . esc_js( __( '1 vehicle being compared', 'mhm-rentiva' ) ) . '",';
		/* translators: %d: number of vehicles */
		echo 'multiple_vehicles_compared: "' . esc_js( __( '%d vehicles being compared', 'mhm-rentiva' ) ) . '"';
		echo '},';
		echo 'features: ' . wp_json_encode( $features, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ) . ',';
		echo 'availableVehicles: ' . wp_json_encode( $all_vehicles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT );
		echo '};';
		echo '</script>';
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
}
