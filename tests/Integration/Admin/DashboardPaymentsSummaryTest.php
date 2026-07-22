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
		// this_month_collected = total - remaining for confirmed+completed this month = (2000-1500) + (300-0)
		$this->assertEqualsWithDelta( 800.0, $s['this_month_collected'], 0.01 );
	}

	public function test_full_payment_booking_without_remaining_meta_does_not_break_pending_total(): void {
		$this->booking( 'confirmed', '500', '1500' ); // deposit booking: remaining 1500, total 2000
		$id = self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
			'post_date'   => gmdate( 'Y-m-15 10:00:00' ),
		) );
		update_post_meta( $id, '_mhm_status', 'confirmed' );
		update_post_meta( $id, '_mhm_total_price', '3000' ); // full-payment: no deposit/remaining meta

		$s = DashboardService::get_payments_summary();

		$this->assertEqualsWithDelta( 1500.0, $s['pending_total'], 0.01 );        // full-payment contributes 0
		$this->assertEqualsWithDelta( 500.0, $s['deposit_blocked'], 0.01 );        // no deposit meta on full-payment
		$this->assertEqualsWithDelta( 3500.0, $s['this_month_collected'], 0.01 );  // (2000-1500) + (3000-0)
	}

	public function test_deposit_blocked_includes_in_progress(): void {
		$this->booking( 'in_progress', '750', '0' );
		$s = DashboardService::get_payments_summary();
		$this->assertEqualsWithDelta( 750.0, $s['deposit_blocked'], 0.01 );
	}
}
