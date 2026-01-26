<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Customer;

use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Messages\REST\Helpers\Auth;
use MHMRentiva\Admin\Messages\REST\Helpers\MessageFormatter;
use MHMRentiva\Admin\Messages\REST\Helpers\MessageQuery;
use MHMRentiva\Admin\Messages\Core\MessageCache;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GetThread {

	/**
	 * Customer thread viewing (WordPress User Auth)
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		// WordPress user authentication check
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response(
				array(
					'error' => __( 'Please login to access thread.', 'mhm-rentiva' ),
				),
				401
			);
		}

		$user           = wp_get_current_user();
		$customer_email = $user->user_email;

		$thread_id       = $request->get_param( 'thread_id' );
		$thread_messages = Message::get_thread_messages( $thread_id );

		// Show only messages belonging to this customer
		$customer_messages = array_filter(
			$thread_messages,
			function ( $message ) use ( $customer_email ) {
				$meta = Message::get_message_meta( $message->ID );
				return $meta['customer_email'] === $customer_email;
			}
		);

		if ( empty( $customer_messages ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Thread not found', 'mhm-rentiva' ) ), 404 );
		}

		// Get subject from first message
		$first_message = reset( $customer_messages );
		$subject       = $first_message->post_title;

		// Mark all admin replies in this thread as read (customer viewed the thread)
		foreach ( $thread_messages as $message ) {
			$meta = Message::get_message_meta( $message->ID );
			if ( $meta['message_type'] === 'admin_to_customer' ) {
				Message::mark_as_read( $message->ID );
			}
		}

		// Format messages
		$formatted_messages = MessageFormatter::formatThreadMessages( $thread_messages, $customer_email );

		// Check thread status
		$thread_status = MessageFormatter::getThreadStatus( $thread_messages );

		// Clear cache so the message list updates (removes "New" badge)
		MessageCache::clear_message_cache( null, $customer_email );
		MessageCache::flush();

		return new WP_REST_Response(
			array(
				'thread_id' => $thread_id, // Keep as string if UUID, or int if numeric
				'subject'   => $subject,
				'messages'  => $formatted_messages,
				'can_reply' => $thread_status['can_reply'],
				'status'    => $thread_status['status'],
			),
			200
		);
	}
}
