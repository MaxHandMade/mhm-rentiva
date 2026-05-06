<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Directory;

use MHMRentiva\Admin\Vendor\Profile\VendorBadgeEligibility;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileProvider;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileUrlBase;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Repository for the Vendor Directory page.
 *
 * Read-only sibling of {@see VendorProfileProvider} — this class queries the
 * vendor list and aggregates per-card data, but does NOT write any meta.
 * It reuses Profile's badge logic and rating aggregation.
 *
 * Cache strategy: 30-min transient, key = `mhm_rentiva_vendor_dir_<md5(query_args)>`.
 * Invalidation lives in {@see VendorDirectoryCacheInvalidator} — a deliberate
 * subset of Profile's invalidation (no bio/avatar triggers — see spec §4).
 *
 * @since 4.38.0
 */
final class VendorDirectoryProvider
{
	private const CACHE_PREFIX = 'mhm_rentiva_vendor_dir_';
	private const CACHE_TTL = 30 * MINUTE_IN_SECONDS;
	private const DEFAULT_PER_PAGE = 12;
	private const MAX_PER_PAGE = 50;

	/**
	 * @param array<string, mixed> $args  Filter args (city, badge, min_rating, sort, paged, per_page)
	 * @return array{
	 *     vendors: array<int, array<string, mixed>>,
	 *     total_count: int,
	 *     pagination: array{current: int, total_pages: int},
	 *     city_pool: array<int, string>
	 * }
	 */
	public static function query(array $args = []): array
	{
		$args = self::normalize_args($args);
		$cache_key = self::cache_key($args);

		$cached = get_transient($cache_key);
		if (is_array($cached)) {
			return $cached;
		}

		$vendor_ids = self::find_active_vendor_ids($args);
		$vendor_ids = self::filter_by_badge($vendor_ids, $args);
		$vendor_ids = self::filter_by_min_rating($vendor_ids, $args);
		$vendor_ids = self::sort_vendor_ids($vendor_ids, $args);

		$total = count($vendor_ids);
		$offset = ((int) $args['paged'] - 1) * (int) $args['per_page'];
		$page_ids = array_slice($vendor_ids, $offset, (int) $args['per_page']);

		$cards = array_map([self::class, 'build_card'], $page_ids);

		$result = [
			'vendors' => $cards,
			'total_count' => $total,
			'pagination' => [
				'current' => (int) $args['paged'],
				'total_pages' => max(1, (int) ceil($total / (int) $args['per_page'])),
			],
			'city_pool' => self::build_city_pool(),
		];

		set_transient($cache_key, $result, self::CACHE_TTL);

		return $result;
	}

	/**
	 * Drops every directory cache hash via prefix-wildcard SQL DELETE on the
	 * options table, and also via per-key delete_transient() for any names
	 * that we discover so that the in-memory wp_cache layer is consistent.
	 *
	 * Called by {@see VendorDirectoryCacheInvalidator} on relevant changes.
	 */
	public static function clear_cache(): void
	{
		global $wpdb;

		// Discover transient names from options table BEFORE deleting them.
		$like = $wpdb->esc_like('_transient_' . self::CACHE_PREFIX) . '%';
		$names = $wpdb->get_col($wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like
		));

		// delete_transient() handles both options-table and object-cache layers.
		foreach ((array) $names as $name) {
			if (is_string($name) && strpos($name, '_transient_') === 0) {
				$key = substr($name, strlen('_transient_'));
				delete_transient($key);
			}
		}

