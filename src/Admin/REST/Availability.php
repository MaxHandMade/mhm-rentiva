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
	 * Permission callback — deliberately PUBLIC, not capability-gated.
	 *
	 * `/availability` and `/availability/with-alternatives` answer "is this
	 * vehicle free for these dates." The response contains only availability
	 * status, pricing, and currency formatting (see check()/
	 * check_with_alternatives()) — no PII, no customer/vendor data, and no
	 * write side-effects — so there is nothing here a `current_user_can()`
	 * gate would protect; a public REST API for availability lookups is
	 * documented in README.md's "Authentication" / "Key Endpoints" sections.
	 *
	 * It is intentionally NOT `__return_true`, though: the request must still
	 * carry a valid `wp_rest` nonce (X-WP-Nonce) verified by
	 * `AuthHelper::verifyAuth()` and stay within `RateLimiter`'s per-IP
	 * budget. Neither check requires login or a capability — a `wp_rest`
	 * nonce is valid for logged-out visitors too — so this keeps the route
	 * same-origin-scoped and rate-limited against blind scripted/bulk
	 * scraping without turning it into an authorization gate.
	 *
	 * Caller note (re-measured 2026-08-13, mechanism now traced exactly): an
	 * earlier version of this note named `UnifiedSearch` as the only in-repo
	 * source of a `wp_rest` nonce for this route, then a later revision
	 * widened that to three page-scoped call sites. Both undersold it — one
	 * of the three is not page-scoped at all:
	 *
	 *   - `AbstractAccountShortcode::enqueue_assets()`
	 *     (Frontend/Shortcodes/Account/AbstractAccountShortcode.php:63)
	 *     localizes `restNonce` with NO page-type check at all — it fires
	 *     whenever `MyBookings` or `PaymentHistory` renders anywhere.
	 *   - `AccountController::enqueue_assets()`
	 *     (Frontend/Account/AccountController.php) has exactly one
	 *     conditional that gates page-scoped output — `if ($is_account ||
	 *     $has_endpoint || $is_woocommerce_account)` at :331-361 — but that
	 *     conditional wraps only the `wp_enqueue_style()` calls (the CSS).
	 *     The script enqueue and the `wp_localize_script( 'mhm-rentiva-my-
	 *     account', 'mhmRentivaAccount', [ …, 'restNonce' =>
	 *     wp_create_nonce('wp_rest'), … ] )` call sit at :363-384, textually
	 *     after and OUTSIDE that `if` block, so they run whether or not it
	 *     did. And `enqueue_assets()` itself is hooked to `wp_enqueue_scripts`
	 *     unconditionally at :123 — no page-type guard on the hook either.
	 *     Net effect: this nonce is minted and printed on every front-end
	 *     page, for every visitor, logged in or not — not just on account
	 *     pages or endpoints.
	 *   - `UnifiedSearch::…` (Frontend/Shortcodes/UnifiedSearch.php:145),
	 *     scoped to pages carrying `rentiva_unified_search`.
	 *
	 * Live-verified 2026-08-13 against the Docker dev site: an anonymous GET
	 * of `/hakkimizda/` — a page with none of this plugin's shortcodes, so
	 * none of the page-scoped conditions above should fire — still returned
	 * a working `restNonce` in the page source (via the unconditional
	 * `AccountController` path traced above), and replaying it as
	 * `X-WP-Nonce` against this route logged-out passed `permission_check()`
	 * (the request only failed later, on missing query parameters). Treat a
	 * harvestable `wp_rest` nonce as present on every front-end page, not
	 * just the three call sites above.
	 *
	 * Treat this route as anonymously callable in practice. That is why the
	 * callbacks below gate the vehicle id on
	 * `VehicleDataHelper::is_publicly_readable()` rather than relying on the
	 * nonce to imply an authorized caller — a nonce proves same-origin, never
	 * authorization.
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

	/**
	 * Public-surface gate for the vehicle id, shaped as a normal "not found".
	 *
	 * `Util::check_availability()` deliberately does NOT carry this check: it is
	 * shared with the admin manual-booking flow, where staff legitimately price
	 * and schedule a vehicle that is still a draft. The restriction belongs to
	 * the PUBLIC entry points, which is here.
	 *
	 * Returning the helper's own `vehicle_not_found` payload (rather than a 403)
	 * is deliberate: an unpublished vehicle must be indistinguishable from an id
	 * that does not exist, otherwise the difference in responses is itself an
	 * enumeration oracle for unpublished inventory.
	 *
	 * @return array{ok:bool,code:string,message:string}|null Null when allowed.
	 */
	private static function reject_non_public_vehicle( int $vehicle_id ): ?array {
		if ( \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::is_publicly_readable( $vehicle_id ) ) {
			return null;
		}

		return array(
			'ok'      => false,
			'code'    => 'vehicle_not_found',
			'message' => __( 'Selected vehicle not found. Please select a valid vehicle.', 'mhm-rentiva' ),
		);
	}

	public static function check( \WP_REST_Request $request ): \WP_REST_Response {
		$vehicle_id   = $request->get_param( 'vehicle_id' );
		$pickup_date  = $request->get_param( 'pickup_date' );
		$pickup_time  = $request->get_param( 'pickup_time' );
		$dropoff_date = $request->get_param( 'dropoff_date' );
		$dropoff_time = $request->get_param( 'dropoff_time' );

		// Availability check
		$result = self::reject_non_public_vehicle( (int) $vehicle_id )
			?? Util::check_availability( $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );

		// Add currency information
		$currency          = Settings::get( 'mhmrentiva_currency', 'USD' );
		$currency_position = Settings::get( 'mhmrentiva_currency_position', 'right_space' );
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
		$result = self::reject_non_public_vehicle( (int) $vehicle_id )
			?? Util::check_availability_with_alternatives( $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );

		// Add currency information
		$currency          = Settings::get( 'mhmrentiva_currency', 'USD' );
		$currency_position = Settings::get( 'mhmrentiva_currency_position', 'right_space' );
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
