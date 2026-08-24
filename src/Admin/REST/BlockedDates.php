<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only blocked-date lookup for the public availability calendar.
 *
 * This used to be `wp_ajax_nopriv_mhmrentiva_get_blocked_dates`, where the
 * only place the "anyone may call this" decision was written down was a
 * `phpcs:ignore` comment above the `$_GET` read. Moving it to REST lets the
 * route itself carry the decision: the vehicle id is a validated route
 * parameter, and the permission gate is an explicit `__return_true` with the
 * reason next to it.
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
				// The callback enforces that "public vehicle page" literally exists --
				// see get_blocked_dates(): anything a logged-out visitor could not
				// already read is answered with the same 404 as a bad id, so the route
				// discloses nothing beyond the published vehicle it is written for.
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

		/*
		 * The reasoning that used to live in this class's own
		 * is_publicly_readable_vehicle() now lives on the shared accessor -- it
		 * was the correct rule, and every other public surface needed the same
		 * one. Keeping a private copy here would have made this route the place
		 * the rule was written down and the other surfaces the places it was
		 * forgotten, which is exactly how they came to disagree.
		 *
		 * Every rejection still returns the same 404 as a bad id, so the
		 * response does not distinguish "not a vehicle" from "a vehicle you may
		 * not see".
		 */
		if ( ! VehicleDataHelper::is_publicly_readable( $vehicle_id ) ) {
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
