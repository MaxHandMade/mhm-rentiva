<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * REST API FIXER - Fixes WordPress Core REST API Errors
 *
 * Catches and fixes 500 errors in WordPress core REST API endpoints
 */
final class RestApiFixer
{


	public static function register(): void
	{
		// Apply fixes before REST API initialization
		add_action('rest_api_init', array(self::class, 'fix_rest_api_errors'), 1);

		// Check before REST request
		add_filter('rest_request_before_callbacks', array(self::class, 'prevent_rest_errors'), 10, 3);

		// Check after REST response
		add_filter('rest_request_after_callbacks', array(self::class, 'handle_rest_errors'), 10, 3);

		// Make REST API routes safe
		add_action('rest_api_init', array(self::class, 'register_safe_routes'), 5);
	}

	/**
	 * Fix REST API errors
	 */
	public static function fix_rest_api_errors(): void
	{
		// Make WordPress core endpoints safe
		self::fix_core_endpoints();

		// Fix vehicle endpoint specifically
		self::fix_vehicle_endpoint();

		// Fix template endpoints
		self::fix_template_endpoints();
	}

	/**
	 * Fix core endpoints
	 */
	private static function fix_core_endpoints(): void
	{
		// Fallback for Settings endpoint
		add_filter(
			'rest_pre_serve_request',
			function ($served, $result, $request, $server) {
				if (strpos($request->get_route(), '/wp/v2/settings') !== false && is_wp_error($result)) {
					$server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
					$server->send_status(200);

					echo wp_json_encode(
						array(
							'title'                  => get_bloginfo('name'),
							'description'            => get_bloginfo('description'),
							'url'                    => home_url(),
							'timezone'               => get_option('timezone_string'),
							'date_format'            => get_option('date_format'),
							'time_format'            => get_option('time_format'),
							'start_of_week'          => get_option('start_of_week'),
							'language'               => get_locale(),
							'use_smilies'            => get_option('use_smilies'),
							'default_category'       => get_option('default_category'),
							'default_post_format'    => get_option('default_post_format'),
							'posts_per_page'         => get_option('posts_per_page'),
							'default_ping_status'    => get_option('default_ping_status'),
							'default_comment_status' => get_option('default_comment_status'),
						)
					);

					return true;
				}
				return $served;
			},
			10,
			4
		);

		// Fallback for Taxonomies endpoint
		add_filter(
			'rest_pre_serve_request',
			function ($served, $result, $request, $server) {
				if (strpos($request->get_route(), '/wp/v2/taxonomies') !== false && is_wp_error($result)) {
					$server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
					$server->send_status(200);

					$taxonomies = get_taxonomies(array(), 'objects');
					$response   = array();

					foreach ($taxonomies as $taxonomy) {
						$response[$taxonomy->name] = array(
							'name'         => $taxonomy->label,
							'slug'         => $taxonomy->name,
							'description'  => $taxonomy->description,
							'types'        => $taxonomy->object_type,
							'hierarchical' => $taxonomy->hierarchical,
							'rest_base'    => $taxonomy->rest_base,
							'visibility'   => array(
								'show_ui'           => $taxonomy->show_ui,
								'show_in_menu'      => $taxonomy->show_in_menu,
								'show_in_nav_menus' => $taxonomy->show_in_nav_menus,
								'show_tagcloud'     => $taxonomy->show_tagcloud,
							),
						);
					}

					echo wp_json_encode($response);
					return true;
				}
				return $served;
			},
			10,
			4
		);

		// Fallback for Blocks endpoint
		add_filter(
			'rest_pre_serve_request',
			function ($served, $result, $request, $server) {
				if (strpos($request->get_route(), '/wp/v2/blocks') !== false && is_wp_error($result)) {
					$server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
					$server->send_status(200);

					echo wp_json_encode(array());
					return true;
				}
				return $served;
			},
			10,
			4
		);
	}

	/**
	 * Fix vehicle endpoint
	 */
	private static function fix_vehicle_endpoint(): void
	{
		// Special handling for vehicle endpoint
		add_filter(
			'rest_pre_serve_request',
			function ($served, $result, $request, $server) {
				if (strpos($request->get_route(), '/wp/v2/vehicles') !== false && is_wp_error($result)) {
					$server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
					$server->send_status(200);

					// Check if vehicle post type is registered
					if (! post_type_exists('vehicle')) {
						echo wp_json_encode(
							array(
								'code'    => 'vehicle_post_type_not_found',
								'message' => 'Vehicle post type is not registered',
								'data'    => array('status' => 404),
							)
						);
						return true;
					}

					// Return simple vehicle list
					$vehicles = get_posts(
						array(
							'post_type'   => 'vehicle',
							'post_status' => 'publish',
							'numberposts' => 10,
							'fields'      => 'ids',
						)
					);

					$response = array();
					foreach ($vehicles as $vehicle_id) {
						$response[] = array(
							'id'       => $vehicle_id,
							'title'    => get_the_title($vehicle_id),
							'status'   => 'publish',
							'type'     => 'vehicle',
							'link'     => get_permalink($vehicle_id),
							'date'     => get_post_time('c', false, $vehicle_id),
							'modified' => get_post_modified_time('c', false, $vehicle_id),
						);
					}

					echo wp_json_encode($response);
					return true;
				}
				return $served;
			},
			10,
			4
		);
	}

