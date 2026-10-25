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
				<span class="rv-total-amount"></span>
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
		display_total: { amount: 3600, html: '<span class="amount">$3.600,00</span>', suffix: '' },
		display_deposit: { amount: 360.8, html: '<span class="amount">$360,80</span>', suffix: '' },
		display_remaining: { amount: 3239.2, html: '<span class="amount">$3.239,20</span>', suffix: '' },
		price_suffix: '<small class="woocommerce-price-suffix">incl. VAT</small>',
		...extra,
	} );

	test( 'total, deposit and remaining are WooCommerce markup, decimals kept', () => {
		const form = setup();

		form.updatePriceDisplay( quote() );

		expect( form.container.find( '.rv-total-amount .amount' ).text() ).toBe( '$3.600,00' );
		expect( form.container.find( '.rv-deposit-amount .amount' ).text() ).toBe( '$360,80' );
		expect( form.container.find( '.rv-remaining-amount .amount' ).text() ).toBe( '$3.239,20' );
	} );

	test( 'WooCommerce price suffix is shown next to the total', () => {
		const form = setup();

		form.updatePriceDisplay( quote() );

		expect( form.container.find( '.rv-total-amount .woocommerce-price-suffix' ).text() ).toBe( 'incl. VAT' );
	} );

	test( 'falls back to the raw amounts when display amounts are absent', () => {
		const form = setup();

		form.updatePriceDisplay( quote( { display_total: undefined, display_deposit: undefined, display_remaining: undefined, price_suffix: undefined } ) );

		expect( form.container.find( '.rv-total-amount' ).text() ).toBe( '3000' );
		expect( form.container.find( '.rv-deposit-amount' ).text() ).toBe( '300' );
		expect( form.container.find( '.rv-total-amount .woocommerce-price-suffix' ).length ).toBe( 0 );
	} );

	test( 'breakdown rows use the display amounts so they add up to the total', () => {
		const form = setup();
		const seen = {};
		const capture = ( key ) => ( { text: ( v ) => { seen[ key ] = v; } } );
		form.priceElements.dailyPrice = capture( 'daily' );
		form.priceElements.vehicleTotal = capture( 'vehicle' );
		form.priceElements.addonsTotal = capture( 'addons' );

		form.updatePriceDisplay( quote( {
			vehicle_total: 3315,
			addon_total: 25,
			display_lines: { vehicle_price: 1020, vehicle_total: 3978, addon_total: 30, weekend_extra: 0 },
		} ) );

		expect( seen ).toEqual( { daily: '1020', vehicle: '3978', addons: '30' } );
	} );

	test( 'no tax summary is drawn by the form', () => {
		const form = setup();

		form.updatePriceDisplay( quote() );

		expect( form.container.find( '.rv-tax-summary' ).length ).toBe( 0 );
	} );
} );
