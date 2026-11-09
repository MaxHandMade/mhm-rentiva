/**
 * @jest-environment jsdom
 */

/**
 * 5a (spec T11): the My Account privacy card no longer deletes the account.
 * "Request Data Erasure" asks once, posts the same AJAX action, shows the
 * server's own message (the add-on now answers with a core privacy request:
 * "confirmation e-mail sent", "already confirmed", ...) and stays on the page.
 */
describe( 'account privacy: erasure request', () => {
	let $;
	let calls;
	let toasts;

	// jQuery runs a ready callback on a timer when the document is already
	// complete; one zero-delay tick is not always enough (measured).
	const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 50 ) );

	beforeAll( async () => {
		document.body.innerHTML = '<button type="button" id="delete-account">Request Data Erasure</button>';
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		calls = [];
		toasts = [];
		$.ajax = jest.fn( ( options ) => {
			calls.push( options );
		} );
		window.MHMRentivaToast = { show: ( message, opts ) => toasts.push( { message, type: opts && opts.type } ) };
		window.mhmRentivaPrivacy = {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'n',
			i18n: {
				confirmErasure: 'CONFIRM ERASURE',
				processing: 'PROCESSING',
				error: 'ERR',
				unknownError: 'UNKNOWN',
			},
		};
		window.prompt = jest.fn( () => 'DELETE' );
		window.confirm = jest.fn( () => true );
		require( '../../assets/js/frontend/account-privacy.js' );
		await flush();
	} );

	beforeEach( () => {
		calls.length = 0;
		toasts.length = 0;
		window.prompt.mockClear();
		window.confirm.mockClear();
	} );

	test( 'submits once after a single confirm, shows the server message, does not redirect', () => {
		const before = window.location.href;

		$( '#delete-account' ).trigger( 'click' );

		expect( window.prompt ).not.toHaveBeenCalled();
		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect( window.confirm ).toHaveBeenCalledWith( 'CONFIRM ERASURE' );
		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].data ).toEqual( { action: 'mhmrentiva_data_deletion', nonce: 'n' } );
		expect( $( '#delete-account' ).prop( 'disabled' ) ).toBe( true );

		calls[ 0 ].success( { success: true, data: { message: 'Confirmation e-mail sent.' } } );
		calls[ 0 ].complete();

		expect( toasts ).toEqual( [ { message: 'Confirmation e-mail sent.', type: 'success' } ] );
		expect( window.location.href ).toBe( before );
		expect( $( '#delete-account' ).prop( 'disabled' ) ).toBe( false );
		expect( $( '#delete-account' ).text() ).toBe( 'Request Data Erasure' );
	} );

	test( 'shows the server message on failure', () => {
		$( '#delete-account' ).trigger( 'click' );
		calls[ 0 ].success( { success: false, data: { message: 'Your request is confirmed.' } } );

		expect( toasts ).toEqual( [ { message: 'ERR: Your request is confirmed.', type: 'error' } ] );
	} );

	test( 'a cancelled confirm sends nothing', () => {
		window.confirm.mockReturnValueOnce( false );

		$( '#delete-account' ).trigger( 'click' );

		expect( calls ).toHaveLength( 0 );
	} );
} );
