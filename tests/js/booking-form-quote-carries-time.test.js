/**
 * @jest-environment jsdom
 */

/**
 * The quote must be priced for the window the submission books. The submit
 * posts the pickup time; the quote did not, so the server priced it at its
 * 10:00 default. A pickup whose weekday differs in UTC (00:00-02:59 in
 * Istanbul) then showed one total on the form and charged another at
 * checkout (measured on dev 2026-10-25: 5375 vs 5725).
 */
describe( 'booking form quote carries the pickup time', () => {
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
		window.mhmRentivaBookingForm = { config: { enable_deposit: true }, strings: {}, ajax_url: '/ajax', nonce: 'n' };

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	test( 'the price request posts the pickup time the submission posts', () => {
		document.body.innerHTML = `
			<div class="rvs-booking" data-rvs-booking-form data-vehicle-id="5">
				<form class="rvs-booking__form">
					<input type="hidden" name="payment_type" value="full">
					<input type="text" name="pickup_date" data-rvs-pickup-date value="2026-11-16">
					<input type="text" name="dropoff_date" data-rvs-dropoff-date value="2026-11-19">
					<select name="pickup_time" data-rvs-pickup-time><option value="00:00" selected>00:00</option></select>
				</form>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '[data-rvs-booking-form]' );
		form.form = form.container.find( 'form' );
		form.showLoading = () => {};
		form.hideMessages = () => {};
		form.validateCalculationData = () => true;
		$.ajax = jest.fn();

		form.calculatePrice();

		const call = $.ajax.mock.calls[ 0 ][ 0 ];
		const data = new URLSearchParams( String( call.data ) );
		expect( data.get( 'pickup_time' ) ).toBe( '00:00' );
	} );
} );
