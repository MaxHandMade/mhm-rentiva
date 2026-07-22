<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

final class VehicleSettingsStateBuilderTest extends WP_UnitTestCase {

	private function field( array $state, string $id ): ?array {
		foreach ( $state['fields'] as $f ) {
			if ( $f['id'] === $id ) {
				return $f;
			}
		}
		return null;
	}

	public function test_shape_and_core_intersection(): void {
		$state = VehicleSettings::build_settings_state();

		$this->assertArrayHasKey( 'fields', $state );
		$this->assertArrayHasKey( 'cardOrder', $state );
		$this->assertArrayHasKey( 'detailOrder', $state );
		$this->assertNotEmpty( $state['fields'] );

		foreach ( $state['fields'] as $f ) {
			foreach ( array( 'id','type','key','label','enabled','core','custom','meta','card','detail','compare' ) as $k ) {
				$this->assertArrayHasKey( $k, $f );
			}
			$this->assertContains( $f['type'], array( 'detail', 'feature', 'equipment' ) );
		}

		// Trap 4: core keys that are not detail fields must NOT appear as rows.
		$this->assertNull( $this->field( $state, 'detail:image' ) );
		$this->assertNull( $this->field( $state, 'detail:gallery_images' ) );
	}

	public function test_empty_comparison_means_nothing_compares(): void {
		update_option( 'mhm_rentiva_settings', array() ); // no comparison_fields at all

		$state = VehicleSettings::build_settings_state();

		// The comparison table renders exactly the stored comparison_fields keys; an empty set
		// renders no rows (VehicleComparison::get_dynamic_features()), so compare is OFF for all.
		foreach ( $state['fields'] as $f ) {
			$this->assertFalse( $f['compare'], "compare should be OFF when comparison_fields is empty for {$f['id']}" );
		}
	}

	public function test_explicit_comparison_selection_is_respected(): void {
		update_option( 'mhm_rentiva_settings', array(
			'comparison_fields' => array( 'details' => array( 'fuel_type' ) ),
		) );

		$state = VehicleSettings::build_settings_state();

		$this->assertTrue( $this->field( $state, 'detail:fuel_type' )['compare'] );
		$this->assertFalse( $this->field( $state, 'detail:transmission' )['compare'] );
	}

	public function test_enabled_reflects_selected_set_for_all_types(): void {
		// Details gated by mhm_selected_details; features/equipment now gated the same way.
		update_option( 'mhm_selected_details', array( 'fuel_type' ) );          // transmission NOT selected
		update_option( 'mhm_selected_features', array( 'bluetooth', 'my_custom_feat' ) ); // navigation NOT selected
		update_option( 'mhm_custom_features', array( 'my_custom_feat' => 'My Custom Feat' ) );

		$state = VehicleSettings::build_settings_state();

		$this->assertTrue( $this->field( $state, 'detail:fuel_type' )['enabled'] );
		$this->assertFalse( $this->field( $state, 'detail:transmission' )['enabled'] );

		// Features are enabled ONLY when selected (Passive = disabled), no longer always-on.
		$this->assertTrue( $this->field( $state, 'feature:bluetooth' )['enabled'] );
		$this->assertFalse( $this->field( $state, 'feature:navigation' )['enabled'] );

		$custom = $this->field( $state, 'feature:my_custom_feat' );
		$this->assertNotNull( $custom );
		$this->assertTrue( $custom['custom'] );
		$this->assertTrue( $custom['enabled'] ); // selected above, so Active
	}

	public function test_core_details_are_the_availability_fallback_when_none_selected(): void {
		// Mirrors get_available_fields_map(): when NO details are selected, the core fields are the
		// available (enabled) set. A non-core detail is then disabled; a core detail is enabled.
		update_option( 'mhm_selected_details', array() );

		$state = VehicleSettings::build_settings_state();

		$brand = $this->field( $state, 'detail:brand' );
		$this->assertNotNull( $brand );
		$this->assertTrue( $brand['core'] );
		$this->assertTrue( $brand['enabled'], 'core detail must be enabled via the empty-selection fallback' );

		$this->assertFalse( $this->field( $state, 'detail:transmission' )['enabled'], 'non-core detail is disabled when nothing is selected' );
	}

	public function test_custom_detail_meta_is_exposed(): void {
		update_option( 'mhm_custom_details', array( 'boot_size' => 'Boot Size' ) );
		update_option( 'mhm_custom_field_meta', array(
			'boot_size' => array( 'type' => 'select', 'options' => array( 'S', 'M', 'L' ) ),
		) );

		$state = VehicleSettings::build_settings_state();
		$f     = $this->field( $state, 'detail:boot_size' );

		$this->assertNotNull( $f );
		$this->assertSame( 'select', $f['meta']['type'] );
		$this->assertSame( array( 'S', 'M', 'L' ), $f['meta']['options'] );
	}

	public function test_orders_contain_only_known_field_ids(): void {
		$state = VehicleSettings::build_settings_state();
		$ids   = array_column( $state['fields'], 'id' );

		foreach ( array_merge( $state['cardOrder'], $state['detailOrder'] ) as $id ) {
			$this->assertContains( $id, $ids, "order contains unknown id {$id}" );
		}
	}
}
