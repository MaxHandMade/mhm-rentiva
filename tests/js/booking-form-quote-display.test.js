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

	test( 'breakdown rows use the display markup so they add up to the total', () => {
		const form = setup();
		const seen = {};
		const capture = ( key ) => ( { html: ( v ) => { seen[ key ] = v; }, text: ( v ) => { seen[ key ] = 'text:' + v; } } );
		form.priceElements.dailyPrice = capture( 'daily' );
		form.priceElements.vehicleTotal = capture( 'vehicle' );
		form.priceElements.addonsTotal = capture( 'addons' );

		form.updatePriceDisplay( quote( {
			vehicle_total: 3315,
			addon_total: 25,
			display_lines: {
				vehicle_price: { amount: 1020, html: '$1.020,00' },
				vehicle_total: { amount: 3978, html: '$3.978,00' },
				addon_total: { amount: 30, html: '$30,00' },
				weekend_extra: { amount: 0, html: '$0,00' },
			},
		} ) );

		expect( seen ).toEqual( { daily: '$1.020,00', vehicle: '$3.978,00', addons: '$30,00' } );
	} );

	test( 'a tax-exclusive deposit and remaining say so', () => {
		const form = setup();
		const label = ' <small class="tax_label">(ex. VAT)</small>';

		form.updatePriceDisplay( quote( {
			display_deposit: { amount: 300, html: '<span class="amount">$300,00</span>', tax_label: label },
			display_remaining: { amount: 2700, html: '<span class="amount">$2.700,00</span>', tax_label: label },
		} ) );

		expect( form.container.find( '.rv-deposit-amount .tax_label' ).text() ).toBe( '(ex. VAT)' );
		expect( form.container.find( '.rv-remaining-amount .tax_label' ).text() ).toBe( '(ex. VAT)' );
	} );
} );
