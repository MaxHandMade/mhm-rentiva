<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Directory;

use MHMRentiva\Admin\Vendor\Directory\VendorDirectoryProvider;
use WP_UnitTestCase;

/**
 * @covers \MHMRentiva\Admin\Vendor\Directory\VendorDirectoryProvider
 */
final class VendorDirectoryProviderTest extends WP_UnitTestCase
{
	private function create_active_vendor(string $city = 'Istanbul', int $score = 90, ?int $approved_days_ago = 200): int
	{
		$user_id = self::factory()->user->create([
			'role' => 'vendor',
			'display_name' => 'Vendor ' . wp_generate_password(8, false),
		]);
		update_user_meta($user_id, '_rentiva_vendor_status', 'active');
		update_user_meta($user_id, '_rentiva_vendor_city', $city);
		update_user_meta($user_id, '_rentiva_vendor_reliability_score', $score);
		if ($approved_days_ago !== null) {
			update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s', time() - ($approved_days_ago * DAY_IN_SECONDS)));
		}
		update_user_meta($user_id, '_rentiva_vendor_slug', 'vendor-' . $user_id);

		return $user_id;
	}

	public function test_query_returns_only_active_vendors(): void
	{
		$active = $this->create_active_vendor();
		$inactive = self::factory()->user->create(['role' => 'vendor']);
		update_user_meta($inactive, '_rentiva_vendor_status', 'suspended');

		$result = VendorDirectoryProvider::query([]);
		$ids = array_column($result['vendors'], 'id');

		$this->assertContains($active, $ids);
		$this->assertNotContains($inactive, $ids);
	}

	public function test_query_filters_by_badge_verified_only(): void
	{
		$verified = $this->create_active_vendor('Istanbul', 90, 200);
		$new = $this->create_active_vendor('Ankara', 50, 30);

		add_filter('mhm_rentiva_vendor_completed_bookings_count', static fn(): int => 15);

		$result = VendorDirectoryProvider::query(['badge' => 'verified']);
		$ids = array_column($result['vendors'], 'id');

		$this->assertContains($verified, $ids);
		$this->assertNotContains($new, $ids);

		remove_all_filters('mhm_rentiva_vendor_completed_bookings_count');
	}

	public function test_query_filters_by_min_rating(): void
	{
		$high_rated = $this->create_active_vendor();
		$low_rated = $this->create_active_vendor('Ankara');

		add_filter('mhm_rentiva_vendor_aggregate_rating_for_test', function ($_, $vendor_id) use ($high_rated, $low_rated): array {
			if ($vendor_id === $high_rated) {
				return ['avg' => 4.5, 'count' => 10];
			}
			if ($vendor_id === $low_rated) {
				return ['avg' => 2.5, 'count' => 4];
			}
			return ['avg' => 0.0, 'count' => 0];
		}, 10, 2);

		$result = VendorDirectoryProvider::query(['min_rating' => 4]);
		$ids = array_column($result['vendors'], 'id');

		$this->assertContains($high_rated, $ids);
		$this->assertNotContains($low_rated, $ids);

		remove_all_filters('mhm_rentiva_vendor_aggregate_rating_for_test');
	}

	public function test_query_sorts_by_rating_desc_with_newest_tiebreaker(): void
	{
		$high = $this->create_active_vendor('Istanbul', 90, 200);
		$tied_old = self::factory()->user->create([
			'role' => 'vendor',
			'user_registered' => '2020-01-01 00:00:00',
		]);
		update_user_meta($tied_old, '_rentiva_vendor_status', 'active');
		update_user_meta($tied_old, '_rentiva_vendor_city', 'Ankara');
		$tied_new = self::factory()->user->create([
			'role' => 'vendor',
			'user_registered' => '2024-01-01 00:00:00',
		]);
		update_user_meta($tied_new, '_rentiva_vendor_status', 'active');
		update_user_meta($tied_new, '_rentiva_vendor_city', 'Izmir');

		add_filter('mhm_rentiva_vendor_aggregate_rating_for_test', function ($_, $id) use ($high, $tied_old, $tied_new): array {
			if ($id === $high) {
				return ['avg' => 5.0, 'count' => 10];
			}
			if ($id === $tied_old) {
				return ['avg' => 3.0, 'count' => 5];
			}
			if ($id === $tied_new) {
				return ['avg' => 3.0, 'count' => 5];
			}
			return ['avg' => 0.0, 'count' => 0];
		}, 10, 2);

		$result = VendorDirectoryProvider::query(['sort' => 'rating']);
		$ids = array_column($result['vendors'], 'id');

		$high_pos = array_search($high, $ids, true);
		$new_pos = array_search($tied_new, $ids, true);
		$old_pos = array_search($tied_old, $ids, true);

		$this->assertLessThan($new_pos, $high_pos, 'Higher rating must rank above tied pair.');
		$this->assertLessThan($old_pos, $new_pos, 'Newer member must rank above older when ratings tied.');

		remove_all_filters('mhm_rentiva_vendor_aggregate_rating_for_test');
	}

