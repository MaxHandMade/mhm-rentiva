<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use WP_UnitTestCase;

final class DashboardPaymentsSummaryTest extends WP_UnitTestCase {

	private function booking( string $status, string $deposit, string $remaining, ?string $date = null ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
			'post_date'   => $date ?? gmdate( 'Y-m-15 10:00:00' ),
		) );
		update_post_meta( $id, '_mhm_status', $status );
		update_post_meta( $id, '_mhm_deposit_amount', $deposit );
		update_post_meta( $id, '_mhm_remaining_amount', $remaining );
		update_post_meta( $id, '_mhm_total_price', (string) ( (float) $deposit + (float) $remaining ) );
		return $id;
	}

	public function test_summary_aggregates(): void {
		// confirmed: counts toward deposit_blocked (500) + pending_total (1500)
		$this->booking( 'confirmed', '500', '1500' );
		// cancelled: excluded everywhere
		$this->booking( 'cancelled', '999', '999' );
		// completed: excluded from pending + deposit_blocked
		$this->booking( 'completed', '300', '0' );

		$s = DashboardService::get_payments_summary();

		$this->assertEqualsWithDelta( 1500.0, $s['pending_total'], 0.01 );
		$this->assertEqualsWithDelta( 500.0, $s['deposit_blocked'], 0.01 );
		// this_month_collected = confirmed+completed total price this month = (2000 + 300)
		$this->assertEqualsWithDelta( 2300.0, $s['this_month_collected'], 0.01 );
	}
}
