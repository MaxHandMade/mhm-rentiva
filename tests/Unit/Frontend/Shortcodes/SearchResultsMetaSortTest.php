<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use WP_UnitTestCase;

/**
 * Görev 14 (T8, SlowDBQuery sweep), rows 21-23: SearchResults.php:543,548,561.
 *
 * perform_search()'s 'price_asc'/'price_desc'/'year_desc' branches used flat
 * top-level 'meta_key' + 'orderby' => 'meta_value_num', purely to sort (no
 * pre-existing meta_query clause on the same key at those points). Rewritten
 * to a named meta_query clause ('compare' => 'EXISTS', matching the exact
 * convention this codebase's own RatingSortHelper::apply_sort_args() already
 * uses) + compound 'orderby' => array($name => $direction). Traced through
 * wp-includes/class-wp-meta-query.php: for a clause with 'compare' => 'EXISTS'
 * and no 'value', the WHERE fragment is `alias.meta_key = 'x'` -- identical
 * to what WP_Query's own WP_Meta_Query::parse_query_vars() already
 * auto-synthesizes from a bare flat 'meta_key' (no meta_compare/meta_value
 * set): both take the '=' case in the meta_key switch, both skip the
 * meta_value block entirely. So this is not a new filter, it's the same
 * auto-synthesized clause written explicitly and named so 'orderby' can
 * reference it directly instead of relying on WP_Query's "first meta clause"
 * fallback resolution.
 *
 * This test was run and PASSED against the pre-change code first (see
 * task-14-report.md for the transcript), including the
 * without-the-sorted-meta-key exclusion case below, which is PRE-EXISTING
 * behavior (the auto-synthesized clause already required the key to exist,
 * via INNER JOIN) -- not something this rewrite introduces.
 *
 * perform_search() is private; invoked via ReflectionMethod, the same
 * pattern already established in this codebase's own
 * AdminFilterQueryVarsTest.php for private-reader coverage.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::perform_search
 */
final class SearchResultsMetaSortTest extends WP_UnitTestCase {

	private function make_vehicle( ?string $price = null, ?string $year = null ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		) );
		// No _mhmrentiva_vehicle_status / _mhmrentiva_vehicle_lifecycle_status meta
		// -> MetaQueryHelper::get_active_vehicle_meta_query()'s legacy-default
		// branch treats the vehicle as active.
		// No _mhmrentiva_vehicle_service_type meta -> passes the transfer-exclude
		// OR(!= 'transfer', NOT EXISTS) clause via its NOT EXISTS arm.
		if ( null !== $price ) {
			update_post_meta( $id, '_mhmrentiva_price_per_day', $price );
		}
		if ( null !== $year ) {
			update_post_meta( $id, '_mhmrentiva_year', $year );
		}
		return $id;
	}

	/**
	 * @return int[] Vehicle IDs in result order.
	 */
	private function search( string $sort ): array {
		$params = array(
			'keyword'         => '',
			'pickup_date'     => '',
			'return_date'     => '',
			'start_date'      => '',
			'end_date'        => '',
			'min_price'       => 0,
			'max_price'       => 0,
			'fuel_type'       => '',
			'transmission'    => '',
			'seats'           => '',
			'brand'           => '',
			'year_min'        => 0,
			'year_max'        => 0,
			'mileage_max'     => 0,
			'sort'            => $sort,
			'page'            => 1,
			'pickup_location' => array(),
		);
		$atts = array( 'results_per_page' => 12 );

		$method = new \ReflectionMethod( SearchResults::class, 'perform_search' );
		$method->setAccessible( true );
		$result = $method->invoke( null, $params, $atts );

		return array_map( static fn( $v ) => (int) $v['id'], $result['vehicles'] );
	}

	public function test_price_asc_sorts_ascending_by_price(): void {
		$cheap = $this->make_vehicle( '50' );
		$mid   = $this->make_vehicle( '150' );
		$exp   = $this->make_vehicle( '500' );

		$this->assertSame( array( $cheap, $mid, $exp ), $this->search( 'price_asc' ) );
	}

	public function test_price_desc_sorts_descending_by_price(): void {
		$cheap = $this->make_vehicle( '50' );
		$mid   = $this->make_vehicle( '150' );
		$exp   = $this->make_vehicle( '500' );

		$this->assertSame( array( $exp, $mid, $cheap ), $this->search( 'price_desc' ) );
	}

	/**
	 * Review fix round 1 (Important finding): every other fixture in this file
	 * creates vehicles in an order where ascending post ID already coincides
	 * with ascending price, so a regression that silently dropped 'orderby'
	 * and fell back to WP_Query's default order could still pass those tests
	 * by accident. This test deliberately inserts in an order that DIFFERS
	 * from the asserted price order -- mirrors
	 * BookingQueryHelperDateRangeSortTest::test_sort_order_is_by_pickup_date_not_by_post_id().
	 */
	public function test_sort_order_is_by_price_not_by_post_id(): void {
		$first_inserted_priciest  = $this->make_vehicle( '500' );
		$second_inserted_cheapest = $this->make_vehicle( '50' );
		$third_inserted_mid_price = $this->make_vehicle( '150' );

		$this->assertSame(
			array( $second_inserted_cheapest, $third_inserted_mid_price, $first_inserted_priciest ),
			$this->search( 'price_asc' )
		);
	}

	/**
	 * Fractional (cents) prices must keep their decimal precision when
	 * sorted -- guards against a numeric CAST that truncates to an integer
	 * (e.g. CAST(... AS SIGNED) would make 99.50 and 99.99 tie at 99, and
	 * could even invert 99.99 vs 100.00 if truncation reduces one operand
	 * more than the other). The old '+0' coercion this replaces preserves
	 * decimals; the replacement must too.
	 */
	public function test_fractional_prices_sort_correctly(): void {
		$a = $this->make_vehicle( '99.50' );
		$b = $this->make_vehicle( '99.99' );
		$c = $this->make_vehicle( '100.00' );

		$this->assertSame( array( $a, $b, $c ), $this->search( 'price_asc' ) );
	}

	public function test_year_desc_sorts_descending_by_year(): void {
		$old = $this->make_vehicle( null, '2015' );
		$mid = $this->make_vehicle( null, '2020' );
		$new = $this->make_vehicle( null, '2026' );

		$this->assertSame( array( $new, $mid, $old ), $this->search( 'year_desc' ) );
	}

	/**
	 * Pre-existing behavior preserved: a vehicle with NO price meta at all is
	 * excluded from a price-sorted result set (the sort key's implicit
	 * existence requirement), same as before this rewrite.
	 */
	public function test_vehicle_without_price_meta_is_excluded_from_price_sort(): void {
		$priced   = $this->make_vehicle( '100' );
		$no_price = $this->make_vehicle( null );

		$result = $this->search( 'price_asc' );

		$this->assertSame( array( $priced ), $result );
		$this->assertNotContains( $no_price, $result );
	}

	/**
	 * Equal prices must not crash the compound-orderby path and must return
	 * every tied vehicle exactly once.
	 */
	public function test_tied_prices_return_all_vehicles_exactly_once(): void {
		$a = $this->make_vehicle( '200' );
		$b = $this->make_vehicle( '200' );

		$result = $this->search( 'price_asc' );

		$this->assertCount( 2, $result );
		$this->assertContains( $a, $result );
		$this->assertContains( $b, $result );
	}
}
