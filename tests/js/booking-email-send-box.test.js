/**
 * @jest-environment jsdom
 *
 * PR 3b-3: the booking screen's "Send email to customer" box.
 * - H9: only the answer to the last template request fills the box.
 * - Codex #178: the send button is locked while a template loads or an
 *   e-mail is being sent, whichever ends first; a failed load returns to the
 *   last type that actually loaded; "edited" means the operator typed (events),
 *   not that the editor's serialisation differs, so an untouched template is
 *   posted empty and the server renders it byte for byte.
 * - Results are notices inside the box, never browser alerts.
 * (jsdom has no TinyMCE: the message is the editor's textarea.)
 */

describe( 'booking customer e-mail box', () => {
	let jQuery;
	let calls;
	let alertSpy;
	let confirmSpy;

	const subject = () => document.getElementById( 'email_subject' );
	const message = () => document.getElementById( 'email_message' );
	const button = () => jQuery( '.mhm-email-send-btn' );
	const notice = () => document.querySelector( '.mhm-email-form .mhm-email-notice' );
	const type = () => jQuery( '#email_type' ).val();
	const change = ( value ) => jQuery( '#email_type' ).val( value ).trigger( 'change' );
	// What the operator does: change the value and the browser fires `input`.
	const typeInto = ( el, value ) => {
		el.value = value;
		el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	};
	const answer = ( call, subjectText, body ) => {
		call.success( { success: true, data: { subject: subjectText, message: body } } );
		call.complete();
	};
	const lastCall = () => calls[ calls.length - 1 ];
	const send = () => button().trigger( 'click' );

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
		confirmSpy = jest.spyOn( window, 'confirm' ).mockReturnValue( true );
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

	test( 'the default type is fetched on load; sending waits for it', () => {
		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].data ).toMatchObject( { action: 'mhmrentiva_get_email_template', email_type: 'booking_created_customer', booking_id: 42, nonce: 'n-fetch' } );
		expect( button().prop( 'disabled' ) ).toBe( true );

		answer( calls[ 0 ], 'A-subj', '<p>A</p>' );

		expect( subject().value ).toBe( 'A-subj' );
		expect( message().value ).toBe( '<p>A</p>' );
		expect( button().prop( 'disabled' ) ).toBe( false );
	} );

	test( 'an untouched template is posted empty, so the server renders it itself', () => {
		calls.length = 0;
		send();
		expect( calls[ 0 ].data ).toMatchObject( { action: 'mhmrentiva_send_customer_email', email_type: 'booking_created_customer', email_subject: '', email_message: '' } );
		calls[ 0 ].success( { success: true, data: 'x' } );
		calls[ 0 ].complete();
		// The success reloads the template.
		answer( lastCall(), 'A-subj', '<p>A</p>' );
	} );

	test( 'H9: a slower answer to an earlier choice does not overwrite a later one', () => {
		calls.length = 0;
		change( 'booking_reminder_customer' );
		change( 'booking_cancelled' );
		expect( calls ).toHaveLength( 2 );

		answer( calls[ 1 ], 'C-subj', '<p>C</p>' );
		answer( calls[ 0 ], 'B-subj', '<p>B late</p>' );

		expect( subject().value ).toBe( 'C-subj' );
		expect( message().value ).toBe( '<p>C</p>' );
	} );

	test( 'a failed load returns to the last type that actually loaded (A → B → C fails)', () => {
		calls.length = 0;
		change( 'booking_created_customer' );
		answer( calls[ 0 ], 'A-subj', '<p>A</p>' );

		calls.length = 0;
		change( 'booking_reminder_customer' ); // B, never answered in time.
		change( 'booking_cancelled' ); // C fails.
		calls[ 1 ].error();
		calls[ 1 ].complete();
		answer( calls[ 0 ], 'B-subj', '<p>B late</p>' ); // stale, ignored

		expect( type() ).toBe( 'booking_created_customer' );
		expect( subject().value ).toBe( 'A-subj' );
		expect( notice().textContent ).toBe( 'TPL' );
		expect( button().prop( 'disabled' ) ).toBe( false );
	} );

	test( 'the end of a send does not unlock a template that is still loading', () => {
		calls.length = 0;
		send();
		const sendCall = calls[ 0 ];
		change( 'booking_reminder_customer' );
		const loadCall = calls[ 1 ];

		sendCall.success( { success: false, data: 'x' } );
		sendCall.complete();
		expect( button().prop( 'disabled' ) ).toBe( true );

		send(); // ignored while loading
		expect( calls ).toHaveLength( 2 );

		answer( loadCall, 'B-subj', '<p>B</p>' );
		expect( button().prop( 'disabled' ) ).toBe( false );
	} );

	test( 'an edited subject counts as edited and is posted, with the message', () => {
		calls.length = 0;
		typeInto( subject(), 'Elle konu' );
		send();
		expect( calls[ 0 ].data ).toMatchObject( { email_subject: 'Elle konu', email_message: '<p>B</p>' } );
		calls[ 0 ].complete();
	} );

	test( 'an edited box asks before a type change and keeps the type when refused', () => {
		calls.length = 0;
		typeInto( message(), '<p>Elle değişti</p>' );
		confirmSpy.mockReturnValueOnce( false );

		change( 'booking_cancelled' );

		expect( confirmSpy ).toHaveBeenCalledWith( 'REPLACE?' );
		expect( type() ).toBe( 'booking_reminder_customer' );
		expect( calls ).toHaveLength( 0 );
		expect( message().value ).toBe( '<p>Elle değişti</p>' );
	} );

	test( 'a programmatic fill is not an edit', () => {
		calls.length = 0;
		change( 'booking_cancelled' ); // confirm → true
		answer( calls[ 0 ], 'C-subj', '<p>C</p>' );
		confirmSpy.mockClear();

		change( 'booking_created_customer' );
		expect( confirmSpy ).not.toHaveBeenCalled();
		answer( lastCall(), 'A-subj', '<p>A</p>' );
	} );

	test( 'a custom message clears the box without a request and is always posted', () => {
		calls.length = 0;
		change( 'custom' );
		expect( calls ).toHaveLength( 0 );
		expect( subject().value ).toBe( '' );
		expect( message().value ).toBe( '' );
		expect( button().prop( 'disabled' ) ).toBe( false );

		typeInto( subject(), 'Anahtar' );
		typeInto( message(), '<p>Ofiste</p>' );
		send();
		expect( calls[ 0 ].data ).toMatchObject( { email_type: 'custom', email_subject: 'Anahtar', email_message: '<p>Ofiste</p>', mhmrentiva_email_nonce: 'n-send' } );
		calls[ 0 ].success( { success: false, data: 'Sunucu dedi' } );
		calls[ 0 ].complete();
		expect( notice().textContent ).toBe( 'ERR Sunucu dedi' );
		expect( notice().classList.contains( 'notice-error' ) ).toBe( true );

		send();
		calls[ 1 ].success( { success: true, data: 'x' } );
		calls[ 1 ].complete();
		expect( notice().textContent ).toBe( 'OK-SENT' );
		expect( alertSpy ).not.toHaveBeenCalled();
	} );

	test( 'Codex #178 r3: a Text-tab toolbar change (a non-bubbling change event) is an edit', () => {
		calls.length = 0;
		change( 'booking_created_customer' );
		answer( calls[ 0 ], 'A-subj', '<p>A</p>' );
		calls.length = 0;

		message().value = '<p><strong>A</strong></p>';
		message().dispatchEvent( new Event( 'change', { bubbles: false } ) );
		send();

		expect( calls[ 0 ].data ).toMatchObject( { email_subject: 'A-subj', email_message: '<p><strong>A</strong></p>' } );
		calls[ 0 ].complete();
	} );

	test( 'Codex #178 r3: the dirty state TinyMCE keeps counts (link edits, shortcuts) and is reset by a fill', () => {
		let dirty = false;
		const editor = {
			id: 'email_message',
			isHidden: () => false,
			isDirty: () => dirty,
			setDirty: ( value ) => {
				dirty = value;
			},
			save: () => {},
			getContent: () => '<p>A <a href="https://new.example/">x</a></p>',
			setContent: () => {},
			undoManager: { clear: () => {}, add: () => {} },
		};
		window.tinymce = { get: () => editor };

		calls.length = 0;
		change( 'booking_created_customer' ); // the box is dirty from the last test: confirm → true
		answer( calls[ 0 ], 'A-subj', '<p>A</p>' );
		expect( dirty ).toBe( false );

		dirty = true; // what TinyMCE reports after the link dialog or Ctrl+U
		calls.length = 0;
		send();
		expect( calls[ 0 ].data ).toMatchObject( { email_message: '<p>A <a href="https://new.example/">x</a></p>' } );
		calls[ 0 ].complete();
		delete window.tinymce;
	} );

	test( 'Enter in the subject does not submit the booking form', () => {
		const event = jQuery.Event( 'keydown', { key: 'Enter' } );
		jQuery( '#email_subject' ).trigger( event );
		expect( event.isDefaultPrevented() ).toBe( true );
	} );
} );
