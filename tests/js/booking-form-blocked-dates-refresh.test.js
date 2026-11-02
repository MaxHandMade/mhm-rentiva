/**
 * @jest-environment jsdom
 */

/**
 * The booking form fetches the vehicle's blocked dates on load
 * (fetchAndApplyBlockedDates) and redraws its pickers when they arrive. A
 * customer who opened the pickup calendar before the answer came back saw it
 * turn into "January 0" with every day greyed: the redraw ran on BOTH fields,
 * and jQuery UI draws every field into the one shared popup
 * (#ui-datepicker-div). The return field, never opened, still had jQuery UI's
 * blank month (drawMonth 0, drawYear 0), so its redraw painted year 0 over the
 * open pickup calendar.
 *
 * Driven with the REAL jQuery UI datepicker (tests/js/support/real-datepicker.js).
 */
const realDatepicker = require( './support/real-datepicker' );

describe( 'booking form: blocked dates arriving while a calendar is open', () => {
	let $;
	let form;

	const pad = ( n ) => String( n ).padStart( 2, '0' );
	const iso = ( d ) => d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
	const today = new Date();
	const todayIso = iso( today );
	const inThreeDays = iso( new Date( today.getFullYear(), today.getMonth(), today.getDate() + 3 ) );

	const months = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];

	beforeEach( async () => {
		jest.resetModules();
		document.body.innerHTML = `
			<div class="rvs-shell rvs-booking" data-rvs-booking-form data-vehicle-id="7">
				<input id="bf-pickup" name="pickup_date" data-rvs-pickup-date value="" readonly>
				<input id="bf-return" name="dropoff_date" data-rvs-dropoff-date value="" readonly>
				<button type="button" data-rvs-submit>Book</button>
			</div>`;
		$ = require( 'jquery' );
		global.jQuery = $;
		global.$ = $;
		window.jQuery = $;
		realDatepicker.install( $ );
		$.fx.off = true;

		window.mhmRentivaBookingForm = {
			siteToday: todayIso,
			restUrl: '/wp-json/mhm-rentiva/v1/',
			config: { min_days: 1, max_days: 30, advance_booking_days: 365 },
			strings: {},
			datepicker_options: {
				dateFormat: 'dd/mm/yy',
				minDate: 0,
				maxDate: 365,
				showButtonPanel: true,
				monthNames: months,
			},
		};

		require( '../../assets/js/frontend/booking-form.js' );
		const BookingForm = window.MHMRentivaBookingForm;
		form = Object.create( BookingForm.prototype );
		form.container = $( '[data-rvs-booking-form]' );
		form.blockedDates = [];
		form.initializeDatePickers();
	} );

	afterEach( () => {
		$.datepicker._hideDatepicker();
		$( '#ui-datepicker-div' ).remove();
	} );

	const popup = () => $( '#ui-datepicker-div' );
	const title = () => popup().find( '.ui-datepicker-title' ).text().replace( /\s+/g, ' ' ).trim();
	const selectableDays = () => popup().find( 'td[data-handler="selectDay"]' ).length;
	const currentTitle = () => months[ today.getMonth() ] + ' ' + today.getFullYear();

	const answer = ( dates ) => {
		$.ajax = jest.fn( ( opts ) => opts.success( { success: true, data: dates } ) );
		form.fetchAndApplyBlockedDates( 7 );
	};

	test( 'the open pickup calendar keeps its month when the answer arrives', () => {
		$( '#bf-pickup' ).datepicker( 'show' );
		expect( title() ).toBe( currentTitle() );

		answer( [ inThreeDays ] );

		expect( title() ).toBe( currentTitle() );
		expect( selectableDays() ).toBeGreaterThan( 0 );
	} );

	test( 'the open calendar takes the blocked dates at once', () => {
		$( '#bf-pickup' ).datepicker( 'show' );
		const target = new Date( today.getFullYear(), today.getMonth(), today.getDate() + 3 );
		const cell = () => popup().find( 'td' ).filter( function () {
			return +this.getAttribute( 'data-month' ) === target.getMonth()
				&& +this.getAttribute( 'data-year' ) === target.getFullYear()
				&& $( this ).text().trim() === String( target.getDate() );
		} );
		// Three days on may fall into next month: step there first.
		if ( target.getMonth() !== today.getMonth() ) {
			popup().find( '[data-handler="next"]' ).trigger( 'click' );
		}
		expect( cell().length ).toBe( 1 );

		answer( [ inThreeDays ] );

		expect( cell().length ).toBe( 0 );
		expect( popup().find( 'td.rv-blocked-date' ).length ).toBe( 1 );
	} );

	test( 'the return calendar opened later is drawn from its own month, with the blocked dates', () => {
		answer( [ inThreeDays ] );

		$( '#bf-return' ).datepicker( 'show' );

		expect( title() ).not.toMatch( / 0$/ );
		expect( selectableDays() ).toBeGreaterThan( 0 );
	} );

	test( 'an answer with no calendar open leaves the popup alone', () => {
		$( '#bf-pickup' ).datepicker( 'show' );
		$.datepicker._hideDatepicker();
		const before = popup().html();

		answer( [ inThreeDays ] );

		expect( popup().html() ).toBe( before );
	} );
} );
