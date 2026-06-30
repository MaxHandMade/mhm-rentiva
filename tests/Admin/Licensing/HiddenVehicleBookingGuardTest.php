<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Licensing;

use MHMRentiva\Admin\Licensing\Restrictions;
use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowRegistry;
use WP_UnitTestCase;

final class HiddenVehicleBookingGuardTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		delete_option( 'mhm_rentiva_lite_overflow_hidden' );
		parent::tearDown();
	}

	public function test_hidden_vehicle_is_reported_not_bookable(): void {
		$vehicle = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		OverflowRegistry::set( 'vehicle', array( $vehicle ) );

		$this->assertTrue( Restrictions::isVehicleOverflowHidden( (int) $vehicle ) );
	}

	public function test_visible_vehicle_is_bookable(): void {
		$vehicle = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		$this->assertFalse( Restrictions::isVehicleOverflowHidden( (int) $vehicle ) );
	}
}
