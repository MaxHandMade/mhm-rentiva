<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

/**
 * The redesigned v2 UI is now the default; the old server-rendered tabs remain reachable
 * as a fallback via ?ui=legacy.
 */
final class VehicleSettingsV2MountTest extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_GET['ui'] );
		parent::tearDown();
	}

	public function test_v2_is_the_default_ui(): void {
		unset( $_GET['ui'] );
		$this->assertTrue( VehicleSettings::is_v2_ui(), 'default (no flag) must be the redesigned v2 UI' );

		$_GET['ui'] = 'v2';
		$this->assertTrue( VehicleSettings::is_v2_ui(), 'the explicit v2 value must still opt in' );

		$_GET['ui'] = 'something-else';
		$this->assertTrue( VehicleSettings::is_v2_ui(), 'any value other than legacy resolves to v2' );
	}

	public function test_legacy_fallback_opts_out(): void {
		$_GET['ui'] = 'legacy';
		$this->assertFalse( VehicleSettings::is_v2_ui(), '?ui=legacy must fall back to the old UI' );
	}
}
