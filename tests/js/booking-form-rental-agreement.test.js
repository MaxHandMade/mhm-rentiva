/**
 * @jest-environment jsdom
 */

/**
 * The rental agreement on the booking form: an unticked consent box stops
 * the submission in the browser and says why (the server refuses it too);
 * a ticked one rides along as terms_accepted=1; a form without the agreement
 * sends no such field.
 */
describe( 'booking form rental agreement', () => {
	let $;
	let BookingForm;

	const markup = ( agreement ) => `
		<div class="rvs-booking" data-rvs-booking-form>
			<form class="rvs-booking__form">
				<input name="pickup_date" data-rvs-pickup-date value="2026-11-01" readonly required>
				<input name="dropoff_date" data-rvs-dropoff-date value="2026-11-03" readonly required>
				<select name="pickup_time" data-rvs-pickup-time required><option value="10:00" selected>10:00</option></select>
				<input type="hidden" name="payment_type" value="deposit">
				${ agreement ? `
				<section data-rvs-agreement>
					<label><input type="checkbox" name="terms_accepted" value="1" data-rvs-agreement-consent></label>
					<p data-rvs-agreement-error hidden>Accept it.</p>
				</section>` : '' }
			</form>
		</div>`;

	const make = ( agreement ) => {
		document.body.innerHTML = markup( agreement );
		const form = Object.create( BookingForm.prototype );
		form.container = $( '[data-rvs-booking-form]' );
		form.form = form.container.find( 'form' );
		form.showError = jest.fn();
		form.getMessage = ( key ) => key;
		return form;
	};

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

	test( 'an unticked agreement stops the submission and marks the box', () => {
		const form = make( true );

		expect( form.validateForm() ).toBe( false );
		expect( form.showError ).toHaveBeenCalledWith( 'agreement_required' );
		expect( form.container.find( '[data-rvs-agreement-consent]' ).attr( 'aria-invalid' ) ).toBe( 'true' );
		expect( form.container.find( '[data-rvs-agreement-error]' ).prop( 'hidden' ) ).toBe( false );
	} );

	test( 'a ticked agreement validates and is sent', () => {
		const form = make( true );
		form.container.find( '[data-rvs-agreement-consent]' ).prop( 'checked', true );

		expect( form.validateForm() ).toBe( true );
		expect( form.container.find( '[data-rvs-agreement-error]' ).prop( 'hidden' ) ).toBe( true );
		expect( form.getFormData().terms_accepted ).toBe( '1' );
	} );

	test( 'ticking the box after an error clears it', () => {
		const form = make( true );
		form.validateForm();
		form.bindAgreement();

		form.container.find( '[data-rvs-agreement-consent]' ).prop( 'checked', true ).trigger( 'change' );

		expect( form.container.find( '[data-rvs-agreement-consent]' ).attr( 'aria-invalid' ) ).toBe( 'false' );
		expect( form.container.find( '[data-rvs-agreement-error]' ).prop( 'hidden' ) ).toBe( true );
	} );

	test( 'a form without the agreement neither checks nor sends it', () => {
		const form = make( false );

		expect( form.validateForm() ).toBe( true );
		expect( form.getFormData() ).not.toHaveProperty( 'terms_accepted' );
	} );
} );
