/**
 * @jest-environment jsdom
 */

/**
 * PR 0: the form shows the amounts WooCommerce will show at checkout
 * (`display_total` / `display_deposit` / `display_remaining`, computed through
 * wc_get_price_to_display() on the server) and no longer draws its own tax line.
 */
describe( 'booking form shows the quote through WooCommerce display amounts', () => {
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
			<div class="rv-booking-form-wrapper">
				<form class="rv-booking-form-content">
					<input type="hidden" name="payment_type" value="deposit">
				</form>
				<span class="rv-total-amount"></span><span class="rv-price-suffix"></span>
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
			vehicleTotal: stub(),
			addonsTotal: stub(),
			totalAmount: form.container.find( '.rv-total-amount' ),
			depositAmount: form.container.find( '.rv-deposit-amount' ),
			remainingAmount: form.container.find( '.rv-remaining-amount' ),
		};
		form.formatMoney = ( amount ) => String( amount );
		return form;
	};

	const quote = ( extra ) => ( {
		vehicle_price: 1000,
		days: 3,
		addon_total: 0,
		total_price: 3000,
		deposit_amount: 300,
		remaining_amount: 2700,
		payment_type: 'deposit',
		display_total: { amount: 3600, html: '', suffix: '' },
		display_deposit: { amount: 360, html: '', suffix: '' },
		display_remaining: { amount: 3240, html: '', suffix: '' },
		price_suffix: '<small class="woocommerce-price-suffix">incl. VAT</small>',
		...extra,
	} );

	test( 'total, deposit and remaining come from the display amounts', () => {
		const form = setup();

		form.updatePriceDisplay( quote() );

		expect( form.container.find( '.rv-total-amount' ).text() ).toBe( '3600' );
		expect( form.container.find( '.rv-deposit-amount' ).text() ).toBe( '360' );
		expect( form.container.find( '.rv-remaining-amount' ).text() ).toBe( '3240' );
	} );

	test( 'WooCommerce price suffix is shown next to the total', () => {
		const form = setup();

		form.updatePriceDisplay( quote() );

		expect( form.container.find( '.rv-price-suffix .woocommerce-price-suffix' ).text() ).toBe( 'incl. VAT' );
	} );

	test( 'falls back to the raw amounts when display amounts are absent', () => {
		const form = setup();

		form.updatePriceDisplay( quote( { display_total: undefined, display_deposit: undefined, display_remaining: undefined, price_suffix: undefined } ) );

		expect( form.container.find( '.rv-total-amount' ).text() ).toBe( '3000' );
		expect( form.container.find( '.rv-deposit-amount' ).text() ).toBe( '300' );
		expect( form.container.find( '.rv-price-suffix' ).html() ).toBe( '' );
	} );

	test( 'no tax summary is drawn by the form', () => {
		const form = setup();

		form.updatePriceDisplay( quote() );

		expect( form.container.find( '.rv-tax-summary' ).length ).toBe( 0 );
	} );
} );
