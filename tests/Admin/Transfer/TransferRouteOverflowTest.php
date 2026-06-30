<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Transfer;

use MHMRentiva\Admin\Transfer\Engine\TransferRouteProvider;
use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowRegistry;
use WP_UnitTestCase;

/**
 * @group transfer
 * @group lite-overflow
 */
final class TransferRouteOverflowTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		global $wpdb;
		$loc_table    = $wpdb->prefix . 'rentiva_transfer_locations';
		$routes_table = $wpdb->prefix . 'rentiva_transfer_routes';
		$wpdb->query( "DELETE FROM {$routes_table} WHERE origin_id >= 89000 OR destination_id >= 89000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$loc_table} WHERE id >= 89000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( 'mhm_rentiva_lite_overflow_hidden' );
		TransferRouteProvider::clear_cache();
		parent::tearDown();
	}

	public function test_hidden_routes_excluded_from_popular_routes(): void {
		[ $route_keep, $route_hide ] = $this->seed_two_routes();

		OverflowRegistry::set( 'route', array( $route_hide ) );

		$rows = TransferRouteProvider::get_popular_routes( array( 'force_refresh' => true, 'limit' => 50 ) );
		$ids  = array_map( static fn( $r ) => (int) $r->id, $rows );

		$this->assertContains( $route_keep, $ids );
		$this->assertNotContains( $route_hide, $ids );
	}

	/** @return int[] [keepId, hideId] */
	private function seed_two_routes(): array {
		global $wpdb;

		$loc_table    = $wpdb->prefix . 'rentiva_transfer_locations';
		$routes_table = $wpdb->prefix . 'rentiva_transfer_routes';

		// Clear any leftovers in our test ID range.
		$wpdb->query( "DELETE FROM {$routes_table} WHERE origin_id >= 89000 OR destination_id >= 89000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$loc_table} WHERE id >= 89000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Two active, transfer-allowed locations.
		$wpdb->insert(
			$loc_table,
			array(
				'id'             => 89001,
				'name'           => 'Overflow Test Airport',
				'city'           => 'TestCity',
				'type'           => 'airport',
				'is_active'      => 1,
				'allow_rental'   => 0,
				'allow_transfer' => 1,
				'priority'       => 1,
			)
		);
		$wpdb->insert(
			$loc_table,
			array(
				'id'             => 89002,
				'name'           => 'Overflow Test Hotel',
				'city'           => 'TestCity',
				'type'           => 'hotel',
				'is_active'      => 1,
				'allow_rental'   => 0,
				'allow_transfer' => 1,
				'priority'       => 2,
			)
		);

		// Route to keep: 89001 → 89002.
		$wpdb->insert(
			$routes_table,
			array(
				'origin_id'      => 89001,
				'destination_id' => 89002,
				'distance_km'    => 40.0,
				'duration_min'   => 50,
				'pricing_method' => 'fixed',
				'base_price'     => 600.00,
				'min_price'      => 600.00,
				'max_price'      => 700.00,
				'is_featured'    => 0,
			)
		);
		$keep_id = (int) $wpdb->insert_id;

		// Route to hide: 89002 → 89001.
		$wpdb->insert(
			$routes_table,
			array(
				'origin_id'      => 89002,
				'destination_id' => 89001,
				'distance_km'    => 40.0,
				'duration_min'   => 50,
				'pricing_method' => 'fixed',
				'base_price'     => 600.00,
				'min_price'      => 600.00,
				'max_price'      => 700.00,
				'is_featured'    => 0,
			)
		);
		$hide_id = (int) $wpdb->insert_id;

		return array( $keep_id, $hide_id );
	}
}
