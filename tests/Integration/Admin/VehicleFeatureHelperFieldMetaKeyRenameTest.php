<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use WP_UnitTestCase;

/**
 * Görev 14 (T8, SlowDBQuery sweep): get_available_fields_map()'s UI-descriptor
 * array key was renamed from 'meta_key' to 'field_meta_key'.
 *
 * Görev -1's warning-inventory.md proved rows 28-31 (VehicleFeatureHelper.php:
 * 274/290/305/334) are false positives -- get_available_fields_map() builds a
 * plain PHP display array from get_option()/array_merge(); there is no $wpdb
 * or WP_Query call anywhere in the function. The WPCS SlowDBQuery sniff
 * (AbstractArrayAssignmentRestrictionsSniff) matches the literal array-key
 * token 'meta_key' wherever it appears, with no awareness of whether a query
 * is nearby, so it flagged this UI array the same way it would flag a real
 * WP_Query arg.
 *
 * Consumer-surface check (done before renaming, see task-14-report.md):
 * exactly one call site in the whole codebase (Lite + Pro) reads
 * $available[$type][$key]['meta_key'] --
 * VehicleFeatureHelper::collect_items()'s TYPE_DETAIL branch. VehicleSettings
 * ::render_display_tab() and VehicleComparison::get_feature_label() also call
 * get_available_fields_map(), but only ever read ['label']. Zero references
 * exist in mhm-rentiva-pro.
 *
 * This test is the positive control for that one live consumer: it proves a
 * DETAIL-type field still resolves its stored post meta value through
 * collect_items() after the rename -- the exact path that would silently
 * break (empty/blank detail text) if the rename had missed a reader.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_available_fields_map
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::collect_items
 */
final class VehicleFeatureHelperFieldMetaKeyRenameTest extends WP_UnitTestCase {

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

	/**
	 * The renamed key exists and the old spelling is gone, for every field
	 * type get_available_fields_map() produces (detail/feature/equipment/
	 * taxonomy) -- not just the one type collect_items() happens to read.
	 */
	public function test_available_fields_map_uses_field_meta_key_not_meta_key(): void {
		update_option( 'mhmrentiva_selected_details', array( 'mileage' ) );
		update_option( 'mhmrentiva_vehicle_features', array( 'bluetooth' => 'Bluetooth' ) );

		$map = VehicleFeatureHelper::get_available_fields_map();

		$this->assertNotEmpty( $map[ VehicleFeatureHelper::TYPE_DETAIL ], 'Fixture must produce at least one detail entry.' );
		foreach ( $map as $type => $fields ) {
			foreach ( $fields as $key => $entry ) {
				$this->assertArrayHasKey( 'field_meta_key', $entry, "Type '$type', key '$key' is missing 'field_meta_key'." );
				$this->assertArrayNotHasKey( 'meta_key', $entry, "Type '$type', key '$key' still carries the old 'meta_key' shape the sniff flagged." );
			}
		}
	}

	/**
	 * End-to-end positive control: a DETAIL field's stored post meta value is
	 * still resolved and rendered by collect_items() after the rename.
	 */
	public function test_detail_field_value_still_resolves_through_collect_items(): void {
		update_option( 'mhmrentiva_vehicle_card_fields', array(
			array( 'type' => VehicleFeatureHelper::TYPE_DETAIL, 'key' => 'mileage' ),
		) );
		update_option( 'mhmrentiva_selected_details', array( 'mileage' ) );

		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		update_post_meta( $vehicle_id, '_mhmrentiva_mileage', '12345' );

		$items = VehicleFeatureHelper::collect_items( $vehicle_id );
		$mileage_items = array_values( array_filter( $items, static fn( $item ) => $item['key'] === 'mileage' ) );

		$this->assertCount( 1, $mileage_items, 'Mileage detail must be collected exactly once when its meta value is present.' );
		$this->assertStringContainsString( '12.345', $mileage_items[0]['text'], 'The stored meta value must reach the rendered text -- proves field_meta_key resolves to the right post meta key.' );
	}

	/**
	 * A detail field with NO stored meta value must not render -- proves the
	 * lookup key genuinely reaches get_post_meta() rather than accidentally
	 * matching everything (e.g. via a coding mistake that reads an empty
	 * string constant for every field).
	 */
	public function test_detail_field_without_meta_value_does_not_render(): void {
		update_option( 'mhmrentiva_vehicle_card_fields', array(
			array( 'type' => VehicleFeatureHelper::TYPE_DETAIL, 'key' => 'mileage' ),
		) );
		update_option( 'mhmrentiva_selected_details', array( 'mileage' ) );

		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		// Intentionally no _mhmrentiva_mileage meta.

		$items = VehicleFeatureHelper::collect_items( $vehicle_id );
		$mileage_items = array_filter( $items, static fn( $item ) => $item['key'] === 'mileage' );

		$this->assertCount( 0, $mileage_items, 'Mileage detail without a stored value must not render (format_detail_value() returns null for <= 0 / empty).' );
	}
}
