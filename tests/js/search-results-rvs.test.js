/**
 * @jest-environment jsdom
 */

/**
 * Aşama 2 PR 3: the results page script -- filters, chips, sort and pages
 * reload over AJAX with the window and every filter; the server's partials
 * replace the header, chips and grid; the mobile drawer is a modal dialog;
 * the comparison band follows compare changes.
 */
describe( 'rvs search results', () => {
	let jQuery;

	const page = () => `
		<header id="site-head"><a href="/" id="home">Home</a></header>
		<div class="rvs-shell" data-rvs-shortcode="rentiva_search_results">
			<div class="rvs-results" data-rvs-results data-rv-instance="r1" data-show-booking-btn="0" data-results-per-page="6" data-default-view="grid" data-default-sort="price_asc">
				<form data-rvs-window hidden>
					<input type="hidden" name="mhmrentiva_pickup_date" value="2027-07-12">
					<input type="hidden" name="mhmrentiva_return_date" value="2027-07-15">
					<input type="hidden" name="mhmrentiva_keyword" value="golf">
				</form>
				<div class="rvs-results__mobilebar">
					<button type="button" id="opener" data-rvs-drawer-open aria-expanded="false">Filters (<span data-rvs-filter-count>1</span>)</button>
				</div>
				<div class="rvs-results__layout">
					<aside id="r1-filters" class="rvs-results__filters" data-rvs-drawer>
						<div class="rvs-results__filters-head">
							<h2 class="rvs-results__filters-title">Filter</h2>
							<button type="button" id="close" data-rvs-drawer-close>x</button>
						</div>
						<form data-rvs-filters>
							<label><input type="checkbox" id="cat5" name="mhmrentiva_category[]" value="5" checked> SUV</label>
							<label><input type="checkbox" id="cat6" name="mhmrentiva_category[]" value="6"> Sedan</label>
							<input type="number" id="minp" name="mhmrentiva_min_price" value="100">
						</form>
						<div class="rvs-results__drawer-foot">
							<button type="button" id="apply" data-rvs-drawer-apply>Show <span data-rvs-apply-count>4</span></button>
						</div>
					</aside>
					<section class="rvs-results__main">
						<p data-rvs-summary><strong>4 vehicles</strong></p>
						<select data-rvs-sort name="mhmrentiva_sort"><option value="price_asc" selected>a</option><option value="price_desc">b</option></select>
						<button type="button" data-rvs-view="grid" aria-pressed="true">g</button>
						<button type="button" data-rvs-view="list" aria-pressed="false">l</button>
						<div data-rvs-chips><button type="button" data-rvs-chip data-name="mhmrentiva_category[]" data-value="5">SUV</button></div>
						<div data-rvs-notice></div>
						<div class="rvs-card-grid" data-rvs-grid><article class="rvs-card rvs-card--grid" data-rvs-card></article></div>
						<nav data-rvs-pagination><a href="?page=2" data-rvs-page="2">2</a></nav>
					</section>
				</div>
				<div data-rvs-compare-band data-count="0" data-max="3">
					<span data-rvs-band-count></span><span data-rvs-band-names></span>
				</div>
			</div>
		</div>`;

	const boot = async ( response ) => {
		jest.resetModules();
		document.body.innerHTML = page();
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;
		window.mhmRentivaSearchResults = {
			ajax_url: '/wp-admin/admin-ajax.php',
			nonce: 'n1',
			i18n: { band_one: '%1$s selected of %2$s', band_many: '%1$s selected of %2$s' },
		};
		jQuery.ajax = jest.fn( ( options ) => {
			if ( response ) {
				response( options );
			}
			if ( options.complete ) {
				options.complete();
			}
		} );
		require( '../../assets/js/frontend/search-results.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	};

	afterEach( () => {
		delete global.jQuery;
		delete global.$;
		delete window.mhmRentivaSearchResults;
	} );

	test( 'a filter change posts the form fields with the window and the toggles', async () => {
		await boot();
		const box = document.getElementById( 'cat6' );
		box.checked = true;
		box.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( jQuery.ajax ).toHaveBeenCalledTimes( 1 );
		const data = jQuery.ajax.mock.calls[ 0 ][ 0 ].data;
		expect( data.action ).toBe( 'mhmrentiva_filter_results' );
		expect( data.mhmrentiva_category ).toEqual( [ '5', '6' ] );
		expect( data.mhmrentiva_pickup_date ).toBe( '2027-07-12' );
		expect( data.mhmrentiva_sort ).toBe( 'price_asc' );
		expect( data.show_booking_button ).toBe( '0' );
		expect( data.per_page ).toBe( 6 );
		expect( data.page ).toBe( 1 );
	} );

	test( 'without the sort control the default sort and the keyword still go along', async () => {
		await boot();
		document.querySelector( '[data-rvs-sort]' ).remove();
		document.querySelector( '[data-rvs-page]' ).click();

		const data = jQuery.ajax.mock.calls[ 0 ][ 0 ].data;
		expect( data.mhmrentiva_sort ).toBe( 'price_asc' );
		expect( data.mhmrentiva_keyword ).toBe( 'golf' );
		expect( data.mhmrentiva_pickup_date ).toBe( '2027-07-12' );
	} );

	test( 'the keyword chip and clear remove the keyword', async () => {
		await boot();
		const chips = document.querySelector( '[data-rvs-chips]' );
		chips.insertAdjacentHTML( 'beforeend', '<button type="button" id="kw" data-rvs-chip data-name="mhmrentiva_keyword" data-value="golf">golf</button>' );
		document.getElementById( 'kw' ).click();

		// The carried keyword input is gone: no keyword reaches the search.
		expect( jQuery.ajax.mock.calls[ 0 ][ 0 ].data.mhmrentiva_keyword ).toBeUndefined();
		expect( jQuery.ajax.mock.calls[ 0 ][ 0 ].data.mhmrentiva_pickup_date ).toBe( '2027-07-12' );
	} );

	test( 'a chip for a carried filter drops only that value', async () => {
		await boot();
		const win = document.querySelector( '[data-rvs-window]' );
		win.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="mhmrentiva_fuel_type[]" value="diesel"><input type="hidden" name="mhmrentiva_fuel_type[]" value="petrol">' );
		document.querySelector( '[data-rvs-chips]' ).insertAdjacentHTML( 'beforeend', '<button type="button" id="fuel" data-rvs-chip data-name="mhmrentiva_fuel_type[]" data-value="diesel">Diesel</button>' );
		document.getElementById( 'fuel' ).click();

		expect( jQuery.ajax.mock.calls[ 0 ][ 0 ].data.mhmrentiva_fuel_type ).toEqual( [ 'petrol' ] );
	} );

	test( 'a chip click clears its input and reloads', async () => {
		await boot();
		document.querySelector( '[data-rvs-chip]' ).click();

		expect( document.getElementById( 'cat5' ).checked ).toBe( false );
		expect( jQuery.ajax.mock.calls[ 0 ][ 0 ].data.mhmrentiva_category ).toBeUndefined();
	} );

	test( 'clear resets every filter but keeps the window', async () => {
		document.body.innerHTML = '';
		await boot();
		const clear = document.createElement( 'button' );
		clear.setAttribute( 'data-rvs-clear', '' );
		document.querySelector( '[data-rvs-results]' ).appendChild( clear );
		clear.click();

		const data = jQuery.ajax.mock.calls[ 0 ][ 0 ].data;
		expect( data.mhmrentiva_category ).toBeUndefined();
		expect( data.mhmrentiva_min_price ).toBe( '' );
		expect( data.mhmrentiva_keyword ).toBe( '' );
		expect( data.mhmrentiva_return_date ).toBe( '2027-07-15' );
	} );

	test( 'a page button reloads that page with the current filters', async () => {
		await boot();
		Element.prototype.scrollIntoView = jest.fn();
		document.querySelector( '[data-rvs-page]' ).click();

		const data = jQuery.ajax.mock.calls[ 0 ][ 0 ].data;
		expect( data.page ).toBe( 2 );
		expect( data.mhmrentiva_category ).toEqual( [ '5' ] );
	} );

	test( 'the response replaces summary, chips, grid, pagination and counts', async () => {
		await boot( ( o ) => o.success( {
			success: true,
			data: {
				html: '<article class="rvs-card rvs-card--grid" data-rvs-card id="new"></article>',
				pagination: '',
				summary_html: '<strong>1 vehicle</strong>',
				chips_html: '',
				notice_html: '<div data-rvs-empty="not_searched"></div>',
				meta: { total: 1, active_filters: 0 },
			},
		} ) );
		document.querySelector( '[data-rvs-view="list"]' ).click();
		document.getElementById( 'cat6' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( document.querySelector( '[data-rvs-summary]' ).textContent ).toBe( '1 vehicle' );
		expect( document.querySelector( '[data-rvs-chips]' ).innerHTML ).toBe( '' );
		expect( document.querySelector( '[data-rvs-filter-count]' ).textContent ).toBe( '0' );
		expect( document.querySelector( '[data-rvs-apply-count]' ).textContent ).toBe( '1' );
		expect( document.querySelector( '[data-rvs-notice] [data-rvs-empty]' ) ).not.toBeNull();
		// The list view chosen before the reload stays on the new cards.
		expect( document.getElementById( 'new' ).classList.contains( 'rvs-card--list' ) ).toBe( true );
		expect( document.querySelector( '[data-rvs-grid]' ).classList.contains( 'rvs-card-list' ) ).toBe( true );
		expect( jQuery.ajax.mock.calls[ 0 ][ 0 ].data.layout ).toBe( 'list' );
	} );

	test( 'opening the drawer makes it a modal dialog and focuses its title', async () => {
		await boot();
		document.getElementById( 'opener' ).click();
		const drawer = document.querySelector( '[data-rvs-drawer]' );

		expect( drawer.getAttribute( 'role' ) ).toBe( 'dialog' );
		expect( drawer.getAttribute( 'aria-modal' ) ).toBe( 'true' );
		expect( drawer.classList.contains( 'is-open' ) ).toBe( true );
		expect( document.activeElement ).toBe( drawer.querySelector( '.rvs-results__filters-title' ) );
		expect( document.getElementById( 'opener' ).getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	} );

	test( 'the background is inert (or hidden) while the drawer is open', async () => {
		await boot();
		document.getElementById( 'opener' ).click();
		const head = document.getElementById( 'site-head' );
		const main = document.querySelector( '.rvs-results__main' );
		const blocked = ( el ) => el.inert === true || el.getAttribute( 'aria-hidden' ) === 'true';

		expect( blocked( head ) ).toBe( true );
		expect( blocked( main ) ).toBe( true );
		expect( blocked( document.querySelector( '[data-rvs-drawer]' ) ) ).toBe( false );

		document.getElementById( 'close' ).click();
		expect( blocked( head ) ).toBe( false );
		expect( blocked( main ) ).toBe( false );
	} );

	test( 'Tab wraps inside the drawer', async () => {
		await boot();
		document.getElementById( 'opener' ).click();
		document.getElementById( 'apply' ).focus();
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Tab', bubbles: true } ) );

		expect( document.activeElement ).toBe( document.getElementById( 'close' ) );

		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Tab', shiftKey: true, bubbles: true } ) );
		expect( document.activeElement ).toBe( document.getElementById( 'apply' ) );
	} );

	test( 'Escape closes the drawer and returns focus to the opener', async () => {
		await boot();
		const opener = document.getElementById( 'opener' );
		opener.click();
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
		const drawer = document.querySelector( '[data-rvs-drawer]' );

		expect( drawer.classList.contains( 'is-open' ) ).toBe( false );
		expect( drawer.hasAttribute( 'role' ) ).toBe( false );
		expect( document.activeElement ).toBe( opener );
		expect( opener.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	test( 'the apply button closes the drawer', async () => {
		await boot();
		document.getElementById( 'opener' ).click();
		document.getElementById( 'apply' ).click();

		expect( document.querySelector( '[data-rvs-drawer]' ).classList.contains( 'is-open' ) ).toBe( false );
	} );

	test( 'the comparison band shows the count, limit and names after a compare change', async () => {
		await boot();
		const band = document.querySelector( '[data-rvs-compare-band]' );
		expect( band.getAttribute( 'data-count' ) ).toBe( '0' );

		jQuery( document ).trigger( 'mhm-rentiva:compare-changed', [ { items: [ { id: 1, title: 'Tucson' }, { id: 2, title: '3008' } ], max: 3 } ] );

		expect( band.getAttribute( 'data-count' ) ).toBe( '2' );
		expect( band.querySelector( '[data-rvs-band-count]' ).textContent ).toBe( '2 selected of 3' );
		expect( band.querySelector( '[data-rvs-band-names]' ).textContent ).toBe( 'Tucson, 3008' );
	} );
} );
