<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Attribute;

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\CanonicalAttributeMapper;
use MHMRentiva\Core\Attribute\KeyNormalizer;
use WP_UnitTestCase;

/**
 * Slice 1 schema corrections (spec 2.4): page size key, per-tag orderby values, aliases.
 *
 * @covers \MHMRentiva\Core\Attribute\AllowlistRegistry
 */
final class SchemaSlice1Test extends WP_UnitTestCase
{
	public function test_results_per_page_is_its_own_key(): void
	{
		$schema = AllowlistRegistry::get_schema('rentiva_search_results');
		$this->assertSame('results_per_page', KeyNormalizer::normalize('results_per_page', $schema));
		$this->assertArrayNotHasKey('limit', $schema);
		$mapped = CanonicalAttributeMapper::map('rentiva_search_results', array( 'results_per_page' => '24' ));
		$this->assertSame(24, $mapped['results_per_page']);
	}

	public function test_search_results_limit_maps_to_results_per_page(): void
	{
		$schema = AllowlistRegistry::get_schema('rentiva_search_results');
		$this->assertSame('results_per_page', KeyNormalizer::normalize('limit', $schema));
	}

	public function test_limit_stays_limit_for_other_tags(): void
	{
		$schema = AllowlistRegistry::get_schema('rentiva_vehicles_list');
		$this->assertSame('limit', KeyNormalizer::normalize('limit', $schema));
	}

	public function test_search_results_default_sort_matches_shortcode(): void
	{
		$schema = AllowlistRegistry::get_schema('rentiva_search_results');
		$this->assertSame('price_asc', $schema['default_sort']['default']);
	}

	public function test_grid_orderby_featured_survives_map(): void
	{
		$mapped = CanonicalAttributeMapper::map('rentiva_vehicles_grid', array( 'orderby' => 'featured' ));
		$this->assertSame('featured', $mapped['orderby']);
	}

	public function test_my_bookings_orderby_id_survives_map(): void
	{
		$mapped = CanonicalAttributeMapper::map('rentiva_my_bookings', array( 'orderby' => 'id' ));
		$this->assertSame('id', $mapped['orderby']);
	}

	public function test_shared_orderby_values_unchanged(): void
	{
		$mapped = CanonicalAttributeMapper::map('rentiva_testimonials', array( 'orderby' => 'featured' ));
		$this->assertNotSame('featured', $mapped['orderby']);
		$this->assertNotContains('featured', AllowlistRegistry::ALLOWLIST['orderby']['values']);
		$this->assertNotContains('id', AllowlistRegistry::ALLOWLIST['orderby']['values']);
	}

	public function test_aliases_resolve(): void
	{
		$list = AllowlistRegistry::get_schema('rentiva_vehicles_list');
		$this->assertSame('show_favorite_button', KeyNormalizer::normalize('show_favorite', $list));
		$this->assertSame('show_image', KeyNormalizer::normalize('show_images', $list));
		$this->assertSame('show_rating', KeyNormalizer::normalize('showRating', $list));
		$testimonials = AllowlistRegistry::get_schema('rentiva_testimonials');
		$this->assertSame('rating', KeyNormalizer::normalize('filterRating', $testimonials));
		$this->assertSame('rating', KeyNormalizer::normalize('filter_rating', $testimonials));
	}

	public function test_testimonials_filter_rating_maps_to_rating(): void
	{
		$mapped = CanonicalAttributeMapper::map('rentiva_testimonials', array( 'filterRating' => '4' ));
		$this->assertSame(4, $mapped['rating']);
	}
}
