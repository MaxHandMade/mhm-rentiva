<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Addons;

use MHMRentiva\Admin\Booking\Addons\AddonBooking;
use WP_UnitTestCase;

/**
 * AddonBooking used to hook enqueue_addon_scripts() to wp_enqueue_scripts, so
 * assets/js/components/addon-booking.js and its stylesheet loaded on every
 * front page of every site, although the only markup they served
 * (render_addon_checkboxes() on the dead `mhmrentiva_booking_form_after_vehicle`
 * action) was never printed: no do_action() for it exists in either tree. The
 * booking form now owns its add-on UI, and the admin add-ons screen reads its
 * own `mhmRentivaAddonsScreen` object.
 *
 * This file used to fence the opposite behaviour (the payload, and Pro's read
 * of `mhmRentivaAddons.currencySymbol`). Pro now localizes its own symbol, so
 * the fence is gone and the enqueue with it.
 */
final class AddonBookingEnqueueTest extends WP_UnitTestCase {

	private const HANDLE = 'mhm-rentiva-addons';

	protected function setUp(): void {
		parent::setUp();
		$this->reset_handle();
	}

	protected function tearDown(): void {
		$this->reset_handle();
		parent::tearDown();
	}

	public function test_addon_booking_does_not_hook_the_front_enqueue(): void {
		if ( method_exists( AddonBooking::class, 'register' ) ) {
			AddonBooking::register();
		}

		$this->assertFalse(
			has_action( 'wp_enqueue_scripts', array( AddonBooking::class, 'enqueue_addon_scripts' ) ),
			'AddonBooking must not load its script on front pages.'
		);
	}

	public function test_addon_booking_is_not_enqueued_on_front_pages(): void {
		if ( method_exists( AddonBooking::class, 'register' ) ) {
			AddonBooking::register();
		}

		do_action( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_script_is( self::HANDLE, 'enqueued' ), 'The booking add-on script must not be enqueued on a front request.' );
		$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ), 'The booking add-on stylesheet must not be enqueued on a front request.' );
	}

	public function test_dead_render_hooks_are_not_registered(): void {
		if ( method_exists( AddonBooking::class, 'register' ) ) {
			AddonBooking::register();
		}

		foreach ( array( 'mhmrentiva_booking_form_after_vehicle', 'mhmrentiva_booking_summary_addons', 'mhmrentiva_admin_booking_meta_addons' ) as $hook ) {
			$this->assertFalse( has_action( $hook ), "Nothing fires {$hook}; no callback may sit on it." );
		}
	}

	public function test_the_orphaned_assets_are_gone(): void {
		$this->assertFileDoesNotExist( MHMRENTIVA_PLUGIN_DIR . 'assets/js/components/addon-booking.js' );
		$this->assertFileDoesNotExist( MHMRENTIVA_PLUGIN_DIR . 'assets/css/components/addon-booking.css' );
	}

	private function reset_handle(): void {
		wp_dequeue_script( self::HANDLE );
		wp_deregister_script( self::HANDLE );
		wp_dequeue_style( self::HANDLE );
		wp_deregister_style( self::HANDLE );
	}
}
