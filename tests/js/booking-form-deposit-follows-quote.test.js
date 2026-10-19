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
		window.mhmRentivaBookingForm = { config: {}, strings: {}, enable_deposit: true };

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	const setup = () => {
		document.body.innerHTML = `
			<div class="rv-booking-form-wrapper">
				<form class="rv-booking-form-content">
					<input type="hidden" name="payment_type" value="deposit">
				</form>
				<div class="rv-deposit-summary rv-hidden"><span class="rv-deposit-amount"></span></div>
				<div class="rv-remaining-summary rv-hidden"><span class="rv-remaining-amount"></span></div>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '.rv-booking-form-wrapper' );
		form.form = form.container.find( '.rv-booking-form-content' );
		const stub = () => ( { text: () => {} } );
		form.priceElements = {
			dailyPrice: stub(),
			daysCount: stub(),
			weekendDiffAmount: stub(),
			taxLabel: stub(),
			taxAmount: stub(),
			vehicleTotal: stub(),
			addonsTotal: stub(),
			totalAmount: stub(),
			depositAmount: form.container.find( '.rv-deposit-amount' ),
			remainingAmount: form.container.find( '.rv-remaining-amount' ),
		};
		form.formatMoney = ( amount ) => String( amount );
		return form;
	};

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

		expect( form.container.find( '.rv-deposit-summary' ).hasClass( 'rv-hidden' ) ).toBe( false );
		expect( form.container.find( '.rv-remaining-summary' ).hasClass( 'rv-hidden' ) ).toBe( false );
	} );

	test( 'a full-payment quote does not call the whole total a deposit', () => {
		const form = setup();

		form.updatePriceDisplay( quote( { payment_type: 'full', deposit_amount: 2000, remaining_amount: 0 } ) );

		expect( form.container.find( '.rv-deposit-summary' ).hasClass( 'rv-hidden' ) ).toBe( true );
		expect( form.container.find( '.rv-remaining-summary' ).hasClass( 'rv-hidden' ) ).toBe( true );
	} );
} );
