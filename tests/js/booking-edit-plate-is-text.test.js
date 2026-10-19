/**
 * @jest-environment jsdom
 */

/**
 * The booking edit screen shows the selected vehicle's plate, taken from the option text.
 * Option text is decoded, so a plate stored as numeric entities (they survive strtoupper and
 * sanitize_text_field; in Pro a vendor edits the plate without re-review) came back as markup
 * when it was written with .html(). The plate is text.
 */
describe( 'booking edit: license plate is shown as text', () => {
	let $;

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmBookingEdit = { text: { currentLicensePlate: 'Current License Plate:' } };
	} );

	test( 'markup in the plate does not become elements', async () => {
		document.body.innerHTML = '<select id="mhmrentiva_booking_edit_vehicle_id"><option value="1">x</option><option value="2">y</option></select>';
		// What the browser holds after decoding esc_html('&#60;IMG SRC=X ONERROR=...&#62;').
		document.querySelector( 'option[value="2"]' ).textContent = 'Van (<IMG SRC=X ONERROR=window.__rvInjected=1>)';

		require( '../../assets/js/admin/booking-edit-meta.js' );
		await new Promise( ( r ) => $( r ) );

		$( '#mhmrentiva_booking_edit_vehicle_id' ).val( '2' ).trigger( 'change' );

		const description = document.querySelector( 'p.description' );
		expect( description ).not.toBeNull();
		expect( description.querySelector( 'img' ) ).toBeNull();
		expect( description.querySelector( 'strong' ).textContent ).toBe( '<IMG SRC=X ONERROR=window.__rvInjected=1>' );
		expect( description.textContent ).toBe( 'Current License Plate: <IMG SRC=X ONERROR=window.__rvInjected=1>' );
	} );
} );
