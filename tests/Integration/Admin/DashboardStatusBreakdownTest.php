<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use WP_UnitTestCase;

final class DashboardStatusBreakdownTest extends WP_UnitTestCase {

	private function booking( string $status ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
		) );
		update_post_meta( $id, '_mhmrentiva_status', $status );
		return $id;
	}

	/**
	 * A booking with NO `_mhmrentiva_status` meta at all -- the canonical accessor
	 * \MHMRentiva\Admin\Booking\Core\Status::get() treats missing/invalid
	 * status as 'pending'.
	 */
	private function booking_without_status(): int {
		return (int) self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
		) );
	}

	public function test_counts_group_by_status_ordered_desc(): void {
		$this->booking( 'confirmed' );
		$this->booking( 'confirmed' );
		$this->booking( 'confirmed' );
		$this->booking( 'completed' );
		$this->booking( 'cancelled' );

		$rows = DashboardService::get_status_breakdown();

		$this->assertNotEmpty( $rows );
		$this->assertSame( 'confirmed', $rows[0]['status'] );
		$this->assertSame( 3, $rows[0]['count'] );
		$this->assertSame( 'Confirmed', $rows[0]['label'] );
		$this->assertArrayHasKey( 'dot', $rows[0] );
		$this->assertMatchesRegularExpression( '/^#[0-9a-fA-F]{6}$/', $rows[0]['dot'] );
	}

	public function test_excludes_trashed(): void {
		$id = self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'trash',
		) );
		update_post_meta( $id, '_mhmrentiva_status', 'confirmed' );

		$rows = DashboardService::get_status_breakdown();

		$this->assertSame( array(), $rows );
	}

	/**
	 * Fix D regression guard. A status-less booking must be bucketed as
	 * 'pending' (matching Status::get()'s fallback) rather than silently
	 * disappearing from every bucket -- and the per-status counts must sum
	 * to the total number of non-trashed `vehicle_booking` posts.
	 */
	public function test_missing_status_is_counted_as_pending_and_sums_match_total(): void {
		$this->booking( 'confirmed' );
		$this->booking( 'confirmed' );
		$this->booking( 'completed' );
		$this->booking_without_status();
		$this->booking_without_status();

		// A trashed booking must never be counted, regardless of status.
		$trashed_id = self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'trash',
		) );
		update_post_meta( $trashed_id, '_mhmrentiva_status', 'confirmed' );

		$rows = DashboardService::get_status_breakdown();

		$pending_row = null;
		foreach ( $rows as $row ) {
			if ( 'pending' === $row['status'] ) {
				$pending_row = $row;
			}
		}
		$this->assertNotNull( $pending_row, 'status-less bookings must be bucketed as pending' );
		$this->assertSame( 2, $pending_row['count'] );

		global $wpdb;
		$total_bookings = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = %s AND post_status IN ('publish','private','pending') AND post_status != 'trash'",
				'vehicle_booking'
			)
		);

		$sum = array_sum( array_column( $rows, 'count' ) );
		$this->assertSame( $total_bookings, $sum );
	}
}
