<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\REST\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

use WP_Error;
use WP_REST_Request;
use Exception;



/**
 * Central authorization helper class for REST API
 *
 * This class meets the common authorization needs of all REST endpoints
 * and prevents code duplication.
 */
final class AuthHelper {


	/**
	 * Admin permission check
	 *
	 * @param WP_REST_Request $request REST request object
	 * @return bool Does admin permission exist?
	 */
	public static function adminPermissionsCheck(WP_REST_Request $request): bool
	{
		return current_user_can('manage_options');
	}

	/**
	 * REST API request validation
	 *
	 * This method supports two different authorization methods:
	 * 1. WordPress REST nonce (X-WP-Nonce header)
	 * 2. MHM custom nonce (mhmrentiva_nonce in request body)
	 *
	 * @param WP_REST_Request $request REST request object
	 * @param int             $booking_id Booking ID (for nonce validation)
	 * @param string          $gateway_prefix Gateway prefix (e.g. 'offline')
	 * @return bool|WP_Error True if successful, WP_Error if error
	 */
	public static function verifyAuth(WP_REST_Request $request, int $booking_id = 0, string $gateway_prefix = ''): bool|WP_Error
	{
		// 1. WordPress REST nonce check (logged-in users)
		$wpNonce = $request->get_header('X-WP-Nonce');
		if ($wpNonce && wp_verify_nonce($wpNonce, 'wp_rest')) {
			return true;
		}

		// 2. MHM custom nonce check (guest users)
		if ($booking_id > 0 && ! empty($gateway_prefix)) {
			$body     = $request->get_json_params();
			$mhmNonce = is_array($body) ? (string) ( $body['mhmrentiva_nonce'] ?? '' ) : '';

			if ($mhmNonce && wp_verify_nonce($mhmNonce, 'mhmrentiva_' . $gateway_prefix . '_' . $booking_id)) {
				return true;
			}
		}

		return new WP_Error(
			'forbidden',
			__('Authorization failed. Please refresh the page and try again.', 'mhm-rentiva'),
			array( 'status' => 403 )
		);
	}
}
