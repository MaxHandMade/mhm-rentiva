<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Utilities;

use MHMRentiva\Admin\Messages\Settings\MessagesSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common utility functions for the messaging system
 */
final class MessageUtilities {



	/**
	 * Update message status
	 */
	public static function update_message_status( int $message_id, string $new_status ): bool {
		$old_status = get_post_meta( $message_id, '_mhm_message_status', true );

		if ( $old_status === $new_status ) {
			return true; // Status unchanged
		}

		$statuses = MessagesSettings::get_statuses();
		if ( ! array_key_exists( $new_status, $statuses ) ) {
			return false;
		}

		$updated = update_post_meta( $message_id, '_mhm_message_status', $new_status );

		if ( $updated ) {
			// Trigger hook
			do_action( 'mhm_message_status_changed', $message_id, $old_status, $new_status );

			// Clear cache
			self::clear_message_cache( $message_id );
		}

		return (bool) $updated;
	}

	/**
	 * Mark message as read
	 */
	public static function mark_as_read( int $message_id ): bool {
		$updated = update_post_meta( $message_id, '_mhm_is_read', '1' );

		if ( $updated ) {
			update_post_meta( $message_id, '_mhm_read_at', current_time( 'mysql' ) );

			// Clear cache
			self::clear_message_cache( $message_id );
		}

		return (bool) $updated;
	}

	/**
	 * Clear message cache
	 */
	public static function clear_message_cache( int $message_id, ?string $email = null ): void {
		// Use cache helper
		if ( class_exists( '\MHMRentiva\Admin\Messages\Core\MessageCache' ) ) {
			\MHMRentiva\Admin\Messages\Core\MessageCache::clear_message_cache( $message_id, $email );
		}

		// General cache clearing
		wp_cache_delete( $message_id, 'posts' );
		wp_cache_delete( $message_id, 'post_meta' );
	}

	/**
	 * Send email
	 */
	public static function send_email( string $to, string $subject, string $message, array $headers = array() ): bool {
		$from_name  = MessagesSettings::get_setting( 'email_from_name', get_bloginfo( 'name' ) );
		$from_email = MessagesSettings::get_setting( 'email_from_email', get_option( 'admin_email' ) );

		$default_headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);

		$headers = array_merge( $default_headers, $headers );

		return wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Get message meta information
	 */
	public static function get_message_meta( int $message_id ): array {
		return array(
			'customer_name'  => get_post_meta( $message_id, '_mhm_customer_name', true ) ?: '',
			'customer_email' => get_post_meta( $message_id, '_mhm_customer_email', true ) ?: '',
			'category'       => get_post_meta( $message_id, '_mhm_message_category', true ) ?: 'general',
			'status'         => get_post_meta( $message_id, '_mhm_message_status', true ) ?: 'pending',
			'message_type'   => get_post_meta( $message_id, '_mhm_message_type', true ) ?: 'customer_to_admin',
			'thread_id'      => (int) get_post_meta( $message_id, '_mhm_thread_id', true ),
			'booking_id'     => (int) get_post_meta( $message_id, '_mhm_booking_id', true ),
			'is_read'        => (bool) get_post_meta( $message_id, '_mhm_is_read', true ),
			'attachments'    => get_post_meta( $message_id, '_mhm_attachments', true ) ?: array(),
		);
	}

