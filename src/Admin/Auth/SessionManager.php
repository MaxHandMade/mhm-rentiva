<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Auth;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;



/**
 * Session Manager
 *
 * Handles customer session timeout and management
 *
 * @since 4.0.0
 */
final class SessionManager {


	/**
	 * Default session timeout in hours
	 */
	public const DEFAULT_SESSION_TIMEOUT = 24;

	/**
	 * Initialize session management
	 */
	public static function init(): void
	{
		add_action('init', array( self::class, 'check_session_timeout' ));
		add_action('wp_login', array( self::class, 'set_session_timeout' ), 10, 2);
		add_action('wp_logout', array( self::class, 'clear_session_timeout' ));
	}

	/**
	 * Check if user session has timed out
	 */
	public static function check_session_timeout(): void
	{
		if (! is_user_logged_in()) {
			return;
		}

		$user_id         = get_current_user_id();
		$session_timeout = SettingsCore::get('mhm_rentiva_customer_session_timeout', self::DEFAULT_SESSION_TIMEOUT);
		$timeout_hours   = (int) $session_timeout;

		// Convert hours to seconds
		$timeout_seconds = $timeout_hours * 3600;

		// Get last activity time
		$last_activity = get_user_meta($user_id, 'mhm_rentiva_last_activity', true);

		if (empty($last_activity)) {
			// Set initial activity time
			update_user_meta($user_id, 'mhm_rentiva_last_activity', time());
			return;
		}

		// Check if session has expired
		if (( time() - (int) $last_activity ) > $timeout_seconds) {
			// Session expired, log out user
			wp_logout();

			// Redirect to login page with message
			wp_safe_redirect(add_query_arg('session_expired', '1', wp_login_url()));
			exit;
		}

		// Update last activity time
		update_user_meta($user_id, 'mhm_rentiva_last_activity', time());
	}

	/**
	 * Set session timeout on login and record last successful login timestamp.
	 *
	 * @param string       $user_login The user login name (provided by wp_login action).
	 * @param \WP_User|null $user       The logged-in user object.
	 */
	public static function set_session_timeout($user_login = '', $user = null): void
	{
		$user_id = 0;
		if ($user instanceof \WP_User) {
			$user_id = (int) $user->ID;
		}
		if (! $user_id) {
			$user_id = get_current_user_id();
		}
		if (! $user_id) {
			return;
		}
		update_user_meta($user_id, 'mhm_rentiva_last_activity', time());
		update_user_meta($user_id, 'last_login', current_time('mysql'));
	}

	/**
	 * Clear session timeout on logout
	 */
	public static function clear_session_timeout(): void
	{
		$user_id = get_current_user_id();
		if ($user_id) {
			delete_user_meta($user_id, 'mhm_rentiva_last_activity');
		}
	}

	/**
	 * Get remaining session time in minutes
	 */
	public static function get_remaining_session_time(): int
	{
		if (! is_user_logged_in()) {
			return 0;
		}

		$user_id         = get_current_user_id();
		$session_timeout = SettingsCore::get('mhm_rentiva_customer_session_timeout', self::DEFAULT_SESSION_TIMEOUT);
		$timeout_hours   = (int) $session_timeout;
		$timeout_seconds = $timeout_hours * 3600;

		$last_activity = get_user_meta($user_id, 'mhm_rentiva_last_activity', true);

		if (empty($last_activity)) {
			return $timeout_hours * 60; // Return full timeout in minutes
		}

		$elapsed   = time() - (int) $last_activity;
		$remaining = $timeout_seconds - $elapsed;

		return max(0, (int) ( $remaining / 60 )); // Return remaining minutes
	}

	/**
	 * Extend session (reset timeout)
	 */
	public static function extend_session(): void
	{
		if (is_user_logged_in()) {
			$user_id = get_current_user_id();
			update_user_meta($user_id, 'mhm_rentiva_last_activity', time());
		}
	}
}
