<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers\REST;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Customers\CustomersOptimizer;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded customer queries delegated to optimizer.

final class CustomersRestController {

	private const SORT_WHITELIST = array( 'name', 'email', 'bookings', 'total_spent', 'last_booking', 'date' );

	public static function register_routes(): void
	{
		register_rest_route(
			'mhm-rentiva/v1',
			'/customers',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_list' ),
				// Returns private customer PII (name, email, phone, address) plus booking
				// and total-spend data. Gated on `edit_users`: a capability (not a role),
				// and strong enough for the data class. History: T4 asked us to move off
				// the blanket `manage_options`, so this was `list_users`; T6 then flagged
				// `list_users` as too weak for PII, so it is now `edit_users` — which
				// satisfies both (capability-based, and scoped to the data returned).
				'permission_callback' => fn() => current_user_can( 'edit_users' ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sort_by'  => array(
						'type'              => 'string',
						'default'           => 'last_booking',
						'sanitize_callback' => 'sanitize_key',
					),
					'sort_dir' => array(
						'type'              => 'string',
						'default'           => 'desc',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'mhm-rentiva/v1',
			'/customers/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_detail' ),
				// Same private-PII data class as the list route above (name, email, phone,
				// address, bookings, spend), so the same capability applies: `edit_users`.
				'permission_callback' => fn() => current_user_can( 'edit_users' ),
				'args'                => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);

		register_rest_route(
			'mhm-rentiva/v1',
			'/customers/bulk',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'bulk_delete' ),
				// Deletes real WordPress user accounts, so the route-level gate
				// matches the operation's WP capability directly (mirrors the
				// handler-body defense-in-depth guard below).
				'permission_callback' => fn() => current_user_can( 'delete_users' ),
			)
		);
	}

	public static function get_list( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error
	{
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		$search   = (string) $request->get_param( 'search' );
		$sort_by  = (string) $request->get_param( 'sort_by' );
		$sort_dir = (string) $request->get_param( 'sort_dir' );

		if ( ! in_array( $sort_by, self::SORT_WHITELIST, true ) ) {
			return new \WP_Error(
				'invalid_sort_by',
				__( 'Invalid sort_by value.', 'mhm-rentiva' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $sort_dir, array( 'asc', 'desc' ), true ) ) {
			$sort_dir = 'desc';
		}

		$result = CustomersOptimizer::get_customers_optimized( $page, $per_page, $search, $sort_by, $sort_dir );

		return new \WP_REST_Response(
			array(
				'items'       => $result['customers'] ?? array(),
				'total'       => $result['total'] ?? 0,
				'total_pages' => $result['total_pages'] ?? 0,
				'page'        => $result['page'] ?? $page,
			),
			200
		);
	}

	public static function get_detail( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error
	{
		$id   = (int) $request->get_param( 'id' );
		$data = CustomersOptimizer::get_customer_details_optimized( $id );

		if ( null === $data ) {
			return new \WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'mhm-rentiva' ),
				array( 'status' => 404 )
			);
		}

		return new \WP_REST_Response( $data, 200 );
	}

	public static function bulk_delete( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error
	{
		// Deleting customers deletes real WordPress user accounts. Defense-in-depth
		// operation guard on delete_users, independent of the route's
		// permission_callback.
		if ( ! current_user_can( 'delete_users' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to delete customers.', 'mhm-rentiva' ),
				array( 'status' => 403 )
			);
		}

		$body    = $request->get_json_params();
		$raw_ids = isset( $body['ids'] ) ? (array) $body['ids'] : array();

		if ( empty( $raw_ids ) ) {
			return new \WP_Error(
				'empty_ids',
				__( 'ids must be a non-empty array.', 'mhm-rentiva' ),
				array( 'status' => 400 )
			);
		}

		$ids     = array_values( array_filter( array_map( 'intval', $raw_ids ), fn( $id ) => $id > 1 ) ); // Skip user ID 1 silently.
		$deleted = 0;
		$skipped = count( $raw_ids ) - count( $ids );

		foreach ( $ids as $id ) {
			if ( wp_delete_user( $id ) ) {
				++$deleted;
			} else {
				++$skipped;
			}
		}

		CustomersOptimizer::clear_cache();

		return new \WP_REST_Response(
			array(
				'deleted' => $deleted,
				'skipped' => $skipped,
			),
			200
		);
	}
}