	/**
	 * Create message
	 */
	public static function create_message( array $message_data ): ?int {
		$defaults = array(
			'subject'           => '',
			'message'           => '',
			'message_type'      => 'customer_to_admin',
			'customer_email'    => '',
			'customer_name'     => '',
			'category'          => 'general',
			'status'            => 'pending',
			'thread_id'         => 0,
			'parent_message_id' => 0,
			'booking_id'        => 0,
			'sender_id'         => 0,
		);

		$data = array_merge( $defaults, $message_data );

		// Create thread ID
		if ( $data['thread_id'] === 0 ) {
			$data['thread_id'] = wp_generate_uuid4();
		}

		// Create post
		$post_data = array(
			'post_title'   => sanitize_text_field( $data['subject'] ),
			'post_content' => wp_kses_post( $data['message'] ),
			'post_type'    => 'mhm_message',
			'post_status'  => 'publish',
			'post_author'  => $data['sender_id'],
		);

		$message_id = wp_insert_post( $post_data );

		if ( is_wp_error( $message_id ) ) {
			return null;
		}

		// Save meta information
		$meta_fields = array(
			'_mhm_customer_name'    => $data['customer_name'],
			'_mhm_customer_email'   => $data['customer_email'],
			'_mhm_message_category' => $data['category'],
			'_mhm_message_status'   => $data['status'],
			'_mhm_message_type'     => $data['message_type'],
			'_mhm_thread_id'        => $data['thread_id'],
			'_mhm_booking_id'       => $data['booking_id'],
		);

		if ( $data['parent_message_id'] > 0 ) {
			$meta_fields['_mhm_parent_message_id'] = $data['parent_message_id'];
		}

		foreach ( $meta_fields as $key => $value ) {
			update_post_meta( $message_id, $key, $value );
		}

		// Trigger hook
		do_action( 'mhm_message_created', $message_id, $data );

		// Clear cache
		self::clear_message_cache( $message_id, $data['customer_email'] );

		return $message_id;
	}

