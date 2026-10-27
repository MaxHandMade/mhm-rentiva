<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\LocationContext;
use MHMRentiva\Admin\Core\MetaKeys;
use WP_UnitTestCase;

/**
 * PR S: one place that knows the site's rental pickup points.
 *
 * Single vs multi follows the number of rental locations (no setting): 0 is
 * Lite alone (no location anywhere), 1 is "one pickup point" (no select, an
 * info line instead), 2+ is the select plus a separate "same city" section.
 */
final class LocationContextTest extends WP_UnitTestCase
{
	/** @var array<int, object> */
	private array $rows = array();

	public function setUp(): void
	{
		parent::setUp();
		LocationContext::flush();
	}

	public function tearDown(): void
	{
		remove_all_filters('mhmrentiva_locations');
		delete_option('mhmrentiva_settings');
		LocationContext::flush();
		parent::tearDown();
	}

	/**
	 * Provide rental locations the way Pro's provider does (DB rows: string ids).
	 *
	 * @param array<int, array{0:int,1:string,2:string}> $rows id, name, city.
	 */
	private function provide(array $rows): void
	{
		$this->rows = array();
		foreach ($rows as $row) {
			$this->rows[] = (object) array(
				'id'           => (string) $row[0],
				'name'         => $row[1],
				'city'         => $row[2],
				'allow_rental' => '1',
			);
		}
		remove_all_filters('mhmrentiva_locations');
		// A freed closure's object id can be reused by the next one, so the
		// subscriber fingerprint cannot be relied on across a remove + add.
		LocationContext::flush();
		$rows_out = $this->rows;
		add_filter(
			'mhmrentiva_locations',
			static function ($locations, $type) use ($rows_out) {
				return 'rental' === $type ? $rows_out : $locations;
			},
			10,
			2
		);
	}

