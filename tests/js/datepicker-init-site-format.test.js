/**
 * @jest-environment jsdom
 */

/**
 * PR S Task 8 item 2: a `.js-datepicker` shows the site's date format and
 * submits Y-m-d.
 *
 * jQuery UI is not a dev dependency: tests/js/support/fake-datepicker.js stands
 * in for formatDate/parseDate (dd d mm MM yy) and $.fn.datepicker. The PHP ->
 * jQuery UI format conversion itself is tested in DatepickerFormatTest.
 */
const { install, MONTHS } = require( './support/fake-datepicker' );

describe( 'shared datepicker init: site format on screen, Y-m-d submitted', () => {
	let jQuery;
	let calls;

	// The options of the last init call.
	const options = () => calls.filter( ( c ) => c.args[ 0 ] && typeof c.args[ 0 ] === 'object' ).pop().args[ 0 ];

	const form = ( value ) => `
		<form>
			<label for="pickup">Pickup</label>
			<input id="pickup" type="text" class="js-datepicker" name="mhmrentiva_pickup_date" value="${ value }" data-rvs-pickup-date>
		</form>`;

	const boot = async ( dateFormat, value ) => {
		jest.resetModules();
		document.body.innerHTML = form( value );
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		window.jQuery = jQuery;
		calls = install( jQuery );
		window.mhmDatepickerL10n = { dateFormat, altFormat: 'yy-mm-dd', monthNames: MONTHS };
		require( '../../assets/js/core/datepicker-init.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	};

	const visible = () => document.getElementById( 'pickup' );
	const hidden = () => document.querySelector( 'input[type="hidden"][data-rvs-date-value]' );
	const submitted = () => jQuery( 'form' ).serializeArray();

	afterEach( () => {
		delete global.jQuery;
		delete window.mhmDatepickerL10n;
		delete window.mhmRentivaDatepicker;
	} );

	test.each( [
		[ 'dd/mm/yy', '10/12/2026' ],
		[ 'mm/dd/yy', '12/10/2026' ],
		[ 'yy-mm-dd', '2026-12-10' ],
		[ 'd MM yy', '10 December 2026' ],
	] )( 'a server-filled Y-m-d shows as %s and still submits Y-m-d', async ( format, shown ) => {
		await boot( format, '2026-12-10' );

		expect( visible().value ).toBe( shown );
		expect( visible().hasAttribute( 'name' ) ).toBe( false );
		expect( submitted() ).toEqual( [ { name: 'mhmrentiva_pickup_date', value: '2026-12-10' } ] );
		expect( options().dateFormat ).toBe( format );
		expect( options().altFormat ).toBe( 'yy-mm-dd' );
		expect( options().altField.get( 0 ) ).toBe( hidden() );
	} );

	test( 'the visible field keeps its id and hooks; the hidden value sits right after it', async () => {
		await boot( 'dd/mm/yy', '2026-12-10' );

		expect( visible().getAttribute( 'data-rvs-pickup-date' ) ).toBe( '' );
		expect( visible().classList.contains( 'hasDatepicker' ) ).toBe( true );
		expect( visible().nextElementSibling ).toBe( hidden() );
		expect( document.querySelectorAll( '[data-rvs-date-value]' ) ).toHaveLength( 1 );
	} );

	test( 'an empty field stays empty on both sides', async () => {
		await boot( 'dd/mm/yy', '' );

		expect( visible().value ).toBe( '' );
		expect( submitted() ).toEqual( [ { name: 'mhmrentiva_pickup_date', value: '' } ] );
	} );

	test( 'a typed date in the site format is stored as Y-m-d', async () => {
		await boot( 'dd/mm/yy', '2026-12-10' );
		jQuery( visible() ).val( '24/12/2026' ).trigger( 'change' );

		expect( hidden().value ).toBe( '2026-12-24' );
	} );

	test( 'emptying the field clears the submitted value', async () => {
		await boot( 'dd/mm/yy', '2026-12-10' );
		jQuery( visible() ).val( '' ).trigger( 'change' );

		expect( hidden().value ).toBe( '' );
	} );

	test( 'text that is not a date shows the stored date again', async () => {
		await boot( 'dd/mm/yy', '2026-12-10' );
		jQuery( visible() ).val( 'next friday' ).trigger( 'change' );

		expect( visible().value ).toBe( '10/12/2026' );
		expect( hidden().value ).toBe( '2026-12-10' );
	} );

	test( 'value() and setValue() speak Y-m-d whatever the field shows', async () => {
		await boot( 'dd/mm/yy', '2026-12-10' );
		const api = window.mhmRentivaDatepicker;

		expect( api.value( visible() ) ).toBe( '2026-12-10' );

		api.setValue( visible(), '2027-01-05' );
		expect( visible().value ).toBe( '05/01/2027' );
		expect( hidden().value ).toBe( '2027-01-05' );

		// A date typed but not yet committed by the change handler is read as typed.
		visible().value = '07/01/2027';
		expect( api.value( visible() ) ).toBe( '2027-01-07' );

		api.setValue( visible(), '' );
		expect( visible().value ).toBe( '' );
		expect( hidden().value ).toBe( '' );
	} );

	test( 'init on a field twice does not split it twice', async () => {
		await boot( 'dd/mm/yy', '2026-12-10' );
		visible().classList.remove( 'hasDatepicker' );
		window.mhmRentivaDatepicker.init( visible() );

		expect( document.querySelectorAll( '[data-rvs-date-value]' ) ).toHaveLength( 1 );
		expect( submitted() ).toEqual( [ { name: 'mhmrentiva_pickup_date', value: '2026-12-10' } ] );
	} );
} );

/**
 * PR S Task 9 item 4: the server renders the pair already -- the visible field
 * in the site format (no Y-m-d flash before this script), the hidden Y-m-d
 * value named after it. The init adopts the pair instead of splitting again.
 */
describe( 'shared datepicker init: a pair the server rendered', () => {
	let jQuery;
	let calls;

	const options = () => calls.filter( ( c ) => c.args[ 0 ] && typeof c.args[ 0 ] === 'object' ).pop().args[ 0 ];

	const boot = async ( shown, value ) => {
		jest.resetModules();
		document.body.innerHTML = `
			<form>
				<input id="pickup" type="text" class="js-datepicker" value="${ shown }" data-rvs-pickup-date>
				<input type="hidden" name="mhmrentiva_pickup_date" value="${ value }" data-rvs-date-value>
			</form>`;
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		window.jQuery = jQuery;
		calls = install( jQuery );
		window.mhmDatepickerL10n = { dateFormat: 'dd/mm/yy', altFormat: 'yy-mm-dd', monthNames: MONTHS };
		require( '../../assets/js/core/datepicker-init.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	};

	const visible = () => document.getElementById( 'pickup' );
	const hidden = () => document.querySelector( 'input[type="hidden"][data-rvs-date-value]' );

	afterEach( () => {
		delete global.jQuery;
		delete window.mhmDatepickerL10n;
		delete window.mhmRentivaDatepicker;
	} );

	test( 'the rendered pair is kept as it is: one hidden value, the shown text untouched', async () => {
		await boot( '10/12/2026', '2026-12-10' );

		expect( document.querySelectorAll( '[data-rvs-date-value]' ) ).toHaveLength( 1 );
		expect( visible().value ).toBe( '10/12/2026' );
		expect( hidden().value ).toBe( '2026-12-10' );
		expect( jQuery( 'form' ).serializeArray() ).toEqual( [ { name: 'mhmrentiva_pickup_date', value: '2026-12-10' } ] );
		expect( options().altField.get( 0 ) ).toBe( hidden() );
	} );

	test( 'the API reads and writes the rendered pair', async () => {
		await boot( '10/12/2026', '2026-12-10' );
		const api = window.mhmRentivaDatepicker;

		expect( api.value( visible() ) ).toBe( '2026-12-10' );
		api.setValue( visible(), '2027-01-05' );
		expect( visible().value ).toBe( '05/01/2027' );
		expect( hidden().value ).toBe( '2027-01-05' );

		jQuery( visible() ).val( 'junk' ).trigger( 'change' );
		expect( visible().value ).toBe( '05/01/2027' );
	} );

	test( 'a rendered value without shown text is shown in the site format', async () => {
		await boot( '', '2026-12-10' );

		expect( visible().value ).toBe( '10/12/2026' );
		expect( hidden().value ).toBe( '2026-12-10' );
	} );
} );
