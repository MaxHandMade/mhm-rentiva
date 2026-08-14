<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers\REST;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Customers\CustomerIdentity;
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
				// and strong enough for the data class. `manage_options` is too blunt --
				// it is effectively an administrator check rather than a capability tied to
				// this data -- and `list_users` is too weak, since it permits listing users
				// without implying access to their personal data. `edit_users` satisfies
				// both constraints: capability-based, and scoped to what is returned.
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
		$id = (int) $request->get_param( 'id' );

		// Same shape as bulk_delete, read side: the route gate is edit_users,
		// which says nothing about WHICH account, and this returns a full
		// customer profile. Refuse with the same 404 the not-found path uses
		// rather than a 403 -- a distinct status would turn this route into a
		// probe for which arbitrary user IDs exist.
		if ( ! current_user_can( 'edit_user', $id ) || ! CustomerIdentity::is_customer( $id ) ) {
			return new \WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'mhm-rentiva' ),
				array( 'status' => 404 )
			);
		}

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
			// Per target, not once for the batch. delete_users says the caller
			// may delete users; it does not say which, and it was the only thing
			// standing between this route and any account on the site -- an
			// editor, a second administrator -- which is what WordPress.org's T8
			// review found. WordPress models the per-target question as the meta
			// cap delete_user( $id ), so ask it, and ask whether the account is
			// this plugin's to delete at all. Neither check implies the other:
			// the cap is about the caller, CustomerIdentity is about the target.
			if ( ! current_user_can( 'delete_user', $id ) || ! CustomerIdentity::is_customer( $id ) ) {
				++$skipped;
				continue;
			}

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
