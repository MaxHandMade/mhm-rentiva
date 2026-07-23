<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails;
use WP_UnitTestCase;

final class VehicleDetailsFeatureGateTest extends WP_UnitTestCase {

	private static function call_private( string $method, array $args ) {
		$ref = new \ReflectionMethod( VehicleDetails::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	public function test_passive_feature_excluded_from_detail_feature_list(): void {
		update_option( 'mhm_selected_features', array( 'bluetooth' ) ); // navigation Passive

		$vehicle_id = self::factory()->post->create();
		update_post_meta( $vehicle_id, '_mhm_rentiva_features', array( 'bluetooth', 'navigation' ) );

		$features = self::call_private( 'get_features', array( $vehicle_id ) );

		$this->assertContains( 'bluetooth', $features );
		$this->assertNotContains( 'navigation', $features, 'Passive feature must not appear on the detail page' );

		// Stored vehicle meta is untouched (render-gate, not a data change).
		$this->assertContains( 'navigation', (array) get_post_meta( $vehicle_id, '_mhm_rentiva_features', true ) );
	}
}
