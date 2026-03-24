<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Restricts the media library AJAX query to the current user's uploads for vendors.
 * Prevents vendors from browsing other vendors' uploaded files.
 */
final class VendorMediaIsolation
{
	/**
	 * Hook into WordPress filters.
	 */
	public static function register(): void
	{
		add_filter('ajax_query_attachments_args', array(self::class, 'isolate_vendor_media'));
	}

	/**
	 * Filter media library for vendors to show only their own uploads.
	 */
	public static function isolate_vendor_media(array $query): array
	{
		$user_id = get_current_user_id();
		if (0 === $user_id) {
			return $query;
		}

		// Restrict media library to own uploads for vendors only.
		// Plain customers and other non-vendor roles are not restricted.
		$user = wp_get_current_user();
		if (in_array('rentiva_vendor', (array) $user->roles, true)) {
			$query['author'] = $user_id;
		}

		return $query;
	}
}
