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

		$pending = get_posts(array(
			'post_type'      => \MHMRentiva\Admin\Vendor\PostType\VendorApplication::POST_TYPE,
			'post_author'    => $user->ID,
			'post_status'    => 'pending',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		));

		if (! empty($pending)) {
			return 'vendor_application_pending';
		}

		return 'customer';
	}
}
