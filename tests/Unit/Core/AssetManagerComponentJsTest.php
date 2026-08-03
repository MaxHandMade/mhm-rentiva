<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use MHMRentiva\Admin\Core\AssetManager;
use WP_UnitTestCase;

/**
 * T8 F05 (independent audit), SCOPE CAUTION half: AssetManager::enqueue_component_js()
 * is a GENERIC mechanism keyed by component name -- 'vehicle-meta' and
 * 'vehicle-quick-edit' are live consumers (called from
 * enqueue_screen_specific_scripts() at :742 and :747). The 'addon-booking' entry
 * in the same $components array (plus its case in localize_component_script())
 * was dead: nothing anywhere ever called
 * AssetManager::enqueue_component_js('addon-booking'), and it produced a SECOND,
 * never-printed script tag localizing window.mhmAddonBooking -- the var name the
 * frontend reader looked for, while the live frontend path localizes
 * window.mhmRentivaAddons instead (see AddonBookingEnqueueTest). Only that one
 * entry/case is removed here; the generic loader and its other two components
 * must keep working, which is what the two "still enqueues" tests below prove.
 *
 * @covers \MHMRentiva\Admin\Core\AssetManager::enqueue_component_js
 */
final class AssetManagerComponentJsTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->reset_handles();
	}

	protected function tearDown(): void {
		$this->reset_handles();
		parent::tearDown();
	}

	/**
	 * The dead entry: removed from $components, so asking the generic loader
	 * for it must no-op exactly like any other unknown component name, instead
	 * of registering the never-reached 'mhm-rentiva-addon-booking' handle and
	 * localizing the stale window.mhmAddonBooking var.
	 */
	public function test_addon_booking_component_entry_no_longer_exists(): void {
		AssetManager::enqueue_component_js( 'addon-booking' );

		$this->assertFalse(
			wp_script_is( 'mhm-rentiva-addon-booking', 'enqueued' ),
			"The dead 'addon-booking' entry must be gone from the generic component loader."
		);
		$this->assertFalse(
			wp_script_is( 'mhm-rentiva-addon-booking', 'registered' ),
			"The dead 'addon-booking' entry must be gone from the generic component loader."
		);
	}

	/**
	 * Regression guard for the SCOPE CAUTION: deleting the addon-booking entry
	 * must not disturb its sibling 'vehicle-meta' entry in the same array/switch.
	 */
	public function test_vehicle_meta_component_still_enqueues_and_localizes(): void {
		AssetManager::enqueue_component_js( 'vehicle-meta' );

		$this->assertTrue( wp_script_is( 'mhm-rentiva-vehicle-meta', 'enqueued' ) );

		$raw = wp_scripts()->get_data( 'mhm-rentiva-vehicle-meta', 'data' );
		$this->assertIsString( $raw );
		$this->assertStringContainsString( 'var mhmVehicleMeta = ', $raw );
	}

	/**
	 * Regression guard for the SCOPE CAUTION: deleting the addon-booking entry
	 * must not disturb its sibling 'vehicle-quick-edit' entry in the same
	 * array/switch.
	 */
	public function test_vehicle_quick_edit_component_still_enqueues_and_localizes(): void {
		AssetManager::enqueue_component_js( 'vehicle-quick-edit' );

		$this->assertTrue( wp_script_is( 'mhm-rentiva-vehicle-quick-edit', 'enqueued' ) );

		$raw = wp_scripts()->get_data( 'mhm-rentiva-vehicle-quick-edit', 'data' );
		$this->assertIsString( $raw );
		$this->assertStringContainsString( 'var mhmVehicleQuickEdit = ', $raw );
	}

	private function reset_handles(): void {
		foreach ( array( 'mhm-rentiva-addon-booking', 'mhm-rentiva-vehicle-meta', 'mhm-rentiva-vehicle-quick-edit' ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}
}
