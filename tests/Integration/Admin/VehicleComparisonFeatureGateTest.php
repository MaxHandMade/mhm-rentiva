<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleComparison;
use WP_UnitTestCase;

final class VehicleComparisonFeatureGateTest extends WP_UnitTestCase {

	private static function call_private( string $method, array $args ) {
		$ref = new \ReflectionMethod( VehicleComparison::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	public function test_passive_feature_is_not_a_comparison_row(): void {
		update_option( 'mhm_selected_features', array( 'bluetooth' ) ); // navigation Passive
		update_option( 'mhm_rentiva_settings', array(
			'comparison_fields' => array( 'features' => array( 'bluetooth', 'navigation' ) ),
		) );

		$rows = self::call_private( 'get_dynamic_features', array( array() ) );

		$this->assertArrayHasKey( 'bluetooth', $rows );
		$this->assertArrayNotHasKey( 'navigation', $rows, 'Passive feature must not be a comparison row' );

		// Stored comparison selection is preserved (render-gate, not strip).
		$stored = get_option( 'mhm_rentiva_settings' );
		$this->assertContains( 'navigation', $stored['comparison_fields']['features'] );
	}
}
