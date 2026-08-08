/**
 * @jest-environment jsdom
 */

describe( 'vehicle favorite accessibility state', () => {
	beforeEach( async () => {
		jest.resetModules();
		document.body.innerHTML = `
			<button class="mhm-card-favorite mhm-vehicle-favorite-btn is-active"
				data-vehicle-id="10"
				data-nonce="nonce"
				title="Remove from Favorites"
				aria-label="Remove from Favorites"
				aria-pressed="true">
				<span class="text-label">Remove from Favorites</span>
			</button>
		`;

		const jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;

		global.mhmrentiva_vars = {
			ajax_url: '/wp-admin/admin-ajax.php',
			nonce: 'fallback-nonce',
			favorites_page_url: '/favorites/',
			i18n: {
				adding_favorite: 'Adding to favorites...',
				removing_favorite: 'Removing from favorites...',
				added_favorite: 'Added to favorites.',
				removed_favorite: 'Removed from favorites.',
				add_favorite: 'Add to Favorites',
				remove_favorite: 'Remove from Favorites',
			},
		};
		window.mhmrentiva_vars = global.mhmrentiva_vars;

		global.MHMRentivaToast = { show: jest.fn().mockReturnValue( 'toast-1' ) };
		window.MHMRentivaToast = global.MHMRentivaToast;

		jQuery.ajax = jest.fn( ( options ) => {
			options.success( {
				success: true,
				data: { is_favorite: false },
			} );
		} );

		require( '../../assets/js/frontend/vehicle-interactions.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	} );

	afterEach( () => {
		delete global.jQuery;
		delete global.$;
		delete global.mhmrentiva_vars;
		delete global.MHMRentivaToast;
	} );

	test( 'removing a favorite synchronizes its accessible name and pressed state', () => {
		const button = document.querySelector( '.mhm-vehicle-favorite-btn' );

		jQuery( button ).trigger( 'click' );

		expect( button.querySelector( '.text-label' ).textContent ).toBe( 'Add to Favorites' );
		expect( button.getAttribute( 'aria-label' ) ).toBe( 'Add to Favorites' );
		expect( button.getAttribute( 'title' ) ).toBe( 'Add to Favorites' );
		expect( button.getAttribute( 'aria-pressed' ) ).toBe( 'false' );
	} );
} );

describe( 'vehicle compare accessibility state', () => {
	beforeEach( async () => {
		jest.resetModules();
		document.body.innerHTML = `
			<button class="mhm-card-compare mhm-vehicle-compare-btn"
				data-vehicle-id="10"
				data-nonce="nonce"
				title="Compare"
				aria-label="Compare"
				aria-pressed="false">
				<span class="text-label">Compare</span>
			</button>
		`;

		const jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;

		global.mhmrentiva_vars = {
			ajax_url: '/wp-admin/admin-ajax.php',
			nonce: 'fallback-nonce',
			compare_page_url: '/compare/',
			i18n: {
				adding_compare: 'Adding to comparison...',
				removing_compare: 'Removing from comparison...',
				added_to_compare: 'Added to comparison.',
				removed_from_compare: 'Removed from comparison.',
				add_compare: 'Compare',
				remove_compare: 'Remove Compare',
			},
		};
		window.mhmrentiva_vars = global.mhmrentiva_vars;

		global.MHMRentivaToast = { show: jest.fn().mockReturnValue( 'toast-1' ) };
		window.MHMRentivaToast = global.MHMRentivaToast;

		jQuery.ajax = jest.fn( ( options ) => {
			options.success( {
				success: true,
				data: { is_in_compare: true },
			} );
		} );

		require( '../../assets/js/frontend/vehicle-interactions.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	} );

	afterEach( () => {
		delete global.jQuery;
		delete global.$;
		delete global.mhmrentiva_vars;
		delete global.MHMRentivaToast;
	} );

	test( 'adding a vehicle to comparison synchronizes its accessible name and pressed state', () => {
		const button = document.querySelector( '.mhm-vehicle-compare-btn' );

		jQuery( button ).trigger( 'click' );

		expect( button.querySelector( '.text-label' ).textContent ).toBe( 'Remove Compare' );
		expect( button.getAttribute( 'aria-label' ) ).toBe( 'Remove Compare' );
		expect( button.getAttribute( 'title' ) ).toBe( 'Remove Compare' );
		expect( button.getAttribute( 'aria-pressed' ) ).toBe( 'true' );
	} );
} );
