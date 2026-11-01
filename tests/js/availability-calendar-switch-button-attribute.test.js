/**
 * @jest-environment jsdom
 */

/**
 * A vehicle title comes from the server and may hold quotes or markup (Pro:
 * vendors set it). Changing the vehicle writes it as text and as an attribute
 * value, so it stays data: no element or attribute can come out of it.
 */
describe( 'availability calendar: a switched vehicle title stays data', () => {
	let $;
	let calls;

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		calls = [];
		$.ajax = jest.fn( ( options ) => {
			calls.push( options );
		} );
		window.mhmRentivaAvailability = { ajax_url: '/ajax', nonce: 'n', strings: {} };
		require( '../../assets/js/frontend/rvs-dialog.js' );
		require( '../../assets/js/frontend/availability-calendar.js' );
	} );

	test( 'a_title_with_quotes_and_markup_survives_as_data', () => {
		const title = 'Driver\'s x\' autofocus onfocus=\'window.__rvInjected=1\' x=\'<img src=x onerror=alert(1)>"';
		document.body.innerHTML = `
			<section data-rvs-availability data-vehicle-id="7" data-start-month="2026-11" data-months-to-show="1" data-week-start="1" data-show-pricing="1" data-show-booking-button="1">
				<div class="rvs-availability__vehicle"><img data-rvs-cal-image src="a.jpg" alt=""><p data-rvs-cal-name>Old</p></div>
				<button type="button" data-rvs-cal-switch>Change</button>
				<div data-rvs-cal-months></div>
				<div class="rvs-dialog" data-rvs-cal-dialog hidden>
					<button type="button" data-rvs-cal-option="8" aria-pressed="false">Other</button>
				</div>
			</section>`;
		window.mhmRentivaAvailabilityCalendar.init();
		const answer = ( call, data ) => {
			call.success( { success: true, data } );
			if ( call.complete ) {
				call.complete();
			}
		};
		answer( calls[ 0 ], { html: '', prev_allowed: false, start_month: '2026-11' } );
		calls.length = 0;

		const dlgOpener = document.querySelector( '[data-rvs-cal-switch]' );
		dlgOpener.click();
		document.querySelector( '[data-rvs-cal-option="8"]' ).click();

		answer( calls[ 0 ], { id: 8, title, image: 'b.jpg', price_html: '', bookable: true } );
		answer( calls[ 1 ], { html: '', prev_allowed: false, start_month: '2026-11' } );

		const name = document.querySelector( '[data-rvs-cal-name]' );
		const image = document.querySelector( '[data-rvs-cal-image]' );

		expect( name.textContent ).toBe( title );
		expect( name.children ).toHaveLength( 0 );
		expect( image.getAttribute( 'alt' ) ).toBe( title );
		expect( image.hasAttribute( 'onfocus' ) ).toBe( false );
		expect( image.hasAttribute( 'autofocus' ) ).toBe( false );
		expect( document.querySelector( '[onerror]' ) ).toBeNull();
	} );
} );
