<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\Cache;
use MHMRentiva\Admin\Core\Utilities\OccupancyMapService;
use WP_UnitTestCase;

/**
 * PR S (M2, review I2) -- an invalidated cache entry is never served again,
 * whatever backs the transients.
 *
 * Invalidation used to be a raw `DELETE FROM wp_options ... LIKE`. With a
 * persistent object cache (Redis, Memcached) transients never reach that
 * table, so the DELETE removed nothing and a removed blocked day -- or a
 * cancelled booking -- stayed "booked" for the cache's lifetime. Within one
 * request the same happens on any site: get_transient() answers from the
 * in-memory object cache the DELETE never touched.
 *
 * The keys now carry a generation salt kept in an option (object-cache
 * coherent through update_option()). Invalidation moves the salt, so the old
 * keys are simply never asked for again; the DELETE stays as cleanup.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\Cache
 * @covers \MHMRentiva\Admin\Core\Utilities\OccupancyMapService::invalidate
 */
final class CacheGenerationTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		OccupancyMapService::reset_memo();
	}

	private function vehicle(): int
	{
		return (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
	}

	public function test_an_invalidated_answer_is_not_served_in_the_same_request(): void
	{
		$vehicle = $this->vehicle();
		$other   = $this->vehicle();
		Cache::setAvailability($vehicle, 100, 200, array( 'ok' => false ));
		Cache::setAvailability($other, 100, 200, array( 'ok' => false ));
		$this->assertNotNull(Cache::getAvailability($vehicle, 100, 200), 'Precondition: cached.');

		Cache::invalidateVehicle($vehicle);

		$this->assertNull(Cache::getAvailability($vehicle, 100, 200));
		$this->assertNotNull(Cache::getAvailability($other, 100, 200), 'Only that vehicle is invalidated.');
	}

	public function test_a_lost_generation_never_revives_old_entries(): void
	{
		$vehicle = $this->vehicle();
		Cache::setAvailability($vehicle, 100, 200, array( 'ok' => false ));

		// The salt is gone (a cleanup tool, a restored table): the next salt is new.
		global $wpdb;
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('mhmrentiva_cache_gen_') . '%'));
		wp_cache_flush();

		$this->assertNull(Cache::getAvailability($vehicle, 100, 200));
	}

	public function test_clear_all_orphans_every_entry(): void
	{
		$vehicle = $this->vehicle();
		Cache::setAvailability($vehicle, 100, 200, array( 'ok' => false ));

		Cache::clearAll();

		$this->assertNull(Cache::getAvailability($vehicle, 100, 200));
	}

	public function test_occupancy_invalidation_is_seen_in_the_same_request(): void
	{
		$vehicle = $this->vehicle();
		$this->assertArrayNotHasKey($vehicle, OccupancyMapService::get_map('2027-01-01', '2027-01-07'), 'Precondition: nothing booked, and now cached.');

		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($booking, '_mhmrentiva_vehicle_id', $vehicle);
		update_post_meta($booking, '_mhmrentiva_status', 'confirmed');
		update_post_meta($booking, '_mhmrentiva_pickup_date', '2027-01-02');
		update_post_meta($booking, '_mhmrentiva_dropoff_date', '2027-01-04');

		OccupancyMapService::invalidate();

		$this->assertArrayHasKey($vehicle, OccupancyMapService::get_map('2027-01-01', '2027-01-07'));
	}
}
