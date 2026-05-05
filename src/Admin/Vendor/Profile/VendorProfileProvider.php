<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Repository for public vendor profile rendering.
 *
 * Provides slug→user lookup, render-ready data array, 1h transient cache.
 * The status check (`_rentiva_vendor_status === 'active'`) is the canonical
 * "is this vendor publicly visible" gate.
 *
 * @since 4.37.0
 */
final class VendorProfileProvider
{
    public const TRANSIENT_PREFIX = 'mhm_rentiva_vendor_profile_';
    public const TRANSIENT_TTL    = HOUR_IN_SECONDS;

    /**
     * Returns vendor user ID if slug matches an active vendor, else 0.
     */
    public static function lookup_by_slug(string $slug): int
    {
        if ($slug === '') {
            return 0;
        }

        $users = get_users([
            'meta_key'   => MetaKeys::VENDOR_SLUG,
            'meta_value' => $slug,
            'fields'     => ['ID'],
            'number'     => 1,
        ]);

        if (empty($users)) {
            return 0;
        }

        $user_id = (int) $users[0]->ID;
        $status  = (string) get_user_meta($user_id, '_rentiva_vendor_status', true);

        return $status === 'active' ? $user_id : 0;
    }

    /**
     * Build the render-ready data array for a vendor.
     *
     * Returns [] if vendor is not active. Result cached in transient for 1h
     * keyed by user ID + (max_vehicles, max_reviews) so that different
     * shortcode invocations with different caps do not pollute each other's
     * cache entries. The array is constructed from an explicit allowlist of
     * public-safe fields so that sensitive metas (phone, IBAN, tax number)
     * cannot leak even if a future contributor calls this method on the
     * frontend.
     *
     * Filterable via `mhm_rentiva_vendor_profile_data` ($data, $user_id).
     *
     * @param int $user_id      Vendor user ID.
     * @param int $max_vehicles Cap on the vehicle list (clamped 1..50, default 6).
     * @param int $max_reviews  Cap on the review list (clamped 1..50, default 10).
     * @return array<string,mixed>
     */
    public static function get_profile_data(int $user_id, int $max_vehicles = 6, int $max_reviews = 10): array
    {
        if ($user_id <= 0) {
            return [];
        }

        // Defensive clamp — shortcode atts arrive as user-controlled strings.
        // Lower bound 1 keeps the grid non-empty when intent was "show some";
        // upper bound 50 prevents unbounded WP_Query cost via shortcode abuse.
        $max_vehicles = max(1, min(50, $max_vehicles));
        $max_reviews  = max(1, min(50, $max_reviews));

        $status = (string) get_user_meta($user_id, '_rentiva_vendor_status', true);
        if ($status !== 'active') {
            return [];
        }

        $cache_key = self::TRANSIENT_PREFIX . $user_id . '_' . $max_vehicles . '_' . $max_reviews;
        $cached    = get_transient($cache_key);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        // ALLOWLIST: only public-safe fields enter the array. Sensitive metas
        // (_rentiva_vendor_phone / _iban / _tax_number) are deliberately not
        // read here.
        $data = [
            'user_id'      => $user_id,
            'display_name' => (string) $user->display_name,
            'slug'         => (string) get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true),
            'avatar_id'    => (int) get_user_meta($user_id, MetaKeys::VENDOR_AVATAR_ID, true),
            'avatar_url'   => self::resolve_avatar_url($user_id, (string) $user->user_email),
            'city'         => (string) get_user_meta($user_id, '_rentiva_vendor_city', true),
            'bio'          => self::sanitize_bio((string) get_user_meta($user_id, '_rentiva_vendor_bio', true)),
            'approved_at'  => (string) get_user_meta($user_id, '_rentiva_vendor_approved_at', true),
            'badge_status' => VendorBadgeEligibility::evaluate($user_id),
            'rating'       => self::aggregate_rating($user_id),
            'vehicles'     => self::collect_vehicles($user_id, $max_vehicles),
            'reviews'      => self::collect_reviews($user_id, $max_reviews),
        ];

