<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use WP_UnitTestCase;

final class DashboardStatusBreakdownTest extends WP_UnitTestCase {

	private function booking( string $status ): void {
		$id = self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
		) );
		update_post_meta( $id, '_mhm_status', $status );
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

	public function test_excludes_zero_and_trashed(): void {
		$id = self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'trash',
		) );
		update_post_meta( $id, '_mhm_status', 'confirmed' );

		$rows = DashboardService::get_status_breakdown();

		$this->assertSame( array(), $rows );
	}
}
