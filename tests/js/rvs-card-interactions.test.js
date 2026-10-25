/**
 * @jest-environment jsdom
 */

/**
 * Aşama 2 PR 2: the front-end standard card's favourite button and compare
 * checkbox go through vehicle-interactions.js -- one request per action, the
 * state follows the server, a failure reverts, and on the favourites page a
 * removed card leaves the grid.
 */
describe( 'rvs card interactions', () => {
	let jQuery;

	const card = ( extra = '' ) => `
		<div class="rvs-shell" data-rvs-shortcode="rentiva_my_favorites">
			<div class="mhm-my-favorites-container rv-my-favorites-wrapper rvs-listing ${ extra }">
				<div class="rvs-card-grid">
					<article class="rvs-card rvs-card--grid" data-rvs-card data-id="10">
						<button type="button" class="rvs-card__fav mhm-vehicle-favorite-btn is-active" data-rvs-fav data-vehicle-id="10" data-nonce="n1" aria-pressed="true" aria-label="Remove from favorites"></button>
						<label class="rvs-check rvs-card__compare"><input type="checkbox" data-rvs-compare data-vehicle-id="10" data-nonce="n2"> <span>Compare</span></label>
					</article>
					<article class="rvs-card rvs-card--grid" data-rvs-card data-id="11"></article>
				</div>
			</div>
		</div>`;

	const boot = async ( html, response ) => {
		jest.resetModules();
		document.body.innerHTML = html;
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;
		global.mhmrentiva_vars = { ajax_url: '/wp-admin/admin-ajax.php', nonce: 'fallback', i18n: {} };
		window.mhmrentiva_vars = global.mhmrentiva_vars;
		global.MHMRentivaToast = { show: jest.fn() };
		window.MHMRentivaToast = global.MHMRentivaToast;
		jQuery.fn.fadeOut = function ( duration, done ) {
			this.each( function () {
				done.call( this );
			} );
			return this;
		};
		jQuery.ajax = jest.fn( ( options ) => response( options ) );
		require( '../../assets/js/frontend/vehicle-interactions.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	};

	afterEach( () => {
		delete global.jQuery;
		delete global.$;
		delete global.mhmrentiva_vars;
		delete global.MHMRentivaToast;
	} );

	test( 'checking compare sends one toggle request and follows the server', async () => {
		await boot( card(), ( o ) => o.success( { success: true, data: { is_in_compare: true } } ) );
		const box = document.querySelector( 'input[data-rvs-compare]' );

		box.checked = true;
		jQuery( box ).trigger( 'change' );

		expect( jQuery.ajax ).toHaveBeenCalledTimes( 1 );
		expect( jQuery.ajax.mock.calls[ 0 ][ 0 ].data ).toEqual( { action: 'mhmrentiva_toggle_compare', vehicle_id: 10, nonce: 'n2' } );
		expect( box.checked ).toBe( true );
	} );

	test( 'a refused compare reverts the checkbox', async () => {
		await boot( card(), ( o ) => o.success( { success: false, data: { message: 'Limit reached' } } ) );
		const box = document.querySelector( 'input[data-rvs-compare]' );

		box.checked = true;
		jQuery( box ).trigger( 'change' );

		expect( box.checked ).toBe( false );
		expect( global.MHMRentivaToast.show ).toHaveBeenLastCalledWith( 'Limit reached', { type: 'error' } );
	} );

	test( 'a network error reverts the checkbox', async () => {
		await boot( card(), ( o ) => o.error() );
		const box = document.querySelector( 'input[data-rvs-compare]' );

		box.checked = true;
		jQuery( box ).trigger( 'change' );

		expect( box.checked ).toBe( false );
	} );

	test( 'removing a favourite on the favourites page removes the rvs card', async () => {
		await boot( card(), ( o ) => o.success( { success: true, data: { is_favorite: false } } ) );
		const fav = document.querySelector( '[data-rvs-fav]' );

		jQuery( fav ).trigger( 'click' );

		expect( jQuery.ajax ).toHaveBeenCalledTimes( 1 );
		expect( document.querySelector( '[data-rvs-card][data-id="10"]' ) ).toBeNull();
		expect( document.querySelector( '[data-rvs-card][data-id="11"]' ) ).not.toBeNull();
	} );
} );
