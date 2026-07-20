<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Availability {

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Permission callback — deliberately PUBLIC, not capability-gated (WP.org T4 #7).
	 *
	 * `/availability` and `/availability/with-alternatives` answer "is this
	 * vehicle free for these dates" for the front-end booking widget, which
	 * must work for anonymous site visitors who are not logged in and hold no
	 * WP capability at all. The response contains only availability status,
	 * pricing, and currency formatting (see check()/check_with_alternatives())
	 * — no PII, no customer/vendor data, and no write side-effects — so a
	 * `current_user_can()` gate would break the booking flow for every
	 * anonymous visitor without protecting anything sensitive. This is a
	 * documented part of the public REST API (README.md "Authentication"
	 * section), not an oversight.
	 *
	 * It is intentionally NOT `__return_true`: the request must still carry a
	 * valid standard `wp_rest` nonce (X-WP-Nonce), same mechanism WP core uses
	 * for its own REST endpoints, and be within the RateLimiter's per-IP
	 * budget. Neither check requires login or a capability — an anonymous
	 * visitor gets a nonce automatically from the page that embeds this
	 * widget — so the endpoint remains fully public while still rejecting
	 * cross-origin/no-session scripted probing and bulk scraping.
	 */
	public static function permission_check( \WP_REST_Request $request ): bool {
		// 1. Nonce check (CSRF / same-origin, not an authorization check —
		// works for logged-out visitors too).
		$auth_check = \MHMRentiva\Admin\REST\Helpers\AuthHelper::verifyAuth( $request );
		if ( is_wp_error( $auth_check ) ) {
			return false;
		}

		// 2. Rate limiting check (abuse/scrape protection, IP-scoped).
		$client_ip = \MHMRentiva\Admin\Core\Utilities\RateLimiter::getClientIP();
		return \MHMRentiva\Admin\Core\Utilities\RateLimiter::check( $client_ip, 'general' );
	}

	public static function register_routes(): void {
		register_rest_route(
			'mhm-rentiva/v1',
			'/availability',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( self::class, 'check' ),
				'permission_callback' => array( self::class, 'permission_check' ),
				'args'                => array(
					'vehicle_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'pickup_date'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'pickup_time'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'dropoff_date' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'dropoff_time' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Alternative vehicle suggestions endpoint
		register_rest_route(
			'mhm-rentiva/v1',
			'/availability/with-alternatives',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( self::class, 'check_with_alternatives' ),
				'permission_callback' => array( self::class, 'permission_check' ),
				'args'                => array(
					'vehicle_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'pickup_date'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'pickup_time'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'dropoff_date' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'dropoff_time' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'        => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 5,
						'minimum'           => 1,
						'maximum'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public static function check( \WP_REST_Request $request ): \WP_REST_Response {
		$vehicle_id   = $request->get_param( 'vehicle_id' );
		$pickup_date  = $request->get_param( 'pickup_date' );
		$pickup_time  = $request->get_param( 'pickup_time' );
		$dropoff_date = $request->get_param( 'dropoff_date' );
		$dropoff_time = $request->get_param( 'dropoff_time' );

		// Availability check
		$result = Util::check_availability( $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );

		// Add currency information
		$currency          = Settings::get( 'mhm_rentiva_currency', 'USD' );
		$currency_position = Settings::get( 'mhm_rentiva_currency_position', 'right_space' );
		$currency_symbol   = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol( $currency );

		$response_data = array(
			'ok'                => $result['ok'],
			'code'              => $result['code'],
			'message'           => $result['message'],
			'currency'          => $currency,
			'currency_symbol'   => $currency_symbol,
			'currency_position' => $currency_position,
		);

		// Add additional information on success
		if ( $result['ok'] ) {
			$response_data = array_merge(
				$response_data,
				array(
					'days'          => $result['days'],
					'price_per_day' => $result['price_per_day'],
					'total_price'   => $result['total_price'],
					'start_ts'      => $result['start_ts'],
					'end_ts'        => $result['end_ts'],
				)
			);
		}

		return new \WP_REST_Response( $response_data, $result['ok'] ? 200 : 400 );
	}

	public static function check_with_alternatives( \WP_REST_Request $request ): \WP_REST_Response {
		$vehicle_id   = $request->get_param( 'vehicle_id' );
		$pickup_date  = $request->get_param( 'pickup_date' );
		$pickup_time  = $request->get_param( 'pickup_time' );
		$dropoff_date = $request->get_param( 'dropoff_date' );
		$dropoff_time = $request->get_param( 'dropoff_time' );
		$limit        = $request->get_param( 'limit' ) ?: 5;

		// Advanced availability check (with alternative suggestions)
		$result = Util::check_availability_with_alternatives( $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );

		// Add currency information
		$currency          = Settings::get( 'mhm_rentiva_currency', 'USD' );
		$currency_position = Settings::get( 'mhm_rentiva_currency_position', 'right_space' );
		$currency_symbol   = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol( $currency );

		$response_data = array(
			'ok'                => $result['ok'],
			'code'              => $result['code'],
			'message'           => $result['message'],
			'currency'          => $currency,
			'currency_symbol'   => $currency_symbol,
			'currency_position' => $currency_position,
		);

		// Add additional information on success
		if ( $result['ok'] ) {
			$response_data = array_merge(
				$response_data,
				array(
					'days'          => $result['days'],
					'price_per_day' => $result['price_per_day'],
					'total_price'   => $result['total_price'],
					'start_ts'      => $result['start_ts'],
					'end_ts'        => $result['end_ts'],
				)
			);
		}

		// Add alternative vehicles (if available)
		if ( isset( $result['alternatives'] ) && ! empty( $result['alternatives'] ) ) {
			$response_data['alternatives'] = $result['alternatives'];
		}

		return new \WP_REST_Response( $response_data, $result['ok'] ? 200 : 400 );
	}
}
