<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Directory;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Invalidates the Vendor Directory transient cache when underlying data changes.
 *
 * The cache is keyed by `mhm_rentiva_vendor_dir_<md5(query_args)>` — there are
 * many possible hashes (one per filter combo × page), so invalidation goes
 * through {@see VendorDirectoryProvider::clear_cache()} which drops every
 * variant via a prefix-wildcard SQL DELETE.
 *
 * Triggers (subset of VendorProfileCacheInvalidator — see spec §4):
 *   - User meta change for status / city / reliability_score (NOT bio / avatar_id)
 *   - `save_post_vehicle` (city pool, vehicle_count, rating may change)
 *   - `transition_comment_status` (rating)
 *   - `mhm_rentiva_vehicle_lifecycle_changed` (active vs withdrawn)
 *   - `profile_update` (display_name affects sort A→Z)
 *
 * @since 4.38.0
 */
final class VendorDirectoryCacheInvalidator
{
	private const INVALIDATING_META_KEYS = [
		'_rentiva_vendor_status',
		'_rentiva_vendor_city',
		'_rentiva_vendor_reliability_score',
	];

	public static function register(): void
	{
		add_action('updated_user_meta', [self::class, 'on_user_meta_change'], 10, 4);
		add_action('added_user_meta', [self::class, 'on_user_meta_change'], 10, 4);
		add_action('deleted_user_meta', [self::class, 'on_user_meta_change'], 10, 4);

		add_action('save_post_vehicle', [self::class, 'on_vehicle_save'], 10, 1);
		add_action('transition_comment_status', [self::class, 'on_comment_status'], 10, 3);
		add_action('mhm_rentiva_vehicle_lifecycle_changed', [self::class, 'on_lifecycle_change'], 10, 3);
		add_action('profile_update', [self::class, 'on_profile_update'], 10, 1);
	}

	/**
	 * @param int|array<int> $meta_id     Meta row ID(s) (added/updated single, deleted array).
	 * @param int            $object_id   User ID.
	 * @param string         $meta_key    Meta key being mutated.
	 * @param mixed          $_meta_value Meta value (unused — change alone invalidates).
	 */
	public static function on_user_meta_change($meta_id, $object_id, $meta_key, $_meta_value = null): void
	{
		unset($meta_id, $object_id, $_meta_value);
		if (!in_array($meta_key, self::INVALIDATING_META_KEYS, true)) {
			return;
		}

		VendorDirectoryProvider::clear_cache();
	}

	public static function on_vehicle_save(int $post_id): void
	{
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}
		VendorDirectoryProvider::clear_cache();
	}

	/**
	 * @param string             $new_status
	 * @param string             $old_status
	 * @param \WP_Comment|object $comment
	 */
	public static function on_comment_status(string $new_status, string $old_status, $comment): void
	{
		unset($comment);
		if ($new_status === $old_status) {
			return;
		}
		VendorDirectoryProvider::clear_cache();
	}

	public static function on_lifecycle_change(int $vehicle_id, string $new_status, string $old_status): void
	{
		unset($vehicle_id, $new_status, $old_status);
		VendorDirectoryProvider::clear_cache();
	}

	public static function on_profile_update(int $user_id): void
	{
		unset($user_id);
		VendorDirectoryProvider::clear_cache();
	}
}