	public function test_query_per_page_clamp(): void
	{
		for ($i = 0; $i < 5; $i++) {
			$this->create_active_vendor('Istanbul');
		}

		$result = VendorDirectoryProvider::query(['per_page' => 2, 'paged' => 1]);
		$this->assertCount(2, $result['vendors']);
		$this->assertSame(1, $result['pagination']['current']);

		// per_page over MAX_PER_PAGE clamps to 50
		$clamped = VendorDirectoryProvider::query(['per_page' => 999]);
		$this->assertLessThanOrEqual(50, count($clamped['vendors']));
	}

	public function test_query_negative_paged_coerces_to_one(): void
	{
		$this->create_active_vendor();
		$result = VendorDirectoryProvider::query(['paged' => -5]);
		$this->assertSame(1, $result['pagination']['current']);
	}

	public function test_cache_hit_skips_underlying_query(): void
	{
		$vendor_id = $this->create_active_vendor();

		// First call populates cache
		VendorDirectoryProvider::query([]);

		// Modify status directly via $wpdb to bypass cache invalidator hooks
		global $wpdb;
		$wpdb->update($wpdb->usermeta,
			['meta_value' => 'suspended'],
			['user_id' => $vendor_id, 'meta_key' => '_rentiva_vendor_status']
		);
		wp_cache_delete($vendor_id, 'user_meta');

		// Second call should still return the cached vendor (cache wasn't invalidated)
		$result = VendorDirectoryProvider::query([]);
		$ids = array_column($result['vendors'], 'id');
		$this->assertContains($vendor_id, $ids, 'Cache hit must return stale vendor before invalidation.');

		// Now manually clear cache and verify fresh query reflects change
		VendorDirectoryProvider::clear_cache();
		$fresh = VendorDirectoryProvider::query([]);
		$fresh_ids = array_column($fresh['vendors'], 'id');
		$this->assertNotContains($vendor_id, $fresh_ids, 'After clear_cache, suspended vendor must be excluded.');
	}

	public function test_city_pool_unions_vendor_and_vehicle_cities(): void
	{
		$vendor_a = $this->create_active_vendor('Istanbul');
		$vendor_b = $this->create_active_vendor('Ankara');

		$vehicle_id = self::factory()->post->create([
			'post_type' => 'vehicle',
			'post_status' => 'publish',
			'post_author' => $vendor_a,
		]);
		update_post_meta($vehicle_id, '_mhm_rentiva_vehicle_city', 'Antalya');

		$result = VendorDirectoryProvider::query([]);
		$cities = $result['city_pool'];

		$this->assertContains('Istanbul', $cities);
		$this->assertContains('Ankara', $cities);
		$this->assertContains('Antalya', $cities, 'Vehicle.city must appear in pool even if no vendor is headquartered there.');
	}

	public function test_badge_filter_uses_canonical_completed_bookings_default(): void
	{
		// No filter override — use ReliabilityScoreCalculator default count
		$vendor = $this->create_active_vendor('Istanbul', 90, 200);

		// Without bookings, count_completed_bookings() returns 0,
		// so VendorBadgeEligibility falls below min_completed_bookings threshold
		// and returns 'new' (not 'verified').
		$result_verified = VendorDirectoryProvider::query(['badge' => 'verified']);
		$result_new = VendorDirectoryProvider::query(['badge' => 'new']);

		$verified_ids = array_column($result_verified['vendors'], 'id');
		$new_ids = array_column($result_new['vendors'], 'id');

		$this->assertNotContains($vendor, $verified_ids, 'Vendor with 0 bookings must not be verified.');
		$this->assertContains($vendor, $new_ids, 'Vendor with 0 bookings must be in new bucket.');
	}

	public function test_real_profile_delegate_returns_zero_for_vendor_with_no_reviews(): void
	{
		// No filter override — exercise the real VendorProfileProvider delegate.
		// Vendor with 0 reviews must yield avg=0.0, count=0 from real aggregator.
		$vendor = $this->create_active_vendor('Istanbul');

		$result = VendorDirectoryProvider::query([]);
		$cards = $result['vendors'];

		// Find this vendor's card
		$found_card = null;
		foreach ($cards as $card) {
			if ($card['id'] === $vendor) {
				$found_card = $card;
				break;
			}
		}

		$this->assertNotNull($found_card, 'Vendor must appear in directory result.');
		$this->assertSame(0.0, (float) $found_card['rating_avg'], 'Vendor with no reviews must have rating_avg = 0.0 (real Profile delegate).');
		$this->assertSame(0, (int) $found_card['rating_count'], 'Vendor with no reviews must have rating_count = 0 (real Profile delegate).');
	}
}
