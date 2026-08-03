<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Addons;

use MHMRentiva\Admin\Booking\Addons\AddonBooking;
use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Core\LanguageHelper;
use WP_UnitTestCase;

/**
 * T8 F05 (independent audit): assets/js/components/addon-booking.js (:149-151)
 * reads window.mhmAddonBooking.{currency,locale,strings}, but the LIVE enqueue
 * path -- AddonBooking::enqueue_addon_scripts(), hooked to wp_enqueue_scripts --
 * has only ever localized window.mhmRentivaAddons (:242-257 pre-fix). The object
 * the reader looked for was never produced on the live path, so every read fell
 * back to the script's own hardcoded defaults ('$', 'en-US', {}) and frontend
 * addon totals always rendered '$' plus English strings, regardless of the
 * site's configured currency or language.
 *
 * mhmRentivaAddons and its currencySymbol field are NOT free to rename or drop:
 * the Pro add-on's transfer-addon-modal.js:20 reads
 * window.mhmRentivaAddons.currencySymbol directly. test_pro_contract_* below is
 * that fence -- it is the FIRST test in this file and is already green against
 * the pre-fix payload; it must stay green through every later change this task
 * makes, so a future accidental rename back to mhmAddonBooking (or away from
 * currencySymbol) fails loudly here instead of silently breaking Pro.
 *
 * The other fields the reader needs -- locale and strings.{totalAddons,
 * noAddonsSelected} -- were never part of the live payload at all (only
 * `currency`, the ISO code, already was); that gap is what
 * test_payload_carries_locale_and_strings_the_js_reader_needs() covers, and it
 * is RED until enqueue_addon_scripts() localizes both.
 *
 * @covers \MHMRentiva\Admin\Booking\Addons\AddonBooking::enqueue_addon_scripts
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

	/**
	 * The Pro fence. currencySymbol is the exact field
	 * mhm-rentiva-pro/assets/js/components/transfer-addon-modal.js:20 reads off
	 * window.mhmRentivaAddons -- both the object name and this field must
	 * survive every change this task makes untouched.
	 */
	public function test_pro_contract_payload_carries_currency_symbol_on_the_frozen_object_name(): void {
		$payload = $this->localized_payload();

		$this->assertArrayHasKey(
			'currencySymbol',
			$payload,
			'Pro (transfer-addon-modal.js:20) reads window.mhmRentivaAddons.currencySymbol directly -- this field may not be renamed or dropped.'
		);
		$this->assertSame( CurrencyHelper::get_currency_symbol(), $payload['currencySymbol'] );
	}

	/**
	 * addon-booking.js (:150-151) reads .locale and .strings.{totalAddons,
	 * noAddonsSelected} off the same object. Neither ever shipped on the live
	 * payload, so both fell back to the script's hardcoded English defaults --
	 * this is the audit's "always renders ... English" half of the symptom.
	 */
	public function test_payload_carries_locale_and_strings_the_js_reader_needs(): void {
		$payload = $this->localized_payload();

		$this->assertArrayHasKey( 'locale', $payload, 'addon-booking.js:150 reads mhmRentivaAddons.locale.' );
		$this->assertSame( LanguageHelper::get_current_js_locale(), $payload['locale'] );

		$this->assertArrayHasKey( 'strings', $payload, 'addon-booking.js:151 reads mhmRentivaAddons.strings.' );
		$this->assertIsArray( $payload['strings'] );

		foreach ( array( 'totalAddons', 'noAddonsSelected' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload['strings'], "addon-booking.js reads strings.$key." );
			$this->assertNotSame( '', $payload['strings'][ $key ], "strings.$key must not be empty." );
		}
	}

	public function test_register_wires_enqueue_addon_scripts_to_wp_enqueue_scripts(): void {
		AddonBooking::register();

		$this->assertNotFalse(
			has_action( 'wp_enqueue_scripts', array( AddonBooking::class, 'enqueue_addon_scripts' ) ),
			'register() must hook enqueue_addon_scripts() to wp_enqueue_scripts -- that is the live, frontend-only path this whole file tests.'
		);
	}

	/**
	 * Runs the shipped enqueue path and pulls the JSON payload
	 * wp_localize_script() attached to the live handle back off the FROZEN
	 * object name -- so a rename away from mhmRentivaAddons fails every
	 * assertion in this file instead of silently reading an absent var.
	 *
	 * @return array<string, mixed>
	 */
	private function localized_payload(): array {
		AddonBooking::enqueue_addon_scripts();

		$raw = wp_scripts()->get_data( self::HANDLE, 'data' );
		$this->assertIsString( $raw, 'Premise: enqueue_addon_scripts() must localize data onto the handle.' );
		$this->assertMatchesRegularExpression(
			'/var mhmRentivaAddons = (\{.*\});/',
			$raw,
			'Frozen object name: Pro (transfer-addon-modal.js:20) reads window.mhmRentivaAddons.'
		);

		preg_match( '/var mhmRentivaAddons = (\{.*\});/', $raw, $matches );
		$payload = json_decode( $matches[1], true );
		$this->assertIsArray( $payload );

		return $payload;
	}

	private function reset_handle(): void {
		wp_dequeue_script( self::HANDLE );
		wp_deregister_script( self::HANDLE );
		wp_dequeue_style( self::HANDLE );
		wp_deregister_style( self::HANDLE );
	}
}
