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

		expect( form.container.find( '.rvs-breakdown__amount .amount' ).text() ).toBe( '$3.600,00' );
		expect( form.container.find( '.rvs-breakdown__deposit .amount' ).text() ).toBe( '$360,80' );
		expect( form.container.find( '.rvs-breakdown__pickup .amount' ).text() ).toBe( '$3.239,20' );
	} );

	test( 'WooCommerce price suffix is shown next to the total', () => {
		const form = setup();

		form.updatePriceDisplay( quote() );

		expect( form.container.find( '.rvs-breakdown__total .woocommerce-price-suffix' ).text() ).toBe( 'incl. VAT' );
	} );

	test( 'falls back to the raw amounts when display amounts are absent', () => {
		const form = setup();

		form.updatePriceDisplay( quote( { display_total: undefined, display_deposit: undefined, display_remaining: undefined, price_suffix: undefined } ) );

		expect( form.container.find( '.rvs-breakdown__amount' ).text() ).toBe( '3000' );
		expect( form.container.find( '.rvs-breakdown__deposit strong' ).text() ).toBe( '300' );
		expect( form.container.find( '.rvs-breakdown__total .woocommerce-price-suffix' ).length ).toBe( 0 );
	} );

	test( 'breakdown rows use the display markup so they add up to the total', () => {
		const form = setup();
		const row = ( kind ) => form.container.find( '.rvs-breakdown__row--' + kind + ' dd' ).html();

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

		const seen = { daily: row( 'daily' ), vehicle: row( 'rental' ), addons: row( 'addon' ) };
		expect( seen ).toEqual( { daily: '$1.020,00', vehicle: '$3.978,00', addons: '$30,00' } );
	} );

	test( 'a tax-exclusive deposit and remaining say so', () => {
		const form = setup();
		const label = ' <small class="tax_label">(ex. VAT)</small>';

		form.updatePriceDisplay( quote( {
			display_deposit: { amount: 300, html: '<span class="amount">$300,00</span>', tax_label: label },
			display_remaining: { amount: 2700, html: '<span class="amount">$2.700,00</span>', tax_label: label },
		} ) );

		expect( form.container.find( '.rvs-breakdown__deposit .tax_label' ).text() ).toBe( '(ex. VAT)' );
		expect( form.container.find( '.rvs-breakdown__pickup .tax_label' ).text() ).toBe( '(ex. VAT)' );
	} );
} );
