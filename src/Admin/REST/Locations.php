<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\Utilities\RateLimiter;



/**
 * Locations API endpoint.
 */
final class Locations {


	/**
	 * Register REST route.
	 */
	public static function register(): void
	{
		register_rest_route(
			'mhm-rentiva/v1',
			'/locations',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_locations' ),
				'permission_callback' => array( self::class, 'permission_check' ),
				'args'                => array(
					'service_type' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => 'both',
						'enum'              => array( 'rental', 'transfer', 'both' ),
					),
				),
			)
		);
	}

	/**
	 * Permission check with rate limiting and nonce verification.
	 */
	public static function permission_check(\WP_REST_Request $request): bool
	{
		$client_ip = RateLimiter::getClientIP();
		if (! RateLimiter::check($client_ip, 'general')) {
			return false;
		}

		$nonce = $request->get_header('X-WP-Nonce');
		if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
			return true;
		}

		if (is_user_logged_in() && current_user_can('manage_options')) {
			return true;
		}

		return false;
	}

	/**
	 * Get filtered locations.
	 */
	public static function get_locations(\WP_REST_Request $request): \WP_REST_Response
	{
		$service_type = sanitize_key( (string) $request->get_param('service_type'));
		$results      = \MHMRentiva\Admin\Transfer\Engine\LocationProvider::get_locations($service_type);

		return rest_ensure_response($results);
	}
}
