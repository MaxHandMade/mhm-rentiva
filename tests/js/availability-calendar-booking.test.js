/**
 * @jest-environment jsdom
 */

/**
 * PR E (R4, R8, R10, m4, m17, m21, M2): the calendar's selection and months.
 * The second click no longer redirects: the server's range, total and link show
 * in the panel. The script builds no URL and formats no date. Markup follows
 * templates/shortcodes/availability-calendar.php and partials/rvs/calendar-panel.php.
 */
describe( 'availability calendar: selection, panel and months', () => {
	let $;
	let calls;

	const day = ( date ) => `<td class="rvs-cal__day is-available" data-date="${ date }"><button type="button" class="rvs-cal__btn" data-date="${ date }" aria-label="${ date }, Available"><span class="rvs-cal__num">x</span></button></td>`;
	const booked = ( date ) => `<td class="rvs-cal__day is-booked" data-date="${ date }"><span class="rvs-cal__num">x</span></td>`;
	const month = ( key, cells ) => `<div class="rvs-cal is-interactive" data-rvs-calendar data-title="${ key }"><table><tbody><tr>${ cells }</tr></tbody></table></div>`;
	const NOVEMBER = month( '2026-11', day( '2026-11-10' ) + day( '2026-11-11' ) + booked( '2026-11-12' ) + day( '2026-11-13' ) + day( '2026-11-14' ) );
	const DECEMBER = month( '2026-12', day( '2026-12-01' ) );

	const page = ( { pricing = '1', booking = '1', vehicle = '7' } = {} ) => `
		<section class="rvs-availability" data-rvs-availability data-vehicle-id="${ vehicle }" data-start-month="2026-11" data-months-to-show="2" data-week-start="1" data-show-pricing="${ pricing }" data-show-booking-button="${ booking }">
			<div class="rvs-availability__vehicle">
				<img class="rvs-availability__image" data-rvs-cal-image src="old.jpg" alt="Old">
				<p data-rvs-cal-name>Old</p>
				${ '1' === pricing ? '<p data-rvs-cal-price>100</p>' : '' }
				<button type="button" id="switch" data-rvs-cal-switch>Change vehicle</button>
			</div>
			<button type="button" data-rvs-cal-nav="-1" disabled>prev</button>
			<button type="button" data-rvs-cal-nav="1">next</button>
			<div data-rvs-cal-months>${ NOVEMBER }</div>
			<div data-rvs-cal-panel hidden>
				<p data-rvs-cal-range></p>
				<p data-rvs-cal-time></p>
				${ '1' === pricing ? '<p data-rvs-cal-total></p>' : '' }
				<p data-rvs-cal-message role="status" aria-live="polite"></p>
				${ '1' === booking ? '<a data-rvs-cal-book href="#" hidden>Make reservation</a>' : '' }
			</div>
			<div class="rvs-dialog" data-rvs-cal-dialog hidden>
				<div class="rvs-dialog__panel">
					<h2 class="rvs-dialog__title" id="dlg-title">Select vehicle</h2>
					<button type="button" data-rvs-dialog-close>×</button>
					<ul>
						<li><button type="button" data-rvs-cal-option="7" aria-pressed="true">Old</button></li>
						<li><button type="button" data-rvs-cal-option="9" aria-pressed="false">New</button></li>
					</ul>
				</div>
			</div>
		</section>`;

	const QUOTE = {
		priced: true,
		days: 1,
		total_html: '<span class="amount">₺100</span>',
		available: true,
		message: '',
		continue_url: 'https://example.test/book/?pickup_date=2026-11-10&return_date=2026-11-11',
		range_label: '10/11/2026 – 11/11/2026 · 1 day',
		time_hint: 'Pickup and return at 10:00',
	};
	const REFUSED = { priced: false, days: 0, total_html: '', available: false, message: 'The dates you selected include a booked day.', continue_url: '', range_label: '10/11/2026 – 13/11/2026', time_hint: '' };

	const root = () => document.querySelector( '[data-rvs-availability]' );
	const q = ( sel ) => root().querySelector( sel );
	const click = ( el ) => el.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	const pick = ( date ) => click( q( `.rvs-cal__btn[data-date="${ date }"]` ) );
	const cell = ( date ) => q( `td[data-date="${ date }"]` );
	const panel = () => q( '[data-rvs-cal-panel]' );
	const message = () => q( '[data-rvs-cal-message]' ).textContent;
	const total = () => q( '[data-rvs-cal-total]' );
	const bookLink = () => q( '[data-rvs-cal-book]' );

	const sent = ( action ) => calls.filter( ( c ) => c.data.action === action );
	const ok = ( call, data ) => {
		call.success( { success: true, data } );
		if ( call.complete ) {
			call.complete();
		}
	};
	const refuse = ( call, msg ) => {
		call.success( { success: false, data: { message: msg } } );
		if ( call.complete ) {
			call.complete();
		}
	};
	const broken = ( call ) => {
		call.error( {} );
		if ( call.complete ) {
			call.complete();
		}
	};

	const boot = ( options ) => {
		document.body.innerHTML = page( options );
		calls.length = 0;
		window.mhmRentivaAvailabilityCalendar.init();
		ok( calls[ 0 ], { html: NOVEMBER, prev_allowed: false, start_month: '2026-11' } );
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
		window.mhmRentivaAvailability = {
			ajax_url: '/wp-admin/admin-ajax.php',
			nonce: 'n',
			strings: { error: 'ERR', loading: 'LOADING', pickReturn: 'PICK RETURN', selected: 'SELECTED' },
		};
		require( '../../assets/js/frontend/rvs-dialog.js' );
		document.body.innerHTML = '';
		require( '../../assets/js/frontend/availability-calendar.js' );
	} );

	test( 'on_load_months_are_refreshed_with_the_root_settings', () => {
		document.body.innerHTML = page();
		calls.length = 0;
		window.mhmRentivaAvailabilityCalendar.init();

		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].data ).toMatchObject( { action: 'mhmrentiva_calendar_months', nonce: 'n', vehicle_id: '7', start_month: '2026-11', months_to_show: 2, week_start: 1 } );

		// Cached HTML opened on a month now past: the server's month and prev state win.
		ok( calls[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

		expect( q( '.rvs-cal__btn[data-date="2026-12-01"]' ) ).not.toBeNull();
		expect( q( '.rvs-cal__btn[data-date="2026-11-10"]' ) ).toBeNull();
		expect( q( '[data-rvs-cal-nav="-1"]' ).disabled ).toBe( false );
		expect( root().getAttribute( 'data-start-month' ) ).toBe( '2026-12' );
	} );

	describe( 'a month step is spoken in the panel message', () => {
		const titled = ( ...names ) => names.map( ( name ) => `<div class="rvs-cal"><h3 class="rvs-cal__title">${ name }</h3></div>` ).join( '' ) + DECEMBER;
		const stepTo = ( ...names ) => {
			click( q( '[data-rvs-cal-nav="1"]' ) );
			ok( calls[ calls.length - 1 ], { html: titled( ...names ), prev_allowed: true, start_month: '2026-12' } );
		};

		test( 'alone_it_is_for_screen_readers_with_no_box_on_screen', () => {
			boot();
			expect( panel().hidden ).toBe( true );

			stepTo( 'December 2026', 'January 2027' );

			expect( panel().hidden ).toBe( false );
			expect( panel().classList.contains( 'rvs-sr-only' ) ).toBe( true );
			expect( message() ).toBe( 'December 2026 – January 2027' );
			expect( q( '[data-rvs-cal-message]' ).getAttribute( 'role' ) ).toBe( 'status' );
		} );

		test( 'the_quiet_refresh_at_load_says_nothing', () => {
			document.body.innerHTML = page();
			calls.length = 0;
			window.mhmRentivaAvailabilityCalendar.init();
			ok( calls[ 0 ], { html: titled( 'November 2026' ), prev_allowed: false, start_month: '2026-11' } );

			expect( panel().hidden ).toBe( true );
			expect( message() ).toBe( '' );
		} );

		test( 'it_joins_the_return_prompt_and_the_next_click_clears_it', () => {
			boot();
			pick( '2026-11-10' );
			stepTo( 'December 2026' );

			expect( message() ).toBe( 'December 2026 · PICK RETURN' );
			expect( panel().classList.contains( 'rvs-sr-only' ) ).toBe( false );

			pick( '2026-12-01' );
			expect( message() ).toBe( 'LOADING' );
		} );

		test( 'over_a_quote_it_stays_out_of_sight_and_the_total_stays', () => {
			boot();
			pick( '2026-11-10' );
			pick( '2026-11-11' );
			ok( calls[ 0 ], QUOTE );
			stepTo( 'December 2026' );

			expect( message() ).toBe( 'December 2026' );
			expect( q( '[data-rvs-cal-message]' ).classList.contains( 'rvs-sr-only' ) ).toBe( true );
			expect( panel().classList.contains( 'rvs-sr-only' ) ).toBe( false );
			expect( total().innerHTML ).toBe( QUOTE.total_html );
		} );

		test( 'an_error_keeps_the_line_to_itself', () => {
			boot();
			click( q( '[data-rvs-cal-nav="1"]' ) );
			broken( calls[ calls.length - 1 ] );

			expect( message() ).toBe( 'ERR' );
		} );
	} );

	test( 'a_failed_load_keeps_the_page_months_and_says_so', () => {
		document.body.innerHTML = page();
		calls.length = 0;
		window.mhmRentivaAvailabilityCalendar.init();

		broken( calls[ 0 ] );

		expect( q( '.rvs-cal__btn[data-date="2026-11-10"]' ) ).not.toBeNull();
		expect( panel().hidden ).toBe( false );
		expect( message() ).toBe( 'ERR' );
		expect( q( '[data-rvs-cal-message]' ).getAttribute( 'aria-live' ) ).toBe( 'polite' );
		expect( q( '[data-rvs-cal-message]' ).getAttribute( 'data-state' ) ).toBe( 'error' );
	} );

	test( 'first_click_marks_the_start_and_asks_for_the_return', () => {
		boot();
		pick( '2026-11-10' );

		expect( calls ).toHaveLength( 0 );
		expect( cell( '2026-11-10' ).classList.contains( 'is-selected' ) ).toBe( true );
		expect( cell( '2026-11-10' ).classList.contains( 'is-available' ) ).toBe( false );
		expect( q( '.rvs-cal__btn[data-date="2026-11-10"]' ).getAttribute( 'aria-pressed' ) ).toBe( 'true' );
		expect( panel().hidden ).toBe( false );
		expect( message() ).toBe( 'PICK RETURN' );
		expect( bookLink().hidden ).toBe( true );
	} );

	test( 'second_click_shows_range_total_and_the_server_link_without_redirecting', () => {
		boot();
		pick( '2026-11-10' );
		pick( '2026-11-11' );

		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].data ).toMatchObject( { action: 'mhmrentiva_calendar_quote', vehicle_id: '7', start_date: '2026-11-10', end_date: '2026-11-11' } );
		expect( message() ).toBe( 'LOADING' );
		expect( bookLink().hidden ).toBe( true );

		ok( calls[ 0 ], QUOTE );

		expect( q( '[data-rvs-cal-range]' ).textContent ).toBe( QUOTE.range_label );
		expect( q( '[data-rvs-cal-time]' ).textContent ).toBe( QUOTE.time_hint );
		expect( total().querySelector( '.amount' ).textContent ).toBe( '₺100' );
		expect( bookLink().hidden ).toBe( false );
		expect( bookLink().getAttribute( 'href' ) ).toBe( QUOTE.continue_url );
		expect( message() ).toBe( `${ QUOTE.range_label } · ₺100` );
		expect( q( '[data-rvs-cal-message]' ).classList.contains( 'rvs-sr-only' ) ).toBe( true );
		expect( q( '[data-rvs-cal-message]' ).children ).toHaveLength( 0 );
		expect( q( '[data-rvs-cal-message]' ).hasAttribute( 'data-state' ) ).toBe( false );
		expect( cell( '2026-11-10' ).classList.contains( 'is-range-start' ) ).toBe( true );
		expect( cell( '2026-11-11' ).classList.contains( 'is-range-end' ) ).toBe( true );
	} );

	test( 'same_day_twice_stays_the_start_with_no_total', () => {
		boot();
		pick( '2026-11-10' );
		pick( '2026-11-10' );

		expect( calls ).toHaveLength( 0 );
		expect( cell( '2026-11-10' ).classList.contains( 'is-selected' ) ).toBe( true );
		expect( total().textContent ).toBe( '' );
		expect( bookLink().hidden ).toBe( true );
		expect( message() ).toBe( 'PICK RETURN' );

		// The next later day still completes the range.
		pick( '2026-11-11' );
		expect( sent( 'mhmrentiva_calendar_quote' )[ 0 ].data.start_date ).toBe( '2026-11-10' );
	} );

	test( 'click_before_the_start_restarts', () => {
		boot();
		pick( '2026-11-11' );
		pick( '2026-11-10' );

		expect( calls ).toHaveLength( 0 );
		expect( cell( '2026-11-11' ).classList.contains( 'is-selected' ) ).toBe( false );
		expect( cell( '2026-11-11' ).classList.contains( 'is-available' ) ).toBe( true );
		expect( cell( '2026-11-10' ).classList.contains( 'is-selected' ) ).toBe( true );
	} );

	test( 'click_after_a_full_range_starts_over_and_drops_the_total', () => {
		boot();
		pick( '2026-11-10' );
		pick( '2026-11-11' );
		ok( calls[ 0 ], QUOTE );

		pick( '2026-11-14' );

		expect( cell( '2026-11-10' ).classList.contains( 'is-selected' ) ).toBe( false );
		expect( total().textContent ).toBe( '' );
		expect( q( '[data-rvs-cal-range]' ).textContent ).toBe( '' );
		expect( bookLink().hidden ).toBe( true );
		expect( bookLink().getAttribute( 'href' ) ).toBe( '#' );
	} );

	test( 'range_with_a_booked_day_shows_the_message_no_total_no_button', () => {
		boot();
		pick( '2026-11-10' );
		pick( '2026-11-13' );
		ok( calls[ 0 ], REFUSED );

		expect( message() ).toBe( REFUSED.message );
		expect( q( '[data-rvs-cal-message]' ).getAttribute( 'data-state' ) ).toBe( 'error' );
		expect( total().textContent ).toBe( '' );
		expect( bookLink().hidden ).toBe( true );
		// The booked cell stays booked inside the range.
		expect( cell( '2026-11-12' ).classList.contains( 'is-booked' ) ).toBe( true );
		expect( cell( '2026-11-12' ).classList.contains( 'is-selected' ) ).toBe( false );
	} );

	test( 'clicking_a_booked_cell_does_nothing', () => {
		boot();
		click( cell( '2026-11-12' ) );

		expect( calls ).toHaveLength( 0 );
		expect( panel().hidden ).toBe( true );
	} );

	test( 'stale_quote_answer_is_ignored', () => {
		boot();
		pick( '2026-11-10' );
		pick( '2026-11-11' );
		const older = calls[ 0 ];
		pick( '2026-11-13' ); // Restart: the visitor left that range.
		pick( '2026-11-14' );
		const newer = calls[ 1 ];

		ok( newer, { ...QUOTE, range_label: 'NEW', total_html: 'NEW TOTAL' } );
		ok( older, { ...QUOTE, range_label: 'OLD', total_html: 'OLD TOTAL' } );

		expect( q( '[data-rvs-cal-range]' ).textContent ).toBe( 'NEW' );
		expect( total().textContent ).toBe( 'NEW TOTAL' );
	} );

	test( 'quote_error_is_told_in_the_live_region_and_offers_no_link', () => {
		boot();
		pick( '2026-11-10' );
		pick( '2026-11-11' );
		refuse( calls[ 0 ], 'Too many price requests. Please wait.' );

		expect( message() ).toBe( 'Too many price requests. Please wait.' );
		expect( q( '[data-rvs-cal-message]' ).getAttribute( 'role' ) ).toBe( 'status' );
		expect( bookLink().hidden ).toBe( true );
	} );

	test( 'show_pricing_off_draws_no_total_even_when_the_answer_has_one_and_keeps_the_button', () => {
		boot( { pricing: '0' } );
		// A total element that should not be there still stays empty.
		panel().insertAdjacentHTML( 'afterbegin', '<p data-rvs-cal-total></p>' );
		pick( '2026-11-10' );
		pick( '2026-11-11' );
		ok( calls[ 0 ], QUOTE );

		expect( total().textContent ).toBe( '' );
		expect( root().textContent ).not.toContain( '₺100' );
		expect( bookLink().hidden ).toBe( false );
		expect( bookLink().getAttribute( 'href' ) ).toBe( QUOTE.continue_url );
		expect( q( '[data-rvs-cal-range]' ).textContent ).toBe( QUOTE.range_label );
	} );

	test( 'live_summary_leaves_the_total_out_with_pricing_off', () => {
		boot( { pricing: '0' } );
		pick( '2026-11-10' );
		pick( '2026-11-11' );
		ok( calls[ 0 ], QUOTE );

		expect( message() ).toBe( QUOTE.range_label );
		expect( message() ).not.toContain( '₺' );
	} );

	test( 'a_visible_message_after_a_summary_is_visible_again', () => {
		boot();
		pick( '2026-11-10' );
		pick( '2026-11-11' );
		ok( calls[ 0 ], QUOTE );
		pick( '2026-11-14' );

		expect( message() ).toBe( 'PICK RETURN' );
		expect( q( '[data-rvs-cal-message]' ).classList.contains( 'rvs-sr-only' ) ).toBe( false );
	} );

	describe( 'bringing the panel into view', () => {
		let scroll;
		const motion = ( reduced ) => {
			window.matchMedia = jest.fn( ( query ) => ( { matches: reduced && query.includes( 'reduce' ) } ) );
		};

		beforeEach( () => {
			scroll = jest.fn();
			window.HTMLElement.prototype.scrollIntoView = scroll;
		} );

		afterEach( () => {
			delete window.HTMLElement.prototype.scrollIntoView;
			delete window.matchMedia;
		} );

		const quoted = () => {
			boot();
			pick( '2026-11-10' );
			pick( '2026-11-11' );
		};

		test( 'after_the_quote_arrives_and_not_before', () => {
			motion( false );
			quoted();
			expect( scroll ).not.toHaveBeenCalled();

			ok( calls[ 0 ], QUOTE );

			expect( scroll ).toHaveBeenCalledTimes( 1 );
			expect( scroll.mock.instances[ 0 ] ).toBe( panel() );
			expect( scroll ).toHaveBeenCalledWith( { block: 'nearest', behavior: 'smooth' } );
		} );

		test( 'instantly_under_reduced_motion', () => {
			motion( true );
			quoted();
			ok( calls[ 0 ], QUOTE );

			expect( scroll ).toHaveBeenCalledWith( { block: 'nearest', behavior: 'instant' } );
		} );

		test( 'a_stale_answer_moves_nothing', () => {
			motion( false );
			quoted();
			const older = calls[ 0 ];
			pick( '2026-11-14' );
			ok( older, QUOTE );

			expect( scroll ).not.toHaveBeenCalled();
		} );

		test( 'a_refused_range_comes_into_view_too', () => {
			motion( false );
			boot();
			pick( '2026-11-10' );
			pick( '2026-11-13' );
			ok( calls[ 0 ], REFUSED );

			expect( scroll ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'the_first_click_and_a_month_step_do_not_scroll', () => {
			motion( false );
			boot();
			pick( '2026-11-10' );
			click( q( '[data-rvs-cal-nav="1"]' ) );
			ok( calls[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

			expect( scroll ).not.toHaveBeenCalled();
		} );
	} );

	test( 'a_selected_day_says_so_in_its_name_and_stops_when_released', () => {
		boot();
		const button = () => q( '.rvs-cal__btn[data-date="2026-11-10"]' );
		pick( '2026-11-10' );
		expect( button().getAttribute( 'aria-label' ) ).toBe( '2026-11-10, Available, SELECTED' );

		// Marked again after the months come back: never twice.
		click( q( '[data-rvs-cal-nav="1"]' ) );
		ok( calls[ 0 ], { html: NOVEMBER, prev_allowed: true, start_month: '2026-11' } );
		expect( button().getAttribute( 'aria-label' ) ).toBe( '2026-11-10, Available, SELECTED' );

		pick( '2026-11-10' ); // Stays the start.
		expect( button().getAttribute( 'aria-label' ) ).toBe( '2026-11-10, Available, SELECTED' );

		pick( '2026-11-11' ); // Range; the next click releases the old days.
		pick( '2026-11-14' );
		expect( button().getAttribute( 'aria-label' ) ).toBe( '2026-11-10, Available' );
		expect( button().getAttribute( 'aria-pressed' ) ).toBe( 'false' );
	} );

	test( 'a_months_reload_keeps_a_showing_quote_error', () => {
		boot();
		pick( '2026-11-10' );
		pick( '2026-11-11' );
		refuse( calls[ 0 ], 'Too many price requests. Please wait.' );
		click( q( '[data-rvs-cal-nav="1"]' ) );
		ok( calls[ 1 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

		expect( message() ).toBe( 'Too many price requests. Please wait.' );
		expect( q( '[data-rvs-cal-message]' ).getAttribute( 'data-state' ) ).toBe( 'error' );
	} );

	test( 'a_months_reload_ends_its_own_error', () => {
		boot();
		click( q( '[data-rvs-cal-nav="1"]' ) );
		broken( calls[ 0 ] );
		expect( message() ).toBe( 'ERR' );

		click( q( '[data-rvs-cal-nav="1"]' ) );
		ok( calls[ 1 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

		expect( message() ).toBe( '' );
		expect( panel().hidden ).toBe( true );
	} );

	test( 'show_booking_button_off_keeps_the_total', () => {
		boot( { booking: '0' } );
		pick( '2026-11-10' );
		pick( '2026-11-11' );
		ok( calls[ 0 ], QUOTE );

		expect( q( '[data-rvs-cal-book]' ) ).toBeNull();
		expect( total().textContent ).toBe( '₺100' );
	} );

	test( 'month_navigation_applies_the_server_answer_and_keeps_the_selection', () => {
		boot();
		pick( '2026-11-10' );
		click( q( '[data-rvs-cal-nav="1"]' ) );

		expect( calls[ 0 ].data ).toMatchObject( { action: 'mhmrentiva_calendar_months', start_month: '2026-12', vehicle_id: '7' } );
		ok( calls[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );
		expect( q( '.rvs-cal__btn[data-date="2026-12-01"]' ) ).not.toBeNull();
		expect( q( '[data-rvs-cal-nav="-1"]' ).disabled ).toBe( false );

		// The return may be in the next month.
		pick( '2026-12-01' );
		expect( sent( 'mhmrentiva_calendar_quote' )[ 0 ].data ).toMatchObject( { start_date: '2026-11-10', end_date: '2026-12-01' } );
		expect( cell( '2026-12-01' ).classList.contains( 'is-selected' ) ).toBe( true );

		// Back on November the start is marked again.
		click( q( '[data-rvs-cal-nav="-1"]' ) );
		ok( sent( 'mhmrentiva_calendar_months' )[ 1 ], { html: NOVEMBER, prev_allowed: false, start_month: '2026-11' } );
		expect( cell( '2026-11-10' ).classList.contains( 'is-range-start' ) ).toBe( true );
		expect( q( '[data-rvs-cal-nav="-1"]' ).disabled ).toBe( true );
	} );

	test( 'quick_steps_build_on_each_other_and_only_the_last_answer_counts', () => {
		boot();
		click( q( '[data-rvs-cal-nav="1"]' ) );
		click( q( '[data-rvs-cal-nav="1"]' ) );

		expect( calls.map( ( c ) => c.data.start_month ) ).toEqual( [ '2026-12', '2027-01' ] );

		ok( calls[ 1 ], { html: month( '2027-01', day( '2027-01-05' ) ), prev_allowed: true, start_month: '2027-01' } );
		ok( calls[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

		expect( q( '.rvs-cal__btn[data-date="2027-01-05"]' ) ).not.toBeNull();
		expect( root().getAttribute( 'data-start-month' ) ).toBe( '2027-01' );
	} );

	test( 'next_next_prev_prev_all_count_before_any_answer', () => {
		boot();
		const next = q( '[data-rvs-cal-nav="1"]' );
		const prev = q( '[data-rvs-cal-nav="-1"]' );

		click( next );
		expect( prev.disabled ).toBe( false );
		click( next );
		click( prev );
		expect( prev.disabled ).toBe( false );
		click( prev );

		expect( calls.map( ( c ) => c.data.start_month ) ).toEqual( [ '2026-12', '2027-01', '2026-12', '2026-11' ] );
		// Back on the month the server called closed: no further step, before any answer.
		expect( prev.disabled ).toBe( true );

		ok( calls[ 3 ], { html: NOVEMBER, prev_allowed: false, start_month: '2026-11' } );
		ok( calls[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );
		ok( calls[ 2 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

		expect( q( '.rvs-cal__btn[data-date="2026-11-10"]' ) ).not.toBeNull();
		expect( prev.disabled ).toBe( true );
	} );

	test( 'a_step_shows_loading_until_the_last_answer_only', () => {
		boot();
		const next = q( '[data-rvs-cal-nav="1"]' );

		expect( root().hasAttribute( 'data-rvs-cal-loading' ) ).toBe( false );
		click( next );
		click( next );
		expect( root().hasAttribute( 'data-rvs-cal-loading' ) ).toBe( true );

		ok( calls[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );
		expect( root().hasAttribute( 'data-rvs-cal-loading' ) ).toBe( true );
		expect( root().getAttribute( 'aria-busy' ) ).toBe( 'true' );

		ok( calls[ 1 ], { html: month( '2027-01', day( '2027-01-05' ) ), prev_allowed: true, start_month: '2027-01' } );
		expect( root().hasAttribute( 'data-rvs-cal-loading' ) ).toBe( false );
		expect( root().hasAttribute( 'aria-busy' ) ).toBe( false );
	} );

	test( 'the_load_time_refresh_shows_no_spinner', () => {
		document.body.innerHTML = page();
		calls.length = 0;
		window.mhmRentivaAvailabilityCalendar.init();

		expect( root().getAttribute( 'aria-busy' ) ).toBe( 'true' );
		expect( root().hasAttribute( 'data-rvs-cal-loading' ) ).toBe( false );
	} );

	test( 'the_server_month_wins_when_a_step_back_passes_an_unknown_floor', () => {
		boot();
		click( q( '[data-rvs-cal-nav="1"]' ) );
		ok( calls[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );
		const prev = q( '[data-rvs-cal-nav="-1"]' );
		expect( prev.disabled ).toBe( false );

		// The month rolled over while the page stood open: the server clamps.
		click( prev );
		ok( calls[ 1 ], { html: DECEMBER, prev_allowed: false, start_month: '2026-12' } );

		expect( prev.disabled ).toBe( true );
		expect( root().getAttribute( 'data-start-month' ) ).toBe( '2026-12' );
	} );

	test( 'a_failed_step_puts_the_way_back_where_the_screen_is', () => {
		boot();
		const prev = q( '[data-rvs-cal-nav="-1"]' );
		click( q( '[data-rvs-cal-nav="1"]' ) );
		expect( prev.disabled ).toBe( false );

		broken( calls[ 0 ] );

		expect( prev.disabled ).toBe( true );
		expect( root().hasAttribute( 'data-rvs-cal-loading' ) ).toBe( false );
	} );

	test( 'failed_month_step_stays_on_the_month_and_tells', () => {
		boot();
		click( q( '[data-rvs-cal-nav="1"]' ) );
		broken( calls[ 0 ] );

		expect( message() ).toBe( 'ERR' );
		expect( q( '.rvs-cal__btn[data-date="2026-11-10"]' ) ).not.toBeNull();

		click( q( '[data-rvs-cal-nav="1"]' ) );
		expect( calls[ 1 ].data.start_month ).toBe( '2026-12' );
	} );

	test( 'a_disabled_prev_hands_the_focus_to_next', () => {
		boot();
		const prev = q( '[data-rvs-cal-nav="-1"]' );
		prev.disabled = false;
		prev.focus();
		click( prev );
		ok( calls[ 0 ], { html: NOVEMBER, prev_allowed: false, start_month: '2026-11' } );

		expect( prev.disabled ).toBe( true );
		expect( document.activeElement ).toBe( q( '[data-rvs-cal-nav="1"]' ) );
	} );

	describe( 'vehicle change', () => {
		const INFO = { id: 9, title: 'New', image: 'new.jpg', price_html: '<span class="amount">₺200</span> per day', bookable: true };
		const choose = () => {
			click( q( '#switch' ) );
			click( q( '[data-rvs-cal-option="9"]' ) );
		};

		test( 'dialog_opens_on_the_shared_module_and_focus_returns_to_the_opener', () => {
			boot();
			const opener = q( '#switch' );
			const dlg = q( '[data-rvs-cal-dialog]' );
			opener.focus();
			click( opener );

			expect( opener.getAttribute( 'aria-haspopup' ) ).toBe( 'dialog' );
			expect( dlg.hidden ).toBe( false );
			expect( dlg.classList.contains( 'is-open' ) ).toBe( true );
			expect( dlg.getAttribute( 'role' ) ).toBe( 'dialog' );
			expect( dlg.getAttribute( 'aria-labelledby' ) ).toBe( 'dlg-title' );
			expect( document.activeElement ).toBe( q( '[data-rvs-cal-option="7"]' ) );

			click( q( '[data-rvs-dialog-close]' ) );

			expect( dlg.hidden ).toBe( true );
			expect( document.activeElement ).toBe( opener );
		} );

		test( 'clicking_outside_the_panel_closes_the_dialog', () => {
			boot();
			const dlg = q( '[data-rvs-cal-dialog]' );
			click( q( '#switch' ) );
			click( dlg );
			expect( dlg.hidden ).toBe( true );
		} );

		test( 'choosing_the_same_vehicle_only_closes', () => {
			boot();
			click( q( '#switch' ) );
			click( q( '[data-rvs-cal-option="7"]' ) );

			expect( calls ).toHaveLength( 0 );
			expect( q( '[data-rvs-cal-dialog]' ).hidden ).toBe( true );
		} );

		test( 'clears_the_selection_and_updates_card_and_months_together', () => {
			boot();
			pick( '2026-11-10' );
			pick( '2026-11-11' );
			const quote = calls[ 0 ];
			calls.length = 0;
			const opener = q( '#switch' );
			opener.focus();
			choose();

			expect( document.activeElement ).toBe( opener );
			expect( panel().hidden ).toBe( true );
			expect( cell( '2026-11-10' ).classList.contains( 'is-selected' ) ).toBe( false );
			expect( root().getAttribute( 'aria-busy' ) ).toBe( 'true' );
			expect( sent( 'mhmrentiva_get_vehicle_info' )[ 0 ].data.vehicle_id ).toBe( '9' );
			expect( sent( 'mhmrentiva_calendar_months' )[ 0 ].data.vehicle_id ).toBe( '9' );

			// Nothing changes on one answer alone.
			ok( sent( 'mhmrentiva_get_vehicle_info' )[ 0 ], INFO );
			expect( q( '[data-rvs-cal-name]' ).textContent ).toBe( 'Old' );
			ok( sent( 'mhmrentiva_calendar_months' )[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

			expect( q( '[data-rvs-cal-name]' ).textContent ).toBe( 'New' );
			expect( q( '[data-rvs-cal-image]' ).getAttribute( 'src' ) ).toBe( 'new.jpg' );
			expect( q( '[data-rvs-cal-image]' ).getAttribute( 'alt' ) ).toBe( 'New' );
			expect( q( '[data-rvs-cal-price] .amount' ).textContent ).toBe( '₺200' );
			expect( root().getAttribute( 'data-vehicle-id' ) ).toBe( '9' );
			expect( q( '[data-rvs-cal-option="9"]' ).getAttribute( 'aria-pressed' ) ).toBe( 'true' );
			expect( q( '[data-rvs-cal-option="7"]' ).getAttribute( 'aria-pressed' ) ).toBe( 'false' );
			expect( root().hasAttribute( 'aria-busy' ) ).toBe( false );

			// The old car's total, arriving late, is not shown for the new one.
			ok( quote, QUOTE );
			expect( panel().hidden ).toBe( true );
			expect( total().textContent ).toBe( '' );

			// Later requests are for the new car.
			calls.length = 0;
			click( q( '[data-rvs-cal-nav="1"]' ) );
			expect( calls[ 0 ].data.vehicle_id ).toBe( '9' );
		} );

		test( 'a_failed_switch_keeps_the_old_vehicle_and_tells', () => {
			boot();
			choose();
			refuse( sent( 'mhmrentiva_get_vehicle_info' )[ 0 ], 'Vehicle not found.' );
			ok( sent( 'mhmrentiva_calendar_months' )[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

			expect( message() ).toBe( 'Vehicle not found.' );
			expect( q( '[data-rvs-cal-name]' ).textContent ).toBe( 'Old' );
			expect( root().getAttribute( 'data-vehicle-id' ) ).toBe( '7' );
			expect( q( '.rvs-cal__btn[data-date="2026-11-10"]' ) ).not.toBeNull();
		} );

		test( 'a_vehicle_that_closed_meanwhile_is_not_switched_to', () => {
			boot();
			choose();
			ok( sent( 'mhmrentiva_get_vehicle_info' )[ 0 ], { ...INFO, bookable: false } );
			ok( sent( 'mhmrentiva_calendar_months' )[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

			expect( q( '[data-rvs-cal-name]' ).textContent ).toBe( 'Old' );
			expect( message() ).toBe( 'ERR' );
		} );

		test( 'the_old_calendar_takes_no_clicks_during_a_switch', () => {
			boot();
			choose();
			calls.length = 0;
			pick( '2026-11-10' );
			click( q( '[data-rvs-cal-nav="1"]' ) );

			expect( calls ).toHaveLength( 0 );
			expect( cell( '2026-11-10' ).classList.contains( 'is-selected' ) ).toBe( false );
		} );

		test( 'without_pricing_the_price_stays_away_after_a_switch', () => {
			boot( { pricing: '0' } );
			choose();
			ok( sent( 'mhmrentiva_get_vehicle_info' )[ 0 ], INFO );
			ok( sent( 'mhmrentiva_calendar_months' )[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );

			expect( root().textContent ).not.toContain( '₺200' );
			expect( q( '[data-rvs-cal-name]' ).textContent ).toBe( 'New' );
		} );

		test( 'a_vehicle_without_image_removes_it_and_one_with_image_adds_it', () => {
			boot();
			choose();
			ok( sent( 'mhmrentiva_get_vehicle_info' )[ 0 ], { ...INFO, image: '' } );
			ok( sent( 'mhmrentiva_calendar_months' )[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );
			expect( q( '[data-rvs-cal-image]' ) ).toBeNull();

			calls.length = 0;
			click( q( '#switch' ) );
			click( q( '[data-rvs-cal-option="7"]' ) );
			ok( sent( 'mhmrentiva_get_vehicle_info' )[ 0 ], { ...INFO, id: 7, image: 'back.jpg' } );
			ok( sent( 'mhmrentiva_calendar_months' )[ 0 ], { html: DECEMBER, prev_allowed: true, start_month: '2026-12' } );
			expect( q( '.rvs-availability__vehicle' ).firstElementChild.getAttribute( 'src' ) ).toBe( 'back.jpg' );
		} );
	} );

	test( 'the_script_builds_no_url_and_formats_no_date', () => {
		const source = require( 'fs' ).readFileSync( require( 'path' ).join( __dirname, '../../assets/js/frontend/availability-calendar.js' ), 'utf8' );

		[ 'toLocaleDateString', 'new URL', 'searchParams', 'new Date', '1400', 'mhmrentiva_availability_unified', 'bookingPageUrl', 'MHMRentivaToast' ].forEach( ( banned ) => {
			expect( source ).not.toContain( banned );
		} );
	} );
} );
