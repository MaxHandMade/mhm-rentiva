<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Data Retention Manager
 *
 * Handles customer data retention policies and automatic cleanup
 *
 * @since 4.0.0
 */
final class DataRetentionManager
{

	/**
	 * Initialize data retention management
	 */
	public static function init(): void
	{
		add_action('wp_scheduled_delete', array(self::class, 'cleanup_expired_data'));
		// Register cron hook for scheduled cleanup - must be registered before scheduling
		add_action('mhm_data_retention_cleanup', array(self::class, 'cleanup_expired_data'));
		// Schedule cleanup - use higher priority to ensure SettingsCore is loaded
		add_action('init', array(self::class, 'schedule_cleanup'), 99);
	}

	/**
	 * Get data retention period in days
	 */
	public static function get_retention_period(): int
	{
		return (int) SettingsCore::get('mhm_rentiva_customer_data_retention_days', 2550);
	}

	/**
	 * Schedule data cleanup
	 */
	public static function schedule_cleanup(): void
	{
		// Check if already scheduled
		$next_scheduled = wp_next_scheduled('mhm_data_retention_cleanup');
		if ($next_scheduled) {
			return; // Already scheduled
		}

		// Schedule the event
		$result = wp_schedule_event(time(), 'daily', 'mhm_data_retention_cleanup');

		if ($result === false) {
			error_log('DataRetentionManager: Failed to schedule cleanup event. Error: ' . print_r(error_get_last(), true));
		} else {
			error_log('DataRetentionManager: Successfully scheduled cleanup event');
		}
	}

	/**
	 * Cleanup expired data
	 */
	public static function cleanup_expired_data(): void
	{
		$retention_days = self::get_retention_period();
		$cutoff_date    = gmdate('Y-m-d H:i:s', (int) strtotime("-{$retention_days} days"));

		// Cleanup inactive users
		self::cleanup_inactive_users($cutoff_date);

		// Cleanup old bookings
		self::cleanup_old_bookings($cutoff_date);

		// Note: Log cleanup removed - should be handled by centralized maintenance utilities
	}

