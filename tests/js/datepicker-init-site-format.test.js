/**
 * @jest-environment jsdom
 */

/**
 * PR S: a `.js-datepicker` shows the site's date format and
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
 * The server renders the pair already -- the visible field in the site format
 * (no Y-m-d flash before this script) carrying the field name, the hidden Y-m-d
 * value unnamed right after it. Without JavaScript the visible text is what
 * the form submits (the server parses it in the site format); the init hands
 * the name to the hidden Y-m-d field and adopts the pair instead of splitting
 * again.
 */
describe( 'shared datepicker init: a pair the server rendered', () => {
	let jQuery;
	let calls;

	const options = () => calls.filter( ( c ) => c.args[ 0 ] && typeof c.args[ 0 ] === 'object' ).pop().args[ 0 ];

	// The markup the templates print (unified-search.php, booking-card.php).
	const rendered = ( shown, value ) => `
		<form>
			<input id="pickup" type="text" class="js-datepicker" name="mhmrentiva_pickup_date" value="${ shown }" data-rvs-pickup-date>
			<input type="hidden" value="${ value }" data-rvs-date-value>
		</form>`;

	// A page cached before the name moved to the visible field.
	const renderedHiddenNamed = ( shown, value ) => `
		<form>
			<input id="pickup" type="text" class="js-datepicker" value="${ shown }" data-rvs-pickup-date>
			<input type="hidden" name="mhmrentiva_pickup_date" value="${ value }" data-rvs-date-value>
		</form>`;

	const boot = async ( shown, value, markup = rendered ) => {
		jest.resetModules();
		document.body.innerHTML = markup( shown, value );
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

	test( 'without the script the visible text is submitted under the field name', () => {
		document.body.innerHTML = rendered( '10/12/2026', '2026-12-10' );
		const $ = require( 'jquery' );

		expect( $( 'form' ).serializeArray() ).toEqual( [ { name: 'mhmrentiva_pickup_date', value: '10/12/2026' } ] );
	} );

	test( 'the init hands the field name from the visible field to the hidden Y-m-d one', async () => {
		await boot( '10/12/2026', '2026-12-10' );

		expect( visible().hasAttribute( 'name' ) ).toBe( false );
		expect( hidden().getAttribute( 'name' ) ).toBe( 'mhmrentiva_pickup_date' );
		expect( jQuery( 'form' ).serializeArray() ).toEqual( [ { name: 'mhmrentiva_pickup_date', value: '2026-12-10' } ] );
	} );

	test( 'a cached pair with the name on the hidden field is adopted as it is', async () => {
		await boot( '10/12/2026', '2026-12-10', renderedHiddenNamed );

		expect( document.querySelectorAll( '[data-rvs-date-value]' ) ).toHaveLength( 1 );
		expect( visible().hasAttribute( 'name' ) ).toBe( false );
		expect( jQuery( 'form' ).serializeArray() ).toEqual( [ { name: 'mhmrentiva_pickup_date', value: '2026-12-10' } ] );
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

/**
 * The one return-date rule the search strip and the
 * vehicle card share -- the return is at least pickup + the minimum rental.
 */
describe( 'shared datepicker API: constrainReturn', () => {
	let jQuery;
	let calls;

	const boot = async ( pickup, ret ) => {
		jest.resetModules();
		document.body.innerHTML = `
			<form>
				<input id="p" class="js-datepicker" name="pickup_date" value="${ pickup }">
				<input id="r" class="js-datepicker" name="return_date" value="${ ret }">
			</form>`;
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		window.jQuery = jQuery;
		calls = install( jQuery );
		window.mhmDatepickerL10n = { dateFormat: 'dd/mm/yy', altFormat: 'yy-mm-dd', monthNames: MONTHS };
		require( '../../assets/js/core/datepicker-init.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	};

	const api = () => window.mhmRentivaDatepicker;
	const p = () => document.getElementById( 'p' );
	const r = () => document.getElementById( 'r' );

	afterEach( () => {
		delete global.jQuery;
		delete window.mhmDatepickerL10n;
		delete window.mhmRentivaDatepicker;
	} );

	test( 'a return before pickup + minimum moves up to it, and the picker learns the minimum', async () => {
		await boot( '2027-07-20', '2027-07-15' );
		api().constrainReturn( p(), r(), 2 );

		expect( api().value( r() ) ).toBe( '2027-07-22' );
		expect( r().value ).toBe( '22/07/2027' );
		const min = calls.filter( ( c ) => c.el === r() && c.args[ 0 ] === 'option' && c.args[ 1 ] === 'minDate' ).pop();
		expect( min.args[ 2 ] ).toEqual( new Date( 2027, 6, 22 ) );
	} );

	test( 'a later return is kept; a month end rolls over', async () => {
		await boot( '2027-07-31', '2027-08-05' );
		api().constrainReturn( p(), r(), 1 );
		expect( api().value( r() ) ).toBe( '2027-08-05' );

		api().setValue( r(), '2027-07-30' );
		api().constrainReturn( p(), r(), 1 );
		expect( api().value( r() ) ).toBe( '2027-08-01' );
	} );

	test( 'no pickup: nothing moves; no minimum given: one day', async () => {
		await boot( '', '2027-07-15' );
		api().constrainReturn( p(), r(), 2 );
		expect( api().value( r() ) ).toBe( '2027-07-15' );

		api().setValue( p(), '2027-07-15' );
		api().constrainReturn( p(), r() );
		expect( api().value( r() ) ).toBe( '2027-07-16' );
	} );

	test( 'an empty return stays empty (dates may be optional)', async () => {
		await boot( '2027-07-20', '' );
		api().constrainReturn( p(), r(), 1 );
		expect( api().value( r() ) ).toBe( '' );
	} );
} );
