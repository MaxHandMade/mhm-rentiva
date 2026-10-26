/**
 * @jest-environment jsdom
 */

/**
 * Spec 3B-2b §2.3 (U3): one URL builder for the booking redirect.
 * The second date click (same day or later) and the "Make Reservation" button
 * both go through AvailabilityCalendar#buildBookingUrl() and
 * window.location.assign(). End date is sent only for two distinct days;
 * no pickup location (the booking form takes it from the car, and a
 * data-location-id left on the markup is ignored); a non-http(s) or empty base never
 * redirects. Elements with the calendar's class names outside a calendar root
 * are left alone.
 */
describe( 'availability calendar: booking redirect', () => {
	let $;

	const BASE = 'https://example.test/book/';
	const START_HREF = 'https://example.test/arac/';
	const DAY = { status: 'available', is_weekend: false, is_today: false, is_past: false };

	// Response shape read from handleAvailabilityDataSuccess() (same as the
	// Task 2 fixture in availability-calendar-instances.test.js).
	const responseFor = ( startMonth ) => ( {
		success: true,
		data: {
			availability_data: {
				[ startMonth ]: {
					month_name: 'October',
					year: 2026,
					days: startMonth === '2026-10'
						? {
							'2026-10-20': { ...DAY, day_number: 20 },
							'2026-10-22': { ...DAY, day_number: 22 },
							'2026-10-24': { ...DAY, day_number: 24 },
						}
						: {},
				},
			},
		},
	} );

	// Markup from templates/shortcodes/availability-calendar.php (root,
	// day cells, hidden selected-dates panel with the book now button).
	const calendarRoot = ( vehicleId, locationId = 0 ) => `
		<div class="rv-availability-calendar rv-theme-default"
			data-vehicle-id="${ vehicleId }"
			data-vehicle-price="100"
			data-start-month="2026-10"
			data-months-to-show="2"
			data-location-id="${ locationId }">
			<div class="rv-availability-grid">
				<div class="rv-month-container" data-month="2026-10">
					<div class="rv-calendar-days">
						<div class="rv-calendar-day rv-day-available" data-date="2026-10-20"><span class="rv-day-number">20</span></div>
						<div class="rv-calendar-day rv-day-available" data-date="2026-10-22"><span class="rv-day-number">22</span></div>
						<div class="rv-calendar-day rv-day-available" data-date="2026-10-24"><span class="rv-day-number">24</span></div>
					</div>
				</div>
			</div>
			<div class="rv-selected-dates rv-hidden">
				<span class="rv-start-date"></span><span class="rv-end-date"></span>
				<span class="rv-value rv-days-count"></span>
				<span class="rv-value rv-price-total"></span>
				<div class="rv-booking-actions">
					<button type="button" class="rv-button rv-button-primary rv-book-now-btn" disabled>Make Reservation</button>
				</div>
			</div>
			<div class="rv-availability-loading rv-hidden"></div>
		</div>`;

	const click = ( el ) => {
		const ev = new MouseEvent( 'click', { bubbles: true, cancelable: true } );
		el.dispatchEvent( ev );
		return ev;
	};
	const root = () => document.querySelector( '.rv-availability-calendar' );
	const day = ( date ) => root().querySelector( `.rv-calendar-day[data-date="${ date }"]` );
	const cal = () => $( root() ).data( 'rvCalendar' );
	const assigned = ( n = 0 ) => new URL( window.location.assign.mock.calls[ n ][ 0 ] );
	const lastAssigned = () => assigned( window.location.assign.mock.calls.length - 1 );

	const boot = async ( html ) => {
		document.body.innerHTML = html;
		await new Promise( ( r ) => $( r ) );
		window.AvailabilityCalendar.initAll();
		$.post.mockClear();
	};

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;

		window.mhmRentivaAvailability = {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'n',
			bookingPageUrl: BASE,
			strings: {},
			messages: { error: 'e', success: 's' },
		};
		window.MHMRentivaToast = { show: jest.fn() };

		delete window.location;
		window.location = { assign: jest.fn(), href: START_HREF };

		$.post = jest.fn( ( url, data ) => $.Deferred().resolve( responseFor( data.start_month ) ).promise() );

		require( '../../assets/js/frontend/availability-calendar.js' );
	} );

	beforeEach( () => {
		window.mhmRentivaAvailability.bookingPageUrl = BASE;
		window.location.assign.mockClear();
		window.location.href = START_HREF;
		window.MHMRentivaToast.show.mockClear();
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	test( 'second_distinct_date_redirects_with_range', async () => {
		await boot( calendarRoot( 3017 ) );

		click( day( '2026-10-20' ) );
		click( day( '2026-10-22' ) );

		expect( window.location.assign ).toHaveBeenCalledTimes( 1 );
		const url = assigned();
		expect( url.origin + url.pathname ).toBe( BASE );
		expect( url.searchParams.get( 'mhmrentiva_vehicle_id' ) ).toBe( '3017' );
		expect( url.searchParams.get( 'mhmrentiva_start_date' ) ).toBe( '2026-10-20' );
		expect( url.searchParams.get( 'mhmrentiva_end_date' ) ).toBe( '2026-10-22' );
		expect( url.searchParams.has( 'mhmrentiva_pickup_location' ) ).toBe( false );
	} );

	test( 'same_day_twice_redirects_start_only', async () => {
		await boot( calendarRoot( 3017 ) );

		click( day( '2026-10-20' ) );
		click( day( '2026-10-20' ) );

		expect( window.location.assign ).toHaveBeenCalledTimes( 1 );
		const url = assigned();
		expect( url.searchParams.get( 'mhmrentiva_start_date' ) ).toBe( '2026-10-20' );
		expect( url.searchParams.has( 'mhmrentiva_end_date' ) ).toBe( false );
	} );

	// Regression guard: green before the change too.
	test( 'single_click_does_not_redirect', async () => {
		await boot( calendarRoot( 3017 ) );

		click( day( '2026-10-20' ) );

		expect( window.location.assign ).toHaveBeenCalledTimes( 0 );
		expect( window.location.href ).toBe( START_HREF );
		expect( cal().selectedDates ).toEqual( [ '2026-10-20' ] );
	} );

	test( 'button_single_date_redirects_start_only', async () => {
		await boot( calendarRoot( 3017 ) );

		click( day( '2026-10-20' ) );
		// jQuery .trigger() so a handler error (openBookingModal) reaches the test.
		expect( () => $( root().querySelector( '.rv-book-now-btn' ) ).trigger( 'click' ) ).not.toThrow();

		expect( window.location.assign ).toHaveBeenCalledTimes( 1 );
		const url = assigned();
		expect( url.searchParams.get( 'mhmrentiva_vehicle_id' ) ).toBe( '3017' );
		expect( url.searchParams.get( 'mhmrentiva_start_date' ) ).toBe( '2026-10-20' );
		expect( url.searchParams.has( 'mhmrentiva_end_date' ) ).toBe( false );
	} );

	test( 'no_pickup_location_is_sent', async () => {
		await boot( calendarRoot( 3017, 5 ) );

		click( day( '2026-10-20' ) );
		click( day( '2026-10-22' ) );
		expect( lastAssigned().searchParams.has( 'mhmrentiva_pickup_location' ) ).toBe( false );

		// A vehicle answer that still carries a location_id changes nothing.
		cal().updateVehicleDisplay( { id: 2992, title: 'X', location_id: 7 } );
		click( day( '2026-10-20' ) ); // restarts the completed selection
		click( day( '2026-10-22' ) );
		expect( lastAssigned().searchParams.has( 'mhmrentiva_pickup_location' ) ).toBe( false );

		expect( window.location.assign ).toHaveBeenCalledTimes( 2 );
	} );

	test.each( [
		[ 'javascript', 'javascript:alert(1)' ],
		[ 'empty', '' ],
		[ 'undefined', undefined ],
	] )( 'invalid_or_empty_base_does_not_redirect (%s)', async ( label, base ) => {
		await boot( calendarRoot( 3017 ) );
		window.mhmRentivaAvailability.bookingPageUrl = base;
		const warn = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		expect( cal().buildBookingUrl ).toEqual( expect.any( Function ) );

		click( day( '2026-10-20' ) );
		click( day( '2026-10-22' ) );

		expect( window.location.assign ).toHaveBeenCalledTimes( 0 );
		expect( window.location.href ).toBe( START_HREF );
		expect( warn ).toHaveBeenCalled();
	} );

	// Regression guard: green since Task 2 (delegate() skips elements outside a root).
	test( 'foreign_book_now_and_day_cells_not_hijacked', async () => {
		const foreign = `
			<div class="rv-vehicle-comparison">
				<a class="rv-book-now-btn" href="https://example.test/book/?mhmrentiva_vehicle_id=1">Book</a>
			</div>
			<div class="rv-vehicle-details-calendar">
				<div class="rv-calendar-day" data-date="2026-10-20">20</div>
			</div>`;

		// With a calendar root on the page.
		await boot( calendarRoot( 3017 ) + foreign );
		const btnOutside = document.querySelector( '.rv-vehicle-comparison .rv-book-now-btn' );
		const dayOutside = document.querySelector( '.rv-vehicle-details-calendar .rv-calendar-day' );
		expect( click( btnOutside ).defaultPrevented ).toBe( false );
		expect( click( dayOutside ).defaultPrevented ).toBe( false );
		expect( cal().selectedDates ).toEqual( [] );

		// Without any calendar root.
		await boot( foreign );
		expect( click( document.querySelector( '.rv-book-now-btn' ) ).defaultPrevented ).toBe( false );
		expect( click( document.querySelector( '.rv-calendar-day' ) ).defaultPrevented ).toBe( false );

		expect( window.location.assign ).toHaveBeenCalledTimes( 0 );
		expect( window.MHMRentivaToast.show ).toHaveBeenCalledTimes( 0 );
	} );

	test( 'earlier_click_restarts_selection', async () => {
		await boot( calendarRoot( 3017 ) );

		click( day( '2026-10-22' ) );
		click( day( '2026-10-20' ) );

		expect( window.location.assign ).toHaveBeenCalledTimes( 0 );
		expect( cal().selectedDates ).toEqual( [ '2026-10-20' ] );

		click( day( '2026-10-24' ) );

		expect( window.location.assign ).toHaveBeenCalledTimes( 1 );
		const url = assigned();
		expect( url.searchParams.get( 'mhmrentiva_start_date' ) ).toBe( '2026-10-20' );
		expect( url.searchParams.get( 'mhmrentiva_end_date' ) ).toBe( '2026-10-24' );
	} );

	test( 'base_with_query_keeps_existing_params', async () => {
		await boot( calendarRoot( 3017, 5 ) );
		window.mhmRentivaAvailability.bookingPageUrl = 'https://example.test/book?lang=tr';

		click( day( '2026-10-20' ) );
		click( day( '2026-10-22' ) );

		expect( window.location.assign ).toHaveBeenCalledTimes( 1 );
		const url = assigned();
		expect( url.origin + url.pathname ).toBe( 'https://example.test/book' );
		expect( url.searchParams.get( 'lang' ) ).toBe( 'tr' );
		expect( url.searchParams.get( 'mhmrentiva_vehicle_id' ) ).toBe( '3017' );
		expect( url.searchParams.get( 'mhmrentiva_start_date' ) ).toBe( '2026-10-20' );
		expect( url.searchParams.get( 'mhmrentiva_end_date' ) ).toBe( '2026-10-22' );
		expect( url.searchParams.has( 'mhmrentiva_pickup_location' ) ).toBe( false );
	} );
} );
