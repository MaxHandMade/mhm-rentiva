<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
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
final class VehicleComparison extends AbstractShortcode
{


	public const SHORTCODE = 'rentiva_vehicle_comparison';

	public static function register(): void
	{
		parent::register();

		add_action('wp_ajax_mhm_rentiva_add_vehicle_to_comparison', array(self::class, 'ajax_add_vehicle'));
		add_action('wp_ajax_nopriv_mhm_rentiva_add_vehicle_to_comparison', array(self::class, 'ajax_add_vehicle'));
		add_action('wp_ajax_mhm_rentiva_remove_vehicle_from_comparison', array(self::class, 'ajax_remove_vehicle'));
		add_action('wp_ajax_nopriv_mhm_rentiva_remove_vehicle_from_comparison', array(self::class, 'ajax_remove_vehicle'));
		add_action('wp_ajax_mhm_rentiva_get_available_vehicles', array(self::class, 'ajax_get_available_vehicles'));
		add_action('wp_ajax_nopriv_mhm_rentiva_get_available_vehicles', array(self::class, 'ajax_get_available_vehicles'));
	}

	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_vehicle_comparison';
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/vehicle-comparison';
	}

	protected static function get_default_attributes(): array
	{
		return array(
			'vehicle_ids' => '',
			'show_features' => 'all',
			'max_vehicles' => '4',
			'class' => '',
		);
	}

	protected static function get_css_filename(): string
	{
		return 'vehicle-comparison.css';
	}

	protected static function get_js_filename(): string
	{
		return 'vehicle-comparison.js';
	}

	/**
	 * Enqueue assets
	 */
	protected static function enqueue_assets(array $atts = []): void
	{
		// Call parent method (enqueue_styles and enqueue_scripts from AbstractShortcode)
		parent::enqueue_assets();

		// Localize script
		self::localize_script(self::get_asset_handle());

		// Add inline script for configuration (WordPress way)
		add_action('wp_footer', array(self::class, 'add_configuration_script'));
	}

	protected static function get_asset_handle(): string
	{
		return 'mhm-rentiva-vehicle-comparison';
	}

	protected static function get_script_object_name(): string
	{
		return 'mhmRentivaVehicleComparison';
	}

	protected static function get_localized_data(): array
	{
		return array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('mhm_rentiva_vehicle_comparison_nonce'),
			'loading' => __('Loading...', 'mhm-rentiva'),
			'error' => __('An error occurred', 'mhm-rentiva'),
			'vehicleAdded' => __('Vehicle added to comparison', 'mhm-rentiva'),
			'vehicleRemoved' => __('Vehicle removed from comparison', 'mhm-rentiva'),
			'maxVehiclesReached' => __('Maximum vehicle count reached', 'mhm-rentiva'),
			'noVehiclesToCompare' => __('No vehicles to compare', 'mhm-rentiva'),
			'addVehicle' => __('Add Vehicle', 'mhm-rentiva'),
			'removeVehicle' => __('Remove', 'mhm-rentiva'),
			'bookNow' => __('Book Now', 'mhm-rentiva'),
			'one_vehicle_compared' => __('1 vehicle being compared', 'mhm-rentiva'),
			/* translators: %d: number of vehicles */
			'multiple_vehicles_compared' => __('%d vehicles being compared', 'mhm-rentiva'),
		);
	}

	public static function render(array $atts = array(), ?string $content = null): string
	{
		// Manually enqueue assets
		self::enqueue_assets_once();

		$defaults = array(
			'vehicle_ids' => '', // Vehicle IDs (comma-separated)
			'show_features' => 'all', // Features to show: all, basic, detailed
			'max_vehicles' => '4', // Maximum number of vehicles
			'show_add_vehicle' => '1', // Show add vehicle button
			'show_remove_buttons' => '1', // Show remove buttons
			'show_prices' => '1', // Show prices
			'show_images' => '1', // Show vehicle images
			'show_booking_buttons' => '1', // Show booking buttons
			'layout' => 'table', // table, cards
			'class' => '', // Custom CSS class
		);

		$atts = shortcode_atts($defaults, $atts, self::SHORTCODE);

		// Prepare template data
		$data = self::prepare_template_data($atts);

		// Render template
		return Templates::render('shortcodes/vehicle-comparison', $data, true);
	}

	// ...
	protected static function prepare_template_data(array $atts): array
	{

		$vehicle_ids = self::parse_vehicle_ids($atts['vehicle_ids']);
		$max_vehicles = intval($atts['max_vehicles']);

		// Refactor: Get from CompareService if empty
		if (empty($vehicle_ids) && class_exists('\MHMRentiva\Admin\Services\CompareService')) {
			$vehicle_ids = \MHMRentiva\Admin\Services\CompareService::get_list();
		}

		// Check maximum number of vehicles
		if (count($vehicle_ids) > $max_vehicles) {
			$vehicle_ids = array_slice($vehicle_ids, 0, $max_vehicles);
		}

		$vehicles = self::get_vehicles_data($vehicle_ids);
		$features = self::get_comparison_features($atts['show_features'], $vehicles);
		$all_vehicles = self::get_all_available_vehicles();

		return array(
			'atts' => $atts,
			'vehicles' => $vehicles,
			'features' => $features,
			'all_vehicles' => $all_vehicles,
			'max_vehicles' => $max_vehicles,
			'has_vehicles' => ! empty($vehicles),
			'can_add_more' => count($vehicles) < $max_vehicles,
			'show_add_vehicle' => count($vehicles) < $max_vehicles,
		);
	}
    // ...
    // ...
	/**
	 * Add vehicle via AJAX
	 *
	 * @return void
	 */
	public static function ajax_add_vehicle(): void
	{
		try {
			// Security check
			if (! check_ajax_referer('mhm_rentiva_vehicle_comparison_nonce', 'nonce', false)) {
				wp_send_json_error(array('message' => __('Security check failed.', 'mhm-rentiva')));
				return;
			}

			$vehicle_id = intval(isset($_POST['vehicle_id']) ? wp_unslash($_POST['vehicle_id']) : 0);

			if ($vehicle_id <= 0) {
				wp_send_json_error(array('message' => __('Invalid vehicle ID.', 'mhm-rentiva')));
			}

			// Use Service
			if (class_exists('\MHMRentiva\Admin\Services\CompareService')) {
				try {
					\MHMRentiva\Admin\Services\CompareService::add($vehicle_id);
				} catch (\Exception $e) {
					wp_send_json_error(array('message' => $e->getMessage()));
				}
			}

			// Get vehicle data
			$vehicle_data = self::get_vehicle_data($vehicle_id);
			if (! $vehicle_data) {
				wp_send_json_error(array('message' => __('Vehicle not found.', 'mhm-rentiva')));
			}

			wp_send_json_success(
				array(
					'vehicle' => $vehicle_data,
					'message' => __('Vehicle added to comparison.', 'mhm-rentiva'),
				)
			);
		} catch (\Exception $e) {
			wp_send_json_error(array('message' => __('An error occurred while adding vehicle.', 'mhm-rentiva')));
		}
	}

	/**
	 * Remove vehicle via AJAX
	 *
	 * @return void
	 */
	public static function ajax_remove_vehicle(): void
	{
		try {
			// Security check
			if (! check_ajax_referer('mhm_rentiva_vehicle_comparison_nonce', 'nonce', false)) {
				wp_send_json_error(array('message' => __('Security check failed.', 'mhm-rentiva')));
				return;
			}

			$vehicle_id = intval(isset($_POST['vehicle_id']) ? wp_unslash($_POST['vehicle_id']) : 0);

			if ($vehicle_id <= 0) {
				wp_send_json_error(array('message' => __('Invalid vehicle ID.', 'mhm-rentiva')));
			}

			// Use Service
			if (class_exists('\MHMRentiva\Admin\Services\CompareService')) {
				\MHMRentiva\Admin\Services\CompareService::remove($vehicle_id);
			}

			wp_send_json_success(
				array(
					'vehicle_id' => $vehicle_id,
					'message' => __('Vehicle removed from comparison.', 'mhm-rentiva'),
				)
			);
		} catch (\Exception $e) {
			wp_send_json_error(array('message' => __('An error occurred while removing vehicle.', 'mhm-rentiva')));
		}
	}

	/**
	 * Create dynamic feature list
	 */
	private static function get_dynamic_features(array $vehicles = array()): array
	{
		// Get selected fields from settings
		$settings = get_option('mhm_rentiva_settings', array());
		$selected_fields_map = $settings['comparison_fields'] ?? array();

		// If no selection is made in the settings, return default empty structure
		if (empty($selected_fields_map)) {
			return array(
				'all' => array(),
			);
		}

		// Flatten all selected fields from all categories (details, features, equipment, etc.)
		$all_selected_keys = array();
		foreach ($selected_fields_map as $category => $fields) {
			if (is_array($fields)) {
				$all_selected_keys = array_merge($all_selected_keys, $fields);
			}
		}
		$all_selected_keys = array_unique($all_selected_keys);

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
		foreach ($field_order as $field_key) {
			if (in_array($field_key, $all_selected_keys, true)) {
				$features['all'][$field_key] = self::get_feature_label($field_key);
			}
		}

		// 2. Add remaining fields (custom fields, taxonomies, etc.)
		foreach ($all_selected_keys as $field_key) {
			if (! isset($features['all'][$field_key])) {
				$features['all'][$field_key] = self::get_feature_label($field_key);
			}
		}

		return $features;
	}

	/**
	 * Default features
	 */
	private static function get_default_features(): array
	{
		// Remove default fields - only use fields selected from admin settings
		return array(
			'all' => array(),
		);
	}

	/**
	 * Get feature label
	 */
	private static function get_feature_label(string $feature_key): string
	{
		// 1. Try to get label from centralized VehicleFeatureHelper (Dynamic/Custom Fields)
		if (class_exists('\MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper')) {
			$available_map = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_available_fields_map();
			foreach ($available_map as $type => $group_fields) {
				if (isset($group_fields[$feature_key]['label'])) {
					return $group_fields[$feature_key]['label'];
				}
			}
		}

		// 2. Fallback: Normalize the key and use hardcoded map
		$normalized_key = strtolower(str_replace(' ', '_', trim($feature_key)));

		$labels = array(
			'availability' => __('Availability', 'mhm-rentiva'),
			'available' => __('Available', 'mhm-rentiva'),
			'brand' => __('Brand', 'mhm-rentiva'),
			'model' => __('Model', 'mhm-rentiva'),
			'price_per_day' => __('Daily Price', 'mhm-rentiva'),
			'fuel_type' => __('Fuel Type', 'mhm-rentiva'),
			'transmission' => __('Transmission', 'mhm-rentiva'),
			'seats' => __('Seats', 'mhm-rentiva'),
			'doors' => __('Doors', 'mhm-rentiva'),
			'engine_size' => __('Engine Size', 'mhm-rentiva'),
			'year' => __('Model Year', 'mhm-rentiva'),
			'mileage' => __('Mileage', 'mhm-rentiva'),
			'color' => __('Color', 'mhm-rentiva'),
			'deposit' => __('Deposit', 'mhm-rentiva'),
			'license_plate' => __('License Plate', 'mhm-rentiva'),
			'rating_average' => __('Rating Average', 'mhm-rentiva'),
			'rating_count' => __('Rating Count', 'mhm-rentiva'),
			'gallery_images' => __('Gallery Images', 'mhm-rentiva'),
			'air_conditioning' => __('Air Conditioning', 'mhm-rentiva'),
			'gps' => __('GPS', 'mhm-rentiva'),
			'bluetooth' => __('Bluetooth', 'mhm-rentiva'),
			'usb_port' => __('USB Port', 'mhm-rentiva'),
			'sunroof' => __('Sunroof', 'mhm-rentiva'),
			// Common vehicle features
			'power_steering' => __('Power Steering', 'mhm-rentiva'),
			'central_locking' => __('Central Locking', 'mhm-rentiva'),
			'cruise_control' => __('Cruise Control', 'mhm-rentiva'),
			'airbags' => __('Airbags', 'mhm-rentiva'),
			'abs_brakes' => __('ABS Brakes', 'mhm-rentiva'),
			'abs' => __('ABS Brakes', 'mhm-rentiva'), // Fallback
			'fog_lights' => __('Fog Lights', 'mhm-rentiva'),
			'parking_sensors' => __('Parking Sensors', 'mhm-rentiva'),
			'backup_camera' => __('Backup Camera', 'mhm-rentiva'),
			'leather_seats' => __('Leather Seats', 'mhm-rentiva'),
			'heated_seats' => __('Heated Seats', 'mhm-rentiva'),
			'electric_windows' => __('Electric Windows', 'mhm-rentiva'),
			'electric_mirrors' => __('Electric Mirrors', 'mhm-rentiva'), // Fallback
			'power_mirrors' => __('Power Mirrors', 'mhm-rentiva'),
			'alloy_wheels' => __('Alloy Wheels', 'mhm-rentiva'),
			'roof_rack' => __('Roof Rack', 'mhm-rentiva'),
			'navigation' => __('Navigation', 'mhm-rentiva'),
		);

		return $labels[$normalized_key] ?? ucfirst(str_replace('_', ' ', $normalized_key));
	}

	/**
	 * Add configuration script to footer (WordPress way)
	 */
	public static function add_configuration_script(): void
	{
		// Only add if shortcode is used on current page
		if (! self::is_shortcode_used()) {
			return;
		}

		$features = array();
		$all_vehicles = self::get_all_available_vehicles();

		echo '<script type="text/javascript">
					';
		echo 'window.mhmRentivaVehicleComparison = {';
		echo 'ajax_url: "' . esc_url(admin_url('admin-ajax.php')) .
			'",';
		echo 'nonce: "' . esc_js(wp_create_nonce('mhm_rentiva_vehicle_comparison_nonce')) .
			'",';
		echo 'strings: {';
		echo 'loading: "' . esc_js(__('Loading...', 'mhm-rentiva')) .
			'",';
		echo 'error: "' . esc_js(__('An error occurred.', 'mhm-rentiva')) .
			'",';
		echo 'vehicleAdded: "' . esc_js(__('Vehicle added to comparison', 'mhm-rentiva')) .
			'",';
		echo 'vehicleRemoved: "' . esc_js(__('Vehicle removed from comparison', 'mhm-rentiva')) .
			'",';
		echo 'maxVehiclesReached: "' . esc_js(__('Maximum number of vehicles reached', 'mhm-rentiva')) .
			'",';
		echo 'noVehiclesToCompare: "' . esc_js(__('No vehicles found to compare', 'mhm-rentiva')) .
			'",';
		echo 'addVehicle: "' . esc_js(__('Add Vehicle', 'mhm-rentiva')) .
			'",';
		echo 'removeVehicle: "' . esc_js(__('Remove', 'mhm-rentiva')) .
			'",';
		echo 'bookNow: "' . esc_js(__('Make Reservation', 'mhm-rentiva')) .
			'",';
		echo 'one_vehicle_compared: "' . esc_js(__('1 vehicle being compared', 'mhm-rentiva')) .
			'",';
		/* translators: %d: number of vehicles */
		echo 'multiple_vehicles_compared: "' . esc_js(__('%d vehicles being compared', 'mhm-rentiva')) .
			'"';
		echo '},';
		echo 'features: ' . wp_json_encode($features, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) .
			',';
		echo 'availableVehicles: ' . wp_json_encode($all_vehicles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		echo '};';
		echo '
				</script>';
	}

	/**
	 * Check if shortcode is used on current page
	 */
	private static function is_shortcode_used(): bool
	{
		global $post;
		if (! $post) {
			return false;
		}

		return has_shortcode($post->post_content, self::SHORTCODE);
	}
	/**
	 * Parse vehicle IDs string
	 */
	private static function parse_vehicle_ids(string $ids_string): array
	{
		if (empty($ids_string)) {
			return array();
		}

		$ids = explode(',', $ids_string);
		$ids = array_map('intval', $ids);
		$ids = array_filter($ids, function ($id) {
			return $id > 0;
		});

		return array_unique($ids);
	}

	/**
	 * Get data for multiple vehicles
	 */
	private static function get_vehicles_data(array $vehicle_ids): array
	{
		if (empty($vehicle_ids)) {
			return array();
		}

		$vehicles = array();
		foreach ($vehicle_ids as $id) {
			$data = self::get_vehicle_data($id);
			if ($data) {
				$vehicles[] = $data;
			}
		}

		return $vehicles;
	}

	/**
	 * Get data for a single vehicle
	 */
	private static function get_vehicle_data(int $vehicle_id): ?array
	{
		$post = get_post($vehicle_id);
		if (! $post || $post->post_type !== 'vehicle' || $post->post_status !== 'publish') {
			return null;
		}

		// Basic data
		$data = array(
			'id' => $post->ID,
			'title' => get_the_title($post),
			'permalink' => get_permalink($post),
			'image' => get_the_post_thumbnail_url($post, 'medium'),
			'price' => get_post_meta($post->ID, 'mhm_rentiva_price_per_day', true),
			'features' => array(),
		);

		// Features
		// Use standard feature keys
		$feature_keys = array(
			'brand',
			'model',
			'year',
			'fuel_type',
			'transmission',
			'seeds',
			'doors',
			'engine_size',
			'color',
			'air_conditioning',
			'gps',
			'bluetooth'
		);

		foreach ($feature_keys as $key) {
			$meta_key = 'mhm_rentiva_' . $key;
			$value = get_post_meta($post->ID, $meta_key, true);
			if ($value) {
				$data['features'][$key] = $value;
			}
		}

		// Add availability status
		$data['available'] = true; // Simplified for now

		return $data;
	}

	/**
	 * Get comparison features based on vehicles
	 */
	private static function get_comparison_features(string $show_features, array $vehicles): array
	{
		// Reuse get_dynamic_features logic
		return self::get_dynamic_features($vehicles);
	}

	/**
	 * Get all available vehicles for dropdown
	 */
	private static function get_all_available_vehicles(): array
	{
		$args = array(
			'post_type' => 'vehicle',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);

		$query = new \WP_Query($args);
		$vehicles = array();

		if ($query->have_posts()) {
			foreach ($query->posts as $id) {
				$vehicles[] = array(
					'id' => $id,
					'text' => get_the_title($id),
				);
			}
		}

		return $vehicles;
	}
}