	/**
	 * Get thread messages
	 */
	public static function get_thread_messages( int $thread_id ): array {
		// Use cache helper
		if ( class_exists( '\MHMRentiva\Admin\Messages\Core\MessageQueryHelper' ) ) {
			return \MHMRentiva\Admin\Messages\Core\MessageQueryHelper::get_thread_messages( $thread_id );
		}

		// Fallback - simple query
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, 
                    pm_customer_name.meta_value as customer_name,
                    pm_customer_email.meta_value as customer_email,
                    pm_message_type.meta_value as message_type,
                    pm_status.meta_value as status,
                    pm_booking_id.meta_value as booking_id
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_customer_name ON p.ID = pm_customer_name.post_id AND pm_customer_name.meta_key = '_mhm_customer_name'
             LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id AND pm_customer_email.meta_key = '_mhm_customer_email'
             LEFT JOIN {$wpdb->postmeta} pm_message_type ON p.ID = pm_message_type.post_id AND pm_message_type.meta_key = '_mhm_message_type'
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_message_status'
             LEFT JOIN {$wpdb->postmeta} pm_booking_id ON p.ID = pm_booking_id.post_id AND pm_booking_id.meta_key = '_mhm_booking_id'
             INNER JOIN {$wpdb->postmeta} pm_thread ON p.ID = pm_thread.post_id AND pm_thread.meta_key = '_mhm_thread_id'
             WHERE pm_thread.meta_value = %s
             AND p.post_type = 'mhm_message'
             AND p.post_status = 'publish'
             ORDER BY p.post_date ASC",
				$thread_id
			)
		);
	}

	/**
	 * Get message counts
	 */
	public static function get_message_counts( ?string $email = null ): array {
		// Use cache helper
		if ( class_exists( '\MHMRentiva\Admin\Messages\Core\MessageCache' ) ) {
			return \MHMRentiva\Admin\Messages\Core\MessageCache::get_message_counts( $email );
		}

		// Fallback - simple query
		global $wpdb;

		$where_clause = "WHERE p.post_type = 'mhm_message' AND p.post_status = 'publish'";
		$params       = array();

		if ( $email ) {
			$where_clause .= ' AND pm_email.meta_value = %s';
			$params[]      = $email;
		}

		$query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN pm_status.meta_value = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN pm_status.meta_value = 'answered' THEN 1 ELSE 0 END) as answered,
                SUM(CASE WHEN pm_is_read.meta_value IS NULL OR pm_is_read.meta_value != '1' THEN 1 ELSE 0 END) as unread
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_customer_email'
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_message_status'
            LEFT JOIN {$wpdb->postmeta} pm_is_read ON p.ID = pm_is_read.post_id AND pm_is_read.meta_key = '_mhm_is_read'
            {$where_clause}
        ";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Query string built dynamically but variables prepared safely.
			$result = $wpdb->get_row( $wpdb->prepare( $query, ...$params ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Constants only.
			$result = $wpdb->get_row( $query, ARRAY_A );
		}

		return array(
			'total'    => (int) ( $result['total'] ?? 0 ),
			'pending'  => (int) ( $result['pending'] ?? 0 ),
			'answered' => (int) ( $result['answered'] ?? 0 ),
			'unread'   => (int) ( $result['unread'] ?? 0 ),
		);
	}

	/**
	 * Format message for display (date, status, category)
	 */
	public static function format_message_for_display( \WP_Post $message ): array {
		$meta       = self::get_message_meta( $message->ID );
		$categories = MessagesSettings::get_categories();
		$statuses   = MessagesSettings::get_statuses();

		return array(
			'id'             => $message->ID,
			'subject'        => $message->post_title,
			'content'        => $message->post_content,
			'date'           => $message->post_date,
			/* translators: %s placeholder. */
			'date_human'     => sprintf( __( '%s ago', 'mhm-rentiva' ), human_time_diff( strtotime( $message->post_date ), current_time( 'timestamp' ) ) ),
			'customer_name'  => $meta['customer_name'],
			'customer_email' => $meta['customer_email'],
			'category'       => $meta['category'],
			'category_label' => $categories[ $meta['category'] ] ?? $meta['category'],
			'status'         => $meta['status'],
			'status_label'   => $statuses[ $meta['status'] ] ?? $meta['status'],
			'thread_id'      => $meta['thread_id'],
			'booking_id'     => $meta['booking_id'],
			'is_read'        => $meta['is_read'],
			'preview'        => wp_trim_words( $message->post_content, 20, '...' ),
		);
	}

	/**
	 * Security check - message sending permission
	 */
	public static function can_send_message( string $email ): array {
		$result = array(
			'can_send' => true,
			'reason'   => '',
			'limits'   => array(),
		);

		// Pro version check
		if ( ! class_exists( '\MHMRentiva\Admin\Licensing\Mode' ) || ! \MHMRentiva\Admin\Licensing\Mode::isPro() ) {
			$monthly_limit = MessagesSettings::get_setting( 'lite_messages_per_month', 10 );
			$daily_limit   = MessagesSettings::get_setting( 'lite_messages_per_day', 3 );

			$counts = self::get_message_counts( $email );

			// Daily limit check
			global $wpdb;
			$today_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'mhm_message'
                 AND p.post_status = 'publish'
                 AND pm.meta_key = '_mhm_customer_email'
                 AND pm.meta_value = %s
                 AND DATE(p.post_date) = CURDATE()",
					$email
				)
			);

			if ( $today_count >= $daily_limit ) {
				$result['can_send'] = false;
				/* translators: %d placeholder. */
				$result['reason'] = sprintf( __( 'Daily message limit of %d exceeded', 'mhm-rentiva' ), $daily_limit );
			}

			if ( $counts['total'] >= $monthly_limit ) {
				$result['can_send'] = false;
				/* translators: %d placeholder. */
				$result['reason'] = sprintf( __( 'Monthly message limit of %d exceeded', 'mhm-rentiva' ), $monthly_limit );
			}

			$result['limits'] = array(
				'daily'   => array(
					'used'  => $today_count,
					'limit' => $daily_limit,
				),
				'monthly' => array(
					'used'  => $counts['total'],
					'limit' => $monthly_limit,
				),
			);
		}

		return $result;
	}

	/**
	 * Load template file
	 */
	public static function load_template( string $template_name, array $variables = array() ): string {
		$template_path = MHM_RENTIVA_PLUGIN_PATH . 'templates/messages/' . $template_name;

		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		// Extract variables
		extract( $variables );

		ob_start();
		include $template_path;
		return ob_get_clean();
	}

	/**
	 * Write debug log
	 */
	public static function debug_log( string $message, array $data = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[MHM Messages] ' . $message . ' ' . json_encode( $data ) );
		}
	}
}
