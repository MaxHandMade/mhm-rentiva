<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use WP_UnitTestCase;

final class CollectItemsActiveGateTest extends WP_UnitTestCase {

	private function reset_map_cache(): void {
		$ref = new \ReflectionProperty( VehicleFeatureHelper::class, 'fields_map_cache' );
		$ref->setAccessible( true );
		$ref->setValue( null, null );
	}

	public function setUp(): void {
		parent::setUp();
		$this->reset_map_cache();
	}

	public function tearDown(): void {
		$this->reset_map_cache();
		parent::tearDown();
	}

	public function test_passive_feature_is_not_collected_but_active_one_is(): void {
		update_option( 'mhmrentiva_settings', array(
			'mhmrentiva_vehicle_card_fields' => array(
				array( 'type' => 'feature', 'key' => 'bluetooth' ),
				array( 'type' => 'feature', 'key' => 'navigation' ),
			),
		) );
		update_option( 'mhmrentiva_selected_features', array( 'bluetooth' ) ); // navigation is Passive

		$vehicle_id = self::factory()->post->create();
		update_post_meta( $vehicle_id, '_mhmrentiva_features', array( 'bluetooth', 'navigation' ) );

		$keys = array_column( VehicleFeatureHelper::collect_items( $vehicle_id ), 'key' );
		$this->assertContains( 'bluetooth', $keys );
		$this->assertNotContains( 'navigation', $keys, 'Passive feature must not render on the card' );

		// Selection preserved: it was never stripped, only skipped at render.
		$stored = get_option( 'mhmrentiva_settings' );
		$stored_keys = array_column( $stored['mhmrentiva_vehicle_card_fields'], 'key' );
		$this->assertContains( 'navigation', $stored_keys, 'stored card selection must be unchanged' );

		// Re-activating navigation restores its render.
		update_option( 'mhmrentiva_selected_features', array( 'bluetooth', 'navigation' ) );
		$this->reset_map_cache();
		$keys2 = array_column( VehicleFeatureHelper::collect_items( $vehicle_id ), 'key' );
		$this->assertContains( 'navigation', $keys2, 're-activating must restore the render' );
	}
}
