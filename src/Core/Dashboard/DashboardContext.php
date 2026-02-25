<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Resolve dashboard context from authenticated user roles.
 */
final class DashboardContext
{
	/**
	 * Resolve dashboard type.
	 *
	 * @return string customer|vendor|guest
	 */
	public static function resolve(): string
	{
		if (! is_user_logged_in()) {
			return 'guest';
		}

		$user = wp_get_current_user();

		if (in_array('rentiva_vendor', (array) $user->roles, true)) {
			return 'vendor';
		}

		return 'customer';
	}
}

