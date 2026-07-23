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

	public function test_taxonomy_prefixed_key_passes_through_even_if_not_selected(): void {
		update_option( 'mhm_selected_features', array( 'bluetooth' ) ); // tax_brand not selected

		$vehicle_id = self::factory()->post->create();
		update_post_meta( $vehicle_id, '_mhm_rentiva_features', array( 'bluetooth', 'navigation', 'tax_brand' ) );

		$features = self::call_private( 'get_features', array( $vehicle_id ) );

		$this->assertContains( 'bluetooth', $features, 'Selected feature must be present' );
		$this->assertContains( 'tax_brand', $features, 'Taxonomy-backed key must pass through (D5), even if not in mhm_selected_features' );
		$this->assertNotContains( 'navigation', $features, 'Plain Passive feature must still be excluded' );
	}
}
