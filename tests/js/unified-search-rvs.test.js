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
