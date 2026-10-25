<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView;
use WP_UnitTestCase;

/**
 * A feature the admin put in the vehicle page's highlights renders only when
 * it is Active: a Passive feature does not render, and the stored meta is
 * never changed.
 *
 * Aşama 2 PR B moved the list from VehicleDetails::get_features() into
 * VehicleDetailsView's highlights, which follow the admin's "Vehicle Detail
 * Highlighted Features" selection only (owner decision); the gate is the
 * same one VehicleFeatureHelper applies to the card.
 */
final class VehicleDetailsFeatureGateTest extends WP_UnitTestCase {

	public function tearDown(): void {
		delete_option( 'mhmrentiva_settings' );
		delete_option( 'mhmrentiva_selected_features' );
		parent::tearDown();
	}

	/**
	 * @return list<string> Highlight keys.
	 */
	private static function highlight_keys( int $vehicle_id ): array {
		return array_column( VehicleDetailsView::build( $vehicle_id, array(), array() )['highlights'], 'key' );
	}

	private static function select_features( array $keys ): void {
		$settings = (array) get_option( 'mhmrentiva_settings', array() );
		$settings['mhmrentiva_vehicle_detail_fields'] = array_map( static fn( string $k ): array => array( 'type' => 'feature', 'key' => $k ), $keys );
		update_option( 'mhmrentiva_settings', $settings );
	}

	public function test_passive_feature_excluded_from_detail_feature_list(): void {
		update_option( 'mhmrentiva_selected_features', array( 'bluetooth' ) ); // navigation Passive
		self::select_features( array( 'bluetooth', 'navigation' ) );

		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ) );
		update_post_meta( $vehicle_id, '_mhmrentiva_features', array( 'bluetooth', 'navigation' ) );

		$keys = self::highlight_keys( $vehicle_id );

		$this->assertContains( 'bluetooth', $keys );
		$this->assertNotContains( 'navigation', $keys, 'Passive feature must not appear on the detail page' );

		// Stored vehicle meta is untouched (render-gate, not a data change).
		$this->assertContains( 'navigation', (array) get_post_meta( $vehicle_id, '_mhmrentiva_features', true ) );
	}

	public function test_selected_feature_the_vehicle_lacks_is_not_drawn(): void {
		update_option( 'mhmrentiva_selected_features', array( 'bluetooth', 'navigation' ) );
		self::select_features( array( 'bluetooth', 'navigation' ) );

		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ) );
		update_post_meta( $vehicle_id, '_mhmrentiva_features', array( 'bluetooth' ) );

		$this->assertSame( array( 'bluetooth' ), self::highlight_keys( $vehicle_id ) );
	}
}
