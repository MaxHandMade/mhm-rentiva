<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use WP_UnitTestCase;

/**
 * Contract guard: the three new dashboard data sources exist and return the
 * exact shape DashboardPage localizes and the React app reads.
 */
final class DashboardLocalizeShapeTest extends WP_UnitTestCase {

	public function test_metric_deltas_shape(): void {
		$d = DashboardService::get_metric_deltas();
		foreach ( array( 'bookings', 'revenue', 'customers' ) as $key ) {
			$this->assertArrayHasKey( $key, $d );
			$this->assertArrayHasKey( 'format', $d[ $key ] );
			$this->assertArrayHasKey( 'value', $d[ $key ] );
			$this->assertArrayHasKey( 'direction', $d[ $key ] );
		}
	}

	public function test_status_breakdown_is_list(): void {
		$this->assertIsArray( DashboardService::get_status_breakdown() );
	}

	public function test_payments_summary_keys(): void {
		$s = DashboardService::get_payments_summary();
		$this->assertArrayHasKey( 'pending_total', $s );
		$this->assertArrayHasKey( 'deposit_blocked', $s );
		$this->assertArrayHasKey( 'this_month_collected', $s );
	}
}
