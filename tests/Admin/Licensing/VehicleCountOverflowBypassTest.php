<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Licensing;

use MHMRentiva\Admin\Licensing\Restrictions;
use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowGate;
use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowRegistry;
use WP_UnitTestCase;

/**
 * Restrictions::vehicleCount() is an INTERNAL count used for the Lite limit
 * gate. It must return the TRUE number of vehicles regardless of the public
 * overflow-hiding gate — otherwise, in a non-admin context (front-end / AJAX /
 * cron / CLI) the OverflowGate pre_get_posts filter would exclude the hidden
 * vehicles from the count, making an internal limit counter depend on display
 * state. (Same class as the reconciler-oscillation trap: a display gate must
 * not filter an internal count.)
 *
 * @group lite-overflow
 */
final class VehicleCountOverflowBypassTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		OverflowGate::register(); // activate the pre_get_posts display gate
	}

	protected function tearDown(): void {
		delete_option( 'mhm_rentiva_lite_overflow_hidden' );
		parent::tearDown();
	}

	public function test_vehicle_count_ignores_overflow_hidden(): void {
		$id = self::factory()->post->create(
			array( 'post_type' => 'vehicle', 'post_status' => 'publish' )
		);

		$before = Restrictions::vehicleCount();

		// Marking the vehicle as overflow-hidden must NOT change the internal count.
		OverflowRegistry::set( 'vehicle', array( $id ) );

		$this->assertSame(
			$before,
			Restrictions::vehicleCount(),
			'vehicleCount() must count the true set, not the overflow-filtered set'
		);
	}
}
