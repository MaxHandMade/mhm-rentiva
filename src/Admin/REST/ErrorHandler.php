<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * ✅ REST API ERROR HANDLER - Manages API Errors
 *
 * Catches 500 errors in REST API and returns proper error messages
 */
final class ErrorHandler
{


	public static function register(): void
	{
		add_action('rest_api_init', array(self::class, 'register_error_handlers'));
		add_filter('rest_pre_serve_request', array(self::class, 'handle_rest_errors'), 10, 4);
	}

	/**
	 * Register REST API error handlers
	 */
	public static function register_error_handlers(): void
	{
		// Special error handling for vehicle post type
		add_filter('rest_prepare_vehicle', array(self::class, 'prepare_vehicle_response'), 10, 3);

		// General REST API error handling
		add_filter('rest_request_before_callbacks', array(self::class, 'before_rest_callbacks'), 10, 3);
		add_filter('rest_request_after_callbacks', array(self::class, 'after_rest_callbacks'), 10, 3);
	}

	/**
	 * Prepare vehicle response
	 */
	public static function prepare_vehicle_response($response, $post, $request)
	{
		if (is_wp_error($response)) {
			return $response;
		}

		try {
			// Add vehicle meta data securely
			$meta_data = array(
				'price_per_day' => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($post->ID),
				'seats'         => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_seats($post->ID),
				'transmission'  => get_post_meta($post->ID, '_mhm_rentiva_transmission', true),
				'fuel_type'     => get_post_meta($post->ID, '_mhm_rentiva_fuel_type', true),
				'available'     => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($post->ID) === 'active',
			);

			$response->data['mhm_vehicle_meta'] = $meta_data;
		} catch (\Exception $e) {
			// Return empty meta on error
			$response->data['mhm_vehicle_meta'] = array();
		}

		return $response;
	}

	/**
	 * Error check before REST callbacks
	 */
	public static function before_rest_callbacks($response, $handler, $request)
	{
		// Special check for vehicle endpoint
		if (strpos($request->get_route(), '/wp/v2/vehicle') !== false) {
			// Check if vehicle post type is registered
			if (! post_type_exists('vehicle')) {
				return new \WP_Error(
					'vehicle_post_type_not_found',
					__('Vehicle post type is not registered', 'mhm-rentiva'),
					array('status' => 500)
				);
			}
		}

		return $response;
	}

	/**
	 * Error check after REST callbacks
	 */
	public static function after_rest_callbacks($response, $handler, $request)
	{
		// Catch 500 errors and return more meaningful error messages
		if (is_wp_error($response) && $response->get_error_code() === 'rest_internal_server_error') {
			$route = $request->get_route();

			// Special error messages based on route
			if (strpos($route, '/wp/v2/vehicle') !== false) {
				return new \WP_Error(
					'vehicle_api_error',
					__('Vehicle API is temporarily unavailable. Please try again later.', 'mhm-rentiva'),
					array('status' => 503)
				);
			}

			if (strpos($route, '/wp/v2/settings') !== false) {
				return new \WP_Error(
					'settings_api_error',
					__('Settings API is temporarily unavailable.', 'mhm-rentiva'),
					array('status' => 503)
				);
			}
		}

		return $response;
	}

	/**
	 * Handle REST request errors
	 */
	public static function handle_rest_errors($served, $result, $request, $server)
	{
		// If there is an error and it hasn't been served yet
		if (is_wp_error($result) && ! $served) {
			$error_data = $result->get_error_data();
			$status     = isset($error_data['status']) ? $error_data['status'] : 500;

			// Return JSON response
			$server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
			$server->send_header('X-Content-Type-Options', 'nosniff');
			$server->send_header('X-Robots-Tag', 'noindex');

			if ($status >= 500) {
				$server->send_header('X-Robots-Tag', 'noindex');
			}

			$server->send_status($status);

			echo wp_json_encode(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
					'data'    => $error_data,
				)
			);

			return true;
		}

		return $served;
	}
}

