/**
 * Booking edit screen: "Send email to customer", the history note, and two
 * small handlers of the side boxes.
 */
(function ($) {
	'use strict';

	var EDITOR_ID = 'email_message';

	function strings() {
		return ( window.mhmBookingEmail && window.mhmBookingEmail.strings ) || {};
	}

	function ajaxUrl() {
		if ( typeof ajaxurl !== 'undefined' ) {
			return ajaxurl;
		}
		return ( window.mhmBookingEmail && window.mhmBookingEmail.ajaxUrl ) || '';
	}

	// An inline notice inside a container. Text only -- a server message is
	// not markup.
	function showNotice( $container, cls, type, message ) {
		$container.find( '.' + cls ).remove();
		var $notice = $( '<div class="notice inline"><p></p></div>' );
		$notice.addClass( cls ).addClass( 'notice-' + type );
		$notice.find( 'p' ).text( message );
		$container.prepend( $notice );
	}

	// The history note's result (PR 3b-2: it used to be a browser alert()).
	function showNoteNotice( $container, type, message ) {
		showNotice( $container, 'mhm-history-notice', type, message );
	}

	// The e-mail box's results (PR 3b-3: they used to be browser alerts).
	function showEmailNotice( $container, type, message ) {
		showNotice( $container, 'mhm-email-notice', type, message );
	}

	/* ---- The message editor (wp_editor: TinyMCE, or its Text tab) ---- */

	function tinymceEditor() {
		return ( window.tinymce && typeof window.tinymce.get === 'function' ) ? window.tinymce.get( EDITOR_ID ) : null;
	}

	function getMessage() {
		var editor = tinymceEditor();
		if ( editor && ! editor.isHidden() ) {
			editor.save();
			return editor.getContent();
		}
		return String( $( '#' + EDITOR_ID ).val() || '' );
	}

	function setMessage( html ) {
		$( '#' + EDITOR_ID ).val( html );
		var editor = tinymceEditor();
		if ( editor ) {
			editor.setContent( html );
		}
	}

	// Moving a postbox moves the editor's iframe, which loses its document:
	// the editor is taken down before the move and set up again after
	// (TinyMCE saves into the textarea when it is removed).
	function detachEditor() {
		var editor = tinymceEditor();
		if ( ! editor ) {
			return false;
		}
		editor.save();
		window.tinymce.execCommand( 'mceRemoveEditor', false, EDITOR_ID );
		return true;
	}

	function attachEditor() {
		if ( ! window.tinymce || tinymceEditor() || ! $( '#' + EDITOR_ID ).length ) {
			return;
		}
		var pre = window.tinyMCEPreInit && window.tinyMCEPreInit.mceInit && window.tinyMCEPreInit.mceInit[ EDITOR_ID ];
		if ( pre ) {
			window.tinymce.init( pre );
		} else {
			window.tinymce.execCommand( 'mceAddEditor', false, EDITOR_ID );
		}
	}

	/* ---- Templates ---- */

	// The box's state (Codex #178): the send button is locked while a template
	// loads OR an e-mail is being sent; `loadedType` is the type whose text the
	// box holds; `dirty` is set by what the operator does (typing, pasting, the
	// toolbar, undo) -- never by comparing serialisations, which TinyMCE
	// normalises on its own -- and cleared when a template fills the box.
	var state = {
		request: 0,
		loading: false,
		sending: false,
		selected: '',
		loadedType: '',
		dirty: false,
		filling: false,
	};

	// What counts as an operator's change inside TinyMCE.
	var EDITING_COMMANDS = [ 'Bold', 'Italic', 'mceInsertLink', 'unlink', 'InsertUnorderedList', 'InsertOrderedList', 'mceToggleFormat', 'mceInsertContent', 'Delete', 'ForwardDelete', 'Undo', 'Redo' ];

	function markDirty() {
		if ( ! state.filling ) {
			state.dirty = true;
		}
	}

	function refreshButton( $box ) {
		$box.find( '.mhm-email-send-btn' ).prop( 'disabled', state.loading || state.sending );
	}

	function fill( subjectText, body, emailType ) {
		state.filling = true;
		$( '#email_subject' ).val( subjectText );
		setMessage( body );
		state.filling    = false;
		state.dirty      = false;
		state.loadedType = emailType;
	}

	// H9: only the answer to the LAST request fills the box -- a slower answer
	// to an earlier choice no longer overwrites a later one. A failed load names
	// the type whose text the box still holds.
	function loadEmailTemplate( $box, emailType, bookingId ) {
		var request = ++state.request;

		if ( 'custom' === emailType ) {
			state.loading = false;
			fill( '', '', 'custom' );
			refreshButton( $box );
			return;
		}

		state.loading = true;
		refreshButton( $box );

		var failed = function () {
			showEmailNotice( $box, 'error', strings().templateError || 'The template could not be loaded.' );
			if ( state.loadedType ) {
				state.selected = state.loadedType;
				$( '#email_type' ).val( state.loadedType );
			}
		};

		$.ajax( {
			url: ajaxUrl(),
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'mhmrentiva_get_email_template',
				booking_id: bookingId,
				email_type: emailType,
				nonce: ( window.mhmBookingEmail && window.mhmBookingEmail.emailNonce ) || '',
			},
			success: function ( response ) {
				if ( request !== state.request ) {
					return;
				}
				if ( response && response.success && response.data ) {
					fill( response.data.subject || '', response.data.message || '', emailType );
				} else {
					failed();
				}
			},
			error: function () {
				if ( request === state.request ) {
					failed();
				}
			},
			complete: function () {
				if ( request === state.request ) {
					state.loading = false;
					refreshButton( $box );
				}
			},
		} );
	}

	/* ---- Send ---- */

	function handleEmailFormSubmit( $box ) {
		if ( state.loading || state.sending ) {
			return;
		}

		var text      = strings();
		var bookingId = $box.data( 'booking-id' ) || $( '#post_ID' ).val();
		var nonce     = $box.find( 'input[name="mhmrentiva_email_nonce"]' ).val() || '';

		if ( ! bookingId || ! nonce ) {
			showEmailNotice( $box, 'error', text.securityError || 'Please refresh the page and try again.' );
			return;
		}

		var $button = $box.find( '.mhm-email-send-btn' );
		var label   = $button.text();
		var type    = $box.find( '#email_type' ).val() || '';
		// An untouched template goes out empty: the server renders it itself,
		// byte for byte the automatic e-mail (the editor trims and normalises).
		var edited  = 'custom' === type || state.dirty;

		state.sending = true;
		refreshButton( $box );
		$button.text( text.sending || 'Sending...' );

		$.ajax( {
			url: ajaxUrl(),
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'mhmrentiva_send_customer_email',
				booking_id: bookingId,
				email_type: type,
				email_subject: edited ? ( $box.find( '#email_subject' ).val() || '' ) : '',
				email_message: edited ? getMessage() : '',
				mhmrentiva_email_nonce: nonce,
			},
			success: function ( response ) {
				if ( response && response.success ) {
					showEmailNotice( $box, 'success', text.success || 'Email sent successfully!' );
					// Back to the chosen type's template (an empty box for a custom message).
					loadEmailTemplate( $box, $box.find( '#email_type' ).val(), bookingId );
				} else {
					showEmailNotice( $box, 'error', ( text.sendError || 'The e-mail was not sent: %s' ).replace( '%s', String( ( response && response.data ) || '' ) ) );
				}
			},
			error: function () {
				showEmailNotice( $box, 'error', text.sendFailed || 'The e-mail could not be sent. Please try again.' );
			},
			complete: function () {
				state.sending = false;
				$button.text( label );
				refreshButton( $box );
			},
		} );
	}

	// Initialize
	$( document ).ready( function () {
		var $box = $( '.mhm-email-form' ).first();

		$( document ).off( 'click', '.mhm-email-send-btn' ).on( 'click', '.mhm-email-send-btn', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			handleEmailFormSubmit( $( this ).closest( '.mhm-email-form' ) );
		} );

		// Enter in the subject field must not submit the booking form.
		$( document ).off( 'keydown', '#email_subject' ).on( 'keydown', '#email_subject', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
			}
		} );

		// The operator's own changes (the editor's Text tab is the textarea).
		$( document ).off( 'input', '#email_subject, #' + EDITOR_ID ).on( 'input', '#email_subject, #' + EDITOR_ID, markDirty );

		// A type change replaces the box; edits are not thrown away unasked.
		state.selected = $( '#email_type' ).val() || '';
		$( document ).off( 'change', '#email_type' ).on( 'change', '#email_type', function () {
			var $select = $( this );
			if ( state.dirty && ! window.confirm( strings().confirmReplace || 'Replace your changes with the selected template?' ) ) {
				$select.val( state.selected );
				return;
			}
			state.selected = $select.val();
			loadEmailTemplate( $select.closest( '.mhm-email-form' ), state.selected, $select.closest( '.mhm-email-form' ).data( 'booking-id' ) || $( '#post_ID' ).val() );
		} );

		// The default type's template on page load.
		if ( $box.length && state.selected ) {
			loadEmailTemplate( $box, state.selected, $box.data( 'booking-id' ) || ( window.mhmBookingEmail && window.mhmBookingEmail.bookingId ) || $( '#post_ID' ).val() );
		}

		// Every TinyMCE instance of the box (it is set up again after a move):
		// keep the textarea in step and record the operator's edits.
		$( document ).on( 'tinymce-editor-init', function ( event, editor ) {
			if ( ! editor || EDITOR_ID !== editor.id ) {
				return;
			}
			editor.on( 'input paste cut drop Undo Redo', markDirty );
			editor.on( 'ExecCommand', function ( e ) {
				if ( e && EDITING_COMMANDS.indexOf( e.command ) !== -1 ) {
					markDirty();
				}
			} );
			editor.on( 'change input', function () {
				editor.save();
			} );
		} );

		// Drag and drop of the postbox.
		$( document ).on( 'sortstart', '.meta-box-sortables', function ( event, ui ) {
			if ( ui && ui.item && ui.item.find( '#' + EDITOR_ID ).length ) {
				detachEditor();
			}
		} );
		$( document ).on( 'sortstop', '.meta-box-sortables', function ( event, ui ) {
			if ( ui && ui.item && ui.item.find( '#' + EDITOR_ID ).length ) {
				attachEditor();
			}
		} );
		// The move up/down buttons move the box without a sortstart.
		document.addEventListener( 'click', function ( event ) {
			var target = event.target && event.target.closest ? event.target.closest( '.handle-order-higher, .handle-order-lower' ) : null;
			var postbox = target ? target.closest( '.postbox' ) : null;
			if ( postbox && postbox.querySelector( '#' + EDITOR_ID ) && detachEditor() ) {
				setTimeout( attachEditor, 0 );
			}
		}, true );

		// History Note Click Handler
		$( document ).off( 'click', '.mhm-add-history-btn' ).on( 'click', '.mhm-add-history-btn', function ( e ) {
			e.preventDefault();
			var $container = $( this ).closest( '.mhm-add-history-note' );
			var bookingId = $container.data( 'booking-id' ) || $( '#post_ID' ).val();
			var noteContent = String( $container.find( '#note_content' ).val() || '' ).trim();
			var noteType = $container.find( '#note_type' ).val();
			var nonce = $container.find( 'input[name="mhmrentiva_history_nonce"]' ).val();
			var noteStrings = strings();

			if ( ! noteContent ) {
				showNoteNotice( $container, 'error', noteStrings.noteEmpty || 'Please enter a note.' );
				return;
			}

			var $btn = $( this );
			$btn.prop( 'disabled', true );

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'mhmrentiva_add_booking_history_note',
					booking_id: bookingId,
					note_content: noteContent,
					note_type: noteType,
					mhmrentiva_history_nonce: nonce,
				},
				success: function ( response ) {
					if ( response.success ) {
						showNoteNotice( $container, 'success', noteStrings.noteAdded || 'Note added.' );
						// The list is drawn by the server; reload once the
						// operator has seen the notice.
						setTimeout( function () {
							location.reload();
						}, 800 );
					} else {
						showNoteNotice( $container, 'error', ( noteStrings.noteError || 'Error: %s' ).replace( '%s', String( response.data || '' ) ) );
						$btn.prop( 'disabled', false );
					}
				},
				error: function () {
					showNoteNotice( $container, 'error', noteStrings.noteFailed || 'The note could not be added.' );
					$btn.prop( 'disabled', false );
				},
			} );
		} );

		// History — Show More Button
		$( document ).on( 'click', '.mhm-history-show-more', function () {
			$( '.mhm-history-item--hidden' ).show();
			$( this ).remove();
		} );

		// Refund — Confirm before submit
		$( document ).on( 'click', '.mhm-refund-submit-btn', function ( e ) {
			if ( ! window.confirm( mhmBookingEmail.confirmRefund || 'Proceed with refund?' ) ) {
				e.preventDefault();
			}
		} );
	} );

	// Customer Account — Create Account Button
	$( document ).on( 'click', '.mhm-create-customer-account-btn', function () {
		var $btn      = $( this );
		var bookingId = $btn.data( 'booking-id' );
		var email     = $btn.data( 'email' );
		var name      = $btn.data( 'name' );
		var nonce     = $btn.data( 'nonce' );

		if ( ! window.confirm( mhmBookingEmail.confirmCreateAccount || 'Create a WordPress account for this customer?' ) ) {
			return;
		}

		$btn.prop( 'disabled', true );

		$.post(
			ajaxurl,
			{
				action:     'mhmrentiva_create_customer_account_manual',
				booking_id: bookingId,
				email:      email,
				name:       name,
				nonce:      nonce,
			},
			function ( response ) {
				if ( response.success ) {
					if ( typeof window.mhmShowNotice === 'function' ) {
						window.mhmShowNotice( 'success', response.data.message || 'Account created successfully!' );
					}
					setTimeout( function () { location.reload(); }, 1500 );
				} else {
					if ( typeof window.mhmShowNotice === 'function' ) {
						window.mhmShowNotice( 'error', response.data.message || 'Error creating account.' );
					} else {
						window.alert( response.data.message || 'Error creating account.' );
					}
					$btn.prop( 'disabled', false );
				}
			}
		);
	} );

	// Refund Meta Box — convert the visible amount to the store's minor unit.
	// Not a fixed *100: PHP scales this meta by 10^woocommerce_price_num_decimals
	// on both sides, and a 0-decimal (JPY) or 3-decimal (KWD) store would submit
	// an amount off by a factor of ten.
	$( document ).on( 'input', 'input[name="amount_visible"]', function () {
		var settings = window.mhmBookingEmail || {};
		var decimals = parseInt( settings.priceDecimals, 10 );
		if ( isNaN( decimals ) || decimals < 0 ) {
			decimals = 2;
		}
		var raw   = parseFloat( $( this ).val() || '0' );
		var minor = Math.round( raw * Math.pow( 10, decimals ) );
		if ( ! isFinite( minor ) || minor < 0 ) {
			minor = 0;
		}
		$( '#mhmrentiva_amount_kurus' ).val( String( minor ) );
	} );

} )( jQuery );
