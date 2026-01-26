<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Message URL Helper
 *
 * Dynamic URL generation for message-related pages
 * Eliminates hardcoded URLs
 */
final class MessageUrlHelper {

	/**
	 * Get messages list page URL
	 */
	public static function get_messages_list_url(): string {
		return self::get_admin_page_url( 'mhm-rentiva-messages' );
	}

	/**
	 * Get message view URL
	 */
	public static function get_message_view_url( int $message_id ): string {
		return self::get_admin_page_url(
			'mhm-rentiva-messages',
			array(
				'action' => 'view',
				'id'     => $message_id,
			)
		);
	}

	/**
	 * Get message edit URL
	 */
	public static function get_message_edit_url( int $message_id ): string {
		return self::get_admin_page_url(
			'mhm-rentiva-messages',
			array(
				'action' => 'edit',
				'id'     => $message_id,
			)
		);
	}

	/**
	 * Get message reply URL
	 */
	public static function get_message_reply_url( int $message_id ): string {
		return self::get_admin_page_url(
			'mhm-rentiva-messages',
			array(
				'action' => 'reply',
				'id'     => $message_id,
			)
		);
	}

	/**
	 * Get new message URL
	 */
	public static function get_new_message_url(): string {
		return self::get_admin_page_url(
			'mhm-rentiva-messages',
			array(
				'action' => 'new-message',
			)
		);
	}

	/**
	 * Get messages settings URL
	 */
	public static function get_messages_settings_url( ?string $tab = null ): string {
		$params = array();
		if ( $tab ) {
			$params['tab'] = $tab;
		}
		return self::get_admin_page_url( 'mhm-rentiva-messages-settings', $params );
	}

	/**
	 * Get messages list URL with filters
	 */
	public static function get_messages_list_url_with_filters( array $filters = array() ): string {
		return self::get_admin_page_url( 'mhm-rentiva-messages', $filters );
	}

	/**
	 * Get messages list URL with thread filter
	 */
	public static function get_messages_list_url_with_thread( string|int $thread_id ): string {
		return self::get_admin_page_url(
			'mhm-rentiva-messages',
			array(
				'thread_id' => $thread_id,
			)
		);
	}

	/**
	 * Get admin page URL dynamically
	 *
	 * @param string $page Page slug
	 * @param array  $params Query parameters
	 * @return string Admin page URL
	 */
	public static function get_admin_page_url( string $page, array $params = array() ): string {
		// Try menu_page_url first (if page is registered in menu)
		if ( function_exists( 'menu_page_url' ) ) {
			$base_url = menu_page_url( $page, false );
			if ( $base_url ) {
				if ( ! empty( $params ) ) {
					return add_query_arg( $params, $base_url );
				}
				return $base_url;
			}
		}

		// Fallback: Use admin_url with dynamic construction
		$url            = admin_url( 'admin.php' );
		$params['page'] = $page;

		return add_query_arg( $params, $url );
	}

	/**
	 * Get AJAX URL
	 */
	public static function get_ajax_url(): string {
		return admin_url( 'admin-ajax.php' );
	}

	/**
	 * Get REST API URL
	 */
	public static function get_rest_url( string $endpoint = '' ): string {
		$base_url = rest_url( 'mhm-rentiva/v1/' );
		if ( $endpoint ) {
			return trailingslashit( $base_url ) . ltrim( $endpoint, '/' );
		}
		return $base_url;
	}

	/**
	 * Get booking edit URL (for WordPress posts)
	 */
	public static function get_booking_edit_url( int $booking_id ): string {
		return admin_url( 'post.php?post=' . absint( $booking_id ) . '&action=edit' );
	}
}
