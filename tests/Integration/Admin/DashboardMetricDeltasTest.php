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
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
			'post_date'   => $date,
		) );
		update_post_meta( $id, '_mhm_status', $status );
		update_post_meta( $id, '_mhm_total_price', $price );
		update_post_meta( $id, '_mhm_customer_email', $email );
		return $id;
	}

	public function test_bookings_delta_is_pct_when_previous_month_had_data(): void {
		$this_month = gmdate( 'Y-m-15 10:00:00' );
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
		$this_month = gmdate( 'Y-m-15 10:00:00' );
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
}
