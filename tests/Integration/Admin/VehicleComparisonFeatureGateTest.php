<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Frontend\Shortcodes\ComparisonView;
use WP_UnitTestCase;

final class VehicleComparisonFeatureGateTest extends WP_UnitTestCase {

	public function test_passive_feature_is_not_a_comparison_row(): void {
		update_option( 'mhmrentiva_selected_features', array( 'bluetooth' ) ); // navigation Passive
		update_option( 'mhmrentiva_settings', array(
			'comparison_fields' => array( 'features' => array( 'bluetooth', 'navigation' ) ),
		) );

		$rows = ComparisonView::selected_rows();

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

		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ) );
		update_post_meta( $vehicle_id, '_mhmrentiva_features', array( 'bluetooth', 'navigation' ) );

		$groups = ComparisonView::groups( array( $vehicle_id ) );
		$keys   = array();
		foreach ( $groups as $group ) {
			foreach ( $group['rows'] as $row ) {
				$keys[] = $row['key'];
			}
		}

		$this->assertContains( 'bluetooth', $keys, 'Active feature must be a row of the view' );
		$this->assertNotContains( 'navigation', $keys, 'Passive feature must not reach the view, though the vehicle has it' );
	}
}
