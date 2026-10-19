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
			<div class="rv-booking-form-wrapper">
				<form class="rv-booking-form-content">
					<div class="rv-booking-form-left"><div class="rv-dates-times"></div></div>
					<input class="rv-terms-checkbox-input" type="checkbox" checked>
					<button class="rv-submit-btn">Book</button>
				</form>
				<div class="rv-loading rv-hidden"></div>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '.rv-booking-form-wrapper' );
		form.form = form.container.find( '.rv-booking-form-content' );
		form.submitBtn = form.form.find( '.rv-submit-btn' );
		form.loadingEl = form.container.find( '.rv-loading' );
		form.getMessage = ( key ) => key;
		form.escapeHtml = ( s ) => String( s );
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

	test( 'a price response arriving after "unavailable" does not re-enable submit', () => {
		const form = setup();

		respondWith( { success: false, data: { message: 'taken' } } );
		form.checkAvailability();
		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );

		respondWith( { success: true, data: {} } );
		form.calculatePrice();

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );
	} );

	test( 'ticking the terms box does not re-enable submit for an unavailable vehicle', () => {
		const form = setup();

		respondWith( { success: false, data: { message: 'taken' } } );
		form.checkAvailability();
		form.updateButtonState();

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );
	} );

	test( 'a failed availability request keeps submit blocked', () => {
		const form = setup();

		$.ajax = jest.fn( ( options ) => options.error( {}, 'error', 'boom' ) );
		form.checkAvailability();
		form.showLoading( false );

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( true );
		// The error handler logs the failed request.
		expect( console ).toHaveErrored();
	} );

	test( 'an available answer lifts the block again', () => {
		const form = setup();

		respondWith( { success: false, data: { message: 'taken' } } );
		form.checkAvailability();
		respondWith( { success: true, data: {} } );
		form.checkAvailability();
		form.calculatePrice();

		expect( form.submitBtn.prop( 'disabled' ) ).toBe( false );
	} );

	test( 'the unavailable card is rendered into the form', () => {
		const form = setup();

		respondWith( { success: false, data: { message: 'taken' } } );
		form.checkAvailability();

		expect( form.form.find( '.rv-availability-status .rv-availability-error' ).length ).toBe( 1 );
	} );
} );
