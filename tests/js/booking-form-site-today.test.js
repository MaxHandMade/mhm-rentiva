/**
 * @jest-environment jsdom
 */

/**
 * PR T: the date limits of the booking form start from the SITE's today
 * (localized as siteToday), not from the browser clock. A browser clock that
 * is still on the previous UTC day must not move the limits.
 */
describe( 'booking form: limits start from the site today', () => {
	let $;
	let BookingForm;

	beforeEach( () => {
		jest.resetModules();
		jest.useFakeTimers( { now: new Date( '2026-11-01T22:30:00Z' ), doNotFake: [ 'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'setImmediate', 'clearImmediate', 'nextTick', 'queueMicrotask', 'requestAnimationFrame', 'cancelAnimationFrame', 'performance' ] } );
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		$.fn.datepicker = function () {
			return this;
		};
		$.datepicker = { setDefaults: () => {} };
		window.mhmRentivaBookingForm = { siteToday: '2026-11-02', config: {}, strings: {} };
		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'addDays is calendar arithmetic on the ISO string', () => {
		expect( BookingForm.addDays( '2026-11-02', 1 ) ).toBe( '2026-11-03' );
		expect( BookingForm.addDays( '2026-12-31', 1 ) ).toBe( '2027-01-01' );
		expect( BookingForm.addDays( '2026-03-01', -1 ) ).toBe( '2026-02-28' );
		expect( BookingForm.addDays( '2026-10-25', 1 ) ).toBe( '2026-10-26' );
	} );

	test( 'pickup min is the site today, return min is the day after', () => {
		document.body.innerHTML = `
			<div class="rv-booking-form-wrapper">
				<input class="rv-pickup-date"><input class="rv-dropoff-date">
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '.rv-booking-form-wrapper' );
		form.setupDateValidation();

		expect( form.container.find( '.rv-pickup-date' ).attr( 'min' ) ).toBe( '2026-11-02' );
		expect( form.container.find( '.rv-dropoff-date' ).attr( 'min' ) ).toBe( '2026-11-03' );
	} );

	test( 'numeric minDate/maxDate offsets count from the site today', () => {
		const out = BookingForm.siteDatepickerOptions( { minDate: 0, maxDate: 10, dateFormat: 'yy-mm-dd' } );
		expect( BookingForm.localIso( out.minDate ) ).toBe( '2026-11-02' );
		expect( BookingForm.localIso( out.maxDate ) ).toBe( '2026-11-12' );
		expect( out.dateFormat ).toBe( 'yy-mm-dd' );
	} );

	test( 'without siteToday the browser date is the fallback', () => {
		window.mhmRentivaBookingForm = {};
		expect( BookingForm.siteToday() ).toBe( BookingForm.localIso( new Date() ) );
	} );
} );
