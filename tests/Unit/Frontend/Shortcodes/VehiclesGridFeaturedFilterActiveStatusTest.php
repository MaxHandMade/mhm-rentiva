<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use WP_UnitTestCase;

/**
 * T8 Görev 10c-B (K5-BUG) -- VehiclesGrid.php's featured filter used to
 * assign `$args['meta_query'] = array(...)` WHOLESALE when `featured="1"`,
 * clobbering the active-vehicle-status meta_query already built at the top
 * of get_vehicles() (MetaQueryHelper::get_active_vehicle_meta_query()).
 * Reviewer-verified real and pre-existing at dc36f0cd, untouched by Görev 14
 * (T8's SlowDBQuery sweep, which only renamed this SAME array's OTHER
 * clauses into named meta_query entries -- see VehiclesGridMetaSortTest.php).
 * Net effect: an inactive/suspended vehicle whose own featured flag is set
 * leaked into every `featured="1"` grid, right alongside genuinely active
 * featured vehicles.
 *
 * get_vehicles() is private; invoked via ReflectionMethod, same pattern as
 * VehiclesGridMetaSortTest.php.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::get_vehicles
 */
final class VehiclesGridFeaturedFilterActiveStatusTest extends WP_UnitTestCase {

	private function make_vehicle( ?string $lifecycle_status, ?string $featured ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		) );
		if ( null !== $lifecycle_status ) {
			update_post_meta( $id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, $lifecycle_status );
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

	/**
	 * The RED case: (ii)'s paused/suspended-but-featured vehicle must NOT
	 * leak into a featured="1" grid alongside (i)'s active/featured vehicle.
	 * (iii) (active, non-featured) is a control excluded for a different,
	 * already-correct reason. `orderby=featured` exercises the SAME
	 * meta_query array the Görev-14 named-clause rewrite appends
	 * 'featured_sort' onto (VehiclesGrid.php's orderby branch runs AFTER
	 * this filter), so the fix must survive that interaction too.
	 */
	public function test_only_active_vehicles_appear_in_a_featured_grid_with_orderby(): void {
		$active_featured     = $this->make_vehicle( VehicleLifecycleStatus::ACTIVE, '1' );
		$suspended_featured  = $this->make_vehicle( VehicleLifecycleStatus::PAUSED, '1' );
		$active_not_featured = $this->make_vehicle( VehicleLifecycleStatus::ACTIVE, '0' );

		$result = $this->get_vehicles( array(
			'featured' => '1',
			'orderby'  => 'featured',
			'order'    => 'ASC',
			'limit'    => '12',
		) );

		$this->assertSame( array( $active_featured ), $result );
		$this->assertNotContains(
			$suspended_featured,
			$result,
			'A paused/suspended vehicle must not leak into a featured grid even though its own featured flag is set.'
		);
		$this->assertNotContains( $active_not_featured, $result );
	}

	/**
	 * Same assertion without an orderby, isolating the plain featured="1"
	 * path (no Görev-14 named 'featured_sort' clause involved) from the
	 * orderby-interaction case above.
	 */
	public function test_only_active_vehicles_appear_in_a_featured_grid_without_orderby(): void {
		$active_featured    = $this->make_vehicle( VehicleLifecycleStatus::ACTIVE, '1' );
		$suspended_featured = $this->make_vehicle( VehicleLifecycleStatus::PAUSED, '1' );

		$result = $this->get_vehicles( array( 'featured' => '1', 'limit' => '12' ) );

		$this->assertSame( array( $active_featured ), $result );
		$this->assertNotContains( $suspended_featured, $result );
	}
}
