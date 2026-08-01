<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * Admin vehicle-list "Added by" (vendor vs operator) column + owner filter.
 *
 * A vehicle is a vendor listing when its post_author has the rentiva_vendor role.
 * The owner filter translates that into author__in / author__not_in query vars.
 *
 * @group vehicle-owner
 */
final class VehicleOwnerColumnTest extends WP_UnitTestCase {

	public function test_columns_include_owner_column(): void {
		$cols = VehicleColumns::columns( array( 'title' => 'Title', 'date' => 'Date' ) );
		$this->assertArrayHasKey( 'mhmrentiva_owner', $cols, 'Vehicle list must expose an owner column.' );
		$this->assertNotEmpty( $cols['mhmrentiva_owner'] );
	}

	public function test_owner_filter_args_vendor_targets_vendor_authors(): void {
		$vendor_id = (int) $this->factory->user->create( array( 'role' => 'rentiva_vendor' ) );

		$args = VehicleColumns::owner_filter_args( 'vendor' );

		$this->assertArrayHasKey( 'author__in', $args );
		$this->assertContains( $vendor_id, $args['author__in'], 'Vendor filter must include vendor author IDs.' );
		$this->assertArrayNotHasKey( 'author__not_in', $args );
	}

	public function test_owner_filter_args_operator_excludes_vendor_authors(): void {
		$vendor_id = (int) $this->factory->user->create( array( 'role' => 'rentiva_vendor' ) );

		$args = VehicleColumns::owner_filter_args( 'operator' );

		$this->assertArrayHasKey( 'author__not_in', $args );
		$this->assertContains( $vendor_id, $args['author__not_in'], 'Operator filter must exclude vendor author IDs.' );
	}

	public function test_owner_filter_args_vendor_with_no_vendors_matches_nothing(): void {
		// Clean per-test DB: no vendor users → vendor filter must match nothing (author__in = [0]).
		$args = VehicleColumns::owner_filter_args( 'vendor' );
		$this->assertSame( array( 'author__in' => array( 0 ) ), $args );
	}

	public function test_owner_filter_args_empty_or_invalid_returns_no_filter(): void {
		$this->assertSame( array(), VehicleColumns::owner_filter_args( '' ) );
		$this->assertSame( array(), VehicleColumns::owner_filter_args( 'bogus' ) );
	}
}
