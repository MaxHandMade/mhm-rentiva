/**
 * @jest-environment jsdom
 */

/**
 * Owner request (2026-11-02): when the car is taken for the chosen window, the
 * vehicle page's booking card shows the free vehicles the server suggests under
 * the red message (the quote's `alternatives_html`, the page's own partial).
 * Without alternatives the card shows the message alone; a window change marks
 * the old suggestions at once.
 */
describe( 'rvs vehicle page: alternative vehicles', () => {
	let jQuery;

	const page = ( withBox = true ) => `
		<div class="rvs-shell">
			<div class="rvs-vd" data-rvs-vehicle="7">
				<div data-rvs-vd-months><div class="rvs-cal" data-rvs-calendar data-year="2027" data-month="7"></div></div>
				<aside class="rvs-vd__booking">
					<span data-rvs-vd-total>T0</span><span data-rvs-vd-days></span>
					<form data-rvs-vd-window>
						<input name="pickup_date" value="2027-07-12" data-rvs-pickup-date>
						<select name="pickup_time" data-rvs-pickup-time><option value="10:00" selected>10:00</option></select>
						<input name="return_date" value="2027-07-15" data-rvs-return-date>
					</form>
					<div data-rvs-vd-breakdown></div>
					<p data-rvs-vd-message role="status" hidden></p>
					${ withBox ? '<div class="rvs-vd__alternatives" data-rvs-vd-alternatives role="status"></div>' : '' }
					<a href="/book?old" data-rvs-vd-continue>Continue</a>
				</aside>
			</div>
		</div>`;

	const boot = async ( html ) => {
		jest.resetModules();
		jest.useRealTimers();
		document.body.innerHTML = html || page();
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;
		window.mhmRentivaVehicle = {
			ajax_url: '/wp-admin/admin-ajax.php',
			nonce: 'n1',
			i18n: {
				error: 'Something went wrong',
				days_one: '%s day total',
				days_many: '%s days total',
			},
		};
		jQuery.ajax = jest.fn();
		require( '../../assets/js/frontend/rvs-vehicle.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
		jest.useFakeTimers();
	};

	const answer = ( call, data ) => {
		const options = jQuery.ajax.mock.calls[ call ][ 0 ];
		options.success( { success: true, data } );
		if ( options.complete ) {
			options.complete();
		}
	};

	const requote = ( call, data ) => {
		document.querySelector( '[data-rvs-pickup-date]' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
		jest.advanceTimersByTime( 300 );
		answer( call, data );
	};

	// The server's partial (partials/vehicle-details/alternatives.php) for ids.
	const partial = ( ids ) => ids.length
		? '<p class="rvs-vd__alternatives-title">Alternative Vehicles</p><ul class="rvs-vd__alternatives-list">' +
			ids.map( ( id ) => '<li class="rvs-vd__alt"><div class="rvs-vd__alt-body"><p class="rvs-vd__alt-title" id="rvs-vd-alt-' + id + '">Car ' + id + '</p></div>' +
				'<a class="rvs-btn rvs-btn--ghost rvs-vd__alt-link" href="/vehicle/' + id + '/" aria-describedby="rvs-vd-alt-' + id + '">Select This Vehicle</a></li>' ).join( '' ) +
			'</ul>'
		: '';

	const taken = ( ids ) => ( {
		priced: true,
		days: 3,
		total_html: 'T1',
		breakdown_html: '',
		continue_url: '/book?new',
		available: false,
		message: 'This vehicle is already booked for the selected dates.',
		months_html: '',
		alternatives: ids.map( ( id ) => ( { id } ) ),
		alternatives_html: partial( ids ),
	} );

	afterEach( () => {
		jest.useRealTimers();
		delete global.jQuery;
		delete global.$;
		delete window.mhmRentivaVehicle;
	} );

	test( 'a taken car shows the message and the suggestions the server drew under it', async () => {
		await boot();
		requote( 0, taken( [ 11, 12 ] ) );

		const message = document.querySelector( '[data-rvs-vd-message]' );
		expect( message.hidden ).toBe( false );
		expect( message.textContent ).toBe( 'This vehicle is already booked for the selected dates.' );

		const box = document.querySelector( '[data-rvs-vd-alternatives]' );
		expect( box.getAttribute( 'role' ) ).toBe( 'status' );
		expect( box.textContent ).toContain( 'Alternative Vehicles' );
		const items = box.querySelectorAll( '.rvs-vd__alt' );
		expect( items ).toHaveLength( 2 );
		items.forEach( ( item, index ) => {
			const links = item.querySelectorAll( 'a' );
			expect( links ).toHaveLength( 1 );
			expect( links[ 0 ].getAttribute( 'href' ) ).toBe( '/vehicle/' + ( 11 + index ) + '/' );
			expect( links[ 0 ].textContent ).toBe( 'Select This Vehicle' );
		} );
		expect( box.hasAttribute( 'aria-busy' ) ).toBe( false );
		expect( document.querySelectorAll( '[data-rvs-vd-alternatives]' ) ).toHaveLength( 1 );
	} );

	test( 'no alternatives: the message alone, nothing rendered', async () => {
		await boot();
		requote( 0, taken( [] ) );

		expect( document.querySelector( '[data-rvs-vd-message]' ).hidden ).toBe( false );
		const box = document.querySelector( '[data-rvs-vd-alternatives]' );
		expect( box.children ).toHaveLength( 0 );
		expect( box.innerHTML ).toBe( '' );
	} );

	test( 'a free window clears earlier suggestions, even if the answer carried some', async () => {
		await boot();
		requote( 0, taken( [ 11 ] ) );
		expect( document.querySelectorAll( '.rvs-vd__alt' ) ).toHaveLength( 1 );

		requote( 1, Object.assign( taken( [ 11 ] ), { available: true, message: '' } ) );
		expect( document.querySelector( '[data-rvs-vd-alternatives]' ).innerHTML ).toBe( '' );
	} );

	test( 'a failed quote clears the suggestions', async () => {
		await boot();
		requote( 0, taken( [ 11 ] ) );
		document.querySelector( '[data-rvs-pickup-date]' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
		jest.advanceTimersByTime( 300 );
		const options = jQuery.ajax.mock.calls[ 1 ][ 0 ];
		options.error();
		options.complete();

		expect( document.querySelector( '[data-rvs-vd-alternatives]' ).innerHTML ).toBe( '' );
	} );

	// Coordinator ruling (2026-11-02): the suggestions on screen belong to the
	// window just left -- marked the moment the window changes, not when the
	// answer lands (as PR #155 does for the booking form).
	test( 'a window change dims the old suggestions and holds their links at once', async () => {
		await boot( page().replace( 'role="status"></div>', 'role="status">' + partial( [ 11, 12 ] ) + '</div>' ) );
		const box = document.querySelector( '[data-rvs-vd-alternatives]' );
		expect( box.hasAttribute( 'aria-busy' ) ).toBe( false );

		document.querySelector( '[data-rvs-pickup-date]' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );

		// Before the debounce, before any request.
		expect( jQuery.ajax ).not.toHaveBeenCalled();
		expect( box.getAttribute( 'aria-busy' ) ).toBe( 'true' );
		box.querySelectorAll( 'a' ).forEach( ( a ) => {
			expect( a.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
			expect( a.getAttribute( 'tabindex' ) ).toBe( '-1' );
		} );

		jest.advanceTimersByTime( 300 );
		answer( 0, taken( [ 13 ] ) );
		expect( box.hasAttribute( 'aria-busy' ) ).toBe( false );
		const links = box.querySelectorAll( 'a' );
		expect( links ).toHaveLength( 1 );
		expect( links[ 0 ].getAttribute( 'href' ) ).toBe( '/vehicle/13/' );
		expect( links[ 0 ].hasAttribute( 'aria-disabled' ) ).toBe( false );
	} );

	test( 'a cached page without the container still gets the suggestions right after the message', async () => {
		await boot( page( false ) );
		requote( 0, taken( [ 11 ] ) );

		const box = document.querySelector( '[data-rvs-vd-message]' ).nextElementSibling;
		expect( box.hasAttribute( 'data-rvs-vd-alternatives' ) ).toBe( true );
		expect( box.querySelectorAll( '.rvs-vd__alt' ) ).toHaveLength( 1 );
	} );
} );
