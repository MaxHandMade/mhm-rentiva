/**
 * @jest-environment jsdom
 */

/**
 * PR T: the manual booking form defaults pickup to the site's today and the
 * return to the day after, whatever date the browser clock shows.
 */
describe( 'manual booking: default dates start from the site today', () => {
	let $;

	beforeEach( async () => {
		jest.resetModules();
		jest.useFakeTimers( { now: new Date( '2026-11-01T22:30:00Z' ), doNotFake: [ 'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'setImmediate', 'clearImmediate', 'nextTick', 'queueMicrotask', 'requestAnimationFrame', 'cancelAnimationFrame', 'performance' ] } );
		document.body.innerHTML = `
			<input id="mhmrentiva_manual_pickup_date">
			<input id="mhmrentiva_manual_dropoff_date">`;
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmManualBooking = { ajaxUrl: '/ajax', nonce: 'n', text: {}, currency: '', siteToday: '2026-11-02' };
		global.mhmManualBooking = window.mhmManualBooking;
		require( '../../assets/js/admin/manual-booking-meta.js' );
		await new Promise( ( resolve ) => $( resolve ) );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'pickup is the site today, return the day after', () => {
		expect( $( '#mhmrentiva_manual_pickup_date' ).val() ).toBe( '2026-11-02' );
		expect( $( '#mhmrentiva_manual_dropoff_date' ).val() ).toBe( '2026-11-03' );
	} );
} );
