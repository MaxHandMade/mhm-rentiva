<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView;
use WP_UnitTestCase;

/**
 * The vehicle page lists the vehicle's features in its highlights, gated by
 * the admin's selection: a Passive feature does not render, a taxonomy-backed
 * key passes through (D5), and the stored meta is never changed.
 *
 * Aşama 2 PR B moved the list from VehicleDetails::get_features() into
 * VehicleDetailsView's highlights; the gate is the same.
 */
final class VehicleDetailsFeatureGateTest extends WP_UnitTestCase {

	/**
	 * @return list<string> Highlight labels.
	 */
	private static function highlight_labels( int $vehicle_id ): array {
		return array_column( VehicleDetailsView::build( $vehicle_id, array(), array() )['highlights'], 'label' );
	}

	private static function label( string $key ): string {
		$map = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_available_fields_map();
		return (string) ( $map['feature'][ $key ]['label'] ?? $map['taxonomy'][ $key ]['label'] ?? $key );
	}

	public function test_passive_feature_excluded_from_detail_feature_list(): void {
		update_option( 'mhmrentiva_selected_features', array( 'bluetooth' ) ); // navigation Passive

		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ) );
		update_post_meta( $vehicle_id, '_mhmrentiva_features', array( 'bluetooth', 'navigation' ) );

		$labels = self::highlight_labels( $vehicle_id );

		$this->assertContains( self::label( 'bluetooth' ), $labels );
		$this->assertNotContains( self::label( 'navigation' ), $labels, 'Passive feature must not appear on the detail page' );

		// Stored vehicle meta is untouched (render-gate, not a data change).
		$this->assertContains( 'navigation', (array) get_post_meta( $vehicle_id, '_mhmrentiva_features', true ) );
	}

	public function test_taxonomy_prefixed_key_passes_through_even_if_not_selected(): void {
		update_option( 'mhmrentiva_selected_features', array( 'bluetooth' ) ); // tax_brand not selected

		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ) );
		update_post_meta( $vehicle_id, '_mhmrentiva_features', array( 'bluetooth', 'navigation', 'tax_brand' ) );

		$keys = array_column( VehicleDetailsView::build( $vehicle_id, array(), array() )['highlights'], 'key' );

		$this->assertContains( 'bluetooth', $keys, 'Selected feature must be present' );
		$this->assertContains( 'tax_brand', $keys, 'Taxonomy-backed key must pass through (D5), even if not in mhmrentiva_selected_features' );
		$this->assertNotContains( 'navigation', $keys, 'Plain Passive feature must still be excluded' );
	}
}
