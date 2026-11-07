/**
 * @jest-environment jsdom
 */
/**
 * PR 3b-2 (function scan H4): the edit form refuses a return before the
 * pickup -- date and time, the rule BookingEditDates::is_refused() applies on
 * the server. The handler sits on WordPress's #post form (the edit box itself
 * is a <div>), and a booking in the past is not refused for being in the past.
 */
describe( 'booking edit: return must come after pickup', () => {
	let $;
	// Mounts the edit box with the values the server drew, then loads the
	// script (it records those values on ready, as on the real screen).
	const mount = async ( values = {} ) => {
		jest.resetModules();
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmBookingEdit = { text: {}, defaultTimes: values.defaultTimes || { pickup: '10:00', dropoff: '10:00' } };
		const v = ( k ) => ( values[ k ] !== undefined ? values[ k ] : '' );
		document.body.innerHTML = `
			<form id="post">
				<div class="mhm-booking-edit-form">
					<input type="date" id="mhmrentiva_booking_edit_pickup_date" value="${ v( 'pickup_date' ) }">
					<input type="time" id="mhmrentiva_booking_edit_pickup_time" value="${ v( 'pickup_time' ) }">
					<input type="date" id="mhmrentiva_booking_edit_dropoff_date" value="${ v( 'dropoff_date' ) }">
					<input type="time" id="mhmrentiva_booking_edit_dropoff_time" value="${ v( 'dropoff_time' ) }">
					<select id="mhmrentiva_booking_edit_status">
						<option value="confirmed" selected>Confirmed</option>
						<option value="cancelled">Cancelled</option>
					</select>
				</div>
			</form>`;
		require( '../../assets/js/admin/booking-edit-meta.js' );
		await new Promise( ( r ) => $( r ) );
	};

	beforeEach( async () => {
		await mount();
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

	// Codex code audit 3b-2 r2: an equal moment is no rental for pricing and
	// availability, so a CHANGED range may not end where it starts.
	test( 'a changed range that ends where it starts blocks the Update', () => {
		expect( submit( '2099-06-01', '10:00', '2099-06-01', '10:00' ) ).toBe( true );
	} );

	// Fable code audit M-2: an equal range the booking already carries (a
	// transfer) is not touched, so it does not block saving other fields.
	test( 'an unchanged equal range does not block the Update', async () => {
		await mount( { pickup_date: '2099-06-01', pickup_time: '10:00', dropoff_date: '2099-06-01', dropoff_time: '10:00' } );
		const event = $.Event( 'submit' );
		$( '#post' ).triggerHandler( event );
		expect( event.isDefaultPrevented() ).toBe( false );
	} );

	// Codex r3 Minor 1: a stored '0' time is drawn into the attribute, the time
	// input holds '' -- untouched, that is not a change.
	test( "an attribute the time input cannot hold is not a change", async () => {
		await mount( { pickup_date: '2099-06-01', pickup_time: '0', dropoff_date: '2099-06-01', dropoff_time: '0' } );
		const event = $.Event( 'submit' );
		$( '#post' ).triggerHandler( event );
		expect( event.isDefaultPrevented() ).toBe( false );
	} );

	// Codex r3 Minor 2: the empty-time defaults are the server's filters.
	test( 'empty times use the server defaults', async () => {
		await mount( { defaultTimes: { pickup: '08:00', dropoff: '18:00' } } );
		expect( submit( '2099-06-01', '', '2099-06-01', '' ) ).toBe( false );
	} );
} );