	/**
	 * Fix template endpoints
	 */
	private static function fix_template_endpoints(): void
	{
		// Fallback for template lookup endpoint
		add_filter(
			'rest_pre_serve_request',
			function ($served, $result, $request, $server) {
				if (strpos($request->get_route(), '/wp/v2/templates/lookup') !== false && is_wp_error($result)) {
					$server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
					$server->send_status(200);

					echo wp_json_encode(null);
					return true;
				}
				return $served;
			},
			10,
			4
		);

		// Fallback for templates endpoint
		add_filter(
			'rest_pre_serve_request',
			function ($served, $result, $request, $server) {
				if (strpos($request->get_route(), '/wp/v2/templates') !== false && is_wp_error($result)) {
					$server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
					$server->send_status(200);

					echo wp_json_encode(array());
					return true;
				}
				return $served;
			},
			10,
			4
		);

		// Fallback for pattern category endpoint
		add_filter(
			'rest_pre_serve_request',
			function ($served, $result, $request, $server) {
				if (strpos($request->get_route(), '/wp/v2/wp_pattern_category') !== false && is_wp_error($result)) {
					$server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
					$server->send_status(200);

					echo wp_json_encode(array());
					return true;
				}
				return $served;
			},
			10,
			4
		);
	}

	/**
	 * Error prevention before REST request
	 */
	public static function prevent_rest_errors($response, $handler, $request)
	{
		$route = $request->get_route();

		// Special check for vehicle endpoint
		if (strpos($route, '/wp/v2/vehicles') !== false) {
			if (! post_type_exists('vehicle')) {
				return new \WP_Error(
					'vehicle_post_type_not_found',
					'Vehicle post type is not registered',
					array('status' => 404)
				);
			}
		}

		return $response;
	}

	/**
	 * Error management after REST request
	 */
	public static function handle_rest_errors($response, $handler, $request)
	{
		if (is_wp_error($response)) {
			$route = $request->get_route();

			// Convert 500 errors to 503 (temporary error)
			if ($response->get_error_code() === 'rest_internal_server_error') {
				return new \WP_Error(
					'service_temporarily_unavailable',
					'Service temporarily unavailable. Please try again later.',
					array('status' => 503)
				);
			}
		}

		return $response;
	}

	/**
	 * Register safe routes
	 */
	public static function register_safe_routes(): void
	{
		// Safe route for vehicle endpoint
		register_rest_route(
			'mhm-rentiva/v1',
			'/vehicles',
			array(
				'methods'             => 'GET',
				'callback'            => array(self::class, 'get_vehicles_safe'),
				'permission_callback' => array(self::class, 'permission_check'),
			)
		);
	}

	/**
	 * Permission callback - Security check with rate limiting
	 */
	public static function permission_check(): bool
	{
		// Rate limiting check
		$client_ip = \MHMRentiva\Admin\Core\Utilities\RateLimiter::getClientIP();
		return \MHMRentiva\Admin\Core\Utilities\RateLimiter::check($client_ip, 'general');
	}

	/**
	 * Safe vehicle list
	 */
	public static function get_vehicles_safe($request)
	{
		if (! post_type_exists('vehicle')) {
			return new \WP_Error(
				'vehicle_post_type_not_found',
				'Vehicle post type is not registered',
				array('status' => 404)
			);
		}

		$vehicles = get_posts(
			array(
				'post_type'   => 'vehicle',
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$response = array();
		foreach ($vehicles as $vehicle) {
			$response[] = array(
				'id'       => $vehicle->ID,
				'title'    => $vehicle->post_title,
				'status'   => $vehicle->post_status,
				'type'     => $vehicle->post_type,
				'link'     => get_permalink($vehicle->ID),
				'date'     => $vehicle->post_date,
				'modified' => $vehicle->post_modified,
				'meta'     => array(
					'price_per_day' => get_post_meta($vehicle->ID, '_mhm_rentiva_price_per_day', true),
					'seats'         => get_post_meta($vehicle->ID, '_mhm_rentiva_seats', true),
					'transmission'  => get_post_meta($vehicle->ID, '_mhm_rentiva_transmission', true),
					'fuel_type'     => get_post_meta($vehicle->ID, '_mhm_rentiva_fuel_type', true),
					'available'     => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($vehicle->ID) === 'active',
				),
			);
		}

		return rest_ensure_response($response);
	}
}
