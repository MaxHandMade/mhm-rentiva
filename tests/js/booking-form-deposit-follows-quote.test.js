/**
 * @jest-environment jsdom
 */

/**
 * The price quote says whether a deposit applies to the selected vehicle
 * (`payment_type`: 'deposit' or 'full'). When none applies the server charges
 * the whole total, and the form must not label that total "Deposit to Pay".
 */
describe( 'booking form deposit summary follows the quote', () => {
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
		window.mhmRentivaBookingForm = { config: { enable_deposit: true }, strings: {} };

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	const setup = () => {
		document.body.innerHTML = `
			<div class="rvs-booking" data-rvs-booking-form data-rvs-deposit="1">
				<form class="rvs-booking__form">
					<input type="hidden" name="payment_type" value="deposit">
					<div class="rvs-booking__summary" data-rvs-summary>
						<div class="rvs-booking__breakdown" data-rvs-breakdown></div>
					</div>
					<strong data-rvs-total>-</strong><span data-rvs-bar-deposit hidden></span>
				</form>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '[data-rvs-booking-form]' );
		form.form = form.container.find( 'form' );
		form.formatMoney = ( amount ) => String( amount );
		return form;
	};

	// A summary row the answer does not call for is absent (was: class rv-hidden).
	const isHidden = ( form, selector ) => form.container.find( selector ).length === 0;

	const quote = ( extra ) => ( {
		vehicle_price: 1000,
		days: 2,
		addon_total: 0,
		total_price: 2000,
		...extra,
	} );

	test( 'a deposit quote shows the deposit and the remaining amount', () => {
		const form = setup();

		form.updatePriceDisplay( quote( { payment_type: 'deposit', deposit_amount: 600, remaining_amount: 1400 } ) );

		expect( isHidden( form, '.rvs-breakdown__deposit' ) ).toBe( false );
		expect( isHidden( form, '.rvs-breakdown__pickup' ) ).toBe( false );
	} );

	test( 'a full-payment quote does not call the whole total a deposit', () => {
		const form = setup();

		form.updatePriceDisplay( quote( { payment_type: 'full', deposit_amount: 2000, remaining_amount: 0 } ) );

		expect( isHidden( form, '.rvs-breakdown__deposit' ) ).toBe( true );
		expect( isHidden( form, '.rvs-breakdown__pickup' ) ).toBe( true );
	} );
} );
