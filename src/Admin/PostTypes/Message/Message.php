<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Message;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Message post type handlers intentionally run bounded message/meta lookups.



// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Public/legacy hook names kept stable for compatibility.

use MHMRentiva\Admin\PostTypes\Utilities\ClientUtilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Message {



	public const POST_TYPE = 'mhm_message';

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_now' ) );
	}

	public static function register_now(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => self::labels(),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false, // Hidden in admin menu, managed via custom page
				'show_in_rest'    => false, // Hidden from REST API
				'supports'        => array( 'title', 'editor', 'author' ),
				'capability_type' => 'post',
				'capabilities'    => array(
					'create_posts' => 'manage_options', // Only admins can create
				),
				'map_meta_cap'    => true,
			)
		);
	}

	private static function labels(): array {
		return array(
			'name'                  => __( 'Messages', 'mhm-rentiva' ),
			'singular_name'         => __( 'Message', 'mhm-rentiva' ),
			'menu_name'             => __( 'Messages', 'mhm-rentiva' ),
			'name_admin_bar'        => __( 'Message', 'mhm-rentiva' ),
			'add_new'               => __( 'New Message', 'mhm-rentiva' ),
			'add_new_item'          => __( 'Add New Message', 'mhm-rentiva' ),
			'edit_item'             => __( 'Edit Message', 'mhm-rentiva' ),
			'new_item'              => __( 'New Message', 'mhm-rentiva' ),
			'view_item'             => __( 'View Message', 'mhm-rentiva' ),
			'search_items'          => __( 'Search Messages', 'mhm-rentiva' ),
			'not_found'             => __( 'No messages found', 'mhm-rentiva' ),
			'not_found_in_trash'    => __( 'No messages found in Trash', 'mhm-rentiva' ),
			'all_items'             => __( 'All Messages', 'mhm-rentiva' ),
			'archives'              => __( 'Message Archive', 'mhm-rentiva' ),
			'attributes'            => __( 'Message Attributes', 'mhm-rentiva' ),
			'insert_into_item'      => __( 'Insert into message', 'mhm-rentiva' ),
			'uploaded_to_this_item' => __( 'Uploaded to this message', 'mhm-rentiva' ),
			'featured_image'        => __( 'Featured Image', 'mhm-rentiva' ),
			'set_featured_image'    => __( 'Set featured image', 'mhm-rentiva' ),
			'remove_featured_image' => __( 'Remove featured image', 'mhm-rentiva' ),
			'use_featured_image'    => __( 'Use as featured image', 'mhm-rentiva' ),
		);
	}

	/**
	 * Create new message
	 */
	public static function create_message( array $data ): int {
		$message_data = array(
			'post_title'   => sanitize_text_field( (string) ( ( $data['subject'] ?? __( 'New Message', 'mhm-rentiva' ) ) ?: __( 'New Message', 'mhm-rentiva' ) ) ),
			'post_content' => wp_kses_post( $data['message'] ?? '' ),
			'post_status'  => 'publish',
			'post_type'    => self::POST_TYPE,
			'post_author'  => $data['sender_id'] ?? get_current_user_id(),
		);

		$message_id = wp_insert_post( $message_data );

		if ( ! is_wp_error( $message_id ) && $message_id ) {
			// Save meta data
			self::save_message_meta( $message_id, $data );

			// Create thread ID (for new thread)
			if ( empty( $data['thread_id'] ) ) {
				// Use message ID as thread ID for new threads
				update_post_meta( $message_id, '_mhm_thread_id', (string) $message_id );
			} else {
				// Ensure thread_id is saved as string (supports both UUID and integer)
				update_post_meta( $message_id, '_mhm_thread_id', (string) $data['thread_id'] );
			}

			do_action( 'mhm_message_created', $message_id, $data );
		}

		return $message_id;
	}

	/**
	 * Save message meta data
	 */
	private static function save_message_meta( int $message_id, array $data ): void {
		$meta_fields = array(
			'_mhm_message_type'      => $data['message_type'] ?? 'customer_to_admin',
			'_mhm_message_status'    => $data['status'] ?? 'pending',
			'_mhm_message_category'  => $data['category'] ?? 'general',
			'_mhm_message_priority'  => sanitize_key( $data['priority'] ?? 'normal' ),
			'_mhm_customer_email'    => sanitize_email( (string) ( ( $data['customer_email'] ?? '' ) ?: '' ) ),
			'_mhm_customer_name'     => sanitize_text_field( (string) ( ( $data['customer_name'] ?? '' ) ?: '' ) ),
			'_mhm_booking_id'        => absint( $data['booking_id'] ?? 0 ),
			'_mhm_thread_id'         => ! empty( $data['thread_id'] ) ? $data['thread_id'] : $message_id, // Can be UUID string or integer
			'_mhm_parent_message_id' => absint( $data['parent_message_id'] ?? 0 ),
			'_mhm_is_read'           => 0,
			'_mhm_read_at'           => '',
			'_mhm_ip_address'        => ClientUtilities::get_client_ip(),
			'_mhm_user_agent'        => sanitize_text_field( wp_unslash( (string) ( ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ?: '' ) ) ),
		);

		foreach ( $meta_fields as $key => $value ) {
			update_post_meta( $message_id, $key, $value );
		}

		// Save attachments if provided
		if ( ! empty( $data['attachments'] ) && is_array( $data['attachments'] ) ) {
			update_post_meta( $message_id, '_mhm_attachments', $data['attachments'] );
		}
	}

	/**
	 * Get all messages in the thread
	 */
	public static function get_thread_messages( $thread_id ): array {
		// Normalize thread_id to string for comparison
		$thread_id = (string) $thread_id;

		// Use meta_query for better compatibility with string/UUID thread_id
		// Also try to match both string and numeric versions
		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_mhm_thread_id',
					'value'   => $thread_id,
					'compare' => '=',
				),
				// Also match if thread_id is stored as integer
				array(
					'key'     => '_mhm_thread_id',
					'value'   => is_numeric( $thread_id ) ? (int) $thread_id : $thread_id,
					'compare' => '=',
				),
			),
			'orderby'        => 'date',
			'order'          => 'ASC',
			'no_found_rows'  => true, // Optimize query
		);

		$query    = new \WP_Query( $query_args );
		$messages = $query->posts;

		// If no messages found with meta_query, try direct SQL query as fallback
		if ( empty( $messages ) ) {
			global $wpdb;
			$post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.* FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND pm.meta_key = '_mhm_thread_id'
                AND (pm.meta_value = %s OR pm.meta_value = %d)
                ORDER BY p.post_date ASC",
					self::POST_TYPE,
					$thread_id,
					is_numeric( $thread_id ) ? (int) $thread_id : 0
				)
			);
			if ( ! empty( $post_ids ) ) {
				$messages = array_map( 'get_post', $post_ids );
				$messages = array_filter( $messages ); // Remove null posts
			}
		}

		// Attach meta to each message and ensure post_content is loaded
		foreach ( $messages as &$message ) {
			if ( ! $message || ! isset( $message->ID ) ) {
				continue;
			}

			// Ensure post_content is loaded (WP_Query should include it, but double-check)
			if ( ! isset( $message->post_content ) || $message->post_content === null || $message->post_content === '' ) {
				$message->post_content = get_post_field( 'post_content', $message->ID );
			}

			// Ensure post_content is not empty string - reload if needed
			if ( empty( $message->post_content ) ) {
				$reloaded = get_post( $message->ID );
				if ( $reloaded && ! empty( $reloaded->post_content ) ) {
					$message->post_content = $reloaded->post_content;
				}
			}

			$message->meta = self::get_message_meta( $message->ID );
		}

		wp_reset_postdata(); // Reset query data

		return $messages;
	}

	/**
	 * Get message meta data
	 */
	public static function get_message_meta( int $message_id ): array {
		return array(
			'message_type'      => get_post_meta( $message_id, '_mhm_message_type', true ),
			'status'            => get_post_meta( $message_id, '_mhm_message_status', true ),
			'category'          => get_post_meta( $message_id, '_mhm_message_category', true ),
			'priority'          => get_post_meta( $message_id, '_mhm_message_priority', true ) ?: 'normal',
			'customer_email'    => get_post_meta( $message_id, '_mhm_customer_email', true ),
			'customer_name'     => get_post_meta( $message_id, '_mhm_customer_name', true ),
			'booking_id'        => (int) get_post_meta( $message_id, '_mhm_booking_id', true ),
			'thread_id'         => (int) get_post_meta( $message_id, '_mhm_thread_id', true ),
			'parent_message_id' => (int) get_post_meta( $message_id, '_mhm_parent_message_id', true ),
			'is_read'           => (bool) get_post_meta( $message_id, '_mhm_is_read', true ),
			'read_at'           => get_post_meta( $message_id, '_mhm_read_at', true ),
			'attachments'       => get_post_meta( $message_id, '_mhm_attachments', true ) ?: array(),
		);
	}

	/**
	 * Update message status
	 */
	public static function update_message_status( int $message_id, string $status ): bool {
		$allowed_statuses = array( 'pending', 'answered', 'closed' );

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}

		$old_status = get_post_meta( $message_id, '_mhm_message_status', true );

		if ( $old_status === $status ) {
			return true; // Already the same status
		}

		$updated = update_post_meta( $message_id, '_mhm_message_status', $status );

		if ( $updated ) {
			// Clear cache
			if ( class_exists( \MHMRentiva\Admin\Messages\Core\MessageCache::class ) ) {
				\MHMRentiva\Admin\Messages\Core\MessageCache::clear_message_cache( $message_id );
			}

			// WordPress post cache'ini de temizle
			clean_post_cache( $message_id );

			do_action( 'mhm_message_status_changed', $message_id, $old_status, $status );
		}

		return $updated;
	}

	/**
	 * Mark message as read
	 */
	public static function mark_as_read( int $message_id ): bool {
		update_post_meta( $message_id, '_mhm_is_read', 1 );
		update_post_meta( $message_id, '_mhm_read_at', current_time( 'mysql' ) );

		do_action( 'mhm_message_read', $message_id );

		return true;
	}

	/**
	 * Get client IP address
	 */
	private static function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip_list = explode( ',', sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) ) );
				foreach ( $ip_list as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						return $ip;
					}
				}
			}
		}

		return ClientUtilities::get_client_ip();
	}

	/**
	 * Allowed message categories
	 * Get real settings from MessagesSettings
	 */
	public static function get_categories(): array {
		if ( class_exists( \MHMRentiva\Admin\Messages\Settings\MessagesSettings::class ) ) {
			return \MHMRentiva\Admin\Messages\Settings\MessagesSettings::get_categories();
		}

		// Fallback: If MessagesSettings doesn't exist, use default values
		return array(
			'general'   => __( 'General', 'mhm-rentiva' ),
			'booking'   => __( 'Booking', 'mhm-rentiva' ),
			'payment'   => __( 'Payment', 'mhm-rentiva' ),
			'technical' => __( 'Technical', 'mhm-rentiva' ),
			'complaint' => __( 'Complaint', 'mhm-rentiva' ),
			'refund'    => __( 'Refund', 'mhm-rentiva' ),
		);
	}

	/**
	 * Allowed message statuses
	 * Get real settings from MessagesSettings
	 */
	public static function get_statuses(): array {
		if ( class_exists( \MHMRentiva\Admin\Messages\Settings\MessagesSettings::class ) ) {
			return \MHMRentiva\Admin\Messages\Settings\MessagesSettings::get_statuses();
		}

		// Fallback: If MessagesSettings doesn't exist, use default values
		return array(
			'pending'  => __( 'Pending', 'mhm-rentiva' ),
			'answered' => __( 'Answered', 'mhm-rentiva' ),
			'closed'   => __( 'Closed', 'mhm-rentiva' ),
		);
	}
}
