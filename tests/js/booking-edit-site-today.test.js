/**
 * @jest-environment jsdom
 */

/**
 * PR T: the booking edit screen judges "in the past" against the site's today.
 */
describe( 'booking edit: past-date check uses the site today', () => {
	let $;

	beforeEach( async () => {
		jest.resetModules();
		// Only Date is faked; jQuery's ready queue needs the real timers.
		jest.useFakeTimers( { now: new Date( '2026-11-02T03:00:00Z' ), doNotFake: [ 'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'setImmediate', 'clearImmediate', 'nextTick', 'queueMicrotask', 'requestAnimationFrame', 'cancelAnimationFrame', 'performance' ] } ); // The browser is already on 11-02 (UTC); the site is still on 11-01.
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmBookingEdit = { siteToday: '2026-11-01', text: {} };
		document.body.innerHTML = `
			<form class="mhm-booking-edit-form">
				<input id="mhmrentiva_edit_pickup_date" value="">
				<input id="mhmrentiva_edit_dropoff_date" value="">
			</form>`;
		require( '../../assets/js/admin/booking-edit-meta.js' );
		await new Promise( ( r ) => $( r ) );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	const run = ( pickup, dropoff ) => {
		$( '#mhmrentiva_edit_pickup_date' ).val( pickup );
		$( '#mhmrentiva_edit_dropoff_date' ).val( dropoff );
		$( '#mhmrentiva_edit_pickup_date' ).trigger( 'change' );
		return $( '#mhmrentiva_edit_pickup_date' ).hasClass( 'error' );
	};

	test( 'pickup on the site today is not past', () => {
		expect( run( '2026-11-01', '2026-11-02' ) ).toBe( false );
	} );

	test( 'pickup the day before the site today is past', () => {
		expect( run( '2026-10-31', '2026-11-02' ) ).toBe( true );
	} );
} );
