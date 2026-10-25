/**
 * @jest-environment jsdom
 */

/**
 * Aşama 2 PR B: the vehicle page script -- month navigation and the live
 * booking-card quote replace the page's parts with the server's partials;
 * the gallery viewer and the breakdown sheet are shared rvs dialogs.
 */
describe( 'rvs vehicle page', () => {
	let jQuery;

	const page = ( id ) => `
		<div class="rvs-shell">
			<div class="rvs-vd" id="${ id }" data-rvs-vehicle="${ id === 'vd1' ? 7 : 8 }">
				<section class="rvs-vd__gallery" data-rvs-gallery='[{"src":"/a.jpg","alt":"A"},{"src":"/b.jpg","alt":"B"},{"src":"/c.jpg","alt":"C"}]'>
					<button type="button" id="${ id }-p0" data-rvs-gallery-open="0">0</button>
					<button type="button" id="${ id }-p1" data-rvs-gallery-open="1">1</button>
					<div class="rvs-dialog" data-rvs-gallery-viewer hidden>
						<div class="rvs-dialog__panel">
							<h2 id="${ id }-viewer-title">Car <span data-rvs-gallery-count></span></h2>
							<button type="button" id="${ id }-close" data-rvs-dialog-close>x</button>
							<button type="button" data-rvs-gallery-step="-1">prev</button>
							<img data-rvs-gallery-img src="" alt="">
							<button type="button" data-rvs-gallery-step="1">next</button>
						</div>
					</div>
				</section>
				<button type="button" data-rvs-month-nav="-1">prev</button>
				<button type="button" data-rvs-month-nav="1">next</button>
				<div data-rvs-vd-months>
					<div class="rvs-cal" data-rvs-calendar data-year="2027" data-month="7"></div>
					<div class="rvs-cal" data-rvs-calendar data-year="2027" data-month="8"></div>
				</div>
				<aside class="rvs-vd__booking">
					<span data-rvs-vd-total>T0</span><span data-rvs-vd-days>3 days total</span>
					<form data-rvs-vd-window>
						<input name="pickup_date" value="2027-07-12" data-rvs-pickup-date>
						<select name="pickup_time" data-rvs-pickup-time><option value="10:00" selected>10:00</option><option value="12:30">12:30</option></select>
						<input name="return_date" value="2027-07-15" data-rvs-return-date>
						<span data-rvs-return-time-display>10:00</span>
					</form>
					<div data-rvs-vd-breakdown>B0</div>
					<p data-rvs-vd-message role="status" hidden></p>
					<a href="/book?old" data-rvs-vd-continue>Continue</a>
					<div class="rvs-vd__bar">
						<button type="button" id="${ id }-sheet-open" data-rvs-vd-breakdown-open><strong data-rvs-vd-total>T0</strong></button>
						<a href="/book?old" data-rvs-vd-continue>Continue</a>
					</div>
					<div class="rvs-dialog" data-rvs-vd-breakdown-dialog hidden>
						<div class="rvs-dialog__panel">
							<button type="button" data-rvs-dialog-close>x</button>
							<div data-rvs-vd-breakdown><details><summary>S</summary></details></div>
						</div>
					</div>
				</aside>
			</div>
		</div>`;

	const boot = async ( html ) => {
		jest.resetModules();
		jest.useRealTimers();
		document.body.innerHTML = html || page( 'vd1' );
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
		delete window.mhmRentivaRvsDialog;
		require( '../../assets/js/frontend/rvs-dialog.js' );
		require( '../../assets/js/frontend/rvs-vehicle.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
		jest.useFakeTimers();
	};

	const answer = ( call, data, ok = true ) => {
		const options = jQuery.ajax.mock.calls[ call ][ 0 ];
		if ( ok ) {
			options.success( { success: true, data } );
		} else {
			options.error();
		}
		if ( options.complete ) {
			options.complete();
		}
	};

	const quote = {
		days: 4,
		total_html: '<b>T1</b>',
		breakdown_html: '<div class="new">B1</div>',
		continue_url: '/book?new',
		available: true,
		message: '',
		months_html: '<div class="rvs-cal" data-rvs-calendar data-year="2027" data-month="7">M1</div>',
	};

	const change = ( el ) => el.dispatchEvent( new Event( 'change', { bubbles: true } ) );

	afterEach( () => {
		jest.useRealTimers();
		const dialog = window.mhmRentivaRvsDialog;
		if ( dialog && dialog.current() ) {
			dialog.close( dialog.current() );
		}
		delete global.jQuery;
		delete global.$;
		delete window.mhmRentivaVehicle;
	} );

	test( 'month nav posts vehicle, year, month and swaps the months', async () => {
		await boot();
		document.querySelector( '[data-rvs-month-nav="1"]' ).click();

		const data = jQuery.ajax.mock.calls[ 0 ][ 0 ].data;
		expect( data.action ).toBe( 'mhmrentiva_vd_months' );
		expect( data.vehicle_id ).toBe( '7' );
		expect( data.year ).toBe( 2027 );
		expect( data.month ).toBe( 8 );
		expect( data.nonce ).toBe( 'n1' );

		answer( 0, { html: '<div class="rvs-cal" data-rvs-calendar data-year="2027" data-month="8">AUG</div>' } );
		expect( document.querySelector( '[data-rvs-vd-months]' ).textContent ).toContain( 'AUG' );

		document.querySelector( '[data-rvs-month-nav="-1"]' ).click();
		expect( jQuery.ajax.mock.calls[ 1 ][ 0 ].data.month ).toBe( 7 );
	} );

	test( 'month nav wraps the year', async () => {
		await boot( page( 'vd1' ).replace( 'data-year="2027" data-month="7"', 'data-year="2027" data-month="12"' ) );
		document.querySelector( '[data-rvs-month-nav="1"]' ).click();

		const data = jQuery.ajax.mock.calls[ 0 ][ 0 ].data;
		expect( data.year ).toBe( 2028 );
		expect( data.month ).toBe( 1 );
	} );

	test( 'window change posts the window and updates total, breakdown and continue link', async () => {
		await boot();
		const time = document.querySelector( '[data-rvs-pickup-time]' );
		time.value = '12:30';
		change( time );

		expect( document.querySelector( '[data-rvs-return-time-display]' ).textContent ).toBe( '12:30' );
		expect( jQuery.ajax ).not.toHaveBeenCalled();
		jest.advanceTimersByTime( 300 );

		const data = jQuery.ajax.mock.calls[ 0 ][ 0 ].data;
		expect( data.action ).toBe( 'mhmrentiva_vd_quote' );
		expect( data.pickup_date ).toBe( '2027-07-12' );
		expect( data.pickup_time ).toBe( '12:30' );
		expect( data.return_date ).toBe( '2027-07-15' );

		answer( 0, quote );
		document.querySelectorAll( '[data-rvs-vd-total]' ).forEach( ( el ) => expect( el.innerHTML ).toBe( '<b>T1</b>' ) );
		document.querySelectorAll( '[data-rvs-vd-continue]' ).forEach( ( el ) => {
			expect( el.getAttribute( 'href' ) ).toBe( '/book?new' );
			expect( el.hasAttribute( 'aria-disabled' ) ).toBe( false );
		} );
		expect( document.querySelector( '[data-rvs-vd-days]' ).textContent ).toBe( '4 days total' );
		expect( document.querySelectorAll( '[data-rvs-vd-breakdown] .new' ) ).toHaveLength( 2 );
		expect( document.querySelector( '[data-rvs-vd-months]' ).textContent ).toContain( 'M1' );
	} );

	test( 'unavailable window disables continue and announces the message', async () => {
		await boot();
		change( document.querySelector( '[data-rvs-pickup-date]' ) );
		jest.advanceTimersByTime( 300 );
		answer( 0, Object.assign( {}, quote, { available: false, message: 'Booked on those dates.' } ) );

		document.querySelectorAll( '[data-rvs-vd-continue]' ).forEach( ( el ) => {
			expect( el.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
			expect( el.getAttribute( 'tabindex' ) ).toBe( '-1' );
		} );
		const message = document.querySelector( '[data-rvs-vd-message]' );
		expect( message.hidden ).toBe( false );
		expect( message.textContent ).toBe( 'Booked on those dates.' );

		change( document.querySelector( '[data-rvs-pickup-date]' ) );
		jest.advanceTimersByTime( 300 );
		answer( 1, quote );
		expect( message.hidden ).toBe( true );
		expect( document.querySelector( '[data-rvs-vd-continue]' ).hasAttribute( 'tabindex' ) ).toBe( false );
	} );

	test( 'continue is disabled while a quote is pending and stays disabled on error', async () => {
		await boot();
		change( document.querySelector( '[data-rvs-pickup-date]' ) );

		document.querySelectorAll( '[data-rvs-vd-continue]' ).forEach( ( el ) => expect( el.getAttribute( 'aria-disabled' ) ).toBe( 'true' ) );
		jest.advanceTimersByTime( 300 );
		answer( 0, null, false );
		document.querySelectorAll( '[data-rvs-vd-continue]' ).forEach( ( el ) => {
			expect( el.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
			expect( el.getAttribute( 'tabindex' ) ).toBe( '-1' );
		} );
	} );

	test( 'the quote writes the server window back into the inputs', async () => {
		await boot();
		change( document.querySelector( '[data-rvs-pickup-date]' ) );
		jest.advanceTimersByTime( 300 );
		answer( 0, Object.assign( {}, quote, { window: { pickup_date: '2027-07-13', pickup_time: '12:30', return_date: '2027-07-16' } } ) );

		expect( document.querySelector( '[data-rvs-pickup-date]' ).value ).toBe( '2027-07-13' );
		expect( document.querySelector( '[data-rvs-return-date]' ).value ).toBe( '2027-07-16' );
		expect( document.querySelector( '[data-rvs-pickup-time]' ).value ).toBe( '12:30' );
		expect( document.querySelector( '[data-rvs-return-time-display]' ).textContent ).toBe( '12:30' );
	} );

	test( 'requests queue so the last change wins', async () => {
		await boot();
		const date = document.querySelector( '[data-rvs-return-date]' );
		change( date );
		jest.advanceTimersByTime( 300 );
		date.value = '2027-07-20';
		change( date );
		jest.advanceTimersByTime( 300 );
		expect( jQuery.ajax ).toHaveBeenCalledTimes( 1 );

		// The first answer echoes its own (older) window: it must not overwrite
		// the visitor's newer choice before the queued request reads the fields.
		answer( 0, Object.assign( {}, quote, { window: { pickup_date: '2027-07-12', pickup_time: '10:00', return_date: '2027-07-15' } } ) );
		expect( jQuery.ajax ).toHaveBeenCalledTimes( 2 );
		expect( jQuery.ajax.mock.calls[ 1 ][ 0 ].data.return_date ).toBe( '2027-07-20' );
		expect( date.value ).toBe( '2027-07-20' );
	} );

	test( 'the window form does not submit', async () => {
		await boot();
		const event = new Event( 'submit', { bubbles: true, cancelable: true } );
		document.querySelector( '[data-rvs-vd-window]' ).dispatchEvent( event );
		expect( event.defaultPrevented ).toBe( true );
	} );

	test( 'gallery opens at the clicked image, arrows move, Escape returns focus', async () => {
		await boot();
		const opener = document.getElementById( 'vd1-p1' );
		opener.focus();
		opener.click();

		const viewer = document.querySelector( '[data-rvs-gallery-viewer]' );
		const img = viewer.querySelector( '[data-rvs-gallery-img]' );
		expect( viewer.hidden ).toBe( false );
		expect( viewer.getAttribute( 'role' ) ).toBe( 'dialog' );
		expect( img.getAttribute( 'src' ) ).toBe( '/b.jpg' );
		expect( viewer.querySelector( '[data-rvs-gallery-count]' ).textContent ).toBe( '2 / 3' );

		viewer.querySelector( '[data-rvs-gallery-step="1"]' ).click();
		expect( img.getAttribute( 'src' ) ).toBe( '/c.jpg' );
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowRight', bubbles: true } ) );
		expect( img.getAttribute( 'src' ) ).toBe( '/a.jpg' );
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowLeft', bubbles: true } ) );
		expect( img.getAttribute( 'src' ) ).toBe( '/c.jpg' );

		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
		expect( viewer.hidden ).toBe( true );
		expect( document.activeElement ).toBe( opener );
	} );

	test( 'the phone strip counter follows the scrolled photo', async () => {
		await boot( page( 'vd1' ).replace( '<button type="button" id="vd1-p0"', '<div class="rvs-vd__strip" data-rvs-gallery-strip><button type="button" id="vd1-p0"' ).replace( '<div class="rvs-dialog" data-rvs-gallery-viewer', '</div><span data-rvs-gallery-counter>1 / 3</span><div class="rvs-dialog" data-rvs-gallery-viewer' ) );
		const strip = document.querySelector( '[data-rvs-gallery-strip]' );
		Object.defineProperty( strip, 'clientWidth', { configurable: true, value: 390 } );
		strip.scrollLeft = 780;
		strip.dispatchEvent( new Event( 'scroll' ) );

		expect( document.querySelector( '[data-rvs-gallery-counter]' ).textContent ).toBe( '3 / 3' );
	} );

	test( 'breakdown sheet opens with its rows unfolded and closes from its button', async () => {
		await boot();
		document.getElementById( 'vd1-sheet-open' ).click();

		const sheet = document.querySelector( '[data-rvs-vd-breakdown-dialog]' );
		expect( sheet.hidden ).toBe( false );
		expect( sheet.querySelector( 'details' ).open ).toBe( true );

		sheet.querySelector( '[data-rvs-dialog-close]' ).click();
		expect( sheet.hidden ).toBe( true );
	} );

	test( 'two instances keep separate state', async () => {
		await boot( page( 'vd1' ) + page( 'vd2' ) );
		document.querySelectorAll( '[data-rvs-month-nav="1"]' )[ 1 ].click();

		expect( jQuery.ajax.mock.calls[ 0 ][ 0 ].data.vehicle_id ).toBe( '8' );
		answer( 0, { html: '<div class="rvs-cal" data-rvs-calendar data-year="2027" data-month="8">SECOND</div>' } );
		const months = document.querySelectorAll( '[data-rvs-vd-months]' );
		expect( months[ 0 ].textContent ).not.toContain( 'SECOND' );
		expect( months[ 1 ].textContent ).toContain( 'SECOND' );
	} );

	test( 'server error shows the error text', async () => {
		await boot();
		change( document.querySelector( '[data-rvs-pickup-date]' ) );
		jest.advanceTimersByTime( 300 );
		answer( 0, null, false );

		const message = document.querySelector( '[data-rvs-vd-message]' );
		expect( message.hidden ).toBe( false );
		expect( message.textContent ).toBe( 'Something went wrong' );
	} );
} );
