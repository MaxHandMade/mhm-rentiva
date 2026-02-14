<?php

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Close message endpoint intentionally performs bounded ownership/status checks.

namespace MHMRentiva\Admin\Messages\REST\Customer;

use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Messages\Core\MessageCache;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CloseMessage {

	/**
	 * Customer close message (WordPress User Auth)
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		// WordPress user authentication check
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response(
				array(
					'error' => __( 'Please login to close messages.', 'mhm-rentiva' ),
				),
				401
			);
		}

		$user           = wp_get_current_user();
		$customer_email = $user->user_email;
		$thread_id      = $request->get_param( 'thread_id' );

		if ( ! $thread_id ) {
			return new WP_REST_Response( array( 'error' => __( 'Thread ID is required.', 'mhm-rentiva' ) ), 400 );
		}

		// Get main message (root of thread) - thread_id can be UUID or integer
		global $wpdb;
		$main_message_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_thread ON p.ID = pm_thread.post_id AND pm_thread.meta_key = '_mhm_thread_id' AND pm_thread.meta_value = %s
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_customer_email' AND pm_email.meta_value = %s
             LEFT JOIN {$wpdb->postmeta} pm_parent ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_mhm_parent_message_id'
             WHERE p.post_type = 'mhm_message'
             AND p.post_status = 'publish'
             AND (pm_parent.meta_value IS NULL OR pm_parent.meta_value = '' OR pm_parent.meta_value = '0')
             LIMIT 1",
				$thread_id,
				$customer_email
			)
		);

		if ( ! $main_message_id ) {
			return new WP_REST_Response( array( 'error' => __( 'Message not found or access denied.', 'mhm-rentiva' ) ), 404 );
		}

		// Update status to closed
		$updated = Message::update_message_status( $main_message_id, 'closed' );

		if ( ! $updated ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to close message.', 'mhm-rentiva' ) ), 400 );
		}

		// Clear cache
		MessageCache::clear_message_cache( $main_message_id, $customer_email );
		MessageCache::flush();

		return new WP_REST_Response(
			array(
				'success'    => true,
				'message'    => __( 'Message has been closed successfully.', 'mhm-rentiva' ),
				'message_id' => $main_message_id,
			),
			200
		);
	}
}
