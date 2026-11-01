/**
 * Booking cancellation -- My Account > booking detail (PR D, Task 4).
 *
 * The cancel button ([data-rvs-cancel-open], aria-controls = the dialog's id)
 * opens the shared rvs dialog (window.mhmRentivaRvsDialog) with focus on
 * "Keep booking". The reason select ([data-rvs-cancel-reason]) is optional;
 * "Other" reveals the "Tell us more" field ([data-rvs-cancel-other]). The
 * request keeps its contract: action mhmrentiva_cancel_booking, nonce,
 * booking_id, reason -- the chosen reason's label, plus the text when given.
 * Escape and "Keep booking" close the dialog; focus returns to the button.
 * While the request runs the confirm button is busy, so a second submit cannot
 * start, and the dialog can still be closed: closing does not stop the request.
 * The outcome is said once: on success in the page's status region, then the
 * page reloads; a refusal in the dialog if it is open, else in that region.
 *
 * Config: window.mhmRentivaCancellation = { ajaxUrl, nonce, i18n: { cancelling, error, cancelled } }.
 */
( function () {
	'use strict';

	function dialogApi() {
		return window.mhmRentivaRvsDialog || null;
	}

	function parts( el ) {
		const other = el.querySelector( '[data-rvs-cancel-other]' );
		return {
			reason: el.querySelector( '[data-rvs-cancel-reason]' ),
			other: other,
			otherText: other ? other.querySelector( 'textarea, input' ) : null,
			message: el.querySelector( '[data-rvs-cancel-message]' ),
			confirm: el.querySelector( '[data-rvs-cancel-confirm]' ),
			keep: el.querySelector( '[data-rvs-cancel-keep]' ),
			title: el.querySelector( '.rvs-dialog__title' ),
		};
	}

	function showMessage( p, text, ok ) {
		if ( ! p.message ) {
			return;
		}
		p.message.textContent = text || '';
		p.message.classList.toggle( 'is-success', !! ok );
		p.message.hidden = ! text;
	}

	function syncOther( p ) {
		if ( p.other && p.reason ) {
			p.other.hidden = p.reason.value !== 'other';
		}
	}

	function reasonText( p ) {
		const select = p.reason;
		const label = select && select.value && select.selectedIndex >= 0
			? select.options[ select.selectedIndex ].text.trim()
			: '';
		const extra = p.otherText && p.other && ! p.other.hidden ? p.otherText.value.trim() : '';
		if ( label && extra ) {
			return label + ': ' + extra;
		}
		return label || extra;
	}

	// Long enough to read and hear the outcome before the page goes away.
	const RELOAD_DELAY_MS = 1500;

	// Dialogs with a request running, or done and about to reload.
	const inFlight = new WeakSet();

	function setBusy( p, busy ) {
		p.confirm.disabled = busy;
		p.confirm.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
	}

	function outcomeRegion() {
		return document.querySelector( '[data-rvs-cancel-outcome]' );
	}

	function showOutcome( text, isError ) {
		const region = outcomeRegion();
		if ( region ) {
			region.textContent = text || '';
			region.classList.toggle( 'rvs-error', !! isError );
		}
	}

	function reset( el ) {
		const p = parts( el );
		if ( p.reason ) {
			p.reason.value = '';
		}
		if ( p.otherText ) {
			p.otherText.value = '';
		}
		syncOther( p );
		showMessage( p, '', false );
		if ( p.confirm && ! inFlight.has( el ) ) {
			p.confirm.disabled = false;
		}
	}

	function open( el, opener ) {
		const api = dialogApi();
		if ( ! el || ! api ) {
			return;
		}
		const p = parts( el );
		showOutcome( '', false );
		el.hidden = false;
		el.classList.add( 'is-open' );
		api.open( el, {
			opener: opener,
			labelledBy: p.title ? p.title.id : undefined,
			initialFocus: p.keep || undefined,
			onClose: function () {
				el.classList.remove( 'is-open' );
				el.hidden = true;
				reset( el );
			},
		} );
	}

	function close( el ) {
		const api = dialogApi();
		if ( api ) {
			api.close( el );
		}
	}

	function submit( el ) {
		const cfg = window.mhmRentivaCancellation || {};
		const i18n = cfg.i18n || {};
		const p = parts( el );
		if ( ! p.confirm || inFlight.has( el ) ) {
			return;
		}

		inFlight.add( el );
		const original = p.confirm.textContent;
		setBusy( p, true );
		p.confirm.textContent = i18n.cancelling || original;
		showMessage( p, '', false );

		const body = new URLSearchParams();
		body.append( 'action', 'mhmrentiva_cancel_booking' );
		body.append( 'nonce', cfg.nonce || '' );
		body.append( 'booking_id', el.getAttribute( 'data-booking-id' ) || '' );
		body.append( 'reason', reasonText( p ) );

		const fail = function ( text ) {
			const api = dialogApi();
			const message = text || i18n.error || '';
			inFlight.delete( el );
			if ( ( api && api.current() === el ) || ! outcomeRegion() ) {
				showMessage( p, message, false );
			} else {
				showOutcome( message, true );
			}
			setBusy( p, false );
			p.confirm.textContent = original;
		};

		return fetch( cfg.ajaxUrl || '', {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				if ( response && response.success ) {
					// Stays in flight: nothing may submit again before the reload.
					close( el );
					showOutcome( ( response.data && response.data.message ) || i18n.cancelled || '', false );
					setTimeout( function () {
						window.location.reload();
					}, RELOAD_DELAY_MS );
					return;
				}
				fail( response && response.data && response.data.message );
			} )
			.catch( function () {
				fail( '' );
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		const target = event.target instanceof Element ? event.target : null;
		if ( ! target ) {
			return;
		}

		const opener = target.closest( '[data-rvs-cancel-open]' );
		if ( opener ) {
			event.preventDefault();
			open( document.getElementById( opener.getAttribute( 'aria-controls' ) || '' ), opener );
			return;
		}

		const el = target.closest( '[data-rvs-cancel-dialog]' );
		if ( ! el ) {
			return;
		}
		if ( target === el || target.closest( '[data-rvs-cancel-keep]' ) ) {
			event.preventDefault();
			close( el );
			return;
		}
		if ( target.closest( '[data-rvs-cancel-confirm]' ) ) {
			event.preventDefault();
			submit( el );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		const target = event.target instanceof Element ? event.target : null;
		if ( ! target || ! target.matches( '[data-rvs-cancel-reason]' ) ) {
			return;
		}
		const el = target.closest( '[data-rvs-cancel-dialog]' );
		if ( el ) {
			syncOther( parts( el ) );
		}
	} );
}() );
