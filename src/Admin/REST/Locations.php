<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

use MHMRentiva\Admin\Core\Utilities\RateLimiter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locations API endpoint.
 */
final class Locations {

	/**
	 * Register REST route.
	 */
	public static function register(): void {
		register_rest_route(
			'mhm-rentiva/v1',
			'/locations',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_locations' ),
				'permission_callback' => array( self::class, 'permission_check' ),
				'args'                => array(
					'service_type' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => 'both',
						'enum'              => array( 'rental', 'transfer', 'both' ),
					),
				),
			)
		);
	}

	/**
	 * Permission check with rate limiting and nonce verification.
	 */
	public static function permission_check( \WP_REST_Request $request ): bool {
		$client_ip = RateLimiter::getClientIP();
		if ( ! RateLimiter::check( $client_ip, 'general' ) ) {
			return false;
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}

		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get filtered locations.
	 */
	public static function get_locations( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$service_type = sanitize_key( (string) $request->get_param( 'service_type' ) );

		$table_name = $wpdb->prefix . 'rentiva_transfer_locations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$current_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $current_table !== $table_name ) {
			$table_name = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
		}

		$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', $table_name ) ?? '';
		if ( 'rental' === $service_type ) {
			$prepared_query = $wpdb->prepare(
				'SELECT id, name, type FROM %i WHERE is_active = 1 AND allow_rental = 1 ORDER BY priority ASC, name ASC',
				$table_name
			);
		} elseif ( 'transfer' === $service_type ) {
			$prepared_query = $wpdb->prepare(
				'SELECT id, name, type FROM %i WHERE is_active = 1 AND allow_transfer = 1 ORDER BY priority ASC, name ASC',
				$table_name
			);
		} else {
			$prepared_query = $wpdb->prepare(
				'SELECT id, name, type FROM %i WHERE is_active = 1 ORDER BY priority ASC, name ASC',
				$table_name
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $prepared_query );

		return rest_ensure_response( $results );
	}
}