	private function vehicle(int $location_id = 0, int $author = 0): int
	{
		$meta = array( '_mhmrentiva_vehicle_status' => 'active' );
		if ($location_id > 0) {
			$meta[ MetaKeys::VEHICLE_LOCATION_ID ] = (string) $location_id;
		}
		$args = array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'meta_input'  => $meta,
		);
		if ($author > 0) {
			$args['post_author'] = $author;
		}
		return (int) self::factory()->post->create($args);
	}

	public function test_lite_alone_has_no_locations(): void
	{
		$this->assertSame(array(), LocationContext::rental_locations());
		$this->assertFalse(LocationContext::is_single());
		$this->assertNull(LocationContext::single());
		$this->assertNull(LocationContext::for_vehicle($this->vehicle(3)));
		$this->assertSame(array(), LocationContext::same_city_ids(array( 3 )));
		$this->assertSame(array(), LocationContext::cities_of(array( 3 )));
		$this->assertSame(array(), LocationContext::effective_selected(array( '3' )));
	}

	public function test_prime_reads_every_vendor_point_in_one_go(): void
	{
		$this->provide(array( array( 3, 'Airport', 'İstanbul' ), array( 4, 'Taksim', 'İstanbul' ) ));
		$authors  = self::factory()->user->create_many(2);
		$vehicles = array();
		foreach ($authors as $author) {
			update_user_meta($author, MetaKeys::VENDOR_LOCATION_ID, '4');
			$vehicles[] = $this->vehicle(0, $author);
			$vehicles[] = $this->vehicle(0, $author);
		}
		foreach ($authors as $author) {
			wp_cache_delete($author, 'user_meta');
		}
		foreach ($vehicles as $id) {
			wp_cache_delete($id, 'post_meta');
		}

		LocationContext::prime_vehicles($vehicles);

		global $wpdb;
		$before = $wpdb->num_queries;
		foreach ($vehicles as $id) {
			$this->assertSame(4, LocationContext::for_vehicle($id)->id);
		}
		$this->assertSame(0, $wpdb->num_queries - $before, 'Cards must not look up their vendor one by one.');
	}

	public function test_rental_locations_are_typed_and_cached_per_request(): void
	{
		$calls = 0;
		add_filter(
			'mhmrentiva_locations',
			static function ($locations, $type) use (&$calls) {
				++$calls;
				return array(
					(object) array( 'id' => '4', 'name' => ' Sabiha Gökçen ', 'city' => 'İstanbul' ),
					(object) array( 'id' => '0', 'name' => 'Broken', 'city' => 'X' ),
					(object) array( 'id' => '4', 'name' => 'Duplicate', 'city' => 'İstanbul' ),
					array( 'id' => 5, 'name' => 'Esenboğa', 'city' => 'Ankara' ),
				);
			},
			10,
			2
		);

		$first  = LocationContext::rental_locations();
		$second = LocationContext::rental_locations();

		$this->assertSame(1, $calls, 'One provider call per request.');
		$this->assertCount(2, $first);
		$this->assertSame(4, $first[0]->id);
		$this->assertSame('Sabiha Gökçen', $first[0]->name);
		$this->assertSame('İstanbul', $first[0]->city);
		$this->assertSame(5, $first[1]->id);
		$this->assertEquals($first, $second);
	}

	public function test_cache_follows_a_changed_subscriber(): void
	{
		$this->provide(array( array( 3, 'İstanbul Havalimanı', 'İstanbul' ) ));
		$this->assertTrue(LocationContext::is_single());

		// A subscriber added mid-request (no flush) is seen.
		add_filter(
			'mhmrentiva_locations',
			static function ($locations) {
				$locations[] = (object) array( 'id' => '4', 'name' => 'Sabiha Gökçen', 'city' => 'İstanbul' );
				return $locations;
			},
			20
		);
		$this->assertFalse(LocationContext::is_single());
		$this->assertCount(2, LocationContext::rental_locations());
	}

	public function test_single_location_hides_select_and_shows_line(): void
	{
		$this->provide(array( array( 3, 'İstanbul Havalimanı', 'İstanbul' ) ));

		$this->assertTrue(LocationContext::is_single());
		$this->assertSame(3, LocationContext::single()->id);
		$this->assertSame('İstanbul Havalimanı', LocationContext::single()->name);

		// A vehicle with no point of its own still picks up and returns there.
		$this->assertSame(3, LocationContext::for_vehicle($this->vehicle())->id);

		$this->provide(array( array( 3, 'İstanbul Havalimanı', 'İstanbul' ), array( 4, 'Sabiha Gökçen', 'İstanbul' ) ));
		$this->assertFalse(LocationContext::is_single());
		$this->assertNull(LocationContext::single());
	}

	/**
	 * @return array<string, array{0: bool}>
	 */
	public static function intl_paths(): array
	{
		return array(
			'with intl'    => array( true ),
			'without intl' => array( false ),
		);
	}

	/**
	 * @dataProvider intl_paths
	 */
	public function test_same_city_turkish_folding(bool $use_intl): void
	{
		if ($use_intl && ! class_exists('Normalizer')) {
			$this->markTestSkipped('intl is not loaded in this PHP.');
		}

		$groups = array(
			array( 'İstanbul', 'istanbul', 'ISTANBUL', 'Istanbul ', 'İSTANBUL', "  İstanbul\t" ),
			array( 'Izmir', 'İzmir', 'IZMIR', 'izmir' ),
			array( 'Isparta', 'ISPARTA', 'ısparta', 'isparta' ),
			array( 'Ankara', 'ANKARA' ),
		);
		$keys = array();
		foreach ($groups as $group) {
			$folded = array();
			foreach ($group as $city) {
				$folded[ LocationContext::normalize_city($city, $use_intl) ] = true;
			}
			$this->assertCount(1, $folded, 'One key for ' . implode(' / ', $group) . ': ' . implode(', ', array_keys($folded)));
			$keys[] = (string) array_key_first($folded);
		}
		$this->assertCount(4, array_unique($keys), 'Different cities stay different.');
		$this->assertSame(array( 'istanbul', 'izmir', 'isparta', 'ankara' ), $keys, 'The folded value itself, not only one key per group.');
		$this->assertSame('istanbul', LocationContext::normalize_city('İSTANBUL', $use_intl));
		$this->assertSame('izmir', LocationContext::normalize_city('İzmir', $use_intl));
		$this->assertSame('isparta', LocationContext::normalize_city('ısparta', $use_intl));
		$this->assertSame('', LocationContext::normalize_city('   ', $use_intl));
		$this->assertSame('new york', LocationContext::normalize_city("New   York", $use_intl));
		// A pasted no-break space folds like a plain one.
		$this->assertSame('istanbul', LocationContext::normalize_city("İstanbul\u{00A0}", $use_intl));
		$this->assertSame('new york', LocationContext::normalize_city("New\u{00A0}York", $use_intl));
	}

	public function test_locations_changed_action_drops_the_request_cache(): void
	{
		$rows = array( (object) array( 'id' => '3', 'name' => 'İstanbul Havalimanı', 'city' => 'İstanbul' ) );
		add_filter(
			'mhmrentiva_locations',
			static function ($locations, $type) use (&$rows) {
				return 'rental' === $type ? $rows : $locations;
			},
			10,
			2
		);
		$this->assertTrue(LocationContext::is_single());

		// Same subscriber, new data (a location saved in this request).
		$rows[] = (object) array( 'id' => '4', 'name' => 'Sabiha Gökçen', 'city' => 'İstanbul' );
		$this->assertTrue(LocationContext::is_single(), 'Cached until someone says the list changed.');

		do_action(LocationContext::CHANGED_ACTION);
		$this->assertSame('mhmrentiva_locations_changed', LocationContext::CHANGED_ACTION);
		$this->assertFalse(LocationContext::is_single());
		$this->assertCount(2, LocationContext::rental_locations());
	}

	public function test_a_nameless_location_is_named_by_its_city(): void
	{
		$this->provide(array( array( 3, '', 'İstanbul' ) ));

		$this->assertSame('İstanbul', LocationContext::single()->name);
	}

	public function test_same_city_excludes_selected_and_other_cities(): void
	{
		$this->provide(
			array(
				array( 3, 'İstanbul Havalimanı', 'İstanbul' ),
				array( 4, 'Sabiha Gökçen', 'istanbul' ),
				array( 7, 'Kadıköy', 'ISTANBUL ' ),
				array( 5, 'Esenboğa', 'Ankara' ),
				array( 9, 'Kızılay', 'ANKARA' ),
				array( 6, 'Antalya Havalimanı', 'Antalya' ),
				array( 12, 'Nowhere', '' ),
			)
		);

		$this->assertSame(array( 4, 7 ), LocationContext::same_city_ids(array( 3 )));
		$this->assertSame(array( 7 ), LocationContext::same_city_ids(array( 3, 4 )));
		$this->assertSame(array( 4, 7, 9 ), LocationContext::same_city_ids(array( 3, 5 )));
		$this->assertSame(array(), LocationContext::same_city_ids(array( 6 )), 'Alone in its city.');
		$this->assertSame(array(), LocationContext::same_city_ids(array( 12 )), 'No city, no neighbours.');
		$this->assertSame(array(), LocationContext::same_city_ids(array( 99 )), 'Not a rental location.');
		$this->assertSame(array(), LocationContext::same_city_ids(array()));

		$this->assertSame(array( 'İstanbul' ), LocationContext::cities_of(array( 3, 4 )));
		$this->assertSame(array( 'İstanbul', 'Ankara' ), LocationContext::cities_of(array( 3, 5, 9 )));
		$this->assertSame(array(), LocationContext::cities_of(array( 12, 99 )));
	}

	public function test_for_vehicle_follows_vendor_and_default_fallback(): void
	{
		$this->provide(
			array(
				array( 3, 'İstanbul Havalimanı', 'İstanbul' ),
				array( 4, 'Sabiha Gökçen', 'İstanbul' ),
				array( 5, 'Esenboğa', 'Ankara' ),
			)
		);

		$this->assertSame(4, LocationContext::for_vehicle($this->vehicle(4))->id, 'The vehicle\'s own point.');

		$vendor = (int) self::factory()->user->create();
		update_user_meta($vendor, MetaKeys::VENDOR_LOCATION_ID, '5');
		$this->assertSame(5, LocationContext::for_vehicle($this->vehicle(0, $vendor))->id, 'The vendor\'s point.');

		update_option('mhmrentiva_settings', array( 'mhmrentiva_default_rental_location' => 3 ));
		$this->assertSame(3, LocationContext::for_vehicle($this->vehicle())->id, 'The site default.');

		delete_option('mhmrentiva_settings');
		$this->assertNull(LocationContext::for_vehicle($this->vehicle()), 'No point anywhere.');
		$this->assertNull(LocationContext::for_vehicle($this->vehicle(11)), 'Not a rental location (inactive / transfer only).');
	}

	public function test_url_location_ignored_when_zero_or_one_location(): void
	{
		$this->assertSame(array(), LocationContext::effective_selected(array( '3' )), 'Lite alone.');

		$this->provide(array( array( 3, 'İstanbul Havalimanı', 'İstanbul' ) ));
		$this->assertSame(array(), LocationContext::effective_selected(array( '3' )), 'One location: nothing to choose.');

		$this->provide(
			array(
				array( 3, 'İstanbul Havalimanı', 'İstanbul' ),
				array( 4, 'Sabiha Gökçen', 'İstanbul' ),
			)
		);
		$this->assertSame(array( 3 ), LocationContext::effective_selected(array( '3' )));
		$this->assertSame(array( 4, 3 ), LocationContext::effective_selected(array( '4', 3, '3', '99', 'x', '-4', '' )));
		$this->assertSame(array(), LocationContext::effective_selected(array()));
	}
}
