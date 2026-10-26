/**
 * @jest-environment jsdom
 */

/**
 * When the availability check says the vehicle is taken, the submit button is
 * disabled -- but the price calculation runs alongside it and its success
 * handler (and showLoading(false)) used to switch the button straight back on.
 * Whichever response arrived last won, so a visitor could submit dates the
 * vehicle is not free for and only learn it from the server's refusal.
 */
describe( 'booking form keeps submit blocked while the vehicle is unavailable', () => {
	let $;
	let BookingForm;

	beforeEach( () => {
		jest.resetModules();
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		$.fn.datepicker = function () {
			return this;
		};
		$.datepicker = { parseDate: () => null, setDefaults: () => {} };
		window.mhmRentivaBookingForm = { config: {}, strings: {} };

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	const setup = () => {
		document.body.innerHTML = `
			<div class="rvs-booking" data-rvs-booking-form>
				<form class="rvs-booking__form">
					<div class="rvs-booking__availability" data-rvs-availability role="status" hidden></div>
					<div data-rvs-summary></div>
					<button type="button" data-rvs-submit>Book</button>
				</form>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '[data-rvs-booking-form]' );
		form.form = form.container.find( 'form' );
		form.submitBtn = form.form.find( '[data-rvs-submit]' );
		form.getMessage = ( key ) => key;
		form.getFormData = () => ( {
			vehicle_id: '7',
			pickup_date: '2030-05-10',
			dropoff_date: '2030-05-13',
			pickup_time: '10:00',
			dropoff_time: '10:00',
			addons: [],
		} );
		form.validateCalculationData = () => true;
		form.hideMessages = jest.fn();
		form.showError = jest.fn();
		form.updatePriceDisplay = jest.fn();
		return form;
	};

	const respondWith = ( response ) => {
		$.ajax = jest.fn( ( options ) => options.success( response ) );
	};

	const TAKEN = { success: false, data: { ok: false, code: 'unavailable', message: 'taken' } };

	test( 'a price response arriving after "unavailable" does not re-enable submit', () => {
		const form = setup();

		respondWith( TAKEN );
		form.checkAvailability();
		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );

		respondWith( { success: true, data: {} } );
		form.calculatePrice();

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );
	} );

	test( 'ticking the terms box does not re-enable submit for an unavailable vehicle', () => {
		const form = setup();

		respondWith( TAKEN );
		form.checkAvailability();
		form.updateButtonState();

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );
	} );

	test( 'a vehicle the server says is not offered for rental keeps submit locked', () => {
		const form = setup();

		respondWith( { success: false, data: { ok: false, code: 'service_unavailable', message: 'not for rental' } } );
		form.checkAvailability();
		form.updateButtonState();

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );
	} );

	test( 'a request that never got an answer does not lock the sale', () => {
		// The server re-checks under a lock at checkout, so an unknown answer must
		// not leave a visitor unable to book dates that may well be free.
		const form = setup();

		respondWith( TAKEN );
		form.checkAvailability();
		// New dates: the price answer lands first (the old lock still stands),
		// then the availability request fails. Nothing else re-enables submit,
		// so the failure branch itself must lift the lock.
		respondWith( { success: true, data: {} } );
		form.calculatePrice();
		$.ajax = jest.fn( ( options ) => options.error( { status: 429 }, 'error', 'Too Many Requests' ) );
		form.checkAvailability();

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( false );
		// The error handler logs the failed request.
		expect( console ).toHaveErrored();
	} );

	test( 'a refusal without an availability code (rate limit, nonce) does not lock the sale', () => {
		const form = setup();

		respondWith( TAKEN );
		form.checkAvailability();
		respondWith( { success: false, data: { message: 'Too many availability checks. Please wait.' } } );
		form.checkAvailability();

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( false );
		expect( form.form.find( '[data-rvs-availability]' ).text() ).toContain( 'Too many availability checks' );
	} );

	test( 'an older availability answer arriving late is ignored', () => {
		const form = setup();
		const pending = [];
		$.ajax = jest.fn( ( options ) => pending.push( options ) );

		form.checkAvailability(); // first dates: will turn out taken
		form.checkAvailability(); // second dates: free

		pending[ 1 ].success( { success: true, data: { ok: true } } );
		pending[ 0 ].success( TAKEN );

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( false );
		expect( form.form.find( '[data-rvs-availability]' ).is( '[data-state="ok"]' ) ).toBe( true );
	} );

	test( 'an available answer lifts the block again', () => {
		const form = setup();

		respondWith( TAKEN );
		form.checkAvailability();
		respondWith( { success: true, data: {} } );
		form.checkAvailability();
		form.calculatePrice();

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( false );
	} );

	test( 'the unavailable card is rendered into the form', () => {
		const form = setup();

		respondWith( TAKEN );
		form.checkAvailability();

		expect( form.form.find( '[data-rvs-availability][data-state="error"]' ).length ).toBe( 1 );
	} );
} );
