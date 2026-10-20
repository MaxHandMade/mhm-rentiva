/**
 * @jest-environment jsdom
 */

/**
 * The form posts a single hidden payment_type and renders no payment_type /
 * payment_method / payment_gateway radios. validateForm() used to demand those
 * radios behind a read of window.mhmRentivaBookingForm.enable_deposit at the
 * top level, where PHP never puts it -- so the branch never ran. PHP's value
 * (config.enable_deposit, a constant true) must not resurrect it: with the
 * real localized shape, a complete form validates.
 */
describe( 'booking form validation with the real localized shape', () => {
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
		window.mhmRentivaBookingForm = { config: { enable_deposit: true }, strings: {}, enable_deposit: true };

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	test( 'does not demand payment radios the template never renders', () => {
		document.body.innerHTML = `
			<div class="rv-booking-form-wrapper">
				<form class="rv-booking-form-content">
					<input class="rv-pickup-date" value="2026-11-01">
					<input class="rv-dropoff-date" value="2026-11-03">
					<select class="rv-pickup-time"><option value="10:00" selected>10:00</option></select>
					<input type="hidden" name="payment_type" value="deposit">
				</form>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '.rv-booking-form-wrapper' );
		form.form = form.container.find( '.rv-booking-form-content' );
		form.showError = jest.fn();

		expect( form.validateForm() ).toBe( true );
		expect( form.showError ).not.toHaveBeenCalled();
	} );
} );
