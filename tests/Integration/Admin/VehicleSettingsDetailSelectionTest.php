<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use WP_UnitTestCase;

/**
 * get_selected_detail_fields(): "never configured" still mirrors the card selection,
 * but an explicitly stored empty selection must stay empty.
 */
final class VehicleSettingsDetailSelectionTest extends WP_UnitTestCase {

	private function set_settings( array $settings ): void {
		update_option( 'mhmrentiva_settings', $settings );
	}

	public function test_unset_detail_selection_mirrors_card_selection(): void {
		$this->set_settings( array(
			'mhmrentiva_vehicle_card_fields' => array(
				array( 'type' => 'detail', 'key' => 'fuel_type' ),
			),
		) );

		$this->assertSame(
			VehicleFeatureHelper::get_selected_card_fields(),
			VehicleFeatureHelper::get_selected_detail_fields()
		);
	}

	public function test_explicitly_empty_detail_selection_stays_empty(): void {
		$this->set_settings( array(
			'mhmrentiva_vehicle_card_fields'   => array(
				array( 'type' => 'detail', 'key' => 'fuel_type' ),
			),
			'mhmrentiva_vehicle_detail_fields' => array(),
		) );

		$this->assertSame( array(), VehicleFeatureHelper::get_selected_detail_fields() );
	}

	public function test_explicit_detail_selection_is_honoured(): void {
		$this->set_settings( array(
			'mhmrentiva_vehicle_card_fields'   => array(
				array( 'type' => 'detail', 'key' => 'fuel_type' ),
			),
			'mhmrentiva_vehicle_detail_fields' => array(
				array( 'type' => 'detail', 'key' => 'transmission' ),
			),
		) );

		$selected = VehicleFeatureHelper::get_selected_detail_fields();
		$this->assertCount( 1, $selected );
		$this->assertSame( 'transmission', $selected[0]['key'] );
	}
}
