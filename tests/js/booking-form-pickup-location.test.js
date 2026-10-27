/**
 * @jest-environment jsdom
 */

/**
 * The booking form posts no pickup location. The server derives it from the
 * chosen car (BookingForm::vehicle_location_id()) and ignores anything posted,
 * so the form neither renders a hidden pickup_location_id field nor sends one:
 * a field nobody reads only invites a later reader to trust it.
 */
describe( 'booking form getFormData() posts no pickup location', () => {
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
		$.datepicker = { parseDate: () => new Date(), setDefaults: () => {} };

		window.mhmRentivaBookingForm = { config: {}, strings: {} };

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	const build = ( innerFormHtml ) => {
		document.body.innerHTML = `
			<div class="rvs-booking" data-rvs-booking-form data-vehicle-id="5">
				<form class="rvs-booking__form">
					${ innerFormHtml }
					<input name="pickup_date" data-rvs-pickup-date value="2030-05-10">
					<input name="dropoff_date" data-rvs-dropoff-date value="2030-05-12">
				</form>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '[data-rvs-booking-form]' );
		form.form = form.container.find( 'form' );
		return form;
	};

	test( 'no pickup_location_id is sent, even when a stale field is on the page', () => {
		const form = build( '<input type="hidden" name="pickup_location_id" value="8">' );
		expect( form.getFormData() ).not.toHaveProperty( 'pickup_location_id' );
	} );

	test( 'the rest of the payload is still built', () => {
		const form = build( '' );
		expect( form.getFormData().vehicle_id ).toBeDefined();
	} );
} );
