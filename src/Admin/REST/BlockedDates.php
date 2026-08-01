<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only blocked-date lookup for the public availability calendar.
 *
 * This used to be `wp_ajax_nopriv_mhm_rentiva_get_blocked_dates`, where the
 * only place the "anyone may call this" decision was written down was a
 * `phpcs:ignore` comment above the `$_GET` read. Moving it to REST lets the
 * route itself carry the decision: the vehicle id is a validated route
 * parameter, and the permission gate is an explicit `__return_true` with the
 * reason next to it (WP.org T7).
 */
final class BlockedDates {

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'mhm-rentiva/v1',
			'/vehicles/(?P<id>\d+)/blocked-dates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_blocked_dates' ),
				// Intentionally public: feeds the public availability calendar. Read-only,
				// returns only date strings already rendered on the public vehicle page.
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'required'          => true,
					),
				),
			)
		);
	}

	public static function get_blocked_dates( \WP_REST_Request $request ): \WP_REST_Response {
		$vehicle_id = (int) $request['id'];

		if ( get_post_type( $vehicle_id ) !== Vehicle::POST_TYPE ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => 'Invalid vehicle ID',
				),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => BlockedDatesMetaBox::get_blocked_dates( $vehicle_id ),
			),
			200
		);
	}
}
