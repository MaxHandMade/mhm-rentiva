/**
 * @jest-environment jsdom
 */

/**
 * Manager-lens #15: a booking whose vehicle is in the Trash shows that vehicle,
 * selected, with a line under the selector saying so. The plate updater used to
 * rewrite every `p.description` beside the selector, which would erase that
 * line on page load. The line stays while the booked vehicle is selected and
 * hides when another vehicle is picked.
 */
describe( 'booking edit: vehicle status note', () => {
	let $;

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmBookingEdit = { text: { currentLicensePlate: 'Current License Plate:' } };
		document.body.innerHTML =
			'<div><select id="mhmrentiva_booking_edit_vehicle_id">' +
			'<option value="">Select Vehicle</option>' +
			'<option value="7" selected>Clio — In Trash (34QA015)</option>' +
			'<option value="8">Fiat (34AB123)</option>' +
			'</select>' +
			'<p class="description mhm-vehicle-status-note" data-vehicle-id="7">This vehicle is in the Trash.</p></div>';
	} );

	test( 'the note survives page load and the plate is still read', async () => {
		require( '../../assets/js/admin/booking-edit-meta.js' );
		await new Promise( ( r ) => $( r ) );

		const note = document.querySelector( '.mhm-vehicle-status-note' );
		expect( note.textContent ).toBe( 'This vehicle is in the Trash.' );
		expect( note.hidden ).toBe( false );

		const plate = document.querySelector( 'p.description:not(.mhm-vehicle-status-note) strong' );
		expect( plate ).not.toBeNull();
		expect( plate.textContent ).toBe( '34QA015' );
	} );

	test( 'the note hides for another vehicle and returns for the booked one', () => {
		const $select = $( '#mhmrentiva_booking_edit_vehicle_id' );
		const note    = document.querySelector( '.mhm-vehicle-status-note' );

		$select.val( '8' ).trigger( 'change' );
		expect( note.hidden ).toBe( true );
		expect( note.textContent ).toBe( 'This vehicle is in the Trash.' );

		$select.val( '7' ).trigger( 'change' );
		expect( note.hidden ).toBe( false );
	} );
} );
