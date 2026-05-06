<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Directory;

use MHMRentiva\Admin\Vendor\Directory\VendorDirectoryCacheInvalidator;
use MHMRentiva\Admin\Vendor\Directory\VendorDirectoryProvider;
use WP_UnitTestCase;

/**
 * @covers \MHMRentiva\Admin\Vendor\Directory\VendorDirectoryCacheInvalidator
 */
final class VendorDirectoryCacheInvalidatorTest extends WP_UnitTestCase
{
	private function seed_cache(): void
	{
		$user_id = self::factory()->user->create(['role' => 'vendor']);
		update_user_meta($user_id, '_rentiva_vendor_status', 'active');
		update_user_meta($user_id, '_rentiva_vendor_city', 'Istanbul');
		VendorDirectoryProvider::query([]); // populates transient
	}

	private function cache_count(): int
	{
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_mhm_rentiva_vendor_dir_%'"
		);
	}

	public function test_status_meta_change_invalidates(): void
	{
		VendorDirectoryCacheInvalidator::register();
		$this->seed_cache();
		$this->assertGreaterThan(0, $this->cache_count());

		$user_id = self::factory()->user->create();
		update_user_meta($user_id, '_rentiva_vendor_status', 'active');

		$this->assertSame(0, $this->cache_count(), 'Status change must invalidate directory cache.');
	}

	public function test_bio_change_does_NOT_invalidate(): void
	{
		VendorDirectoryCacheInvalidator::register();
		$this->seed_cache();
		$initial = $this->cache_count();
		$this->assertGreaterThan(0, $initial);

		$user_id = self::factory()->user->create();
		update_user_meta($user_id, '_rentiva_vendor_bio', 'New bio text');

		$this->assertSame($initial, $this->cache_count(),
			'Spec §4: bio change must NOT invalidate directory cache (deliberate scope reduction).');
	}

	public function test_save_post_vehicle_invalidates(): void
	{
		VendorDirectoryCacheInvalidator::register();
		$this->seed_cache();
		$this->assertGreaterThan(0, $this->cache_count());

		$vendor_id = self::factory()->user->create();
		$vehicle_id = self::factory()->post->create([
			'post_type' => 'vehicle',
			'post_status' => 'publish',
			'post_author' => $vendor_id,
		]);

		$this->assertSame(0, $this->cache_count(), 'Vehicle save must invalidate (city pool / count change).');
	}

	public function test_comment_status_transition_invalidates(): void
	{
		VendorDirectoryCacheInvalidator::register();
		$this->seed_cache();
		$this->assertGreaterThan(0, $this->cache_count());

		$vehicle_id = self::factory()->post->create(['post_type' => 'vehicle']);
		$comment_id = self::factory()->comment->create([
			'comment_post_ID' => $vehicle_id,
			'comment_approved' => '0',
		]);
		wp_set_comment_status($comment_id, 'approve');

		$this->assertSame(0, $this->cache_count(), 'Comment approve must invalidate (rating recalc).');
	}

	/**
	 * @group v4.38.1
	 */
	public function test_user_meta_change_ignores_non_string_meta_key(): void
	{
		$this->seed_cache();
		$initial = $this->cache_count();
		$this->assertGreaterThan(0, $initial);

		// Simulate a hook fire with an array meta_key (rare WP edge case
		// + a few legacy plugins). The handler must bail without flushing.
		VendorDirectoryCacheInvalidator::on_user_meta_change(
			1,
			1,
			['_rentiva_vendor_status'],  // array, not string
			null
		);

		$this->assertSame($initial, $this->cache_count(),
			'Non-string $meta_key must short-circuit (parity with VendorProfileCacheInvalidator).');
	}

	/**
	 * @group v4.38.1
	 */
	public function test_lifecycle_change_is_noop_when_status_unchanged(): void
	{
		$this->seed_cache();
		$initial = $this->cache_count();
		$this->assertGreaterThan(0, $initial);

		VendorDirectoryCacheInvalidator::on_lifecycle_change(1, 'active', 'active');

		$this->assertSame($initial, $this->cache_count(),
			'Same-status lifecycle transition must not flush cache (parity with on_comment_status no-op).');
	}

	/**
	 * @group v4.38.1
	 */
	public function test_lifecycle_change_invalidates_when_status_actually_changes(): void
	{
		VendorDirectoryCacheInvalidator::register();
		$this->seed_cache();
		$this->assertGreaterThan(0, $this->cache_count());

		VendorDirectoryCacheInvalidator::on_lifecycle_change(1, 'paused', 'active');

		$this->assertSame(0, $this->cache_count(),
			'Real lifecycle transition must flush cache (regression guard for the no-op fix).');
	}
}
