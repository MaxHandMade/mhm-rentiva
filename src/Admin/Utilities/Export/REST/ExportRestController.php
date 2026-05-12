<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Export\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Query;

final class ExportRestController {

	private const REST_NAMESPACE = 'mhm-rentiva/v1';
	private const BASE           = '/admin/export';
	private const TRANSIENT_KEY  = 'mhm_rentiva_export_history';
	private const ALLOWED_TYPES  = array( 'vehicle_booking', 'vehicle', 'mhm_app_log' );

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		$perm = array( self::class, 'check_permission' );

		register_rest_route(
			self::REST_NAMESPACE,
			self::BASE . '/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_history' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::BASE . '/(?P<id>[^/]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'delete_entry' ),
				'permission_callback' => $perm,
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::BASE . '/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => $perm,
				'args'                => array(
					'post_type' => array(
						'type'              => 'string',
						'required'          => true,
						'enum'              => self::ALLOWED_TYPES,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_key',
					),
					'date_from' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function get_history( WP_REST_Request $request ): WP_REST_Response {
		$history = get_transient( self::TRANSIENT_KEY );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		return new WP_REST_Response(
			array(
				'history' => $history,
				'total'   => count( $history ),
			),
			200
		);
	}

	public static function delete_entry( WP_REST_Request $request ): WP_REST_Response {
		$export_id = $request->get_param( 'id' );
		$history   = get_transient( self::TRANSIENT_KEY );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$index = -1;
		foreach ( $history as $i => $entry ) {
			$matches_id   = isset( $entry['id'] ) && $entry['id'] === $export_id;
			$matches_date = isset( $entry['date'] ) && $entry['date'] === $export_id;
			if ( $matches_id || $matches_date ) {
				$index = $i;
				break;
			}
		}

		if ( $index < 0 ) {
			return new WP_REST_Response( array( 'message' => 'Export entry not found.' ), 404 );
		}

		array_splice( $history, $index, 1 );
		set_transient( self::TRANSIENT_KEY, $history, WEEK_IN_SECONDS );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public static function preview( WP_REST_Request $request ): WP_REST_Response {
		$post_type = $request->get_param( 'post_type' );
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		if ( $date_from || $date_to ) {
			$date_query = array( 'inclusive' => true );
			if ( $date_from ) {
				$date_query['after'] = $date_from;
			}
			if ( $date_to ) {
				$date_query['before'] = $date_to;
			}
			$args['date_query'] = array( $date_query );
		}

		$count_query = new WP_Query( $args );
		$all_ids     = $count_query->posts;
		$total       = count( $all_ids );
		$sample_ids  = array_slice( $all_ids, 0, 5 );

		$sample = array();
		foreach ( $sample_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$sample[] = array(
				'id'     => $post->ID,
				'date'   => $post->post_date,
				'status' => get_post_meta( $post->ID, '_mhm_status', true ) ?: $post->post_status,
			);
		}

		return new WP_REST_Response(
			array(
				'count'  => $total,
				'sample' => $sample,
			),
			200
		);
	}
}
