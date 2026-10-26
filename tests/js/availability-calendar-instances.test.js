/**
 * @jest-environment jsdom
 */

/**
 * Spec 3B-2b §2.2 (U2): every .rv-availability-calendar on a page runs its own
 * AvailabilityCalendar instance. One calendar's navigation, date selection and
 * AJAX request never read or write another calendar; a calendar inside an
 * editor preview root ([data-rv-render="preview"]) is never initialised; and
 * changing the vehicle from the no-data dropdown loads the new vehicle instead
 * of throwing (updateVehicleName never existed).
 */
describe( 'availability calendar: one instance per root', () => {
	let $;

	const MONTH_NAMES = { '2026-10': 'October', '2026-11': 'November', '2026-12': 'December' };
	const DAY = { status: 'available', is_weekend: false, is_today: false, is_past: false };

	// Response shape read from handleAvailabilityDataSuccess(): success +
	// data.availability_data keyed by 'YYYY-MM' with month_name, year, days.
	// The month key follows the requested start_month so a header that moved
	// is distinguishable from one that did not.
	const responseFor = ( startMonth ) => ( {
		success: true,
		data: {
			availability_data: {
				[ startMonth ]: {
					month_name: MONTH_NAMES[ startMonth ],
					year: 2026,
					days: startMonth === '2026-10'
						? {
							'2026-10-20': { ...DAY, day_number: 20 },
							'2026-10-22': { ...DAY, day_number: 22 },
						}
						: {},
				},
			},
		},
	} );

	// Markup taken from templates/shortcodes/availability-calendar.php
	// (root :106, controls :272-281, month name :278, grid :288, month
	// container :315, day cells :350-356, selected dates :442, book now :469).
	const calendarRoot = ( vehicleId ) => `
		<div class="rv-availability-calendar rv-theme-default"
			data-vehicle-id="${ vehicleId }"
			data-vehicle-price="100"
			data-start-month="2026-10"
			data-months-to-show="2"
			data-location-id="0">
			<div class="rv-calendar-controls">
				<button type="button" class="rv-control-btn rv-prev-months" data-action="prev">Previous</button>
				<div class="rv-month-display"><span class="rv-month-name">October 2026</span></div>
				<button type="button" class="rv-control-btn rv-next-months" data-action="next">Next</button>
			</div>
			<div class="rv-availability-grid">
				<div class="rv-month-container" data-month="2026-10">
					<div class="rv-calendar-days">
						<div class="rv-calendar-day rv-day-available" data-date="2026-10-20"><span class="rv-day-number">20</span></div>
						<div class="rv-calendar-day rv-day-available" data-date="2026-10-22"><span class="rv-day-number">22</span></div>
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

	// No-data branch of the template (:288-306): the fallback dropdown.
	const noDataRoot = `
		<div class="rv-availability-calendar rv-theme-default"
			data-vehicle-id="0"
			data-vehicle-price="0"
			data-start-month="2026-10"
			data-months-to-show="2"
			data-location-id="5">
			<div class="rv-availability-grid">
				<div class="rv-no-data-message">
					<div class="rv-no-data-content">
						<div class="rv-vehicle-selector">
							<label for="rv-availability-vehicle-select-fallback" class="rv-selector-label">Select Vehicle:</label>
							<select id="rv-availability-vehicle-select-fallback" class="rv-vehicle-dropdown" data-current-vehicle="0">
								<option value="">Select vehicle...</option>
								<option value="2992">Vehicle 2992</option>
								<option value="2983">Vehicle 2983</option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<div class="rv-availability-loading rv-hidden"></div>
		</div>`;

	const click = ( el ) => el.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	const roots = () => document.querySelectorAll( '.rv-availability-calendar' );
	const nextBtn = ( root ) => root.querySelector( '.rv-control-btn[data-action="next"]' );
	const monthName = ( root ) => root.querySelector( '.rv-month-name' ).textContent;
	const postData = ( n = 0 ) => $.post.mock.calls[ n ][ 1 ];

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
			bookingPageUrl: 'https://example.test/book/',
			strings: {},
			// Real keys, from AvailabilityCalendar::get_localized_data().
			messages: { error: 'e', success: 's' },
		};
		window.MHMRentivaToast = { show: jest.fn() };

		// The second-date-click branch still writes location.href (Task 3 moves it).
		delete window.location;
		window.location = { assign: jest.fn(), href: 'https://example.test/arac/' };

		$.post = jest.fn( ( url, data ) => $.Deferred().resolve( responseFor( data.start_month ) ).promise() );

		require( '../../assets/js/frontend/availability-calendar.js' );
	} );

	test( 'second_root_next_sends_own_vehicle', async () => {
		await boot( calendarRoot( 3017 ) + calendarRoot( 2983 ) );

		click( nextBtn( roots()[ 1 ] ) );

		expect( $.post ).toHaveBeenCalledTimes( 1 );
		expect( postData().vehicle_id ).toBe( 2983 );
	} );

	test( 'second_root_next_moves_only_second', async () => {
		await boot( calendarRoot( 3017 ) + calendarRoot( 2983 ) );
		const [ first, second ] = roots();
		expect( monthName( first ) ).toBe( 'October 2026' );
		expect( monthName( second ) ).toBe( 'October 2026' );

		click( nextBtn( second ) );

		expect( monthName( first ) ).toBe( 'October 2026' );
		expect( monthName( second ) ).toBe( 'November 2026' );
		expect( $( first ).data( 'rvCalendar' ).currentStartMonth ).toBe( '2026-10' );
		expect( $( second ).data( 'rvCalendar' ).currentStartMonth ).toBe( '2026-11' );
	} );

	test( 'date_range_is_per_root', async () => {
		await boot( calendarRoot( 3017 ) + calendarRoot( 2983 ) );
		const [ first, second ] = roots();

		click( first.querySelector( '.rv-calendar-day[data-date="2026-10-20"]' ) );
		click( first.querySelector( '.rv-calendar-day[data-date="2026-10-22"]' ) );

		expect( first.querySelectorAll( '.rv-selected' ).length ).toBeGreaterThan( 0 );
		expect(
			second.querySelectorAll( '.rv-selected, .rv-selected-start, .rv-selected-end, .rv-selected-range' ).length
		).toBe( 0 );
		expect( $( second ).data( 'rvCalendar' ).selectedDates ).toEqual( [] );
	} );

	test( 'preview_root_is_not_initialised', async () => {
		await boot( `<div data-rv-render="preview">${ calendarRoot( 3017 ) }</div>` );
		const root = roots()[ 0 ];

		expect( $( root ).data( 'rvCalendar' ) ).toBeUndefined();

		click( nextBtn( root ) );

		expect( $.post ).toHaveBeenCalledTimes( 0 );
	} );

	test( 'root_initialised_once', async () => {
		await boot( calendarRoot( 3017 ) );
		const root = roots()[ 0 ];
		const instance = $( root ).data( 'rvCalendar' );
		expect( instance ).toBeInstanceOf( window.AvailabilityCalendar );

		window.AvailabilityCalendar.initAll();

		expect( $( root ).data( 'rvCalendar' ) ).toBe( instance );
		expect( $.post ).toHaveBeenCalledTimes( 0 );
	} );

	test( 'single_root_navigation_unchanged', async () => {
		await boot( calendarRoot( 3017 ) );
		const root = roots()[ 0 ];

		click( nextBtn( root ) );

		expect( $.post ).toHaveBeenCalledTimes( 1 );
		expect( postData().vehicle_id ).toBe( 3017 );
		expect( postData().start_month ).toBe( '2026-11' );
		expect( monthName( root ) ).toBe( 'November 2026' );
	} );

	test( 'dropdown_vehicle_change_loads', async () => {
		await boot( noDataRoot );
		const root = roots()[ 0 ];
		const select = root.querySelector( 'select.rv-vehicle-dropdown' );

		// jQuery .trigger() so a handler error reaches the test.
		expect( () => $( select ).val( '2992' ).trigger( 'change' ) ).not.toThrow();

		expect( $.post ).toHaveBeenCalledTimes( 1 );
		expect( postData().vehicle_id ).toBe( 2992 );
	} );
} );
