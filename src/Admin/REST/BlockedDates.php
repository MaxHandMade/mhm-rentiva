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

	/**
	 * Is this id a vehicle whose page a logged-out visitor could already read?
	 *
	 * The post-type check alone was not enough to keep the route's own "public"
	 * justification true. A draft, pending, private or trashed vehicle has no
	 * public page, so answering 200 for it would both disclose unpublished
	 * business data and let an anonymous caller enumerate which post ids are
	 * unpublished vehicles (200 for those, 404 for everything else). A
	 * password-protected vehicle counts as viewable to core but hides its
	 * content behind the password, so its schedule stays behind it too.
	 *
	 * Every rejection returns the same 404 as a bad id, so the response does not
	 * distinguish "not a vehicle" from "a vehicle you may not see".
	 */
	private static function is_publicly_readable_vehicle( int $vehicle_id ): bool {
		$post = get_post( $vehicle_id );

		if ( ! $post instanceof \WP_Post || Vehicle::POST_TYPE !== $post->post_type ) {
			return false;
		}

		if ( '' !== (string) $post->post_password ) {
			return false;
		}

		return is_post_publicly_viewable( $post );
	}

	public static function get_blocked_dates( \WP_REST_Request $request ): \WP_REST_Response {
		$vehicle_id = (int) $request['id'];

		if ( ! self::is_publicly_readable_vehicle( $vehicle_id ) ) {
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
