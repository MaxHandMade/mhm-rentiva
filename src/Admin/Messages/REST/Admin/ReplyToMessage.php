<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Admin;

use MHMRentiva\Admin\PostTypes\Message\Message;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReplyToMessage {

	/**
	 * Reply to message
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$parent_message_id = $request->get_param( 'id' );
		$reply_message     = $request->get_param( 'message' );
		$close_thread      = $request->get_param( 'close_thread' );

		$parent_meta = Message::get_message_meta( $parent_message_id );

		$reply_data = array(
			'subject'           => 'Re: ' . get_the_title( $parent_message_id ),
			'message'           => $reply_message,
			'message_type'      => 'admin_to_customer',
			'customer_email'    => $parent_meta['customer_email'],
			'customer_name'     => $parent_meta['customer_name'],
			'thread_id'         => $parent_meta['thread_id'],
			'parent_message_id' => $parent_message_id,
			'sender_id'         => get_current_user_id(),
		);

		$reply_id = Message::create_message( $reply_data );

		if ( ! $reply_id ) {
			return new WP_REST_Response( array( 'error' => __( 'Reply could not be sent', 'mhm-rentiva' ) ), 400 );
		}

		// Close thread
		if ( $close_thread ) {
			Message::update_message_status( $parent_message_id, 'closed' );
		} else {
			Message::update_message_status( $parent_message_id, 'answered' );
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => __( 'Reply sent successfully', 'mhm-rentiva' ),
				'reply_id' => $reply_id,
			),
			200
		);
	}
}
