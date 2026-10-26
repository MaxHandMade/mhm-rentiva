/**
 * @jest-environment jsdom
 */

/**
 * A booking page opened with a prefilled pickup location renders
 * <input type="hidden" name="pickup_location_id"> inside the form. The submit
 * payload built by getFormData() must carry it, otherwise the booking is stored
 * without a location although the customer saw one.
 *
 * Type: a number (8, not '8'). The payload is sent as URL-encoded text, so both
 * arrive as "8"; the server reads it with $req->int('pickup_location_id'),
 * which is an int cast. A number also makes "absent" a literal 0, the same
 * value the server falls back to.
 */
describe( 'booking form pickup_location_id in getFormData()', () => {
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

	const build = ( innerFormHtml, outsideHtml = '' ) => {
		document.body.innerHTML = `
			${ outsideHtml }
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

	test( 'a hidden pickup_location_id inside the form is submitted', () => {
		const form = build( '<input type="hidden" name="pickup_location_id" value="8">' );
		expect( form.getFormData().pickup_location_id ).toBe( 8 );
	} );

	test( 'without the input, pickup_location_id is 0', () => {
		const form = build( '' );
		expect( form.getFormData().pickup_location_id ).toBe( 0 );
	} );

	test( 'an input outside this form container is ignored', () => {
		const form = build( '', '<input type="hidden" name="pickup_location_id" value="99">' );
		expect( form.getFormData().pickup_location_id ).toBe( 0 );
	} );
} );
