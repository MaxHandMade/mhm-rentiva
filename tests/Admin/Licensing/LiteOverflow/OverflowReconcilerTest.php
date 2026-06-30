<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Licensing\LiteOverflow;

use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowGate;
use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowReconciler;
use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowRegistry;
use WP_UnitTestCase;

final class OverflowReconcilerTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		delete_option( 'mhm_rentiva_lite_overflow_hidden' );
		parent::tearDown();
	}

	public function test_compute_hidden_returns_newest_excess(): void {
		$this->assertSame(
			array( 6, 7, 8 ),
			OverflowReconciler::computeHidden( array( 1, 2, 3, 4, 5, 6, 7, 8 ), 5 )
		);
	}

	public function test_compute_hidden_nothing_when_at_or_under_limit(): void {
		$this->assertSame( array(), OverflowReconciler::computeHidden( array( 1, 2, 3, 4, 5 ), 5 ) );
		$this->assertSame( array(), OverflowReconciler::computeHidden( array( 1, 2 ), 5 ) );
	}

	public function test_reconcile_pro_clears_registry(): void {
		OverflowRegistry::set( 'vehicle', array( 99 ) );
		$result = OverflowReconciler::reconcile( true );
		$this->assertSame( array(), $result['vehicle'] );
		$this->assertSame( array(), OverflowRegistry::get( 'vehicle' ) );
	}

	public function test_reconcile_lite_hides_newest_vehicles(): void {
		// Raise the insert gate cap so factory can create 8 vehicles freely.
		$allow_many = static fn() => 100;
		add_filter( 'mhm_rentiva_lite_max_vehicles', $allow_many, 5 );

		for ( $i = 0; $i < 8; $i++ ) {
			self::factory()->post->create(
				array(
					'post_type'   => 'vehicle',
					'post_status' => 'publish',
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( "-{$i} days", strtotime( '-1 hour' ) ) ),
				)
			);
		}

		// Remove high-cap filter; add cap=5 for the reconciler check.
		remove_filter( 'mhm_rentiva_lite_max_vehicles', $allow_many, 5 );
		add_filter( 'mhm_rentiva_lite_max_vehicles', static fn() => 5 );
		$oldest_first = get_posts(
			array(
				'post_type'        => 'vehicle',
				'post_status'      => 'publish',
				'orderby'          => array( 'date' => 'ASC', 'ID' => 'ASC' ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		$expected_hidden = array_slice( $oldest_first, 5 );

		OverflowReconciler::reconcile( false );

		sort( $expected_hidden );
		$got = OverflowRegistry::get( 'vehicle' );
		sort( $got );
		$this->assertSame( $expected_hidden, $got );
		$this->assertCount( 3, $got );
	}

	public function test_reconcile_is_idempotent_when_gate_is_registered(): void {
		// The frontend gate is a pre_get_posts action; suppress_filters does NOT
		// stop it, so the reconciler must not read the published set through it.
		OverflowGate::register();

		// Seed 6 published vehicles under a high cap so the insert gate allows them.
		$high = static fn() => 100;
		add_filter( 'mhm_rentiva_lite_max_vehicles', $high, 1 );
		for ( $i = 0; $i < 6; $i++ ) {
			self::factory()->post->create(
				array(
					'post_type'   => 'vehicle',
					'post_status' => 'publish',
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( "-{$i} days", strtotime( '-1 hour' ) ) ),
				)
			);
		}
		remove_filter( 'mhm_rentiva_lite_max_vehicles', $high, 1 );
		add_filter( 'mhm_rentiva_lite_max_vehicles', static fn() => 5 );

		$first  = OverflowReconciler::reconcile( false );
		$second = OverflowReconciler::reconcile( false );

		// Stable across runs (no oscillation) and exactly 1 newest hidden (6 - 5).
		$this->assertSame( $first['vehicle'], $second['vehicle'], 'reconcile must be idempotent' );
		$this->assertCount( 1, $second['vehicle'] );
	}
}
