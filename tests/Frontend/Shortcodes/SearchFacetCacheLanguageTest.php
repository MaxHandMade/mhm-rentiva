<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * The search sidebar's facets are cached for a day under one key for every
 * visitor. Their fuel and transmission labels and category names were cached
 * already in the first visitor's language, so on a multilingual site that
 * language reached everyone for up to 24 hours. The cache holds the expensive
 * values; the words are written for the request that reads them.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults
 */
final class SearchFacetCacheLanguageTest extends WP_UnitTestCase
{
	private bool $turkish = false;

	public function setUp(): void
	{
		parent::setUp();
		delete_transient(SearchResults::FILTERS_CACHE_KEY);

		$term    = (int) self::factory()->term->create(array( 'taxonomy' => 'mhmrentiva_vehicle_category', 'name' => 'Zz Hatchback' ));
		$vehicle = ShortcodeFixtures::vehicle();
		update_post_meta($vehicle, '_mhmrentiva_fuel_type', 'petrol');
		update_post_meta($vehicle, '_mhmrentiva_transmission', 'auto');
		wp_set_object_terms($vehicle, array( $term ), 'mhmrentiva_vehicle_category');

		// A second language, switched per request: Rentiva's own labels and,
		// as a translation plugin does, the category names.
		add_filter(
			'gettext',
			function (string $translation, string $text, string $domain): string {
				if ($this->turkish && 'mhm-rentiva' === $domain && in_array($text, array( 'Petrol', 'Automatic' ), true)) {
					return 'TR ' . $text;
				}
				return $translation;
			},
			10,
			3
		);
		add_filter(
			'get_terms',
			function ($terms) {
				if (! $this->turkish || ! is_array($terms)) {
					return $terms;
				}
				foreach ($terms as $i => $term) {
					if ($term instanceof \WP_Term) {
						$term       = clone $term;
						$term->name = 'TR ' . $term->name;
						$terms[ $i ] = $term;
					}
				}
				return $terms;
			}
		);
	}

	public function tearDown(): void
	{
		delete_transient(SearchResults::FILTERS_CACHE_KEY);
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function facets(): array
	{
		$method = new \ReflectionMethod(SearchResults::class, 'filter_options');
		$method->setAccessible(true);

		return $method->invoke(null, array());
	}

	public function test_the_second_visitor_reads_the_facets_in_their_own_language(): void
	{
		$this->turkish = true;
		$first         = $this->facets();
		$this->assertSame('TR Petrol', $first['fuel_types']['petrol'], 'precondition: the first visitor gets their own language');
		$this->assertNotFalse(get_transient(SearchResults::FILTERS_CACHE_KEY), 'precondition: the facets were cached');

		$this->turkish = false;
		$second        = $this->facets();

		$this->assertSame('Petrol', $second['fuel_types']['petrol']);
		$this->assertSame('Automatic', $second['transmissions']['auto']);
		$this->assertContains('Zz Hatchback', array_column($second['categories'], 'name'));
		$this->assertNotContains('TR Zz Hatchback', array_column($second['categories'], 'name'));
	}

	public function test_an_entry_cached_with_labels_is_relabelled_on_read(): void
	{
		// An entry written before the fix carries labels; it lives until its TTL.
		$this->facets();
		$stale                            = get_transient(SearchResults::FILTERS_CACHE_KEY);
		$stale['fuel_types']['petrol']    = 'TR Petrol';
		$stale['transmissions']['auto']   = 'TR Automatic';
		$stale['categories']              = array( array( 'id' => 1, 'name' => 'TR Gone' ) );
		set_transient(SearchResults::FILTERS_CACHE_KEY, $stale, DAY_IN_SECONDS);

		$read = $this->facets();

		$this->assertSame('Petrol', $read['fuel_types']['petrol']);
		$this->assertSame('Automatic', $read['transmissions']['auto']);
		$this->assertNotContains('TR Gone', array_column($read['categories'], 'name'));
	}

	public function test_the_cache_itself_holds_no_labels(): void
	{
		$this->turkish = true;
		$this->facets();

		$cached = get_transient(SearchResults::FILTERS_CACHE_KEY);
		$this->assertSame(array( 'petrol' => 'petrol' ), $cached['fuel_types']);
		$this->assertSame(array( 'auto' => 'auto' ), $cached['transmissions']);
		$this->assertSame(array(), $cached['categories']);
	}
}
