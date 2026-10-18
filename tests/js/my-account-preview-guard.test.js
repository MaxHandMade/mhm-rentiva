/**
 * @jest-environment jsdom
 */
describe( 'my-account preview-root guards', () => {
	let $;

	beforeAll( async () => {
		$ = require( 'jquery' );
		global.jQuery = window.jQuery = $;
		window.mhmRentivaAccount = {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'n',
			uploadNonce: 'u',
			restNonce: 'r',
			i18n: {
				error: 'err',
				favoritesCleared: 'cleared',
				uploading: 'Uploading',
				upload_success: 'ok',
				upload_error: 'upload err',
				confirm_remove_receipt: 'sure?',
				confirm: 'sure?',
				loading: 'Loading',
				success: 'done',
			},
		};
		// FavoritesModule.init binds only if the wrapper exists at ready time;
		// the handlers are delegated on document, so later body swaps keep working.
		document.body.innerHTML = '<div class="mhm-rentiva-account-page"></div>';
		require( '../../assets/js/frontend/my-account.js' );
		await new Promise( ( r ) => $( r ) );
	} );

	const markup = ( inner ) =>
		'<div class="mhm-rentiva-account-page">' + inner + '</div>';
	const BODY =
		'<button id="clear-all-favorites">x</button>' +
		'<label><input type="file" class="mhm-upload-receipt" data-booking-id="9001">Upload</label>' +
		'<div class="receipt-wrapper"><button class="remove-receipt-btn" data-booking-id="9001">rm</button></div>';

	const setup = ( preview ) => {
		document.body.innerHTML = preview
			? '<div data-rv-render="preview">' + markup( BODY ) + '</div>'
			: markup( BODY );
		window.MHMRentivaToast = { show: jest.fn() };
		window.confirm = jest.fn( () => true );
		$.ajax = jest.fn( () =>
			$.Deferred().resolve( { success: true, data: {} } ).promise()
		);
		const input = document.querySelector( '.mhm-upload-receipt' );
		Object.defineProperty( input, 'files', {
			value: [ new File( [ 'x' ], 'r.png', { type: 'image/png' } ) ],
		} );
		$.ajax.mockClear();
	};

	const click = ( sel ) =>
		document
			.querySelector( sel )
			.dispatchEvent(
				new MouseEvent( 'click', { bubbles: true, cancelable: true } )
			);
	const change = () =>
		document
			.querySelector( '.mhm-upload-receipt' )
			.dispatchEvent( new Event( 'change', { bubbles: true } ) );

	test( 'clear_favorites_inert_in_preview', () => {
		setup( true );
		click( '#clear-all-favorites' );
		expect( $.ajax ).toHaveBeenCalledTimes( 0 );
	} );

	test( 'receipt_change_inert_in_preview', () => {
		setup( true );
		change();
		expect( $.ajax ).toHaveBeenCalledTimes( 0 );
	} );

	test( 'remove_receipt_inert_in_preview', () => {
		setup( true );
		click( '.remove-receipt-btn' );
		expect( window.confirm ).toHaveBeenCalledTimes( 0 );
		expect( $.ajax ).toHaveBeenCalledTimes( 0 );
	} );

	test( 'outside_preview_reaches_ajax', () => {
		setup( false );
		click( '#clear-all-favorites' );
		expect( $.ajax ).toHaveBeenCalledTimes( 1 );
		expect( $.ajax.mock.calls[ 0 ][ 0 ].data.action ).toBe(
			'mhmrentiva_clear_favorites'
		);

		$.ajax.mockClear();
		change();
		expect( $.ajax ).toHaveBeenCalledTimes( 1 );
		expect( $.ajax.mock.calls[ 0 ][ 0 ].data.get( 'action' ) ).toBe(
			'mhmrentiva_upload_receipt'
		);

		$.ajax.mockClear();
		click( '.remove-receipt-btn' );
		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect( $.ajax ).toHaveBeenCalledTimes( 1 );
		expect( $.ajax.mock.calls[ 0 ][ 0 ].data.action ).toBe(
			'mhmrentiva_remove_receipt'
		);
	} );
} );
