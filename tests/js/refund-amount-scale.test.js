/**
 * @jest-environment jsdom
 *
 * The browser half of M-02.
 *
 * BookingRefundMetaBox renders a visible lira field and a hidden minor-unit
 * field; this handler is what fills the hidden one, and the hidden one is
 * what the form submits. A fixed *100 submits a tenth of the admin's number
 * in a 3-decimal store.
 */

describe( 'refund amount scale', () => {
	beforeEach( () => {
		jest.resetModules();

		document.body.innerHTML =
			'<input type="number" name="amount_visible" value="" />' +
			'<input type="hidden" id="mhmrentiva_amount_kurus" value="0" />';

		const jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;
	} );

	afterEach( () => {
		delete global.jQuery;
		delete global.$;
		delete window.jQuery;
		delete window.mhmBookingEmail;
		document.body.innerHTML = '';
	} );

	it( 'encodes at the store precision, not a fixed 100', () => {
		// WP_Scripts::localize() casts every scalar with (string) before it
		// reaches the browser (wp-includes/class-wp-scripts.php:656-661), so
		// window.mhmBookingEmail.priceDecimals is always a string here, never
		// a number. Seeding a number would let this test pass without
		// reproducing the shape the page actually ships.
		window.mhmBookingEmail = { priceDecimals: '3' };
		require( '../../assets/js/admin/booking-email-send.js' );

		const field = document.querySelector( 'input[name="amount_visible"]' );
		field.value = '19.99';
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );

		expect( document.getElementById( 'mhmrentiva_amount_kurus' ).value ).toBe( '19990' );
	} );

	it( 'falls back to two decimals when the screen did not localize a precision', () => {
		delete window.mhmBookingEmail;
		require( '../../assets/js/admin/booking-email-send.js' );

		const field = document.querySelector( 'input[name="amount_visible"]' );
		field.value = '19.99';
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );

		expect( document.getElementById( 'mhmrentiva_amount_kurus' ).value ).toBe( '1999' );
	} );
} );
