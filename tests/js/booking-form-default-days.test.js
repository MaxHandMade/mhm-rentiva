/**
 * @jest-environment jsdom
 */

/**
 * The per-form "Default Days" return-date prefill stays inside the site's
 * rental limits (config.min_days / config.max_days), so the date the form
 * supplies never trips validateRentalLimits() and disables submit. A return
 * date the visitor already chose is never overwritten.
 */
describe( 'booking form default_days prefill', () => {
	let $;
	let BookingForm;

	const ymd = ( d ) =>
		d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
	const parse = ( s ) => {
		const [ y, m, d ] = s.split( '-' ).map( Number );
		return new Date( y, m - 1, d );
	};

	beforeEach( () => {
		jest.resetModules();
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;

		// Minimal jQuery UI datepicker stand-in (ISO format), enough for the handler.
		$.fn.datepicker = function ( action, value ) {
			if ( action === 'getDate' ) {
				return this.val() ? parse( this.val() ) : null;
			}
			if ( action === 'setDate' ) {
				this.val( value ? ymd( value ) : '' );
			}
			return this;
		};
		$.datepicker = { parseDate: ( format, s ) => parse( s ), setDefaults: () => {} };

		window.mhmRentivaBookingForm = { config: { min_days: 3, max_days: 10 }, strings: {} };

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	const setup = ( defaultDays, dropoffValue = '' ) => {
		document.body.innerHTML = `
			<div class="rv-booking-form-wrapper" data-default-days="${ defaultDays }">
				<input class="rv-pickup-date" value="">
				<input class="rv-dropoff-date" value="${ dropoffValue }">
				<button class="rv-submit-btn">Book</button>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '.rv-booking-form-wrapper' );
		form.submitBtn = form.container.find( '.rv-submit-btn' );
		form.autoCheckAvailability = jest.fn();
		form.showError = jest.fn();
		form.hideMessages = jest.fn();
		form.updateButtonState = jest.fn();
		form.setupDateValidation();
		form.container.find( '.rv-pickup-date' ).val( '2030-05-10' ).trigger( 'change' );
		return form;
	};

	const returnDays = ( form ) =>
		form.calculateDays( '2030-05-10', form.container.find( '.rv-dropoff-date' ).val() );

	test( 'a default below min_days is raised to min_days', () => {
		const form = setup( 1 );
		expect( returnDays( form ) ).toBe( 3 );
		expect( form.validateRentalLimits() ).toBe( true );
		expect( form.showError ).not.toHaveBeenCalled();
	} );

	test( 'a default above max_days is lowered to max_days', () => {
		const form = setup( 20 );
		expect( returnDays( form ) ).toBe( 10 );
		expect( form.validateRentalLimits() ).toBe( true );
		expect( form.submitBtn.prop( 'disabled' ) ).toBe( false );
	} );

	test( 'a default inside the limits is used as is', () => {
		const form = setup( 5 );
		expect( returnDays( form ) ).toBe( 5 );
		expect( form.validateRentalLimits() ).toBe( true );
	} );

	test( 'limits that cannot be met skip the prefill', () => {
		window.mhmRentivaBookingForm.config = { min_days: 12, max_days: 10 };
		const form = setup( 5 );
		expect( form.container.find( '.rv-dropoff-date' ).val() ).toBe( '' );
	} );

	test( 'a return date the visitor chose is kept', () => {
		const form = setup( 20, '2030-05-12' );
		expect( form.container.find( '.rv-dropoff-date' ).val() ).toBe( '2030-05-12' );
	} );
} );