		// Defensive sweep in case anything slipped through (e.g. orphan timeouts).
		$like_timeout = $wpdb->esc_like('_transient_timeout_' . self::CACHE_PREFIX) . '%';
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$like,
			$like_timeout
		));

		// Also flush the internal options cache so subsequent get_transient()
		// calls hit the database fresh.
		wp_cache_delete('alloptions', 'options');
		wp_cache_delete('notoptions', 'options');
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private static function normalize_args(array $args): array
	{
		$allowed_sorts = ['rating', 'newest', 'alpha'];
		$allowed_badges = ['', 'verified', 'new'];

		$badge_in = (string) ($args['badge'] ?? '');
		$sort_in = (string) ($args['sort'] ?? '');

		return [
			'city'       => isset($args['city']) ? sanitize_text_field((string) $args['city']) : '',
			'badge'      => in_array($badge_in, $allowed_badges, true) ? $badge_in : '',
			'min_rating' => max(0, min(5, (int) ($args['min_rating'] ?? 0))),
			'sort'       => in_array($sort_in, $allowed_sorts, true) ? $sort_in : 'rating',
			'paged'      => max(1, (int) ($args['paged'] ?? 1)),
			'per_page'   => max(1, min(self::MAX_PER_PAGE, (int) ($args['per_page'] ?? self::DEFAULT_PER_PAGE))),
		];
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private static function cache_key(array $args): string
	{
		return self::CACHE_PREFIX . md5(serialize($args));
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<int, int>
	 */
	private static function find_active_vendor_ids(array $args): array
	{
		global $wpdb;

		$city_filter = '';
		if ($args['city'] !== '') {
			$city = $args['city'];
			$city_filter = $wpdb->prepare(" AND (
				EXISTS (SELECT 1 FROM {$wpdb->usermeta} um
					WHERE um.user_id = u.ID
					AND um.meta_key = '_rentiva_vendor_city'
					AND um.meta_value = %s)
				OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm
					JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE p.post_author = u.ID
					AND p.post_type = 'vehicle'
					AND p.post_status = 'publish'
					AND pm.meta_key = '_mhm_rentiva_vehicle_city'
					AND pm.meta_value = %s)
			)", $city, $city);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $city_filter is wpdb->prepare()'d above; rest of query is static.
		$sql = "
			SELECT u.ID FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} status_um
				ON status_um.user_id = u.ID
				AND status_um.meta_key = '_rentiva_vendor_status'
				AND status_um.meta_value = 'active'
			WHERE 1=1 {$city_filter}
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql composed from static fragments + already-prepared $city_filter.
		$ids = $wpdb->get_col($sql);

		return array_map('intval', $ids);
	}

	/**
	 * @param array<int, int> $ids
	 * @param array<string, mixed> $args
	 * @return array<int, int>
	 */
	private static function filter_by_badge(array $ids, array $args): array
	{
		if ($args['badge'] === '') {
			return $ids;
		}

		$target = $args['badge'] === 'verified' ? 'verified' : 'new';

		return array_values(array_filter($ids, static function (int $id) use ($target): bool {
			$status = VendorBadgeEligibility::evaluate($id);
			return $status === $target;
		}));
	}

	/**
	 * @param array<int, int> $ids
	 * @param array<string, mixed> $args
	 * @return array<int, int>
	 */
	private static function filter_by_min_rating(array $ids, array $args): array
	{
		if ((int) $args['min_rating'] <= 0) {
			return $ids;
		}

		$min = (int) $args['min_rating'];

		return array_values(array_filter($ids, static function (int $id) use ($min): bool {
			$rating = self::aggregate_rating_for_vendor($id);
			return $rating['avg'] >= $min;
		}));
	}

	/**
	 * @param array<int, int> $ids
	 * @param array<string, mixed> $args
	 * @return array<int, int>
	 */
	private static function sort_vendor_ids(array $ids, array $args): array
	{
		$sort = $args['sort'];

		if ($sort === 'alpha') {
			usort($ids, static function (int $a, int $b): int {
				$user_a = get_userdata($a);
				$user_b = get_userdata($b);
				$name_a = $user_a ? (string) $user_a->display_name : '';
				$name_b = $user_b ? (string) $user_b->display_name : '';
				return strcasecmp($name_a, $name_b);
			});
			return $ids;
		}

		if ($sort === 'newest') {
			usort($ids, static function (int $a, int $b): int {
				$user_a = get_userdata($a);
				$user_b = get_userdata($b);
				$reg_a = $user_a ? (strtotime((string) $user_a->user_registered) ?: 0) : 0;
				$reg_b = $user_b ? (strtotime((string) $user_b->user_registered) ?: 0) : 0;
				return $reg_b <=> $reg_a;
			});
			return $ids;
		}

		// Default: rating DESC, tie-breaker newest member
		usort($ids, static function (int $a, int $b): int {
			$rating_a = self::aggregate_rating_for_vendor($a);
			$rating_b = self::aggregate_rating_for_vendor($b);
			$cmp = $rating_b['avg'] <=> $rating_a['avg'];
			if ($cmp !== 0) {
				return $cmp;
			}
			$user_a = get_userdata($a);
			$user_b = get_userdata($b);
			$reg_a = $user_a ? (strtotime((string) $user_a->user_registered) ?: 0) : 0;
			$reg_b = $user_b ? (strtotime((string) $user_b->user_registered) ?: 0) : 0;
			return $reg_b <=> $reg_a;
		});

		return $ids;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_card(int $vendor_id): array
	{
		$user = get_userdata($vendor_id);
		$city = (string) get_user_meta($vendor_id, '_rentiva_vendor_city', true);
		$slug = (string) get_user_meta($vendor_id, '_rentiva_vendor_slug', true);
		$vehicle_count = self::count_active_vehicles_for($vendor_id);
		$rating = self::aggregate_rating_for_vendor($vendor_id);
		$badge = VendorBadgeEligibility::evaluate($vendor_id);

		$profile_url = '';
		if ($slug !== '' && class_exists(VendorProfileUrlBase::class)) {
			$profile_url = VendorProfileUrlBase::url_for_slug($slug);
		}

		return [
			'id'           => $vendor_id,
			'slug'         => $slug,
			'display_name' => $user ? (string) $user->display_name : '',
			'city'         => $city,
			'vehicle_count'=> $vehicle_count,
			'badge_status' => $badge,
			'rating_avg'   => $rating['avg'],
			'rating_count' => $rating['count'],
			'profile_url'  => $profile_url,
			'avatar_id'    => (int) get_user_meta($vendor_id, '_rentiva_vendor_avatar_id', true),
		];
	}

	/**
	 * Bridge to Profile's rating aggregator, with test seam.
	 *
	 * @return array{avg: float, count: int}
	 */
	private static function aggregate_rating_for_vendor(int $vendor_id): array
	{
		// Test seam: tests can short-circuit via filter
		$stub = apply_filters('mhm_rentiva_vendor_aggregate_rating_for_test', null, $vendor_id);
		if (is_array($stub) && isset($stub['avg'], $stub['count'])) {
			return ['avg' => (float) $stub['avg'], 'count' => (int) $stub['count']];
		}

		if (class_exists(VendorProfileProvider::class) && method_exists(VendorProfileProvider::class, 'aggregate_rating_for_vendor')) {
			$r = VendorProfileProvider::aggregate_rating_for_vendor($vendor_id);
			return ['avg' => (float) ($r['avg'] ?? 0.0), 'count' => (int) ($r['count'] ?? 0)];
		}

		return ['avg' => 0.0, 'count' => 0];
	}

	private static function count_active_vehicles_for(int $vendor_id): int
	{
		$q = new \WP_Query([
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'author'         => $vendor_id,
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		]);

		return count($q->posts);
	}

	/**
	 * Distinct list of cities (vendor.city ∪ vehicle.city), alphabetically sorted.
	 *
	 * @return array<int, string>
	 */
	private static function build_city_pool(): array
	{
		global $wpdb;

		$sql = "
			SELECT DISTINCT TRIM(meta_value) AS city FROM {$wpdb->usermeta}
			WHERE meta_key = '_rentiva_vendor_city' AND meta_value <> ''
			UNION
			SELECT DISTINCT TRIM(pm.meta_value) AS city FROM {$wpdb->postmeta} pm
			JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = 'vehicle'
			  AND p.post_status = 'publish'
			  AND pm.meta_key = '_mhm_rentiva_vehicle_city'
			  AND pm.meta_value <> ''
			ORDER BY city ASC
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static SQL, no user input.
		$rows = $wpdb->get_col($sql);

		return array_values(array_filter(array_map('strval', $rows)));
	}
}
