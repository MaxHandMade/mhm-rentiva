/**
 * @jest-environment jsdom
 */

/**
 * Owner request (2026-11-02): when the car is taken for the chosen window, the
 * vehicle page's booking card suggests up to two free vehicles under the red
 * message (the quote's `alternatives`), each linking to its own page with the
 * same dates. Without alternatives the card shows the message alone.
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
				alternatives: 'Alternative Vehicles',
				select_vehicle: 'Select This Vehicle',
				per_day: '/day',
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

	const alt = ( id, extra ) => Object.assign( {
		id,
		title: 'Car <b>' + id + '</b>',
		image: '/img/' + id + '.jpg',
		specs: '5 seats · Automatic · Diesel',
		price_html: '<span class="amount">₺900</span>',
		permalink: '/vehicle/' + id + '/?mhmrentiva_pickup_date=2027-07-12',
	}, extra || {} );

	const taken = ( alternatives ) => ( {
		priced: true,
		days: 3,
		total_html: 'T1',
		breakdown_html: '',
		continue_url: '/book?new',
		available: false,
		message: 'This vehicle is already booked for the selected dates.',
		months_html: '',
		alternatives,
	} );

	afterEach( () => {
		jest.useRealTimers();
		delete global.jQuery;
		delete global.$;
		delete window.mhmRentivaVehicle;
	} );

	test( 'a taken car shows the message and at most two suggestions, one link each', async () => {
		await boot();
		requote( 0, taken( [ alt( 11 ), alt( 12 ), alt( 13 ) ] ) );

		const message = document.querySelector( '[data-rvs-vd-message]' );
		expect( message.hidden ).toBe( false );
		expect( message.textContent ).toBe( 'This vehicle is already booked for the selected dates.' );

		const box = document.querySelector( '[data-rvs-vd-alternatives]' );
		expect( box.getAttribute( 'role' ) ).toBe( 'status' );
		expect( box.textContent ).toContain( 'Alternative Vehicles' );

		const items = box.querySelectorAll( '.rvs-vd__alt' );
		expect( items ).toHaveLength( 2 );
		items.forEach( ( item, index ) => {
			const id = 11 + index;
			const links = item.querySelectorAll( 'a' );
			expect( links ).toHaveLength( 1 );
			expect( links[ 0 ].getAttribute( 'href' ) ).toBe( '/vehicle/' + id + '/?mhmrentiva_pickup_date=2027-07-12' );
			expect( links[ 0 ].textContent ).toBe( 'Select This Vehicle' );
			// The link's name says which car: its title describes it.
			const title = item.querySelector( '.rvs-vd__alt-title' );
			expect( title.textContent ).toBe( 'Car <b>' + id + '</b>' );
			expect( title.querySelector( 'b' ) ).toBeNull();
			expect( links[ 0 ].getAttribute( 'aria-describedby' ) ).toBe( title.id );
			expect( item.querySelector( 'img' ).getAttribute( 'src' ) ).toBe( '/img/' + id + '.jpg' );
			expect( item.querySelector( '.rvs-vd__alt-specs' ).textContent ).toBe( '5 seats · Automatic · Diesel' );
			const price = item.querySelector( '.rvs-vd__alt-price' );
			expect( price.querySelector( '.amount' ).textContent ).toBe( '₺900' );
			expect( price.textContent ).toContain( '/day' );
		} );
		expect( document.querySelectorAll( '[data-rvs-vd-alternatives]' ) ).toHaveLength( 1 );
	} );

	test( 'no alternatives: the message alone, nothing rendered', async () => {
		await boot();
		requote( 0, taken( [] ) );

		expect( document.querySelector( '[data-rvs-vd-message]' ).hidden ).toBe( false );
		const box = document.querySelector( '[data-rvs-vd-alternatives]' );
		expect( box.children ).toHaveLength( 0 );
		expect( box.textContent ).toBe( '' );
	} );

	test( 'a free window clears earlier suggestions', async () => {
		await boot();
		requote( 0, taken( [ alt( 11 ) ] ) );
		expect( document.querySelectorAll( '.rvs-vd__alt' ) ).toHaveLength( 1 );

		requote( 1, Object.assign( taken( [ alt( 11 ) ] ), { available: true, message: '' } ) );
		expect( document.querySelectorAll( '.rvs-vd__alt' ) ).toHaveLength( 0 );
	} );

	test( 'a suggestion without a link is left out, never drawn with a dead link', async () => {
		await boot();
		requote( 0, taken( [ alt( 11, { permalink: '', specs: '' } ) ] ) );

		expect( document.querySelectorAll( '.rvs-vd__alt' ) ).toHaveLength( 0 );
	} );

	test( 'a cached page without the container still gets the suggestions right after the message', async () => {
		await boot( page( false ) );
		requote( 0, taken( [ alt( 11 ) ] ) );

		const message = document.querySelector( '[data-rvs-vd-message]' );
		const box = message.nextElementSibling;
		expect( box.hasAttribute( 'data-rvs-vd-alternatives' ) ).toBe( true );
		expect( box.querySelectorAll( '.rvs-vd__alt' ) ).toHaveLength( 1 );
	} );
} );
