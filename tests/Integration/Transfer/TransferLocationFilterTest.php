<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Transfer;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;

/**
 * Regression test: TransferSearchEngine must filter vehicles by the route
 * origin's CITY (not just exact location_id), inheriting from vendor user_meta
 * when the vehicle has no own location.
 *
 * Bug (2026-05-21): TransferSearchEngine had no location filter at all. A
 * search for "Kadıköy Rıhtım → Esenler Otogar" (both in İstanbul) returned
 * vehicles parked in Ankara and Antalya. Fix: extend QueryHelper helper with
 * expand_to_city=true and wire it into TransferSearchEngine via posts_where
 * (with suppress_filters=false because get_posts() suppresses filters by default).
 */
final class TransferLocationFilterTest extends \WP_UnitTestCase
{
	private int $route_id = 0;
	private int $origin_loc_id     = 9901; // city A
	private int $dest_loc_id       = 9902; // city A (same)
	private int $other_city_loc_id = 9903; // city B (different)

	public function setUp(): void
	{
		parent::setUp();
		global $wpdb;

		$loc_table = $wpdb->prefix . 'rentiva_transfer_locations';
		$wpdb->insert($loc_table, [
			'id'             => $this->origin_loc_id,
			'name'           => 'TLF Origin',
			'city'           => 'TLFCityA',
			'type'           => 'airport',
			'is_active'      => 1,
			'allow_transfer' => 1,
		]);
		$wpdb->insert($loc_table, [
			'id'             => $this->dest_loc_id,
			'name'           => 'TLF Dest (same city)',
			'city'           => 'TLFCityA',
			'type'           => 'station',
			'is_active'      => 1,
			'allow_transfer' => 1,
		]);
		$wpdb->insert($loc_table, [
			'id'             => $this->other_city_loc_id,
			'name'           => 'TLF Other (different city)',
			'city'           => 'TLFCityB',
			'type'           => 'airport',
			'is_active'      => 1,
			'allow_transfer' => 1,
		]);

		$routes_table = $wpdb->prefix . 'rentiva_transfer_routes';
		$wpdb->insert($routes_table, [
			'origin_id'      => $this->origin_loc_id,
			'destination_id' => $this->dest_loc_id,
			'distance_km'    => 30,
			'duration_min'   => 45,
			'pricing_method' => 'fixed',
			'base_price'     => 400.00,
			'min_price'      => 200.00,
			'max_price'      => 700.00,
		]);
		$this->route_id = (int) $wpdb->insert_id;
	}

	public function tearDown(): void
	{
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}rentiva_transfer_routes WHERE origin_id = {$this->origin_loc_id}");
		$wpdb->query("DELETE FROM {$wpdb->prefix}rentiva_transfer_locations WHERE id IN ({$this->origin_loc_id}, {$this->dest_loc_id}, {$this->other_city_loc_id})");
		parent::tearDown();
	}

	/**
	 * Create a transfer-eligible vehicle with optional vehicle-level location
	 * and optional author/vendor binding. Pass $location_id=0 to skip the
	 * VEHICLE_LOCATION_ID meta and exercise vendor inheritance.
	 */
	private function create_transfer_vehicle(int $location_id, ?int $author_id = null): int
	{
		$args = [
			'post_type'   => 'vehicle',
			'post_status' => 'publish',
			'post_title'  => "TLF Vehicle@$location_id",
		];
		if ($author_id !== null) {
			$args['post_author'] = $author_id;
		}
		$id = self::factory()->post->create($args);
		update_post_meta($id, '_rentiva_vehicle_service_type', 'transfer');
		update_post_meta($id, '_rentiva_transfer_max_pax', 4);
		update_post_meta($id, '_rentiva_transfer_max_luggage_score', 10);
		if ($location_id > 0) {
			update_post_meta($id, MetaKeys::VEHICLE_LOCATION_ID, $location_id);
		}
		return $id;
	}

	/**
	 * Search by the route configured in setUp.
	 */
	private function search(): array
	{
		return TransferSearchEngine::search([
			'origin_id'      => $this->origin_loc_id,
			'destination_id' => $this->dest_loc_id,
			'date'           => wp_date('Y-m-d', strtotime('+7 days')),
			'time'           => '10:00',
			'adults'         => 2,
			'children'       => 0,
			'luggage_big'    => 0,
			'luggage_small'  => 0,
		]);
	}

	/**
	 * RED: vehicle parked in a DIFFERENT city than the route origin
	 * must be excluded from search results.
	 */
	public function test_search_excludes_vehicles_in_different_city(): void
	{
		$vehicle_same_city  = $this->create_transfer_vehicle($this->dest_loc_id);       // TLFCityA
		$vehicle_other_city = $this->create_transfer_vehicle($this->other_city_loc_id); // TLFCityB

		$ids = array_column($this->search(), 'id');

		$this->assertContains($vehicle_same_city, $ids, 'Vehicle in same city as route origin must be included.');
		$this->assertNotContains($vehicle_other_city, $ids, 'Vehicle in different city must be excluded.');
	}

	/**
	 * Regression baseline: vehicle at a different location within the SAME
	 * city as the route origin must still be included (city expansion).
	 */
	public function test_search_includes_vehicles_in_same_city_different_location(): void
	{
		// Route origin = $this->origin_loc_id. Vehicle at $this->dest_loc_id (same city, different location).
		$vehicle = $this->create_transfer_vehicle($this->dest_loc_id);

		$ids = array_column($this->search(), 'id');
		$this->assertContains($vehicle, $ids, 'Vehicle at a different location in the same city must be included.');
	}

	/**
	 * Regression baseline (positive vendor inheritance): vehicle has no own
	 * location meta, but its author/vendor has a location in the same city
	 * as the route origin → must be included.
	 */
	public function test_vendor_inheritance_with_city_expansion(): void
	{
		$vendor_id = self::factory()->user->create(['role' => 'author']);
		update_user_meta($vendor_id, MetaKeys::VENDOR_LOCATION_ID, $this->dest_loc_id); // same city

		// Pass 0 to skip VEHICLE_LOCATION_ID meta so vendor inheritance kicks in.
		$vehicle_id = $this->create_transfer_vehicle(0, $vendor_id);

		$ids = array_column($this->search(), 'id');
		$this->assertContains(
			$vehicle_id,
			$ids,
			'Vehicle without own location must inherit from vendor (same city as route origin).'
		);
	}

	/**
	 * RED (negative vendor inheritance): vehicle has no own location,
	 * vendor location is in a DIFFERENT city than the route origin → must be excluded.
	 * This guards against fix regressions where inheritance silently passes through.
	 */
	public function test_vendor_inheritance_excludes_different_city(): void
	{
		$vendor_id = self::factory()->user->create(['role' => 'author']);
		update_user_meta($vendor_id, MetaKeys::VENDOR_LOCATION_ID, $this->other_city_loc_id); // different city

		$vehicle_id = $this->create_transfer_vehicle(0, $vendor_id);

		$ids = array_column($this->search(), 'id');
		$this->assertNotContains(
			$vehicle_id,
			$ids,
			'Vehicle inheriting vendor location from a different city must be excluded.'
		);
	}
}
