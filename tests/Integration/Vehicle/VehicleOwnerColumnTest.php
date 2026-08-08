<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * Lite retains the rental list table without the paid vendor-owner surface.
 */
final class VehicleOwnerColumnTest extends WP_UnitTestCase {

	public function test_columns_exclude_paid_owner_column(): void {
		$columns = VehicleColumns::columns( array( 'title' => 'Title', 'date' => 'Date' ) );

		$this->assertArrayNotHasKey( 'mhmrentiva_owner', $columns );
	}

	public function test_query_vars_and_filter_markup_exclude_paid_owner_filter(): void {
		$this->assertNotContains( 'mhmrentiva_owner_filter', VehicleColumns::register_query_vars( array() ) );

		ob_start();
		VehicleColumns::availability_filter( 'mhmrentiva_vehicle' );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'mhmrentiva_owner_filter', $html );
	}
}
