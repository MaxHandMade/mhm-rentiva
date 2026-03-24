<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\PostTypes\Message\Message;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GetMessage {

	/**
	 * Single message details
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$message_id = $request->get_param( 'id' );

		$message = get_post( $message_id );
		if ( ! $message || $message->post_type !== Message::POST_TYPE ) {
			return new WP_REST_Response( array( 'error' => __( 'Message not found', 'mhm-rentiva' ) ), 404 );
		}

		// Ensure post_content is not null for API response
		if ( $message->post_content === null ) {
			$message->post_content = '';
		}

		$meta            = Message::get_message_meta( $message_id );
		$thread_messages = Message::get_thread_messages( $meta['thread_id'] );

		return new WP_REST_Response(
			array(
				'message' => $message,
				'meta'    => $meta,
				'thread'  => $thread_messages,
			),
			200
		);
	}
}
