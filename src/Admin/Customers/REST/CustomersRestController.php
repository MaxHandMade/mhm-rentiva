<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers\REST;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Customers\CustomersOptimizer;

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded customer queries delegated to optimizer.

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
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
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
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
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
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
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
