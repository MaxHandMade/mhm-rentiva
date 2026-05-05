<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Generates and tracks public profile URL slugs for vendors.
 *
 * Slugs are always ASCII (Latin) — sanitize_title(remove_accents()).
 * On change, the previous slug is appended to history meta for 301 redirects.
 *
 * @since 4.37.0
 */
final class VendorSlugManager
{
    public const HISTORY_LIMIT = 10;

    /**
     * Generate a new (unsaved) slug from a user's display name.
     * Falls back to user_login if display_name is empty.
     */
    public static function generate_for_user(int $user_id): string
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return '';
        }
        $source = trim((string) $user->display_name);
        if ($source === '') {
            $source = (string) $user->user_login;
        }
        $slug = sanitize_title(remove_accents($source));
        if ($slug === '') {
            $slug = 'vendor-' . $user_id;
        }
        return $slug;
    }

    /**
     * Assigns a slug to the user (idempotent — returns existing slug if set).
     * Generates from display_name, applies collision suffix.
     */
    public static function assign_slug(int $user_id, ?string $base = null): string
    {
        $existing = (string) get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true);
        if ($existing !== '') {
            return $existing;
        }
        $base = $base ?? self::generate_for_user($user_id);
        if ($base === '') {
            return '';
        }
        $slug = self::resolve_unique_slug($base, $user_id);
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, $slug);
        return $slug;
    }

    /**
     * Find the first free slug starting from $base, $base-2, $base-3, ...
     */
    private static function resolve_unique_slug(string $base, int $exclude_user_id = 0): string
    {
        $candidate = $base;
        $suffix = 2;
        while (self::is_slug_taken($candidate, $exclude_user_id)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
            if ($suffix > 1000) {
                // Sanity bail — should never realistically hit
                return $base . '-' . $exclude_user_id;
            }
        }
        return $candidate;
    }

    /**
     * @param int $exclude_user_id User ID whose slug shouldn't count as a collision (for re-saves)
     */
    public static function is_slug_taken(string $slug, int $exclude_user_id = 0): bool
    {
        $args = [
            'meta_key'   => MetaKeys::VENDOR_SLUG,
            'meta_value' => $slug,
            'fields'     => 'ID',
            'number'     => 1,
        ];
        if ($exclude_user_id > 0) {
            $args['exclude'] = [$exclude_user_id];
        }
        $users = get_users($args);
        return !empty($users);
    }

    /**
     * Change the user's slug and push the previous one to history.
     * The new slug is sanitized and collision-checked.
     */
    public static function change_slug(int $user_id, string $new_raw): string
    {
        $new = sanitize_title(remove_accents($new_raw));
        if ($new === '') {
            return (string) get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true);
        }
        $current = (string) get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true);
        if ($current === $new) {
            return $current;
        }
        // Collision-resolve against other users
        $resolved = self::resolve_unique_slug($new, $user_id);
        if ($current !== '') {
            $existing = get_user_meta($user_id, MetaKeys::VENDOR_SLUG_HISTORY, true);
            $history = is_array($existing) ? $existing : [];
            array_unshift($history, $current);
            $history = array_slice(array_values(array_unique(array_filter($history, 'strlen'))), 0, self::HISTORY_LIMIT);
            update_user_meta($user_id, MetaKeys::VENDOR_SLUG_HISTORY, $history);
        }
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, $resolved);
        return $resolved;
    }

    /**
     * Find a user whose history contains the given slug. Used for 301 redirects
     * from old URLs to the current slug.
     *
     * @return int User ID, or 0 if not found
     */
    public static function find_user_by_history_slug(string $slug): int
    {
        if ($slug === '') {
            return 0;
        }
        $users = get_users([
            'meta_query' => [
                [
                    'key'     => MetaKeys::VENDOR_SLUG_HISTORY,
                    'value'   => $slug,
                    'compare' => 'LIKE',
                ],
            ],
            'fields' => ['ID'],
            'number' => 5,  // a few candidates, then verify in PHP
        ]);
        foreach ($users as $u) {
            $history = (array) get_user_meta((int) $u->ID, MetaKeys::VENDOR_SLUG_HISTORY, true);
            if (in_array($slug, $history, true)) {
                return (int) $u->ID;
            }
        }
        return 0;
    }
}
