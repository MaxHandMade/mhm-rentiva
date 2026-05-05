<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invalidates the vendor profile transient cache when underlying data changes.
 *
 * The profile transient is keyed by `<user_id>_<max_vehicles>_<max_reviews>`,
 * so invalidation goes through {@see VendorProfileProvider::clear_cache_for_user()}
 * which drops every cap variant via a prefix-wildcard SQL delete.
 *
 * Triggers:
 *   - User meta change for any field that feeds the public profile array
 *     (status, bio, city, avatar_id, reliability_score — the latter feeds
 *     `badge_status` via {@see VendorBadgeEligibility}).
 *   - `save_post_vehicle_booking` — booking writes can shift rating aggregates.
 *   - `transition_comment_status` — review approval/rejection changes rating.
 *   - `mhm_rentiva_vehicle_lifecycle_changed` — active/paused/withdrawn vehicles
 *     are filtered when collecting the profile vehicle list.
 *   - `profile_update` — display_name change feeds the profile.
 *
 * @since 4.37.0
 */
final class VendorProfileCacheInvalidator
{
    private const INVALIDATING_META_KEYS = [
        '_rentiva_vendor_status',
        '_rentiva_vendor_bio',
        '_rentiva_vendor_city',
        '_rentiva_vendor_avatar_id',
        '_rentiva_vendor_reliability_score',
    ];

    public static function register(): void
    {
        add_action('updated_user_meta', [self::class, 'on_user_meta_change'], 10, 4);
        add_action('added_user_meta', [self::class, 'on_user_meta_change'], 10, 4);
        add_action('deleted_user_meta', [self::class, 'on_user_meta_change'], 10, 4);

        add_action('save_post_vehicle_booking', [self::class, 'invalidate_for_post_author'], 10, 1);
        add_action('transition_comment_status', [self::class, 'on_comment_status'], 10, 3);
        add_action('mhm_rentiva_vehicle_lifecycle_changed', [self::class, 'on_lifecycle_change'], 10, 3);
        add_action('profile_update', [self::class, 'invalidate_for_user'], 10, 1);
    }

    /**
     * Bridge for the three user-meta hooks (added/updated/deleted) which all
     * share the same `($meta_id, $object_id, $meta_key, $_meta_value)` signature.
     *
     * @param int|array<int>  $meta_id     Meta row ID (added/updated) or array of IDs (deleted).
     * @param int             $object_id   User ID.
     * @param string          $meta_key    Meta key being mutated.
     * @param mixed           $_meta_value Meta value (unused — change alone invalidates).
     */
    public static function on_user_meta_change($meta_id, $object_id, $meta_key, $_meta_value = null): void
    {
        if (!is_string($meta_key) || !in_array($meta_key, self::INVALIDATING_META_KEYS, true)) {
            return;
        }
        self::invalidate_for_user((int) $object_id);
    }

    public static function invalidate_for_user(int $user_id): void
    {
        VendorProfileProvider::clear_cache_for_user($user_id);
    }

    public static function invalidate_for_post_author(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }
        $author_id = (int) get_post_field('post_author', $post_id);
        if ($author_id > 0) {
            self::invalidate_for_user($author_id);
        }
    }

    /**
     * @param string                            $new_status Comment status after transition.
     * @param string                            $old_status Comment status before transition.
     * @param \WP_Comment|object|int|string|null $comment   Comment object.
     */
    public static function on_comment_status($new_status, $old_status, $comment): void
    {
        if (!is_object($comment)) {
            return;
        }
        $post_id = (int) ($comment->comment_post_ID ?? 0);
        if ($post_id <= 0) {
            return;
        }
        self::invalidate_for_post_author($post_id);
    }

    /**
     * Vehicle lifecycle hook fires with three positional args.
     *
     * @param int    $vehicle_id Vehicle post ID.
     * @param string $_old       Previous lifecycle status (unused).
     * @param string $_new       New lifecycle status (unused — any change invalidates).
     */
    public static function on_lifecycle_change($vehicle_id, $_old = '', $_new = ''): void
    {
        $vehicle_id = (int) $vehicle_id;
        if ($vehicle_id <= 0) {
            return;
        }
        self::invalidate_for_post_author($vehicle_id);
    }
}
