/**
 * @jest-environment jsdom
 */

/**
 * Every [data-rvs-availability] on a page keeps its own state, reads its own
 * settings and touches nothing outside its root. A calendar inside an editor
 * preview root ([data-rv-render="preview"]) is never started.
 */
describe( 'availability calendar: one instance per root', () => {
	let $;
	let calls;

	const calendar = ( vehicleId, pricing = '1' ) => `
		<section data-rvs-availability data-vehicle-id="${ vehicleId }" data-start-month="2026-11" data-months-to-show="2" data-week-start="1" data-show-pricing="${ pricing }" data-show-booking-button="1">
			<button type="button" data-rvs-cal-nav="1">next</button>
			<div data-rvs-cal-months>
				<table><tr>
					<td class="rvs-cal__day is-available" data-date="2026-11-10"><button type="button" class="rvs-cal__btn" data-date="2026-11-10">10</button></td>
					<td class="rvs-cal__day is-available" data-date="2026-11-11"><button type="button" class="rvs-cal__btn" data-date="2026-11-11">11</button></td>
				</tr></table>
			</div>
			<div data-rvs-cal-panel hidden>
				<p data-rvs-cal-total></p>
				<p data-rvs-cal-message role="status"></p>
				<a data-rvs-cal-book href="#" hidden>Book</a>
			</div>
		</section>`;

	const click = ( el ) => el.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	const roots = () => document.querySelectorAll( '[data-rvs-availability]' );
	const answer = ( call, data ) => {
		call.success( { success: true, data } );
		if ( call.complete ) {
			call.complete();
		}
	};

	const boot = ( html ) => {
		document.body.innerHTML = html;
		calls.length = 0;
		window.mhmRentivaAvailabilityCalendar.init();
	};

	// Past the load-time refresh of every started root.
	const settle = () => {
		calls.slice().forEach( ( c ) => answer( c, { html: document.querySelector( `[data-vehicle-id="${ c.data.vehicle_id }"] [data-rvs-cal-months]` ).innerHTML, prev_allowed: false, start_month: c.data.start_month } ) );
		calls.length = 0;
	};

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		calls = [];
		$.ajax = jest.fn( ( options ) => {
			calls.push( options );
		} );
		window.mhmRentivaAvailability = { ajax_url: '/ajax', nonce: 'n', strings: { pickReturn: 'PICK', error: 'ERR' } };
		require( '../../assets/js/frontend/availability-calendar.js' );
	} );

	test( 'each_root_loads_its_own_months', () => {
		boot( calendar( 3017 ) + calendar( 2983 ) );

		expect( calls.map( ( c ) => c.data.vehicle_id ) ).toEqual( [ '3017', '2983' ] );
	} );

	test( 'second_root_next_sends_its_own_vehicle_and_moves_only_itself', () => {
		boot( calendar( 3017 ) + calendar( 2983 ) );
		settle();
		const [ first, second ] = roots();

		click( second.querySelector( '[data-rvs-cal-nav="1"]' ) );

		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].data.vehicle_id ).toBe( '2983' );

		answer( calls[ 0 ], { html: '<p id="moved">next</p>', prev_allowed: true, start_month: '2026-12' } );

		expect( second.querySelector( '#moved' ) ).not.toBeNull();
		expect( first.querySelector( '#moved' ) ).toBeNull();
		expect( first.getAttribute( 'data-start-month' ) ).toBe( '2026-11' );
		expect( second.getAttribute( 'data-start-month' ) ).toBe( '2026-12' );
	} );

	test( 'selection_is_per_root', () => {
		boot( calendar( 3017 ) + calendar( 2983 ) );
		settle();
		const [ first, second ] = roots();

		click( first.querySelector( '.rvs-cal__btn[data-date="2026-11-10"]' ) );
		click( second.querySelector( '.rvs-cal__btn[data-date="2026-11-11"]' ) );
		click( first.querySelector( '.rvs-cal__btn[data-date="2026-11-11"]' ) );

		// The second click of the first root completes its own range only.
		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].data ).toMatchObject( { vehicle_id: '3017', start_date: '2026-11-10', end_date: '2026-11-11' } );
		expect( second.querySelector( 'td[data-date="2026-11-10"]' ).classList.contains( 'is-selected' ) ).toBe( false );
		expect( second.querySelector( 'td[data-date="2026-11-11"]' ).classList.contains( 'is-selected' ) ).toBe( true );
	} );

	test( 'settings_are_per_root', () => {
		boot( calendar( 3017, '0' ) + calendar( 2983, '1' ) );
		settle();
		const [ first, second ] = roots();
		const quote = { priced: true, days: 1, total_html: 'TOTAL', available: true, message: '', continue_url: 'https://x.test/', range_label: 'R', time_hint: 'T' };

		[ first, second ].forEach( ( root ) => {
			click( root.querySelector( '.rvs-cal__btn[data-date="2026-11-10"]' ) );
			click( root.querySelector( '.rvs-cal__btn[data-date="2026-11-11"]' ) );
		} );
		calls.slice().forEach( ( c ) => answer( c, quote ) );

		expect( first.querySelector( '[data-rvs-cal-total]' ).textContent ).toBe( '' );
		expect( second.querySelector( '[data-rvs-cal-total]' ).textContent ).toBe( 'TOTAL' );
	} );

	test( 'preview_root_is_not_started', () => {
		boot( `<div data-rv-render="preview">${ calendar( 3017 ) }</div>` );
		const root = roots()[ 0 ];

		expect( calls ).toHaveLength( 0 );

		click( root.querySelector( '[data-rvs-cal-nav="1"]' ) );
		click( root.querySelector( '.rvs-cal__btn' ) );

		expect( calls ).toHaveLength( 0 );
		expect( root.querySelector( 'td.is-selected' ) ).toBeNull();
	} );

	test( 'a_root_starts_once', () => {
		boot( calendar( 3017 ) );
		expect( calls ).toHaveLength( 1 );

		window.mhmRentivaAvailabilityCalendar.init();

		expect( calls ).toHaveLength( 1 );
	} );

	test( 'nothing_outside_the_root_is_touched', () => {
		const foreign = `
			<div id="outside">
				<td class="rvs-cal__day is-available" data-date="2026-11-10"></td>
				<button type="button" class="rvs-cal__btn" id="foreign-day" data-date="2026-11-10">10</button>
				<button type="button" id="foreign-nav" data-rvs-cal-nav="1">next</button>
				<div data-rvs-cal-panel id="foreign-panel" hidden></div>
				<a data-rvs-cal-book id="foreign-book" href="/keep" hidden>x</a>
			</div>`;
		boot( foreign + calendar( 3017 ) );
		settle();
		const before = document.getElementById( 'outside' ).innerHTML;

		const ev = new MouseEvent( 'click', { bubbles: true, cancelable: true } );
		document.getElementById( 'foreign-day' ).dispatchEvent( ev );
		click( document.getElementById( 'foreign-nav' ) );

		expect( ev.defaultPrevented ).toBe( false );
		expect( calls ).toHaveLength( 0 );

		// Working inside the root changes nothing outside either.
		click( roots()[ 0 ].querySelector( '.rvs-cal__btn[data-date="2026-11-10"]' ) );
		click( roots()[ 0 ].querySelector( '.rvs-cal__btn[data-date="2026-11-11"]' ) );
		answer( calls[ 0 ], { priced: true, total_html: 'T', available: true, continue_url: 'https://x.test/', range_label: 'R', time_hint: 'H', message: '' } );

		expect( document.getElementById( 'outside' ).innerHTML ).toBe( before );
	} );
} );
