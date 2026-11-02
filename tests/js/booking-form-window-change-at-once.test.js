/**
 * @jest-environment jsdom
 */

/**
 * The owner on /rezervasyon/?mhmrentiva_vehicle_id=N (2026-11-02): the car
 * showed "not available" for some dates; new dates were picked and the line
 * and the price stayed as they were until a click elsewhere, then it read
 * "available".
 *
 * A pick did reach the form (onSelect -> change), but nothing on screen moved
 * for the first 300 ms, the old "not available" and the old total stayed up
 * unmarked until the new answers came back (2-4 s on dev), each change sent
 * the quote twice (the one that counted 300 ms after the pick), and an answer
 * for the old dates landing in that gap overwrote the screen. Now a pick
 * marks the old figures at once, drops the old answers, and sends one quote
 * and one availability check.
 *
 * Driven with the REAL jQuery UI datepicker (tests/js/support/real-datepicker.js)
 * and the real constructor, so the change comes from a click on a day.
 */
const realDatepicker = require( './support/real-datepicker' );

describe( 'booking form: a picked date updates availability and price at once', () => {
	let $;
	let form;
	let requests;
	let markup;

	const pad = ( n ) => String( n ).padStart( 2, '0' );
	const iso = ( d ) => d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
	const today = new Date();
	const day = ( n ) => new Date( today.getFullYear(), today.getMonth(), today.getDate() + n );

	const TAKEN = { success: false, data: { ok: false, code: 'unavailable', message: 'Not available' } };
	const FREE = { success: true, data: { ok: true, code: 'ok' } };
	const quote = ( total ) => ( { success: true, data: { total_price: total, display_total: { amount: total, html: '<span class="amount">$' + total + '</span>' } } } );

	beforeEach( () => {
		jest.resetModules();
		jest.useFakeTimers();
		document.body.innerHTML = '';
		markup = `
			<div class="rvs-shell rvs-booking" data-rvs-booking-form data-vehicle-id="7">
				<form class="rvs-booking__form">
					<input type="hidden" name="vehicle_id" value="7">
					<input id="bf-pickup" type="text" name="pickup_date" data-rvs-pickup-date value="" readonly>
					<select data-rvs-pickup-time name="pickup_time"><option value="">Time</option><option value="10:00" selected>10:00</option></select>
					<input id="bf-return" type="text" name="dropoff_date" data-rvs-dropoff-date value="" readonly>
					<input type="hidden" name="dropoff_time" value="10:00" data-rvs-dropoff-time>
					<div class="rvs-booking__availability" data-rvs-availability hidden></div>
					<div data-rvs-messages hidden></div>
					<div class="rvs-booking__summary" data-rvs-summary>
						<p data-rvs-summary-dates></p>
						<div data-rvs-breakdown></div>
					</div>
					<strong data-rvs-total>-</strong>
					<input type="hidden" name="payment_type" value="deposit">
					<button type="button" data-rvs-submit>Book</button>
				</form>
			</div>`;
		$ = require( 'jquery' );
		global.jQuery = $;
		global.$ = $;
		window.jQuery = $;
		realDatepicker.install( $ );
		$.fx.off = true;

		window.mhmRentivaBookingForm = {
			siteToday: iso( today ),
			restUrl: '/wp-json/mhm-rentiva/v1/',
			ajax_url: '/ajax',
			ajaxUrl: '/ajax',
			nonce: 'n',
			config: { min_days: 1, max_days: 30, advance_booking_days: 365, currency_symbol: '$', currency_position: 'left' },
			strings: { checking_availability: 'Checking...', vehicle_available: 'Available', vehicle_not_available: 'Not available' },
			datepicker_options: { dateFormat: 'dd/mm/yy', minDate: 0, maxDate: 365 },
		};

		requests = [];
		$.ajax = jest.fn( ( options ) => {
			// The blocked dates of the car: none.
			if ( String( options.url ).indexOf( '/blocked-dates' ) !== -1 ) {
				options.success( { success: true, data: [] } );
				return;
			}
			const data = typeof options.data === 'string' ? Object.fromEntries( new URLSearchParams( options.data ) ) : options.data;
			requests.push( { action: data.action, pickup: data.pickup_date, dropoff: data.dropoff_date, options } );
		} );

		require( '../../assets/js/frontend/booking-form.js' );
		// Its own document-ready start runs on an empty page; the form under
		// test is this one instance.
		jest.runAllTimers();
		document.body.innerHTML = markup;
		form = new window.MHMRentivaBookingForm( $( '[data-rvs-booking-form]' ) );
	} );

	afterEach( () => {
		$.datepicker._hideDatepicker();
		$( '#ui-datepicker-div' ).remove();
		jest.useRealTimers();
	} );

	const popup = () => $( '#ui-datepicker-div' );

	// A click on a day in the open calendar, as a customer does it; then the
	// form's own onSelect delay (50 ms) passes.
	const pick = ( selector, date ) => {
		$( selector ).trigger( 'focus' ).datepicker( 'show' );
		const cell = () => popup().find( 'td[data-handler="selectDay"]' ).filter( function () {
			return +this.getAttribute( 'data-month' ) === date.getMonth()
				&& +this.getAttribute( 'data-year' ) === date.getFullYear()
				&& $( this ).text().trim() === String( date.getDate() );
		} );
		for ( let i = 0; i < 3 && ! cell().length; i++ ) {
			popup().find( '[data-handler="next"]' ).trigger( 'click' );
		}
		cell().find( 'a' ).trigger( 'click' );
		jest.advanceTimersByTime( 50 );
	};

	const settle = () => jest.advanceTimersByTime( 1000 );
	const sent = ( action ) => requests.filter( ( r ) => r.action === action );
	const last = ( action ) => sent( action )[ sent( action ).length - 1 ];
	const $status = () => form.container.find( '[data-rvs-availability]' );
	const $summary = () => form.container.find( '[data-rvs-summary]' );

	// The owner's starting point: dates the car is taken for.
	const takenWindow = () => {
		pick( '#bf-pickup', day( 5 ) );
		pick( '#bf-return', day( 8 ) );
		settle();
		last( 'mhmrentiva_check_availability' ).options.success( TAKEN );
		last( 'mhmrentiva_calculate_price' ).options.success( quote( 300 ) );
		expect( $status().attr( 'data-state' ) ).toBe( 'error' );
		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );
		requests = [];
	};

	test( 'one pick sends one quote and one availability check, for the picked dates', () => {
		takenWindow();

		pick( '#bf-return', day( 10 ) );
		settle();

		expect( sent( 'mhmrentiva_calculate_price' ) ).toHaveLength( 1 );
		expect( sent( 'mhmrentiva_check_availability' ) ).toHaveLength( 1 );
		expect( last( 'mhmrentiva_check_availability' ).dropoff ).toBe( iso( day( 10 ) ) );
		expect( last( 'mhmrentiva_calculate_price' ).dropoff ).toBe( iso( day( 10 ) ) );
	} );

	test( 'a moved pickup (the return date follows it) is one quote and one check too', () => {
		takenWindow();

		pick( '#bf-pickup', day( 20 ) );
		settle();

		expect( sent( 'mhmrentiva_calculate_price' ) ).toHaveLength( 1 );
		expect( sent( 'mhmrentiva_check_availability' ) ).toHaveLength( 1 );
		expect( last( 'mhmrentiva_check_availability' ).pickup ).toBe( iso( day( 20 ) ) );
		expect( last( 'mhmrentiva_check_availability' ).dropoff ).toBe( iso( day( 21 ) ) );
	} );

	test( 'the old "not available" and the old total are marked the moment a date is picked', () => {
		takenWindow();

		pick( '#bf-return', day( 10 ) );

		// No click elsewhere, no wait for the debounce or the server.
		expect( $status().attr( 'data-state' ) ).toBe( 'busy' );
		expect( $status().text() ).toBe( 'Checking...' );
		expect( $summary().attr( 'aria-busy' ) ).toBe( 'true' );
	} );

	test( 'the new answers show without any other click', () => {
		takenWindow();

		pick( '#bf-return', day( 10 ) );
		settle();
		last( 'mhmrentiva_check_availability' ).options.success( FREE );
		last( 'mhmrentiva_calculate_price' ).options.success( quote( 500 ) );

		expect( $status().attr( 'data-state' ) ).toBe( 'ok' );
		expect( $status().text() ).toBe( 'Available' );
		expect( form.container.find( '[data-rvs-total] .amount' ).text() ).toBe( '$500' );
		expect( $summary().attr( 'aria-busy' ) ).toBe( 'false' );
		expect( form.submitBtn.prop( 'disabled' ) ).toBe( false );
	} );

	test( 'answers for the dates the customer left are ignored, even ones landing before the new requests go out', () => {
		pick( '#bf-pickup', day( 5 ) );
		pick( '#bf-return', day( 8 ) );
		settle();
		const oldCheck = last( 'mhmrentiva_check_availability' );
		const oldQuote = last( 'mhmrentiva_calculate_price' );

		pick( '#bf-return', day( 10 ) );
		// Inside the debounce: the old answers arrive now.
		oldCheck.options.success( TAKEN );
		oldQuote.options.success( quote( 300 ) );
		expect( $status().attr( 'data-state' ) ).toBe( 'busy' );
		expect( form.container.find( '[data-rvs-total]' ).text() ).toBe( '-' );

		settle();
		last( 'mhmrentiva_check_availability' ).options.success( FREE );
		last( 'mhmrentiva_calculate_price' ).options.success( quote( 500 ) );
		expect( $status().attr( 'data-state' ) ).toBe( 'ok' );
		expect( form.container.find( '[data-rvs-total] .amount' ).text() ).toBe( '$500' );
		expect( form.submitBtn.prop( 'disabled' ) ).toBe( false );
	} );

	test( 'quick picks in a row send only the last window', () => {
		pick( '#bf-pickup', day( 5 ) );
		pick( '#bf-return', day( 8 ) );
		pick( '#bf-return', day( 9 ) );
		pick( '#bf-return', day( 10 ) );
		settle();

		expect( sent( 'mhmrentiva_check_availability' ) ).toHaveLength( 1 );
		expect( sent( 'mhmrentiva_calculate_price' ) ).toHaveLength( 1 );
		expect( last( 'mhmrentiva_check_availability' ).dropoff ).toBe( iso( day( 10 ) ) );
	} );

	test( 'answers for a window left incomplete (vehicle cleared) do not land', () => {
		pick( '#bf-pickup', day( 5 ) );
		pick( '#bf-return', day( 8 ) );
		settle();
		const oldCheck = last( 'mhmrentiva_check_availability' );
		const oldQuote = last( 'mhmrentiva_calculate_price' );

		form.container.removeAttr( 'data-vehicle-id' ).find( 'input[name="vehicle_id"]' ).val( '' );
		form.autoCheckAvailability();
		oldCheck.options.success( FREE );
		oldQuote.options.success( quote( 300 ) );

		expect( $status().prop( 'hidden' ) ).toBe( true );
		expect( form.container.find( '[data-rvs-total]' ).text() ).toBe( '-' );
		expect( $summary().attr( 'aria-busy' ) ).toBe( 'false' );
	} );

	test( 'a window over the rental limit sends nothing and leaves no old answer on screen', () => {
		takenWindow();

		pick( '#bf-return', day( 60 ) );
		settle();

		expect( requests ).toHaveLength( 0 );
		expect( $status().prop( 'hidden' ) ).toBe( true );
		expect( $summary().attr( 'aria-busy' ) ).toBe( 'false' );
		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );
	} );
} );
