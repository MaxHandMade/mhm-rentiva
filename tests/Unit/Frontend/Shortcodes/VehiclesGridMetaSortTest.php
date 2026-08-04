<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid;
use WP_UnitTestCase;

/**
 * Görev 14 (T8, SlowDBQuery sweep), rows 25-26: VehiclesGrid.php:239,243.
 *
 * get_vehicles()'s 'price'/'featured' orderby branches used a flat top-level
 * 'meta_key' + 'orderby' => 'meta_value_num'|'meta_value'. Rewritten to a
 * named meta_query clause ('compare' => 'EXISTS') + compound 'orderby' =>
 * array($name => $direction), same mechanism and same rationale as
 * SearchResultsMetaSortTest.php (rows 21-23) and
 * BookingQueryHelperDateRangeSortTest.php (row 4) -- see those files for the
 * full WP_Query/WP_Meta_Query trace.
 *
 * The 'featured' clause deliberately has NO 'type' set, matching the ORIGINAL
 * 'orderby' => 'meta_value' (not 'meta_value_num') string-comparison
 * semantics: WP_Meta_Query::get_cast_for_type('') returns 'CHAR', and
 * CAST(meta_value AS CHAR) against an already-text postmeta column sorts
 * identically to the raw column reference the unnamed/auto-synthesized
 * clause used before.
 *
 * get_vehicles() is private; invoked via ReflectionMethod (established
 * pattern in this codebase, see AdminFilterQueryVarsTest.php).
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::get_vehicles
 */
final class VehiclesGridMetaSortTest extends WP_UnitTestCase {

	private function make_vehicle( ?string $price = null, ?string $featured = null ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		) );
		// No status/service-type meta -> passes get_active_vehicle_meta_query()
		// and the (VehiclesGrid has none of its own transfer-exclude clause,
		// only SearchResults does) active-vehicle filter by legacy default.
		if ( null !== $price ) {
			update_post_meta( $id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_PRICE_PER_DAY, $price );
		}
		if ( null !== $featured ) {
			update_post_meta( $id, '_mhmrentiva_featured', $featured );
		}
		return $id;
	}

	/**
	 * @return int[] Vehicle IDs in result order.
	 */
	private function get_vehicles( array $atts ): array {
		$method = new \ReflectionMethod( VehiclesGrid::class, 'get_vehicles' );
		$method->setAccessible( true );
		$result = $method->invoke( null, $atts );

		return array_map( static fn( $v ) => (int) $v['id'], $result );
	}

	public function test_price_orderby_sorts_ascending(): void {
		$cheap = $this->make_vehicle( '50' );
		$mid   = $this->make_vehicle( '150' );
		$exp   = $this->make_vehicle( '500' );

		$result = $this->get_vehicles( array( 'orderby' => 'price', 'order' => 'ASC', 'limit' => '12' ) );

		$this->assertSame( array( $cheap, $mid, $exp ), $result );
	}

	public function test_price_orderby_sorts_descending(): void {
		$cheap = $this->make_vehicle( '50' );
		$mid   = $this->make_vehicle( '150' );
		$exp   = $this->make_vehicle( '500' );

		$result = $this->get_vehicles( array( 'orderby' => 'price', 'order' => 'DESC', 'limit' => '12' ) );

		$this->assertSame( array( $exp, $mid, $cheap ), $result );
	}

	/**
	 * Fractional prices keep decimal precision (DECIMAL(10,2) cast, not a
	 * truncating SIGNED/NUMERIC cast).
	 */
	public function test_fractional_prices_sort_correctly(): void {
		$a = $this->make_vehicle( '99.50' );
		$b = $this->make_vehicle( '99.99' );
		$c = $this->make_vehicle( '100.00' );

		$result = $this->get_vehicles( array( 'orderby' => 'price', 'order' => 'ASC', 'limit' => '12' ) );

		$this->assertSame( array( $a, $b, $c ), $result );
	}

	public function test_vehicle_without_price_meta_is_excluded_from_price_sort(): void {
		$priced   = $this->make_vehicle( '100' );
		$no_price = $this->make_vehicle( null );

		$result = $this->get_vehicles( array( 'orderby' => 'price', 'order' => 'ASC', 'limit' => '12' ) );

		$this->assertSame( array( $priced ), $result );
		$this->assertNotContains( $no_price, $result );
	}

	public function test_featured_orderby_sorts_by_string_value(): void {
		$not_featured = $this->make_vehicle( null, '0' );
		$featured     = $this->make_vehicle( null, '1' );

		$asc = $this->get_vehicles( array( 'orderby' => 'featured', 'order' => 'ASC', 'limit' => '12' ) );
		$this->assertSame( array( $not_featured, $featured ), $asc );

		$desc = $this->get_vehicles( array( 'orderby' => 'featured', 'order' => 'DESC', 'limit' => '12' ) );
		$this->assertSame( array( $featured, $not_featured ), $desc );
	}

	/**
	 * A non-meta orderby ('title') must be completely unaffected -- proves
	 * the rewrite is scoped to the 'price'/'featured' branches only.
	 */
	public function test_title_orderby_is_unaffected(): void {
		$b = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'B Vehicle' ) );
		$a = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'A Vehicle' ) );

		$result = $this->get_vehicles( array( 'orderby' => 'title', 'order' => 'ASC', 'limit' => '12' ) );

		$this->assertSame( array( $a, $b ), $result );
	}
}
