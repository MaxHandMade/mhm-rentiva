<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

/**
 * Contract guard: the payload the UI will read must exist with the agreed shape.
 * (The enqueue itself is screen-dependent; this pins the data contract.)
 */
final class VehicleSettingsStateLocalizeTest extends WP_UnitTestCase {

	public function test_state_payload_is_json_serialisable_with_expected_top_level_keys(): void {
		$state = VehicleSettings::build_settings_state();

		$json = wp_json_encode( $state );
		$this->assertIsString( $json );

		$decoded = json_decode( $json, true );
		$this->assertArrayHasKey( 'fields', $decoded );
		$this->assertArrayHasKey( 'cardOrder', $decoded );
		$this->assertArrayHasKey( 'detailOrder', $decoded );
		$this->assertIsArray( $decoded['fields'] );
	}
}
