/**
 * @jest-environment jsdom
 *
 * PR 3b-3: the booking screen's "Send email to customer" box.
 * - H9: only the answer to the last template request fills the box.
 * - A custom message clears the box without a request.
 * - A type change asks before replacing edits, and reverts when refused.
 * - Results are notices inside the box, never browser alerts.
 * - Without TinyMCE (jsdom) the message is the editor's textarea.
 */

describe( 'booking customer e-mail box', () => {
	let jQuery;
	let calls;
	let alertSpy;
	let confirmSpy;

	const box = () => document.querySelector( '.mhm-email-form' );
	const subject = () => document.getElementById( 'email_subject' );
	const message = () => document.getElementById( 'email_message' );
	const notice = () => document.querySelector( '.mhm-email-form .mhm-email-notice' );
	const change = ( value ) => {
		jQuery( '#email_type' ).val( value ).trigger( 'change' );
	};

	beforeAll( async () => {
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;
		global.ajaxurl = '/wp-admin/admin-ajax.php';
		window.mhmBookingEmail = {
			emailNonce: 'n-fetch',
			strings: { sending: 'S…', success: 'OK-SENT', sendError: 'ERR %s', sendFailed: 'FAIL', templateError: 'TPL', confirmReplace: 'REPLACE?' },
		};
		document.body.innerHTML =
			'<input type="hidden" id="post_ID" value="42" />' +
			'<div class="mhm-email-form" data-booking-id="42">' +
			'<input type="hidden" name="mhmrentiva_email_nonce" value="n-send" />' +
			'<select id="email_type">' +
			'<option value="booking_created_customer" selected>A</option>' +
			'<option value="booking_reminder_customer">B</option>' +
			'<option value="booking_cancelled">C</option>' +
			'<option value="custom">D</option>' +
			'</select>' +
			'<input type="text" id="email_subject" />' +
			'<textarea id="email_message"></textarea>' +
			'<button type="button" class="mhm-email-send-btn">Send</button>' +
			'</div>';
		calls = [];
		jest.spyOn( jQuery, 'ajax' ).mockImplementation( ( options ) => {
			calls.push( options );
		} );
		alertSpy = jest.spyOn( window, 'alert' ).mockImplementation( () => {} );
		confirmSpy = jest.spyOn( window, 'confirm' );
		require( '../../assets/js/admin/booking-email-send.js' );
		// jQuery's ready callback runs asynchronously once the document is loaded.
		await new Promise( ( r ) => setTimeout( r, 20 ) );
	} );

	afterAll( () => {
		jest.restoreAllMocks();
		document.body.innerHTML = '';
		delete global.jQuery;
		delete global.$;
		delete window.jQuery;
		delete window.mhmBookingEmail;
		delete global.ajaxurl;
	} );

	test( 'the default type is fetched on load and fills the box', () => {
		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].data ).toMatchObject( { action: 'mhmrentiva_get_email_template', email_type: 'booking_created_customer', booking_id: 42, nonce: 'n-fetch' } );
		calls[ 0 ].success( { success: true, data: { subject: 'Onay', message: '<p>Merhaba</p>' } } );
		expect( subject().value ).toBe( 'Onay' );
		expect( message().value ).toBe( '<p>Merhaba</p>' );
	} );

	test( 'H9: a slower answer to an earlier choice does not overwrite a later one', () => {
		calls.length = 0;
		change( 'booking_reminder_customer' );
		change( 'booking_cancelled' );
		expect( calls ).toHaveLength( 2 );

		calls[ 1 ].success( { success: true, data: { subject: 'İptal', message: '<p>İptal metni</p>' } } );
		calls[ 0 ].success( { success: true, data: { subject: 'Hatırlatma', message: '<p>Eski</p>' } } );

		expect( subject().value ).toBe( 'İptal' );
		expect( message().value ).toBe( '<p>İptal metni</p>' );
	} );

	test( 'an edited box asks before a type change and reverts when refused', () => {
		calls.length = 0;
		message().value = '<p>Elle değişti</p>';
		confirmSpy.mockReturnValueOnce( false );

		change( 'booking_created_customer' );

		expect( confirmSpy ).toHaveBeenCalledWith( 'REPLACE?' );
		expect( jQuery( '#email_type' ).val() ).toBe( 'booking_cancelled' );
		expect( calls ).toHaveLength( 0 );
		expect( message().value ).toBe( '<p>Elle değişti</p>' );
	} );

	test( 'a custom message clears the box without a request', () => {
		calls.length = 0;
		confirmSpy.mockReturnValueOnce( true );

		change( 'custom' );

		expect( calls ).toHaveLength( 0 );
		expect( subject().value ).toBe( '' );
		expect( message().value ).toBe( '' );
	} );

	test( 'sending posts the textarea message and shows the result in the box', () => {
		calls.length = 0;
		subject().value = 'Anahtar';
		message().value = '<p>Ofiste</p>';

		jQuery( '.mhm-email-send-btn' ).trigger( 'click' );

		expect( calls[ 0 ].data ).toMatchObject( { action: 'mhmrentiva_send_customer_email', email_type: 'custom', email_subject: 'Anahtar', email_message: '<p>Ofiste</p>', mhmrentiva_email_nonce: 'n-send' } );
		calls[ 0 ].success( { success: false, data: 'Sunucu dedi' } );
		calls[ 0 ].complete();
		expect( notice().textContent ).toBe( 'ERR Sunucu dedi' );
		expect( notice().classList.contains( 'notice-error' ) ).toBe( true );

		jQuery( '.mhm-email-send-btn' ).trigger( 'click' );
		calls[ 1 ].success( { success: true, data: 'x' } );
		expect( notice().textContent ).toBe( 'OK-SENT' );
		expect( alertSpy ).not.toHaveBeenCalled();
	} );

	test( 'Codex #178 I1: sending waits while a template loads', () => {
		calls.length = 0;
		confirmSpy.mockReturnValue( true );
		change( 'booking_reminder_customer' );
		expect( jQuery( '.mhm-email-send-btn' ).prop( 'disabled' ) ).toBe( true );
		calls[ 0 ].success( { success: true, data: { subject: 'Hatırlatma', message: '<p>H</p>' } } );
		calls[ 0 ].complete();
		expect( jQuery( '.mhm-email-send-btn' ).prop( 'disabled' ) ).toBe( false );
	} );

	test( 'Codex #178 I1: a failed load puts the previous type back', () => {
		calls.length = 0;
		change( 'booking_cancelled' );
		calls[ 0 ].error();
		calls[ 0 ].complete();
		expect( jQuery( '#email_type' ).val() ).toBe( 'booking_reminder_customer' );
		expect( subject().value ).toBe( 'Hatırlatma' );
		expect( notice().textContent ).toBe( 'TPL' );
	} );

	test( 'Codex #178 I3: an unedited template is sent empty, so the server renders it itself', () => {
		calls.length = 0;
		jQuery( '.mhm-email-send-btn' ).trigger( 'click' );
		expect( calls[ 0 ].data ).toMatchObject( { email_type: 'booking_reminder_customer', email_subject: '', email_message: '' } );
		calls[ 0 ].complete();

		calls.length = 0;
		message().value = '<p>Elle</p>';
		jQuery( '.mhm-email-send-btn' ).trigger( 'click' );
		expect( calls[ 0 ].data ).toMatchObject( { email_subject: 'Hatırlatma', email_message: '<p>Elle</p>' } );
		calls[ 0 ].complete();
		confirmSpy.mockReset();
	} );

	test( 'Enter in the subject does not submit the booking form', () => {
		const event = jQuery.Event( 'keydown', { key: 'Enter' } );
		jQuery( '#email_subject' ).trigger( event );
		expect( event.isDefaultPrevented() ).toBe( true );
	} );
} );
