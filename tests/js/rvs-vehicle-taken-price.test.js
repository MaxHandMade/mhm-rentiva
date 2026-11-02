/**
 * @jest-environment jsdom
 */

/**
 * Owner decision (2026-11-03): while the car is taken for the window (the
 * quote's `taken`), the booking card and the phone bar show the car's daily
 * price instead of the period total, and the total block (total, deposit,
 * due at pickup, the breakdown link) is hidden. A free window brings it all
 * back. Fable B3: a failed answer for a window the visitor already left does
 * not touch the card.
 */
describe( 'rvs vehicle page: price while the car is taken', () => {
	let jQuery;

	const page = () => `
		<div class="rvs-shell">
			<div class="rvs-vd" data-rvs-vehicle="7">
				<div data-rvs-vd-months><div class="rvs-cal" data-rvs-calendar data-year="2027" data-month="7"></div></div>
				<aside class="rvs-vd__booking">
					<div class="rvs-vd__card">
						<div class="rvs-vd__total" data-rvs-vd-priced>
							<span data-rvs-vd-total>T0</span><span data-rvs-vd-days>3 days total</span>
						</div>
						<div class="rvs-vd__total" data-rvs-vd-daily hidden>
							<span data-rvs-vd-daily-amount></span><span>/day</span>
						</div>
						<form data-rvs-vd-window>
							<input name="pickup_date" value="2027-07-12" data-rvs-pickup-date>
							<select name="pickup_time" data-rvs-pickup-time><option value="10:00" selected>10:00</option></select>
							<input name="return_date" value="2027-07-15" data-rvs-return-date>
						</form>
						<div data-rvs-vd-breakdown id="card-bd"><div class="rvs-breakdown">B0</div></div>
						<p data-rvs-vd-message role="status" hidden></p>
						<div class="rvs-vd__alternatives" data-rvs-vd-alternatives role="status"><p>OLD</p></div>
						<a href="/book?old" data-rvs-vd-continue>Continue</a>
					</div>
					<div class="rvs-vd__bar">
						<button type="button" id="bar-total" data-rvs-vd-breakdown-open data-rvs-vd-priced>
							<strong data-rvs-vd-total>T0</strong><span><span data-rvs-vd-days>3 days total</span> · breakdown</span>
						</button>
						<div id="bar-daily" data-rvs-vd-daily hidden><strong data-rvs-vd-daily-amount></strong> <span>/day</span></div>
						<a href="/book?old" data-rvs-vd-continue>Continue</a>
					</div>
					<div class="rvs-dialog" data-rvs-vd-breakdown-dialog hidden>
						<div class="rvs-dialog__body" data-rvs-vd-breakdown id="sheet-bd"><div class="rvs-breakdown">B0</div></div>
					</div>
				</aside>
			</div>
		</div>`;

	const boot = async () => {
		jest.resetModules();
		jest.useRealTimers();
		document.body.innerHTML = page();
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;
		window.mhmRentivaVehicle = {
			ajax_url: '/wp-admin/admin-ajax.php',
			nonce: 'n1',
			i18n: { error: 'Something went wrong', days_one: '%s day total', days_many: '%s days total' },
		};
		jQuery.ajax = jest.fn();
		require( '../../assets/js/frontend/rvs-vehicle.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
		jest.useFakeTimers();
	};

	const change = () => {
		document.querySelector( '[data-rvs-pickup-date]' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
		jest.advanceTimersByTime( 300 );
	};

	const answer = ( call, data ) => {
		const options = jQuery.ajax.mock.calls[ call ][ 0 ];
		options.success( { success: true, data } );
		options.complete();
	};

	// The server's answers (VehicleDetails::ajax_quote).
	const taken = {
		priced: true,
		taken: true,
		days: 0,
		total_html: '',
		breakdown_html: '',
		daily_html: '<span class="amount">$1,750.00</span>',
		continue_url: '/book?new',
		available: false,
		message: 'This vehicle is already booked for the selected dates.',
		months_html: '',
		alternatives_html: '<p>ALT</p>',
	};
	const free = {
		priced: true,
		taken: false,
		days: 3,
		total_html: '<span class="amount">$5,275.00</span>',
		breakdown_html: '<div class="rvs-breakdown"><div class="rvs-breakdown__total">Total</div></div>',
		daily_html: '',
		continue_url: '/book?free',
		available: true,
		message: '',
		months_html: '',
		alternatives_html: '',
	};

	const $ = ( sel ) => document.querySelector( sel );
	const all = ( sel ) => Array.from( document.querySelectorAll( sel ) );

	afterEach( () => {
		jest.useRealTimers();
		delete global.jQuery;
		delete global.$;
		delete window.mhmRentivaVehicle;
	} );

	test( 'a taken window shows the daily price in the card and the bar, and hides the total block', async () => {
		await boot();
		change();
		answer( 0, taken );

		all( '[data-rvs-vd-priced]' ).forEach( ( el ) => expect( el.hidden ).toBe( true ) );
		expect( $( '#bar-total' ).hidden ).toBe( true );
		all( '[data-rvs-vd-daily]' ).forEach( ( el ) => {
			expect( el.hidden ).toBe( false );
			expect( el.querySelector( '[data-rvs-vd-daily-amount]' ).innerHTML ).toBe( '<span class="amount">$1,750.00</span>' );
			expect( el.textContent ).toContain( '/day' );
		} );
		expect( $( '#bar-daily' ).hidden ).toBe( false );
		// No total, deposit, due-at-pickup or breakdown link anywhere.
		[ '#card-bd', '#sheet-bd' ].forEach( ( sel ) => {
			expect( $( sel ).hidden ).toBe( true );
			expect( $( sel ).innerHTML ).toBe( '' );
		} );
		expect( $( '[data-rvs-vd-message]' ).hidden ).toBe( false );
	} );

	test( 'a free window again brings the total, its rows and the "N days total" label back', async () => {
		await boot();
		change();
		answer( 0, taken );
		change();
		answer( 1, free );

		all( '[data-rvs-vd-priced]' ).forEach( ( el ) => expect( el.hidden ).toBe( false ) );
		all( '[data-rvs-vd-total]' ).forEach( ( el ) => expect( el.innerHTML ).toBe( '<span class="amount">$5,275.00</span>' ) );
		all( '[data-rvs-vd-days]' ).forEach( ( el ) => expect( el.textContent ).toBe( '3 days total' ) );
		all( '[data-rvs-vd-daily]' ).forEach( ( el ) => {
			expect( el.hidden ).toBe( true );
			expect( el.querySelector( '[data-rvs-vd-daily-amount]' ).innerHTML ).toBe( '' );
		} );
		[ '#card-bd', '#sheet-bd' ].forEach( ( sel ) => {
			expect( $( sel ).hidden ).toBe( false );
			expect( $( sel ).querySelector( '.rvs-breakdown__total' ) ).not.toBeNull();
		} );
		expect( $( '[data-rvs-vd-message]' ).hidden ).toBe( true );
	} );

	test( 'a rule refusal (not taken) keeps the total', async () => {
		await boot();
		change();
		answer( 0, Object.assign( {}, free, { available: false, message: 'The pickup date has passed.' } ) );

		all( '[data-rvs-vd-priced]' ).forEach( ( el ) => expect( el.hidden ).toBe( false ) );
		all( '[data-rvs-vd-daily]' ).forEach( ( el ) => expect( el.hidden ).toBe( true ) );
		expect( $( '#card-bd' ).hidden ).toBe( false );
	} );

	// Fable B3: like the success branch, a failure for a window the visitor
	// already left writes nothing -- the queued request answers for the new one.
	test( 'a failed answer while a newer change is queued leaves the message and the suggestions alone', async () => {
		await boot();
		change(); // Request 0 in flight.
		change(); // Queued behind it.
		expect( jQuery.ajax ).toHaveBeenCalledTimes( 1 );

		const options = jQuery.ajax.mock.calls[ 0 ][ 0 ];
		options.error();
		expect( $( '[data-rvs-vd-message]' ).hidden ).toBe( true );
		expect( $( '[data-rvs-vd-message]' ).textContent ).toBe( '' );
		expect( $( '[data-rvs-vd-alternatives]' ).innerHTML ).toBe( '<p>OLD</p>' );

		options.complete();
		expect( jQuery.ajax ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'a failed answer with nothing queued still shows the error and clears the suggestions', async () => {
		await boot();
		change();
		const options = jQuery.ajax.mock.calls[ 0 ][ 0 ];
		options.error();
		options.complete();

		expect( $( '[data-rvs-vd-message]' ).hidden ).toBe( false );
		expect( $( '[data-rvs-vd-message]' ).textContent ).toBe( 'Something went wrong' );
		expect( $( '[data-rvs-vd-alternatives]' ).innerHTML ).toBe( '' );
	} );
} );
