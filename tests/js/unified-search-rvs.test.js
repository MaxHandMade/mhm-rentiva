/**
 * @jest-environment jsdom
 */

/**
 * Aşama 2 PR 3: the search strip keeps the return at least the minimum rental
 * after pickup, makes the return time follow the pickup time, and mirrors the
 * pickup location into the drop-off field.
 */
describe( 'rvs search strip', () => {
	let jQuery;

	const strip = () => `
		<div class="rvs-search" data-rvs-search>
			<form>
				<select data-rvs-pickup-location><option value=""></option><option value="7">Airport</option></select>
				<input data-rvs-pickup-date value="2027-07-12">
				<select data-rvs-pickup-time><option value="10:00" selected>10:00</option><option value="14:30">14:30</option></select>
				<input data-rvs-return-date value="2027-07-15">
				<span data-rvs-return-time-display>10:00</span>
				<input type="hidden" data-rvs-return-time value="10:00">
				<select data-rvs-dropoff disabled><option value=""></option><option value="7">Airport</option></select>
				<input type="hidden" data-rvs-dropoff-value value="">
			</form>
		</div>`;

	const boot = async ( min ) => {
		jest.resetModules();
		document.body.innerHTML = strip();
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		window.jQuery = jQuery;
		window.mhmUnifiedSearch = { settings: { minRentalDays: min } };
		require( '../../assets/js/frontend/unified-search.js' );
		// The return rule is the shared datepicker API's (no widget here: plain fields).
		require( '../../assets/js/core/datepicker-init.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	};

	const change = ( selector, value ) => {
		const el = document.querySelector( selector );
		el.value = value;
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};

	afterEach( () => {
		delete global.jQuery;
		delete window.mhmUnifiedSearch;
		delete window.mhmRentivaDatepicker;
	} );

	test( 'a later pickup moves the return up to the minimum rental', async () => {
		await boot( 2 );
		change( '[data-rvs-pickup-date]', '2027-07-20' );

		expect( document.querySelector( '[data-rvs-return-date]' ).value ).toBe( '2027-07-22' );
	} );

	test( 'a return that already satisfies the minimum is kept', async () => {
		await boot( 1 );
		change( '[data-rvs-pickup-date]', '2027-07-13' );

		expect( document.querySelector( '[data-rvs-return-date]' ).value ).toBe( '2027-07-15' );
	} );

	test( 'the return time follows the pickup time', async () => {
		await boot( 1 );
		change( '[data-rvs-pickup-time]', '14:30' );

		expect( document.querySelector( '[data-rvs-return-time-display]' ).textContent ).toBe( '14:30' );
		expect( document.querySelector( '[data-rvs-return-time]' ).value ).toBe( '14:30' );
	} );

	test( 'the drop-off mirrors the pickup location', async () => {
		await boot( 1 );
		change( '[data-rvs-pickup-location]', '7' );

		expect( document.querySelector( '[data-rvs-dropoff]' ).value ).toBe( '7' );
		expect( document.querySelector( '[data-rvs-dropoff-value]' ).value ).toBe( '7' );
	} );
} );

/**
 * PR S Task 8 item 2: the date fields show the site's format (shared datepicker
 * init) and submit Y-m-d; the strip still keeps the return after pickup.
 */
describe( 'rvs search strip with dates in the site format', () => {
	const { install } = require( './support/fake-datepicker' );
	let jQuery;

	const boot = async () => {
		jest.resetModules();
		document.body.innerHTML = `
			<div class="rvs-search" data-rvs-search>
				<form>
					<input class="js-datepicker" name="mhmrentiva_pickup_date" data-rvs-pickup-date value="2027-07-12">
					<select data-rvs-pickup-time><option value="10:00" selected>10:00</option></select>
					<input class="js-datepicker" name="mhmrentiva_return_date" data-rvs-return-date value="2027-07-15">
				</form>
			</div>`;
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		window.jQuery = jQuery;
		install( jQuery );
		window.mhmDatepickerL10n = { dateFormat: 'dd/mm/yy', altFormat: 'yy-mm-dd' };
		window.mhmUnifiedSearch = { settings: { minRentalDays: 2 } };
		require( '../../assets/js/frontend/unified-search.js' );
		require( '../../assets/js/core/datepicker-init.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	};

	afterEach( () => {
		delete global.jQuery;
		delete window.mhmUnifiedSearch;
		delete window.mhmDatepickerL10n;
		delete window.mhmRentivaDatepicker;
	} );

	const submitted = () => {
		const out = {};
		jQuery( 'form' ).serializeArray().forEach( ( item ) => {
			out[ item.name ] = item.value;
		} );
		return out;
	};

	test( 'a later pickup typed in the site format moves the return up', async () => {
		await boot();
		const pickup = document.querySelector( '[data-rvs-pickup-date]' );
		const ret = document.querySelector( '[data-rvs-return-date]' );
		expect( pickup.value ).toBe( '12/07/2027' );

		// unified-search.js binds its change handler before the init's.
		pickup.value = '20/07/2027';
		pickup.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( ret.value ).toBe( '22/07/2027' );
		expect( submitted() ).toEqual( {
			mhmrentiva_pickup_date: '2027-07-20',
			mhmrentiva_return_date: '2027-07-22',
		} );
	} );

	test( 'the return picker gets its minimum as a date', async () => {
		await boot();
		const pickup = document.querySelector( '[data-rvs-pickup-date]' );
		const calls = [];
		jQuery.fn.datepicker = function ( ...args ) {
			calls.push( args );
			return this;
		};
		pickup.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		const min = calls.find( ( args ) => args[ 0 ] === 'option' && args[ 1 ] === 'minDate' );
		expect( min[ 2 ] ).toEqual( new Date( 2027, 6, 14 ) );
	} );
} );
