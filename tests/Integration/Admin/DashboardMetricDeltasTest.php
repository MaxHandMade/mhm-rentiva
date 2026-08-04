<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use WP_UnitTestCase;

/**
 * Covers DashboardService::get_metric_deltas() — period-over-period deltas
 * with mixed pct/abs/neutral format and zero-division guard (spec D5).
 */
final class DashboardMetricDeltasTest extends WP_UnitTestCase {

	private function make_booking( string $date, string $status, string $price, string $email ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'post_date'   => $date,
		) );
		update_post_meta( $id, '_mhmrentiva_status', $status );
		update_post_meta( $id, '_mhmrentiva_total_price', $price );
		update_post_meta( $id, '_mhmrentiva_customer_email', $email );
		return $id;
	}

	public function test_bookings_delta_is_pct_when_previous_month_had_data(): void {
		// Relative to "now", never a fixed day-of-month: day 15 of the CURRENT
		// month is in the future for the first half of every month, and
		// wp_insert_post() silently downgrades an explicit post_status
		// 'publish' to 'future' for any post_date_gmt more than a minute
		// ahead of the real clock -- the row then vanishes from every
		// post_status IN ('publish',...) query DashboardService runs. Day 15
		// of LAST month is always in the past by construction (last month
		// has already fully elapsed), so it is left as-is.
		$this_month = gmdate( 'Y-m-d H:i:s' );
		$last_month = gmdate( 'Y-m-15 10:00:00', strtotime( 'first day of last month' ) );

		// previous month: 2 bookings; current month: 3 bookings → +50% up.
		$this->make_booking( $last_month, 'confirmed', '100', 'a@x.com' );
		$this->make_booking( $last_month, 'confirmed', '100', 'b@x.com' );
		$this->make_booking( $this_month, 'confirmed', '100', 'c@x.com' );
		$this->make_booking( $this_month, 'confirmed', '100', 'd@x.com' );
		$this->make_booking( $this_month, 'confirmed', '100', 'e@x.com' );

		$deltas = DashboardService::get_metric_deltas();

		$this->assertSame( 'pct', $deltas['bookings']['format'] );
		$this->assertSame( 50, $deltas['bookings']['value'] );
		$this->assertSame( 'up', $deltas['bookings']['direction'] );
	}

	public function test_delta_is_abs_when_previous_month_was_zero(): void {
		// Relative to "now" -- see the rationale in the first test above.
		$this_month = gmdate( 'Y-m-d H:i:s' );
		$this->make_booking( $this_month, 'confirmed', '100', 'c@x.com' );
		$this->make_booking( $this_month, 'confirmed', '100', 'd@x.com' );

		$deltas = DashboardService::get_metric_deltas();

		$this->assertSame( 'abs', $deltas['bookings']['format'] );
		$this->assertSame( 2, $deltas['bookings']['value'] );
		$this->assertSame( 'up', $deltas['bookings']['direction'] );
	}

	public function test_delta_is_neutral_when_no_data_either_period(): void {
		$deltas = DashboardService::get_metric_deltas();

		$this->assertSame( 'neutral', $deltas['customers']['format'] );
		$this->assertSame( 0, $deltas['customers']['value'] );
		$this->assertSame( 'none', $deltas['customers']['direction'] );
	}

	public function test_pct_delta_direction_is_none_when_equal_and_nonzero(): void {
		// Relative to "now" -- see the rationale in the first test above.
		$this_month = gmdate( 'Y-m-d H:i:s' );
		$last_month = gmdate( 'Y-m-15 10:00:00', strtotime( 'first day of last month' ) );

		// Equal non-zero counts both months → 0% change, direction must be 'none' (not 'neutral').
		$this->make_booking( $last_month, 'confirmed', '100', 'a@x.com' );
		$this->make_booking( $this_month, 'confirmed', '100', 'b@x.com' );

		$deltas = DashboardService::get_metric_deltas();

		$this->assertSame( 'pct', $deltas['bookings']['format'] );
		$this->assertSame( 0, $deltas['bookings']['value'] );
		$this->assertSame( 'none', $deltas['bookings']['direction'] );
	}
}
