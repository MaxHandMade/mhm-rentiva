/**
 * @jest-environment jsdom
 */

/**
 * Every booking-form price goes through formatMoney(). PHP localizes the
 * currency parts under `config` (BookingForm::get_js_config()), but the script
 * read them at the top level of window.mhmRentivaBookingForm -- where nothing
 * puts them -- so every price fell back to a right-placed symbol and en-US
 * grouping ("1,250 $" on a site WooCommerce formats as "$1.250"). The form's
 * PHP renders the same amounts with CurrencyHelper::format_price( $x, 0 ):
 * WooCommerce's position and separators, no decimals.
 */
describe( 'booking form money formatting', () => {
	let BookingForm;

	const load = ( localized ) => {
		jest.resetModules();
		const $ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		$.fn.datepicker = function () {
			return this;
		};
		$.datepicker = { parseDate: () => null, setDefaults: () => {} };
		window.mhmRentivaBookingForm = localized;

		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
		return Object.create( BookingForm.prototype );
	};

	// The shape BookingForm::get_localized_data() produces on the TR dev site.
	const trConfig = ( extra = {} ) => ( {
		config: {
			currency_symbol: '$',
			currency_position: 'left',
			locale: 'tr-TR',
			thousand_separator: '.',
			decimal_separator: ',',
			...extra,
		},
		strings: {},
	} );

	test( 'uses the symbol, placement and separators PHP puts under config', () => {
		const form = load( trConfig() );

		expect( form.formatMoney( 1250 ) ).toBe( '$1.250' );
		expect( form.formatMoney( 1234567 ) ).toBe( '$1.234.567' );
	} );

	test( 'a right-placed symbol from config', () => {
		const form = load( trConfig( { currency_symbol: '₺', currency_position: 'right_space' } ) );

		expect( form.formatMoney( 1250 ) ).toBe( '1.250 ₺' );
	} );

	test( 'left_space and right placements', () => {
		expect( load( trConfig( { currency_position: 'left_space' } ) ).formatMoney( 1250 ) ).toBe( '$ 1.250' );
		expect( load( trConfig( { currency_position: 'right' } ) ).formatMoney( 1250 ) ).toBe( '1.250$' );
	} );

	test( 'a per-quote symbol still overrides the localized one', () => {
		const form = load( trConfig() );

		expect( form.formatMoney( 1250, '€' ) ).toBe( '€1.250' );
	} );

	test( 'rounds to whole units like format_price( $x, 0 )', () => {
		const form = load( trConfig() );

		expect( form.formatMoney( 1249.6 ) ).toBe( '$1.250' );
	} );

	test( 'an empty thousand separator is honoured (WooCommerce allows it)', () => {
		const form = load( trConfig( { thousand_separator: '' } ) );

		expect( form.formatMoney( 1250 ) ).toBe( '$1250' );
	} );

	test( 'without separators in config, groups by config.locale', () => {
		const form = load( {
			config: { currency_symbol: '$', currency_position: 'left', locale: 'de-DE' },
			strings: {},
		} );

		expect( form.formatMoney( 1250 ) ).toBe( '$1.250' );
	} );

	test( 'falls back to the old top-level shape when config lacks a key', () => {
		const form = load( { config: {}, strings: {}, currency_symbol: '€', currency_position: 'left' } );

		expect( form.formatMoney( 1250 ) ).toBe( '€1,250' );
	} );

	test( 'with nothing localized, keeps the historical default', () => {
		const form = load( { strings: {} } );

		expect( form.formatMoney( 1250 ) ).toBe( '1,250 ' );
	} );

	test( 'alternative-vehicle cards take the symbol from config', () => {
		const form = load( trConfig() );

		expect( form.getConfig( 'currency_symbol' ) ).toBe( '$' );
	} );
} );
