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

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	const setup = () => {
		document.body.innerHTML = `
			<div class="rv-booking-form-wrapper" data-vehicle-id="5">
				<form class="rv-booking-form-content">
					<input type="hidden" name="payment_type" value="full">
					<input type="text" class="rv-pickup-date" value="2027-07-12">
					<input type="text" class="rv-dropoff-date" value="2027-07-14">
					<label><input type="checkbox" class="rv-addon-checkbox" value="11" checked>
						<input type="number" class="rv-addon-qty" name="addon_qty[11]" data-addon-id="11" value="3"></label>
					<label><input type="checkbox" class="rv-addon-checkbox" value="12">
						<input type="number" class="rv-addon-qty" name="addon_qty[12]" data-addon-id="12" value="2"></label>
					<label><input type="checkbox" class="rv-addon-checkbox" value="13" checked></label>
				</form>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '.rv-booking-form-wrapper' );
		form.form = form.container.find( '.rv-booking-form-content' );
		form.showLoading = () => {};
		form.hideMessages = () => {};
		form.validateCalculationData = () => true;
		form.validateForm = () => true;
		form.submitBtn = $( '<button>' );
		form.paymentTypeRadios = form.form.find( 'input[name="payment_type"]' );
		form.paymentMethodRadios = $( [] );
		form.onlinePaymentDetails = $( [] );
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

		form.form.find( '.rv-addon-qty[data-addon-id="11"]' ).val( '2' ).trigger( 'change' );
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
