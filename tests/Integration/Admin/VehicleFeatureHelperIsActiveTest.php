<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use WP_UnitTestCase;

final class VehicleFeatureHelperIsActiveTest extends WP_UnitTestCase {

	public function test_core_detail_is_active_even_when_none_selected(): void {
		update_option( 'mhm_selected_details', array() );
		$this->assertTrue( VehicleFeatureHelper::is_field_active( 'detail', 'brand' ) );
	}

	public function test_detail_active_only_when_selected_or_core(): void {
		update_option( 'mhm_selected_details', array( 'fuel_type' ) );
		$this->assertTrue( VehicleFeatureHelper::is_field_active( 'detail', 'fuel_type' ) );
		$this->assertFalse( VehicleFeatureHelper::is_field_active( 'detail', 'transmission' ) );
	}

	public function test_feature_active_only_when_selected(): void {
		update_option( 'mhm_selected_features', array( 'bluetooth' ) );
		$this->assertTrue( VehicleFeatureHelper::is_field_active( 'feature', 'bluetooth' ) );
		$this->assertFalse( VehicleFeatureHelper::is_field_active( 'feature', 'navigation' ) );
	}

	public function test_equipment_active_only_when_selected(): void {
		update_option( 'mhm_selected_equipment', array( 'spare_tire' ) );
		$this->assertTrue( VehicleFeatureHelper::is_field_active( 'equipment', 'spare_tire' ) );
		$this->assertFalse( VehicleFeatureHelper::is_field_active( 'equipment', 'jack' ) );
	}

	public function test_taxonomy_and_unknown_pass_through(): void {
		$this->assertTrue( VehicleFeatureHelper::is_field_active( 'taxonomy', 'tax_body_sedan' ) );
		$this->assertTrue( VehicleFeatureHelper::is_field_active( 'nonsense', 'whatever' ) );
	}
}
