/**
 * @jest-environment jsdom
 */

/**
 * A failed calendar navigation shows the localized error. PHP localizes it as
 * strings.error_occurred (VehicleDetails::get_localized_strings()); the script
 * read strings.error, which nothing provides, so every site showed a hardcoded
 * Turkish sentence.
 */
describe( 'vehicle details calendar error message', () => {
	let $;

	const boot = async ( ajaxOutcome ) => {
		jest.resetModules();
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		$.fx.off = true;
		$.ajax = jest.fn( ( opts ) => {
			ajaxOutcome( opts );
			opts.complete && opts.complete();
		} );

		document.body.innerHTML = `
			<div class="rv-mini-calendar-widget" data-vehicle-id="42">
				<button class="rv-calendar-nav-btn" data-direction="next">Next</button>
				<span id="rv-current-month-year"></span>
				<div id="rv-calendar-container"></div>
			</div>`;
		window.mhmRentivaVehicleDetails = {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'n',
			strings: { error_occurred: 'Bir hata oluştu' },
		};

		require( '../../assets/js/frontend/vehicle-details.js' );
		await new Promise( ( resolve ) => $( resolve ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		$( '.rv-calendar-nav-btn' ).trigger( 'click' );
	};

	test( 'a transport error shows strings.error_occurred', async () => {
		await boot( ( opts ) => opts.error() );

		expect( $( '.rv-calendar-error' ).text() ).toBe( 'Bir hata oluştu' );
	} );

	test( 'an unsuccessful response shows strings.error_occurred', async () => {
		await boot( ( opts ) => opts.success( { success: false } ) );

		expect( $( '.rv-calendar-error' ).text() ).toBe( 'Bir hata oluştu' );
	} );
} );
