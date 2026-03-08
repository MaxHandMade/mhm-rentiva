<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Prevents vendors from editing or deleting vehicles they do not own.
 * Hooks into WordPress capability checking via user_has_cap filter.
 */
final class VendorOwnershipEnforcer {

	/**
	 * Register the capability filter.
	 */
	public static function register(): void {
		add_filter('user_has_cap', array( self::class, 'enforce_vehicle_ownership' ), 10, 4);
	}

	/**
	 * Strip edit/delete capabilities for vendors attempting to modify
	 * a vehicle post they do not own.
	 *
	 * @param  bool[]  $allcaps All capabilities of the user.
	 * @param  string[] $caps    Required primitive capabilities.
	 * @param  array   $args    Arguments: [0] capability, [1] user_id, [2] post_id.
	 * @param  \WP_User $user   The user object.
	 * @return bool[]
	 */
	public static function enforce_vehicle_ownership(
		array $allcaps,
		array $caps,
		array $args,
		\WP_User $user
	): array {
		// Only applies to rentiva_vendor role users.
		if (! in_array('rentiva_vendor', (array) $user->roles, true)) {
			return $allcaps;
		}

		// Only intercept vehicle-level meta capability checks.
		$requested_cap     = (string) ($args[0] ?? '');
		$vehicle_meta_caps = array( 'edit_post', 'delete_post', 'publish_post' );

		if (! in_array($requested_cap, $vehicle_meta_caps, true)) {
			return $allcaps;
		}

		// A post ID must be present.
		$post_id = isset($args[2]) ? (int) $args[2] : 0;
		if ($post_id === 0) {
			return $allcaps;
		}

		$post = get_post($post_id);
		if (! $post || $post->post_type !== 'vehicle') {
			return $allcaps;
		}

		if ((int) $post->post_author === (int) $user->ID) {
			// Grant the mapped primitive caps for the vendor's own vehicle.
			foreach ($caps as $cap) {
				$allcaps[ $cap ] = true;
			}
		} else {
			// Deny all primitive caps for another vendor's vehicle.
			foreach ($caps as $cap) {
				unset($allcaps[ $cap ]);
			}
		}

		return $allcaps;
	}
}
