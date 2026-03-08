<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

/**
 * Restricts the media library AJAX query to the current user's uploads for vendors.
 * Prevents vendors from browsing other vendors' uploaded files.
 */
final class VendorMediaIsolation
{
	public static function register(): void
	{
		add_filter('ajax_query_attachments_args', array(self::class, 'filter_ajax_query'));
	}

	/**
	 * @param array $query WP_Query arguments for the media AJAX request.
	 * @return array
	 */
	public static function filter_ajax_query(array $query): array
	{
		if (current_user_can('manage_options')) {
			return $query;
		}

		$user = wp_get_current_user();
		if (!$user || !in_array('rentiva_vendor', (array) $user->roles, true)) {
			return $query;
		}

		$query['author'] = $user->ID;
		return $query;
	}
}
