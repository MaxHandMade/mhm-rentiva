<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Licensing\LiteOverflow;

use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowAdminBadge;
use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowRegistry;
use WP_UnitTestCase;

final class OverflowAdminBadgeTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		delete_option( 'mhm_rentiva_lite_overflow_hidden' );
		parent::tearDown();
	}

	public function test_hidden_vehicle_gets_badge_state(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		OverflowRegistry::set( 'vehicle', array( $id ) );

		$states = OverflowAdminBadge::post_states( array(), get_post( $id ) );

		$this->assertArrayHasKey( 'mhm_lite_overflow', $states );
	}

	public function test_visible_vehicle_gets_no_badge(): void {
		$id     = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		$states = OverflowAdminBadge::post_states( array(), get_post( $id ) );
		$this->assertArrayNotHasKey( 'mhm_lite_overflow', $states );
	}
}
