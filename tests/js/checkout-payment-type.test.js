/**
 * @jest-environment jsdom
 */

/**
 * PR K, R11: the payment type cards are `rvs-option`s found through
 * data-rvs-payment-option. WooCommerce re-renders them after update_checkout,
 * so the selected state comes back on `updated_checkout`, and a change still
 * posts the type and asks for that update.
 */
describe( 'checkout payment type cards', () => {
	let $;

	const card = ( value, checked ) => `
		<label class="rvs-option" data-rvs-payment-option>
			<input type="radio" name="mhmrentiva_booking_payment_type" value="${ value }" ${ checked ? 'checked' : '' }>
		</label>`;

	const page = ( first = true ) => `
		<div id="cards">${ card( 'deposit', first ) }${ card( 'full', ! first ) }</div>`;

	const selected = () =>
		$( '.rvs-option--selected input' )
			.map( ( _, el ) => el.value )
			.get();

	beforeEach( async () => {
		jest.resetModules();
		document.body.innerHTML = page();
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		$.post = jest.fn();
		$.ajax = jest.fn( ( options ) => options.success && options.success( { success: true } ) );
		window.mhmCheckoutPaymentType = { ajaxUrl: '/ajax', nonce: 'n' };
		$( document ).off();
		$( document.body ).off();
		require( '../../assets/js/frontend/checkout-payment-type.js' );
		await new Promise( ( resolve ) => $( resolve ) );
	} );

	test( 'the checked card is selected on load and its type is synced once', () => {
		expect( selected() ).toEqual( [ 'deposit' ] );
		expect( $.post ).toHaveBeenCalledTimes( 1 );
		expect( $.post.mock.calls[ 0 ][ 1 ] ).toMatchObject( {
			action: 'mhmrentiva_update_booking_payment_type',
			payment_type: 'deposit',
			nonce: 'n',
		} );
	} );

	test( 'a change moves the selected state, posts the type and updates the checkout', () => {
		const updates = jest.fn();
		$( 'body' ).on( 'update_checkout', updates );

		$( 'input[value="full"]' ).prop( 'checked', true ).trigger( 'change' );

		expect( selected() ).toEqual( [ 'full' ] );
		expect( $.ajax.mock.calls[ 0 ][ 0 ].data ).toMatchObject( { payment_type: 'full', nonce: 'n' } );
		expect( updates ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'cards drawn anew after update_checkout get their state back and keep working', () => {
		document.getElementById( 'cards' ).outerHTML = page( false );
		expect( selected() ).toEqual( [] );

		$( document.body ).trigger( 'updated_checkout' );
		expect( selected() ).toEqual( [ 'full' ] );

		$( 'input[value="deposit"]' ).prop( 'checked', true ).trigger( 'change' );
		expect( selected() ).toEqual( [ 'deposit' ] );
	} );

	test( 'the script no longer looks for the old class', () => {
		const source = require( 'fs' ).readFileSync(
			require( 'path' ).join( __dirname, '../../assets/js/frontend/checkout-payment-type.js' ),
			'utf8'
		);
		expect( source ).not.toContain( 'mhm-payment-type-option' );
		expect( source ).toContain( '[data-rvs-payment-option]' );
	} );
} );
