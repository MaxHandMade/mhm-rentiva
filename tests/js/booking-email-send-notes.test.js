/**
 * @jest-environment jsdom
 *
 * The booking history box's "Add note" (spec PR 3a): its four notices were
 * plain English; they now come from mhmBookingEmail.strings. An all-space
 * note counts as empty, and the booking id comes from the add-note container.
 */

const flush = () => new Promise( ( r ) => setTimeout( r, 20 ) );

describe( 'booking history add note', () => {
	let jQuery;
	let ajax;
	let alertSpy;

	// One module load for the file: the script binds delegated handlers on
	// `document`, which outlives jest.resetModules(), so a second load would
	// leave a stale copy answering the clicks.
	beforeAll( () => {
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;
		global.ajaxurl = '/wp-admin/admin-ajax.php';
		window.mhmBookingEmail = {
			strings: { noteEmpty: 'X1', noteAdded: 'X2', noteError: 'X3 %s', noteFailed: 'X4' },
		};
		require( '../../assets/js/admin/booking-email-send.js' );
	} );

	beforeEach( () => {
		document.body.innerHTML =
			'<input type="hidden" id="post_ID" value="99" />' +
			'<div class="mhm-add-history-note" data-booking-id="42">' +
			'<input type="hidden" name="mhmrentiva_history_nonce" value="n1" />' +
			'<textarea id="note_content"></textarea>' +
			'<select id="note_type"><option value="note" selected>Note</option></select>' +
			'<button type="button" class="mhm-add-history-btn">Add</button>' +
			'</div>';
		ajax = jest.spyOn( jQuery, 'ajax' ).mockImplementation( () => {} );
		alertSpy = jest.spyOn( window, 'alert' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		ajax.mockRestore();
		alertSpy.mockRestore();
		document.body.innerHTML = '';
	} );

	afterAll( () => {
		delete global.jQuery;
		delete global.$;
		delete window.jQuery;
		delete window.mhmBookingEmail;
		delete global.ajaxurl;
	} );

	const clickAdd = async ( text ) => {
		await flush(); // jQuery ready binds asynchronously in jsdom.
		document.getElementById( 'note_content' ).value = text;
		document.querySelector( '.mhm-add-history-btn' ).click();
		await flush();
	};

	it( 'refuses an all-space note with the localized notice', async () => {
		await clickAdd( '   ' );
		expect( alertSpy ).toHaveBeenCalledWith( 'X1' );
		expect( ajax ).not.toHaveBeenCalled();
	} );

	it( 'sends the container booking id and shows the localized success', async () => {
		await clickAdd( 'Called' );
		expect( ajax ).toHaveBeenCalledTimes( 1 );
		const opts = ajax.mock.calls[ 0 ][ 0 ];
		expect( opts.data.booking_id ).toBe( 42 );
		expect( opts.data.note_content ).toBe( 'Called' );
		opts.success( { success: true } );
		expect( alertSpy ).toHaveBeenCalledWith( 'X2' );
		// The reload that follows: jsdom reports navigation as not implemented.
		expect( console ).toHaveErrored();
	} );

	it( 'puts the server message into the localized error', async () => {
		await clickAdd( 'Called' );
		ajax.mock.calls[ 0 ][ 0 ].success( { success: false, data: 'nope' } );
		expect( alertSpy ).toHaveBeenCalledWith( 'X3 nope' );
	} );

	it( 'shows the localized failure on a network error', async () => {
		await clickAdd( 'Called' );
		ajax.mock.calls[ 0 ][ 0 ].error();
		expect( alertSpy ).toHaveBeenCalledWith( 'X4' );
	} );
} );
