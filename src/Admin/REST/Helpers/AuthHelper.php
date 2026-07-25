<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\REST\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

use WP_Error;
use WP_REST_Request;
use Exception;
use MHMRentiva\Admin\REST\Helpers\SecureToken;



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
	 * 2. MHM custom nonce (mhm_nonce in request body)
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
			$mhmNonce = is_array($body) ? (string) ( $body['mhm_nonce'] ?? '' ) : '';

			if ($mhmNonce && wp_verify_nonce($mhmNonce, 'mhm_' . $gateway_prefix . '_' . $booking_id)) {
				return true;
			}
		}

		return new WP_Error(
			'forbidden',
			__('Authorization failed. Please refresh the page and try again.', 'mhm-rentiva'),
			array( 'status' => 403 )
		);
	}

	/**
	 * Customer token validation
	 *
	 * @param string $token Customer token
	 * @param string $post_type Post type to check (default: 'vehicle_booking')
	 * @param string $email_meta_key Email meta key (default: '_booking_customer_email')
	 * @return array|null Customer information or null
	 */
	public static function verifyCustomerToken(string $token, string $post_type = 'vehicle_booking', string $email_meta_key = '_booking_customer_email'): ?array
	{
		// ✅ Use secure token validation system
		return SecureToken::verify_customer_token($token, $post_type, $email_meta_key);
	}

	/**
	 * @deprecated Use dynamic checkRateLimit
	 * Old hardcoded rate limiting system
	 */
	public static function checkRateLimitLegacy(string $identifier, int $limit = 60, int $window = 60): bool
	{
		$cache_key = 'mhm_rate_limit_' . md5($identifier);
		$requests  = get_transient($cache_key) ?: array();

		$now = time();

		// Clean old requests
		$requests = array_filter(
			$requests,
			function ($timestamp) use ($now, $window) {
				return ( $now - $timestamp ) < $window;
			}
		);

		// Limit check
		if (count($requests) >= $limit) {
			return false; // Rate limit exceeded
		}

		// Save new request
		$requests[] = $now;
		set_transient($cache_key, $requests, $window);

		return true; // Rate limit not exceeded
	}
}
