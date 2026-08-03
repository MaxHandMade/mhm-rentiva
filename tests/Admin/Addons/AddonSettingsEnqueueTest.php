<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonSettings;
use WP_UnitTestCase;

/**
 * T8 F01 (independent audit): the "Create Default Additional Services"
 * button (#create-default-addons, AddonSettings::render_page() :199/:202)
 * posts through assets/js/admin/addon-settings.js, but nothing in this
 * plugin ever enqueued that script, and the comment at :211 claiming its
 * data was localized "in enqueue_scripts method" pointed at a method that
 * did not exist -- so the click handler never bound and the button did
 * nothing. The AJAX handler it posts to
 * (wp_ajax_mhmrentiva_create_default_addons, :41) was always reachable;
 * only the click-to-request leg was dead.
 *
 * Every field asserted below is read directly from addon-settings.js
 * (:16-27): mhmAddonSettings.ajax_url, .nonce, .strings.confirm_create,
 * .strings.creating, .strings.error, .strings.create_default.
 *
 * @covers \MHMRentiva\Admin\Addons\AddonSettings::enqueue_scripts
 */
final class AddonSettingsEnqueueTest extends WP_UnitTestCase
{
	private const HOOK   = 'mhm-rentiva_page_mhmrentiva_addon_settings';
	private const HANDLE = 'mhm-rentiva-addon-settings';

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( self::HOOK );
		$this->reset_handle();
	}

	public function tearDown(): void
	{
		$this->reset_handle();
		wp_set_current_user( 0 );
		set_current_screen( 'front' );
		parent::tearDown();
	}

	public function test_enqueue_scripts_registers_the_script_on_its_own_screen(): void
	{
		AddonSettings::enqueue_scripts( self::HOOK );

		$this->assertTrue(
			wp_script_is( self::HANDLE, 'enqueued' ),
			'addon-settings.js must be enqueued when the addon settings screen hook fires.'
		);
	}

	/**
	 * Negative control for "do not enqueue admin-wide": a different admin
	 * screen's hook suffix must not pull this script in.
	 */
	public function test_enqueue_scripts_does_nothing_on_a_different_screen(): void
	{
		AddonSettings::enqueue_scripts( 'toplevel_page_mhm-rentiva' );

		$this->assertFalse(
			wp_script_is( self::HANDLE, 'enqueued' ),
			'addon-settings.js must not load outside its own screen.'
		);
	}

	public function test_localized_payload_carries_every_field_the_script_reads(): void
	{
		AddonSettings::enqueue_scripts( self::HOOK );

		$raw = wp_scripts()->get_data( self::HANDLE, 'data' );
		$this->assertIsString( $raw, 'Premise: enqueue_scripts() must localize data onto the handle.' );

		// wp_localize_script() emits "var mhmAddonSettings = {...};" -- pull
		// the JSON object back out so every field the script reads can be
		// asserted individually instead of string-contains-checked.
		$this->assertMatchesRegularExpression( '/var mhmAddonSettings = (\{.*\});/', $raw );
		preg_match( '/var mhmAddonSettings = (\{.*\});/', $raw, $matches );
		$payload = json_decode( $matches[1], true );
		$this->assertIsArray( $payload );

		$this->assertArrayHasKey( 'ajax_url', $payload );
		$this->assertSame( admin_url( 'admin-ajax.php' ), $payload['ajax_url'] );

		$this->assertArrayHasKey( 'nonce', $payload );
		$this->assertNotEmpty( $payload['nonce'] );
		$this->assertNotFalse(
			wp_verify_nonce( $payload['nonce'], 'mhmrentiva_create_default_addons' ),
			'nonce must verify against the exact action the AJAX handler checks (check_ajax_referer at :218).'
		);

		$this->assertArrayHasKey( 'strings', $payload );
		foreach ( array( 'confirm_create', 'creating', 'error', 'create_default' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload['strings'], "strings.$key must be localized -- addon-settings.js reads it." );
			$this->assertNotSame( '', $payload['strings'][ $key ], "strings.$key must not be empty." );
		}
	}

	public function test_register_wires_enqueue_scripts_to_the_admin_hook(): void
	{
		AddonSettings::register();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( AddonSettings::class, 'enqueue_scripts' ) ),
			'register() must hook enqueue_scripts() to admin_enqueue_scripts.'
		);
	}

	private function reset_handle(): void
	{
		wp_dequeue_script( self::HANDLE );
		wp_deregister_script( self::HANDLE );
	}
}
