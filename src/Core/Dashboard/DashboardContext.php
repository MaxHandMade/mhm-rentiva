<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Resolve the Lite customer dashboard context.
 */
final class DashboardContext {

	/**
	 * @return string Sanitized dashboard context.
	 */
	public static function resolve(): string
	{
		$base_context = is_user_logged_in() ? 'customer' : 'guest';
		$user_id      = is_user_logged_in() ? (int) get_current_user_id() : 0;

		/**
		 * Filters the dashboard context without assigning optional roles in Lite.
		 *
		 * @param string $base_context Default customer or guest context.
		 * @param int    $user_id      Current user ID, or zero for guests.
		 */
		$context = sanitize_key( (string) apply_filters('mhmrentiva_dashboard_context', $base_context, $user_id) );

		return '' !== $context ? $context : $base_context;
	}
}