	/**
	 * Cleanup inactive users
	 */
	private static function cleanup_inactive_users(string $cutoff_date): void
	{
		global $wpdb;

		// Find users who haven't logged in since cutoff date
		$inactive_users = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT u.ID, u.user_email, u.user_registered
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'last_login'
            WHERE (um.meta_value IS NULL OR um.meta_value < %s)
            AND u.user_registered < %s
            AND u.ID NOT IN (
                SELECT DISTINCT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_mhm_customer_user_id' 
                AND meta_value IS NOT NULL
            )
        ",
				$cutoff_date,
				$cutoff_date
			)
		);

		foreach ($inactive_users as $user) {
			// Check if anonymization is enabled
			$anonymization_enabled = SettingsCore::get('mhm_rentiva_customer_data_anonymization', '0');

			if ($anonymization_enabled === '1') {
				// Anonymize instead of delete
				GDPRManager::anonymize_user_data($user->ID);
				error_log("User data anonymized: {$user->user_email} (ID: {$user->ID})");
			} else {
				// Delete user data
				GDPRManager::delete_user_data($user->ID);
				error_log("User data deleted: {$user->user_email} (ID: {$user->ID})");
			}
		}
	}

	/**
	 * Cleanup old bookings
	 */
	private static function cleanup_old_bookings(string $cutoff_date): void
	{
		global $wpdb;

		// Find old completed/cancelled bookings
		$old_bookings = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT ID, post_title, post_date
            FROM {$wpdb->posts}
            WHERE post_type = 'vehicle_booking'
            AND post_status IN ('completed', 'cancelled', 'refunded')
            AND post_date < %s
        ",
				$cutoff_date
			)
		);

		foreach ($old_bookings as $booking) {
			// Delete booking and its meta
			wp_delete_post($booking->ID, true);
			error_log("Old booking deleted: {$booking->post_title} (ID: {$booking->ID})");
		}
	}

	// cleanup_old_logs method removed - should be handled by centralized maintenance utilities

	/**
	 * Get data retention statistics with optimized single query
	 */
	public static function get_retention_stats(): array
	{
		global $wpdb;

		$retention_days = self::get_retention_period();
		$cutoff_date    = gmdate('Y-m-d H:i:s', (int) strtotime("-{$retention_days} days"));

		// Single optimized query to get all stats at once
		$stats_data = $wpdb->get_row(
			$wpdb->prepare(
				"
            SELECT 
                (SELECT COUNT(*) FROM {$wpdb->users}) as total_users,
                (SELECT COUNT(*) 
                 FROM {$wpdb->users} u
                 LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'last_login'
                 WHERE (um.meta_value IS NULL OR um.meta_value < %s)
                 AND u.user_registered < %s) as inactive_users,
                (SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'vehicle_booking') as total_bookings,
                (SELECT COUNT(*)
                 FROM {$wpdb->posts}
                 WHERE post_type = 'vehicle_booking'
                 AND post_status IN ('completed', 'cancelled', 'refunded')
                 AND post_date < %s) as old_bookings
        ",
				$cutoff_date,
				$cutoff_date,
				$cutoff_date
			),
			ARRAY_A
		);

		return array(
			'retention_days' => $retention_days,
			'cutoff_date'    => $cutoff_date,
			'total_users'    => (int) ($stats_data['total_users'] ?? 0),
			'inactive_users' => (int) ($stats_data['inactive_users'] ?? 0),
			'total_bookings' => (int) ($stats_data['total_bookings'] ?? 0),
			'old_bookings'   => (int) ($stats_data['old_bookings'] ?? 0),
		);
	}

	/**
	 * Manual cleanup trigger
	 */
	public static function manual_cleanup(): array
	{
		$before_stats = self::get_retention_stats();
		self::cleanup_expired_data();
		$after_stats = self::get_retention_stats();

		return array(
			'before'           => $before_stats,
			'after'            => $after_stats,
			'cleaned_users'    => $before_stats['inactive_users'] - $after_stats['inactive_users'],
			'cleaned_bookings' => $before_stats['old_bookings'] - $after_stats['old_bookings'],
		);
	}

	/**
	 * Check if user data should be retained
	 */
	public static function should_retain_user_data(int $user_id): bool
	{
		$user = get_userdata($user_id);
		if (! $user) {
			return false;
		}

		$retention_days = self::get_retention_period();
		$cutoff_date    = strtotime("-{$retention_days} days");

		// Check last login
		$last_login = get_user_meta($user_id, 'last_login', true);
		if ($last_login && strtotime($last_login) > $cutoff_date) {
			return true;
		}

		// Check if user has recent bookings
		$recent_bookings = get_posts(
			array(
				'post_type'      => 'vehicle_booking',
				'meta_key'       => '_mhm_customer_user_id',
				'meta_value'     => $user_id,
				'date_query'     => array(
					array(
						'after'     => gmdate('Y-m-d', $cutoff_date),
						'inclusive' => true,
					),
				),
				'posts_per_page' => 1,
			)
		);

		return ! empty($recent_bookings);
	}

	/**
	 * Get users eligible for cleanup
	 */
	public static function get_users_for_cleanup(): array
	{
		global $wpdb;

		$retention_days = self::get_retention_period();
		$cutoff_date    = gmdate('Y-m-d H:i:s', (int) strtotime("-{$retention_days} days"));

		return $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT u.ID, u.user_email, u.user_registered, um.meta_value as last_login
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'last_login'
            WHERE (um.meta_value IS NULL OR um.meta_value < %s)
            AND u.user_registered < %s
            AND u.ID NOT IN (
                SELECT DISTINCT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_mhm_customer_user_id' 
                AND meta_value IS NOT NULL
            )
            ORDER BY u.user_registered ASC
        ",
				$cutoff_date,
				$cutoff_date
			)
		);
	}
}