        /**
         * Filters the render-ready vendor profile data array.
         *
         * Allows themes/extensions to add their own keys. The array is
         * rendered publicly AND cached in a transient for 1h, so any field
         * added here will be readable by anonymous visitors and survive the
         * cache window.
         *
         * Hard contract — DO NOT add sensitive vendor metas via this filter:
         *   - `_rentiva_vendor_phone`
         *   - `_rentiva_vendor_iban`
         *   - `_rentiva_vendor_tax_number`
         *   - any payment, identity, or contact metadata not meant for the
         *     public profile page.
         *
         * The base allowlist construction in {@see get_profile_data()} never
         * reads these metas; injecting them here defeats that protection.
         *
         * @param array<string,mixed> $data    Allowlist-built profile data.
         * @param int                 $user_id Vendor user ID.
         */
        $data = (array) apply_filters('mhm_rentiva_vendor_profile_data', $data, $user_id);

        set_transient($cache_key, $data, self::TRANSIENT_TTL);
        return $data;
    }

    /**
     * Delete every cached profile entry for a vendor across all cap variants.
     *
     * Cache keys carry a `<user_id>_<max_vehicles>_<max_reviews>` suffix so a
     * plain `delete_transient(PREFIX . $user_id)` would miss most rows. Use a
     * prefix-wildcard SQL delete against `_transient_<prefix><user_id>_%` and
     * its `_transient_timeout_` twin to drop the entire variant set.
     *
     * @since 4.37.0
     */
    public static function clear_cache_for_user(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        global $wpdb;

        $prefix       = self::TRANSIENT_PREFIX . $user_id . '_';
        $like_value   = $wpdb->esc_like('_transient_' . $prefix) . '%';
        $like_timeout = $wpdb->esc_like('_transient_timeout_' . $prefix) . '%';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $like_value,
                $like_timeout
            )
        );
        // phpcs:enable
    }

    private static function resolve_avatar_url(int $user_id, string $email): string
    {
        $avatar_id = (int) get_user_meta($user_id, MetaKeys::VENDOR_AVATAR_ID, true);
        if ($avatar_id > 0) {
            $url = wp_get_attachment_image_url($avatar_id, 'medium');
            if ($url) {
                return (string) $url;
            }
        }
        return (string) get_avatar_url($email, ['size' => 160]);
    }

    /**
     * Sanitize a vendor bio for public render.
     *
     * Short bios (≤500 chars) keep their wp_kses_post-allowed HTML formatting.
     * Long bios fall back to plaintext truncation: `mb_substr` on raw HTML can
     * cut through a tag mid-attribute (e.g. `...aaa<stro`) which leaks broken
     * markup into the public page and can collapse adjacent layout. Stripping
     * to plaintext before the cap avoids that entire class of bug at the cost
     * of dropping formatting on bios that exceed the limit — an acceptable
     * trade-off given the limit only matters for outliers.
     */
    private static function sanitize_bio(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        $clean = wp_kses_post($raw);
        if (mb_strlen($clean) > 500) {
            $plain = wp_strip_all_tags($clean);
            $clean = mb_substr($plain, 0, 500) . '…';
        }
        return $clean;
    }

    /**
     * Weighted average across the vendor's active vehicles, weighted by each
     * vehicle's review count.
     *
     * @return array{average:float,count:int}
     */
    private static function aggregate_rating(int $user_id): array
    {
        $vehicle_ids = self::vendor_active_vehicle_ids($user_id);
        if (empty($vehicle_ids)) {
            return ['average' => 0.0, 'count' => 0];
        }

        $weighted_sum = 0.0;
        $total_count  = 0;
        foreach ($vehicle_ids as $vid) {
            $avg = (float) get_post_meta($vid, '_mhm_rentiva_rating_average', true);
            $cnt = (int) get_post_meta($vid, '_mhm_rentiva_rating_count', true);
            if ($cnt > 0) {
                $weighted_sum += $avg * $cnt;
                $total_count  += $cnt;
            }
        }

        if ($total_count === 0) {
            return ['average' => 0.0, 'count' => 0];
        }

        return [
            'average' => round($weighted_sum / $total_count, 2),
            'count'   => $total_count,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function collect_vehicles(int $user_id, int $limit): array
    {
        $args = [
            'post_type'      => 'vehicle',
            'posts_per_page' => $limit,
            'author'         => $user_id,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => '_mhm_vehicle_lifecycle_status',
                    'value'   => 'active',
                    'compare' => '=',
                ],
            ],
            'orderby'  => [
                'meta_value_num' => 'DESC',
                'date'           => 'DESC',
            ],
            'meta_key' => '_mhm_rentiva_rating_average',
            'fields'   => 'ids',
        ];

        /**
         * Filters the WP_Query args used to collect a vendor's vehicle list
         * for the public profile page.
         *
         * @param array $args    WP_Query argument array.
         * @param int   $user_id Vendor user ID.
         */
        $args = (array) apply_filters('mhm_rentiva_vendor_profile_vehicle_query_args', $args, $user_id);

        $ids = get_posts($args);
        $out = [];
        foreach ((array) $ids as $vid) {
            $vid   = (int) $vid;
            $thumb = get_the_post_thumbnail_url($vid, 'medium');
            $out[] = [
                'id'     => $vid,
                'title'  => get_the_title($vid),
                'url'    => (string) get_permalink($vid),
                'thumb'  => $thumb !== false ? (string) $thumb : '',
                'rating' => (float) get_post_meta($vid, '_mhm_rentiva_rating_average', true),
                'count'  => (int) get_post_meta($vid, '_mhm_rentiva_rating_count', true),
            ];
        }
        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function collect_reviews(int $user_id, int $limit): array
    {
        $vehicle_ids = self::vendor_active_vehicle_ids($user_id);
        if (empty($vehicle_ids)) {
            return [];
        }

        $args = [
            'post__in' => $vehicle_ids,
            'type'     => 'review',
            'status'   => 'approve',
            'number'   => $limit,
            'orderby'  => 'comment_date',
            'order'    => 'DESC',
        ];

        /**
         * Filters the get_comments() args used to collect a vendor's reviews
         * for the public profile page.
         *
         * @param array $args    get_comments argument array.
         * @param int   $user_id Vendor user ID.
         */
        $args = (array) apply_filters('mhm_rentiva_vendor_profile_reviews_query_args', $args, $user_id);

        $comments = get_comments($args);
        $out      = [];
        foreach ((array) $comments as $c) {
            $post_id = (int) $c->comment_post_ID;
            $out[]   = [
                'id'            => (int) $c->comment_ID,
                'author'        => (string) $c->comment_author,
                'date'          => (string) $c->comment_date,
                'content'       => wp_kses_post((string) $c->comment_content),
                'rating'        => self::resolve_review_rating((int) $c->comment_ID),
                'vehicle_id'    => $post_id,
                'vehicle_title' => get_the_title($post_id),
                'vehicle_url'   => (string) get_permalink($post_id),
            ];
        }
        return $out;
    }

    /**
     * Resolve a review rating from comment meta.
     *
     * Rentiva canonical key is `mhm_rating` (set by VehicleRatingForm and the
     * ReviewEnforcer normalization hook). Falls back to the WC-standard
     * `rating` key so reviews submitted by 3rd-party plugins (e.g. WC product
     * reviews imported into vehicles, or Site Reviews / Customer Reviews for
     * WooCommerce extensions) still surface their stars on the public vendor
     * profile.
     *
     * @since 4.37.1
     */
    private static function resolve_review_rating(int $comment_id): int
    {
        $rating = (int) get_comment_meta($comment_id, 'mhm_rating', true);
        if ($rating > 0) {
            return $rating;
        }
        return (int) get_comment_meta($comment_id, 'rating', true);
    }

    /**
     * @return array<int,int>
     */
    private static function vendor_active_vehicle_ids(int $user_id): array
    {
        $ids = get_posts([
            'post_type'      => 'vehicle',
            'posts_per_page' => -1,
            'author'         => $user_id,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => '_mhm_vehicle_lifecycle_status',
                    'value'   => 'active',
                    'compare' => '=',
                ],
            ],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        return array_map('intval', (array) $ids);
    }
}
