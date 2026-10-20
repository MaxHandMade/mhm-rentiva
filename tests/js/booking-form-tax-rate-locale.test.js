/**
 * @jest-environment jsdom
 */

/**
 * The price summary's tax label writes the rate the way the quote does
 * (`tax_rate_display`, formatted through the catalogue on the server). It used
 * to glue `rate + '%'` in the browser, so a Turkish site read
 * "Vergi (dahil) (20%)" where Turkish writes "%20".
 */
describe( 'booking form tax label follows the locale', () => {
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
		window.mhmRentivaBookingForm = {
			config: { enable_deposit: true },
			strings: { tax: 'Vergi', tax_included: 'Vergi (dahil)' },
		};

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	const setup = () => {
		document.body.innerHTML = `
			<div class="rv-booking-form-wrapper">
				<form class="rv-booking-form-content"></form>
				<div class="rv-tax-summary rv-hidden"><span class="rv-tax-label"></span><span class="rv-tax-amount"></span></div>
			</div>`;
		const form = Object.create( BookingForm.prototype );
		form.container = $( '.rv-booking-form-wrapper' );
		form.form = form.container.find( '.rv-booking-form-content' );
		const stub = () => ( { text: () => {} } );
		form.priceElements = {
			dailyPrice: stub(),
			daysCount: stub(),
			weekendDiffAmount: stub(),
			taxLabel: form.container.find( '.rv-tax-label' ),
			taxAmount: form.container.find( '.rv-tax-amount' ),
			vehicleTotal: stub(),
			addonsTotal: stub(),
			totalAmount: stub(),
			depositAmount: stub(),
			remainingAmount: stub(),
		};
		form.formatMoney = ( amount ) => String( amount );
		return form;
	};

	const quote = ( extra ) => ( {
		vehicle_price: 1000,
		days: 2,
		addon_total: 0,
		total_price: 2000,
		payment_type: 'full',
		tax_enabled: true,
		tax_amount: 333.33,
		tax_rate: 20,
		...extra,
	} );

	test( 'an inclusive tax shows the rate as the server wrote it', () => {
		const form = setup();

		form.updatePriceDisplay( quote( { tax_inclusive: true, tax_rate_display: '%20' } ) );

		expect( form.container.find( '.rv-tax-label' ).text() ).toBe( 'Vergi (dahil) (%20):' );
	} );

	test( 'an exclusive tax shows the rate as the server wrote it', () => {
		const form = setup();

		form.updatePriceDisplay( quote( { tax_inclusive: false, tax_rate_display: '%20' } ) );

		expect( form.container.find( '.rv-tax-label' ).text() ).toBe( 'Vergi (%20):' );
	} );

	test( 'a quote without the formatted rate falls back to the number', () => {
		const form = setup();

		form.updatePriceDisplay( quote( { tax_inclusive: false } ) );

		expect( form.container.find( '.rv-tax-label' ).text() ).toBe( 'Vergi (20%):' );
	} );
} );
