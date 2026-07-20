<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Resolve dashboard context from authenticated user roles.
 */
final class DashboardContext {

	/**
	 * Resolve dashboard type.
	 *
	 * @return string customer|vendor|vendor_suspended|vendor_application_pending|guest
	 */
	public static function resolve(): string
	{
		if (! is_user_logged_in()) {
			return 'guest';
		}

		$user = wp_get_current_user();

		if (in_array('rentiva_vendor', (array) $user->roles, true)) {
			$status = get_user_meta($user->ID, '_rentiva_vendor_status', true);
			if ($status === 'suspended') {
				return 'vendor_suspended';
			}
			return 'vendor';
		}

		// Lite has no vendor-application post type of its own; a subscriber (the add-on)
		// answers whether this user has a pending vendor application. Lite's own
		// default is false, so an install with no add-on active (or a downgrade, where
		// vendor-role users already exist in the DB) always routes to 'customer'.
		if (apply_filters('mhm_rentiva_dashboard_vendor_application_pending', false, $user->ID)) {
			return 'vendor_application_pending';
		}

		return 'customer';
	}
}
