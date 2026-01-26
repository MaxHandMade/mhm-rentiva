<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Helpers;

use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Core\Utilities\MetaQueryHelper;
use MHMRentiva\Admin\Core\Utilities\TypeValidator;

if (! defined('ABSPATH')) {
	exit;
}

final class MessageQuery
{

	/**
	 * Message list query for admin
	 *
	 * Optimized query using central MetaQueryHelper
	 */
	public static function getAdminMessages(?string $status = null, ?string $category = null, int $per_page = 20, int $page = 1): array
	{
		global $wpdb;

		$where_clauses = array(
			$wpdb->prepare('p.post_type = %s', Message::POST_TYPE),
			"p.post_status = 'publish'",
		);

		if ($status) {
			$where_clauses[] = $wpdb->prepare('pm_status.meta_value = %s', $status);
		}

		if ($category) {
			$where_clauses[] = $wpdb->prepare('pm_category.meta_value = %s', $category);
		}

		$offset = ($page - 1) * $per_page;

		// ✅ CODE QUALITY IMPROVEMENT - MetaQueryHelper usage
		$meta_joins = MetaQueryHelper::get_message_meta_joins();

		// Build query parts safely
		$selects_sql = implode(', ', $meta_joins['selects']);
		$joins_sql   = implode(' ', $meta_joins['joins']);
		$where_sql   = implode(' AND ', $where_clauses);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query components use prepared values; structure is safe.
		$query = $wpdb->prepare(
			"SELECT SQL_CALC_FOUND_ROWS
				p.ID as id,
				p.post_title as subject,
				p.post_date as date,
				{$selects_sql}
			FROM {$wpdb->posts} p
			{$joins_sql}
			WHERE {$where_sql}
			ORDER BY p.post_date DESC
			LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
		$messages = $wpdb->get_results($query);
		$total    = (int) $wpdb->get_var('SELECT FOUND_ROWS()');

		// Format meta data
		foreach ($messages as &$message) {
			$message->customer_name  = $message->customer_name ?: __('Anonymous', 'mhm-rentiva');
			$message->category_label = Message::get_categories()[$message->category] ?? $message->category;
			$message->status_label   = Message::get_statuses()[$message->status] ?? $message->status;
			$message->is_read        = (bool) $message->is_read;
			$message->date_human     = human_time_diff(strtotime($message->date), current_time('timestamp')) . ' ' . __('ago', 'mhm-rentiva');
		}

		return array(
			'messages'     => $messages,
			'total'        => $total,
			'pages'        => ceil($total / $per_page),
			'current_page' => $page,
		);
	}

	/**
	 * Customer messages query
	 */
	public static function getCustomerMessages(string $customer_email): array
	{
		global $wpdb;

		// ✅ CODE QUALITY IMPROVEMENT - MetaQueryHelper usage
		$meta_joins = MetaQueryHelper::get_message_meta_joins();

		// Build query parts safely
		$selects_sql = implode(', ', $meta_joins['selects']);
		$joins_sql   = implode(' ', $meta_joins['joins']);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query components use prepared values; structure is safe.
		$query = $wpdb->prepare(
			"SELECT p.ID as id, p.post_title as subject, p.post_date as date,
				{$selects_sql},
				SUBSTRING(COALESCE(p.post_content, ''), 1, 100) as preview
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_customer_email'
			{$joins_sql}
			LEFT JOIN {$wpdb->postmeta} pm_parent_filter ON p.ID = pm_parent_filter.post_id AND pm_parent_filter.meta_key = '_mhm_parent_message_id'
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND pm_email.meta_value = %s
			AND (pm_parent_filter.meta_value IS NULL OR pm_parent_filter.meta_value = '' OR pm_parent_filter.meta_value = '0')
			ORDER BY p.post_date DESC",
			Message::POST_TYPE,
			$customer_email
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
		$messages = $wpdb->get_results($query);

		// Format data
		foreach ($messages as &$message) {
			$message->category_label = Message::get_categories()[$message->category] ?? $message->category;
			$message->status_label   = Message::get_statuses()[$message->status] ?? $message->status;
			$message->priority_label = \MHMRentiva\Admin\Messages\Settings\MessagesSettings::get_priorities()[$message->priority ?? 'normal'] ?? __('Normal', 'mhm-rentiva');
			$message->is_read        = (bool) $message->is_read;
			$message->date_human     = human_time_diff(strtotime($message->date), current_time('timestamp')) . ' ' . __('ago', 'mhm-rentiva');
			// Full date and time format
			$message->date_full = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($message->date));
			$message->preview   = wp_strip_all_tags($message->preview) . (strlen($message->preview) > 97 ? '...' : '');
			// Convert parent_message_id to integer (0 means no parent)
			$message->parent_message_id = isset($message->parent_message_id) ? (int) $message->parent_message_id : 0;

			// Check if there are unread admin replies in the thread
			$thread_id = isset($message->thread_id) ? (int) $message->thread_id : 0;
			if ($thread_id > 0) {
				$unread_admin_replies = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(p.ID)
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} pm_thread ON p.ID = pm_thread.post_id AND pm_thread.meta_key = '_mhm_thread_id'
						INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = '_mhm_message_type'
						LEFT JOIN {$wpdb->postmeta} pm_read ON p.ID = pm_read.post_id AND pm_read.meta_key = '_mhm_is_read'
						WHERE pm_thread.meta_value = %d
						AND p.post_type = %s
						AND p.post_status = 'publish'
						AND pm_type.meta_value = 'admin_to_customer'
						AND (pm_read.meta_value IS NULL OR pm_read.meta_value != '1')",
						$thread_id,
						Message::POST_TYPE
					)
				);
				$message->has_unread_admin_reply = (int) $unread_admin_replies > 0;
			} else {
				$message->has_unread_admin_reply = false;
			}
		}

		$unread_count = count(
			array_filter(
				$messages,
				function ($msg) {
					return ! $msg->is_read;
				}
			)
		);

		return array(
			'messages'     => $messages,
			'unread_count' => $unread_count,
		);
	}

	/**
	 * Thread verification
	 */
	public static function verifyCustomerThread($thread_id, string $customer_email): bool
	{
		global $wpdb;

		$thread_check = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE p.post_type = %s
				AND pm.meta_key = '_mhm_thread_id'
				AND pm.meta_value = %s
				AND EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm2
					WHERE pm2.post_id = pm.post_id
					AND pm2.meta_key = '_mhm_customer_email'
					AND pm2.meta_value = %s
				)",
				Message::POST_TYPE,
				$thread_id,
				$customer_email
			)
		);

		return (bool) $thread_check;
	}
}
