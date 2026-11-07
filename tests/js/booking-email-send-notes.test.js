/**
 * @jest-environment jsdom
 *
 * The booking history box's "Add note" (spec PR 3a): its four notices were
 * plain English; they now come from mhmBookingEmail.strings. An all-space
 * note counts as empty, and the booking id comes from the add-note container.
 * PR 3b-2: the notices are shown inside the box, not as browser alerts.
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

	const notice = () => {
		const el = document.querySelector( '.mhm-add-history-note .mhm-history-notice' );
		return el ? { text: el.textContent, error: el.classList.contains( 'notice-error' ) } : null;
	};

	const clickAdd = async ( text ) => {
		await flush(); // jQuery ready binds asynchronously in jsdom.
		document.getElementById( 'note_content' ).value = text;
		document.querySelector( '.mhm-add-history-btn' ).click();
		await flush();
	};

	it( 'refuses an all-space note with the localized notice', async () => {
		await clickAdd( '   ' );
		expect( notice() ).toEqual( { text: 'X1', error: true } );
		expect( alertSpy ).not.toHaveBeenCalled();
		expect( ajax ).not.toHaveBeenCalled();
	} );

	it( 'sends the container booking id and shows the localized success', async () => {
		await clickAdd( 'Called' );
		expect( ajax ).toHaveBeenCalledTimes( 1 );
		const opts = ajax.mock.calls[ 0 ][ 0 ];
		expect( opts.data.booking_id ).toBe( 42 );
		expect( opts.data.note_content ).toBe( 'Called' );
		opts.success( { success: true } );
		expect( notice() ).toEqual( { text: 'X2', error: false } );
		expect( alertSpy ).not.toHaveBeenCalled();
	} );

	it( 'puts the server message into the localized error', async () => {
		await clickAdd( 'Called' );
		ajax.mock.calls[ 0 ][ 0 ].success( { success: false, data: '<b>nope</b>' } );
		expect( notice() ).toEqual( { text: 'X3 <b>nope</b>', error: true } );
		expect( document.querySelector( '.mhm-history-notice b' ) ).toBeNull();
	} );

	it( 'shows the localized failure on a network error', async () => {
		await clickAdd( 'Called' );
		ajax.mock.calls[ 0 ][ 0 ].error();
		expect( notice() ).toEqual( { text: 'X4', error: true } );
	} );
} );
