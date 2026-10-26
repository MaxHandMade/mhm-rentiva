/**
 * @jest-environment jsdom
 */

/**
 * Aşama 2 PR G (K12): an add-on sold more than once carries a quantity field
 * (`addon_qty[<id>]`). The quote and the submission post it for ticked
 * add-ons only, and changing it asks for a new quote.
 */
describe( 'booking form add-on quantities', () => {
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

		delete window.MHMRvsStepper;
		require( '../../assets/js/frontend/rvs-stepper.js' );
		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	const setup = () => {
		document.body.innerHTML = `
			<div class="rvs-booking" data-rvs-booking-form data-vehicle-id="5">
				<form class="rvs-booking__form">
					<input type="hidden" name="payment_type" value="full">
					<input type="text" name="pickup_date" data-rvs-pickup-date value="2027-07-12">
					<input type="text" name="dropoff_date" data-rvs-dropoff-date value="2027-07-14">
					<div class="rvs-stepper" data-rvs-stepper="11" data-min="0" data-max="3">
						<button type="button" data-rvs-step="-1">-</button><output>3</output><button type="button" data-rvs-step="1">+</button>
						<input type="hidden" name="addons[]" value="11">
						<input type="hidden" name="addon_qty[11]" value="3">
					</div>
					<div class="rvs-stepper" data-rvs-stepper="12" data-min="0" data-max="3">
						<button type="button" data-rvs-step="-1">-</button><output>0</output><button type="button" data-rvs-step="1">+</button>
						<input type="hidden" name="addons[]" value="12" disabled>
						<input type="hidden" name="addon_qty[12]" value="0" disabled>
					</div>
					<label class="rvs-option"><input type="checkbox" name="addons[]" value="13" checked></label>
				</form>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '[data-rvs-booking-form]' );
		form.form = form.container.find( 'form' );
		form.showLoading = () => {};
		form.hideMessages = () => {};
		form.validateCalculationData = () => true;
		form.validateForm = () => true;
		form.submitBtn = $( '<button>' );
		window.MHMRvsStepper.init( form.container[ 0 ] );
		return form;
	};

	const posted = () => {
		const call = $.ajax.mock.calls[ $.ajax.mock.calls.length - 1 ][ 0 ];
		return new URLSearchParams( typeof call.data === 'string' ? call.data : call.data.toString() );
	};

	test( 'quantity change re-quotes with addon_qty', () => {
		const form = setup();
		form.autoCalculatePrice = jest.fn();
		form.bindEvents();

		form.container.find( '[data-rvs-stepper="11"] [data-rvs-step="-1"]' )[ 0 ].click();
		expect( form.autoCalculatePrice ).toHaveBeenCalled();

		$.ajax = jest.fn();
		form.calculatePrice();
		const data = posted();
		expect( data.getAll( 'addons[]' ) ).toEqual( [ '11', '13' ] );
		expect( data.get( 'addon_qty[11]' ) ).toBe( '2' );
	} );

	test( 'submit sends addon_qty for checked add-ons only', () => {
		const form = setup();
		$.ajax = jest.fn();

		form.submitForm();
		const data = posted();
		expect( data.getAll( 'addons[]' ) ).toEqual( [ '11', '13' ] );
		expect( data.get( 'addon_qty[11]' ) ).toBe( '3' );
		expect( data.has( 'addon_qty[12]' ) ).toBe( false );
		expect( data.has( 'addon_qty[13]' ) ).toBe( false );
		expect( data.has( 'addon_qty' ) ).toBe( false );
	} );
} );
