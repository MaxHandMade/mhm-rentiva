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
		update_option( 'mhmrentiva_selected_features', array( 'bluetooth' ) ); // navigation Passive
		update_option( 'mhmrentiva_settings', array(
			'comparison_fields' => array( 'features' => array( 'bluetooth', 'navigation' ) ),
		) );

		$rows = self::call_private( 'get_dynamic_features', array( array() ) );

		$this->assertArrayHasKey( 'bluetooth', $rows );
		$this->assertArrayNotHasKey( 'navigation', $rows, 'Passive feature must not be a comparison row' );

		// Stored comparison selection is preserved (render-gate, not strip).
		$stored = get_option( 'mhmrentiva_settings' );
		$this->assertContains( 'navigation', $stored['comparison_fields']['features'] );
	}

	public function test_passive_feature_excluded_from_per_vehicle_value_map(): void {
		update_option( 'mhmrentiva_selected_features', array( 'bluetooth' ) ); // navigation Passive
		update_option( 'mhmrentiva_settings', array(
			'comparison_fields' => array( 'features' => array( 'bluetooth', 'navigation' ) ),
		) );

		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		update_post_meta( $vehicle_id, '_mhmrentiva_features', array( 'bluetooth', 'navigation' ) );

		$data = self::call_private( 'get_vehicle_data', array( $vehicle_id ) );

		$this->assertIsArray( $data, 'get_vehicle_data() must resolve the fixture vehicle' );
		$this->assertArrayHasKey( 'bluetooth', $data['features'], 'Active feature must be present in the per-vehicle value map' );
		$this->assertArrayNotHasKey( 'navigation', $data['features'], 'Passive feature must not appear in the per-vehicle value map (ajax_add_vehicle() latent path)' );
	}
}
