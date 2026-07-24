<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

/**
 * The v2 UI is behind a ?ui=v2 flag so the existing page keeps working during the rebuild.
 */
final class VehicleSettingsV2MountTest extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_GET['ui'] );
		parent::tearDown();
	}

	public function test_is_v2_ui_reflects_query_flag(): void {
		unset( $_GET['ui'] );
		$this->assertFalse( VehicleSettings::is_v2_ui(), 'default (no flag) must stay on the old UI' );

		$_GET['ui'] = 'v2';
		$this->assertTrue( VehicleSettings::is_v2_ui() );

		$_GET['ui'] = 'something-else';
		$this->assertFalse( VehicleSettings::is_v2_ui(), 'only the exact v2 value opts in' );
	}
}
