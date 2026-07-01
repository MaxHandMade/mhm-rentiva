<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Transfer;

use MHMRentiva\Admin\Transfer\Engine\TransferRouteProvider;
use WP_UnitTestCase;

/**
 * TransferRouteProvider::route_count() must count the ACTUAL routes table
 * (resolved: new `rentiva_transfer_routes` or legacy `mhm_rentiva_transfer_routes`),
 * not a hardcoded legacy name. On new-table installs the legacy table is empty
 * or absent, so a hardcoded count silently returns 0 — which broke the Lite
 * route limit notice ("0 used") and the route-creation gate (limit never
 * enforced).
 *
 * @group transfer
 */
final class TransferRouteCountTest extends WP_UnitTestCase {

	private function routes_table(): string {
		global $wpdb;
		$new = $wpdb->prefix . 'rentiva_transfer_routes';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new ) );
		return ( $exists === $new ) ? $new : $wpdb->prefix . 'mhm_rentiva_transfer_routes';
	}

	protected function tearDown(): void {
		global $wpdb;
		$t = $this->routes_table();
		$wpdb->query( "DELETE FROM {$t} WHERE origin_id >= 89000 OR destination_id >= 89000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		parent::tearDown();
	}

	public function test_route_count_reflects_actual_table_rows(): void {
		$before = TransferRouteProvider::route_count();

		$this->seed_routes( 2 );

		$this->assertSame( $before + 2, TransferRouteProvider::route_count() );
	}

	private function seed_routes( int $n ): void {
		global $wpdb;
		$t = $this->routes_table();
		for ( $i = 0; $i < $n; $i++ ) {
			$wpdb->insert(
				$t,
				array(
					'origin_id'      => 89001 + $i,
					'destination_id' => 89501 + $i,
					'distance_km'    => 10.0,
					'duration_min'   => 20,
					'pricing_method' => 'fixed',
					'base_price'     => 100.00,
					'min_price'      => 100.00,
					'max_price'      => 150.00,
					'is_featured'    => 0,
				)
			);
		}
	}
}
