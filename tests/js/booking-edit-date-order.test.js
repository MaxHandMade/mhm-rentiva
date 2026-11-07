/**
 * @jest-environment jsdom
 */
/**
 * PR 3b-2 (function scan H4): the edit form refuses a return before the
 * pickup -- date and time, the rule BookingEditDates::is_reversed() applies on
 * the server. The handler sits on WordPress's #post form (the edit box itself
 * is a <div>), and a booking in the past is not refused for being in the past.
 */
describe( 'booking edit: return must come after pickup', () => {
	let $;
	beforeEach( async () => {
		jest.resetModules();
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmBookingEdit = { text: {} };
		document.body.innerHTML = `
			<form id="post">
				<div class="mhm-booking-edit-form">
					<input id="mhmrentiva_booking_edit_pickup_date" value="">
					<input id="mhmrentiva_booking_edit_pickup_time" value="">
					<input id="mhmrentiva_booking_edit_dropoff_date" value="">
					<input id="mhmrentiva_booking_edit_dropoff_time" value="">
					<select id="mhmrentiva_booking_edit_status">
						<option value="confirmed" selected>Confirmed</option>
						<option value="cancelled">Cancelled</option>
					</select>
				</div>
			</form>`;
		require( '../../assets/js/admin/booking-edit-meta.js' );
		await new Promise( ( r ) => $( r ) );
	} );

	const submit = ( pickup, pickupTime, dropoff, dropoffTime ) => {
		$( '#mhmrentiva_booking_edit_pickup_date' ).val( pickup );
		$( '#mhmrentiva_booking_edit_pickup_time' ).val( pickupTime );
		$( '#mhmrentiva_booking_edit_dropoff_date' ).val( dropoff );
		$( '#mhmrentiva_booking_edit_dropoff_time' ).val( dropoffTime );
		// triggerHandler runs the bound handlers without the browser's own
		// submit (jsdom would log "form submission not implemented").
		const event = $.Event( 'submit' );
		$( '#post' ).triggerHandler( event );
		return event.isDefaultPrevented();
	};

	test( 'a return before the pickup blocks the Update', () => {
		expect( submit( '2099-05-04', '10:00', '2099-05-01', '10:00' ) ).toBe( true );
		expect( $( '#mhmrentiva_booking_edit_dropoff_date' ).hasClass( 'error' ) ).toBe( true );
	} );

	test( 'same day with a later return time is fine', () => {
		expect( submit( '2099-05-02', '10:00', '2099-05-02', '18:00' ) ).toBe( false );
	} );

	test( 'a booking in the past is not refused for being in the past', () => {
		expect( submit( '2020-05-01', '10:00', '2020-05-04', '10:00' ) ).toBe( false );
	} );

	test( 'a corrected range clears the error', () => {
		submit( '2099-05-04', '10:00', '2099-05-01', '10:00' );
		submit( '2099-05-01', '10:00', '2099-05-04', '10:00' );
		expect( $( '#mhmrentiva_booking_edit_dropoff_date' ).hasClass( 'error' ) ).toBe( false );
		expect( $( '.error-message' ).length ).toBe( 0 );
	} );

	// Codex code audit 3b-2, Important 3: "Cancelled" chosen on a booking that
	// was not cancelled is a cancellation request; the server ignores the
	// dates then, so the browser must not block it on them.
	test( 'a cancellation request is not blocked by reversed dates', () => {
		$( '#mhmrentiva_booking_edit_status' ).val( 'cancelled' );
		expect( submit( '2099-05-04', '10:00', '2099-05-01', '10:00' ) ).toBe( false );
	} );

	test( 'an already cancelled booking is checked as usual', () => {
		document.querySelector( '#mhmrentiva_booking_edit_status option[value="cancelled"]' ).defaultSelected = true;
		document.querySelector( '#mhmrentiva_booking_edit_status option[value="confirmed"]' ).defaultSelected = false;
		$( '#mhmrentiva_booking_edit_status' ).val( 'cancelled' );
		expect( submit( '2099-05-04', '10:00', '2099-05-01', '10:00' ) ).toBe( true );
	} );
} );
