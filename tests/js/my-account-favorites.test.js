/**
 * @jest-environment jsdom
 *
 * PR D, Task 2: "Clear all favorites" on the account favorites page
 * (templates/account/favorites.php) removes the cards and shows the page's
 * own empty state, which the template drew hidden.
 */
describe( 'my-account favorites clear all', () => {
	let $;

	beforeAll( async () => {
		$ = require( 'jquery' );
		$.fx.off = true;
		global.jQuery = window.jQuery = $;
		window.mhmRentivaAccount = {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'n',
			i18n: { error: 'err', favoritesCleared: 'cleared' },
		};
		document.body.innerHTML = '<div class="mhm-rentiva-account-page"></div>';
		require( '../../assets/js/frontend/my-account.js' );
		await new Promise( ( r ) => $( r ) );
	} );

	const PAGE =
		'<div class="rvs-shell rvs-account mhm-rentiva-account-page" data-rvs-favorites>' +
		'<p data-rvs-favorites-count>2 vehicles in your favorites</p>' +
		'<div data-rvs-favorites-empty hidden><h3>No favorite vehicles yet</h3></div>' +
		'<div data-rvs-favorites-list>' +
		'<article class="rvs-card" data-rvs-card></article><article class="rvs-card" data-rvs-card></article>' +
		'<button type="button" id="clear-all-favorites">Clear all favorites</button>' +
		'</div></div>';

	test( 'clear_all_shows_the_empty_state', () => {
		document.body.innerHTML = PAGE;
		window.MHMRentivaToast = { show: jest.fn() };
		$.ajax = jest.fn( () =>
			$.Deferred().resolve( { success: true, data: {} } ).promise()
		);

		document
			.querySelector( '#clear-all-favorites' )
			.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );

		expect( $.ajax ).toHaveBeenCalledTimes( 1 );
		expect( $.ajax.mock.calls[ 0 ][ 0 ].data.action ).toBe( 'mhmrentiva_clear_favorites' );
		expect( document.querySelector( '[data-rvs-favorites-list]' ) ).toBeNull();
		expect( document.querySelector( '[data-rvs-favorites-empty]' ).hidden ).toBe( false );
		expect( document.querySelector( '[data-rvs-favorites-count]' ) ).toBeNull();
		// Only the success toast; no error toast before the request.
		expect( window.MHMRentivaToast.show ).toHaveBeenCalledTimes( 1 );
		expect( window.MHMRentivaToast.show.mock.calls[ 0 ][ 1 ].type ).toBe( 'success' );
	} );
} );
