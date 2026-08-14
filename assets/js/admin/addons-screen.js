/**
 * Add-ons screen: quick create, row toggle, drag to reorder.
 *
 * Every user-facing string comes from mhmRentivaAddonsScreen (wp_localize_script).
 * No literals here -- a string baked into this file never reaches the .pot and
 * so can never be translated.
 */
( function () {
	'use strict';

	var cfg = window.mhmRentivaAddonsScreen;
	if ( ! cfg ) {
		return;
	}

	var root = document.getElementById( 'mhm-addons-root' );
	if ( ! root ) {
		return;
	}

	function post( action, fields ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );

		Object.keys( fields ).forEach( function ( key ) {
			var value = fields[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( item ) {
					body.append( key + '[]', item );
				} );
				return;
			}
			body.append( key, value );
		} );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function say( element, message, isError ) {
		if ( ! element ) {
			return;
		}
		element.textContent = message;
		element.classList.toggle( 'is-error', !! isError );
	}

	/**
	 * Report a problem without window.alert().
	 *
	 * alert() is modal and synchronous: it freezes the page until dismissed,
	 * which in an admin screen means a failed toggle blocks every other row.
	 * It also cannot be styled, cannot be read by a screen reader as part of
	 * the row it belongs to, and stops automated verification dead -- the first
	 * browser pass of this screen hung on exactly that.
	 */
	function report( message ) {
		var banner = root.querySelector( '.rv-addon-notice' );
		if ( ! banner ) {
			banner = document.createElement( 'p' );
			banner.className = 'rv-addon-notice';
			banner.setAttribute( 'role', 'status' );
			banner.setAttribute( 'aria-live', 'polite' );
			root.insertBefore( banner, root.firstChild.nextSibling );
		}
		banner.textContent = message;
		banner.classList.add( 'is-visible' );
	}

	/* ---------------------------------------------------------------- toggle */

	root.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.rv-addon-status' );
		if ( ! button ) {
			return;
		}

		var row = button.closest( '.rv-addon-row' );
		var wasOn = button.dataset.enabled === '1';
		var next = wasOn ? '0' : '1';

		// Optimistic: the row answers immediately and is put back if the
		// server disagrees. A toggle that waits for a round trip feels broken
		// on a slow connection, and a toggle that never reverts lies.
		button.disabled = true;
		applyState( button, row, ! wasOn );

		post( 'mhmrentiva_addon_toggle_enabled', {
			addon_id: row.dataset.addonId,
			enabled: next
		} ).then( function ( result ) {
			if ( ! result || ! result.success ) {
				applyState( button, row, wasOn );
				report( ( result && result.data && result.data.message ) || cfg.i18n.genericError );
			}
			button.disabled = false;
		} ).catch( function () {
			applyState( button, row, wasOn );
			report( cfg.i18n.genericError );
			button.disabled = false;
		} );
	} );

	/* ---------------------------------------------------------------- delete */

	root.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.rv-addon-delete' );
		if ( ! button ) {
			return;
		}

		var row = button.closest( '.rv-addon-row' );
		var name = row.querySelector( '.rv-addon-name' ).textContent;

		// Confirmed before the request, not after. This is the one action here
		// that removes something, and window.confirm is the admin's own idiom
		// for it -- unlike alert() it is a question, and the answer decides
		// whether anything happens at all.
		if ( ! window.confirm( cfg.i18n.confirmDelete.replace( '%s', name ) ) ) {
			return;
		}

		button.disabled = true;

		post( 'mhmrentiva_addon_delete', { addon_id: row.dataset.addonId } ).then( function ( result ) {
			if ( result && result.success ) {
				// Reload rather than removing the row here: the KPI band, the
				// counter and every later row's palette position all shift.
				window.location.reload();
				return;
			}
			report( ( result && result.data && result.data.message ) || cfg.i18n.genericError );
			button.disabled = false;
		} ).catch( function () {
			report( cfg.i18n.genericError );
			button.disabled = false;
		} );
	} );

	function applyState( button, row, on ) {
		button.dataset.enabled = on ? '1' : '0';
		button.textContent = on ? cfg.i18n.active : cfg.i18n.inactive;
		button.classList.toggle( 'is-on', on );
		if ( row ) {
			row.classList.toggle( 'rv-addon-row--off', ! on );
		}
	}

	/* ---------------------------------------------------------------- create */

	var createButton = root.querySelector( '.rv-addon-create' );
	if ( createButton ) {
		createButton.addEventListener( 'click', function () {
			var feedback = root.querySelector( '.rv-addon-create-feedback' );
			var name = root.querySelector( '#rv-addon-name' );

			if ( ! name.value.trim() ) {
				say( feedback, cfg.i18n.nameRequired, true );
				name.focus();
				return;
			}

			createButton.disabled = true;
			say( feedback, cfg.i18n.saving, false );

			post( 'mhmrentiva_addon_quick_create', {
				title: name.value,
				description: root.querySelector( '#rv-addon-desc' ).value,
				price: root.querySelector( '#rv-addon-price' ).value || '0',
				pricing_type: root.querySelector( '#rv-addon-type' ).value,
				enabled: root.querySelector( '#rv-addon-enabled' ).checked ? '1' : '0',
				required: root.querySelector( '#rv-addon-required' ).checked ? '1' : '0'
			} ).then( function ( result ) {
				if ( result && result.success ) {
					// Reload rather than splice a row in by hand: the KPI band,
					// the active/total counter and the row's palette position
					// all move with it, and re-deriving them here would be a
					// second implementation of the render.
					window.location.reload();
					return;
				}
				say( feedback, ( result && result.data && result.data.message ) || cfg.i18n.genericError, true );
				createButton.disabled = false;
			} ).catch( function () {
				say( feedback, cfg.i18n.genericError, true );
				createButton.disabled = false;
			} );
		} );
	}

	/* --------------------------------------------------------------- reorder */

	var listCard = root.querySelector( '.rv-addon-list-card' );
	if ( listCard && root.querySelector( '.rv-addon-drag' ) ) {
		enableDragging( listCard );
	}

	function enableDragging( container ) {
		var dragged = null;

		container.querySelectorAll( '.rv-addon-row' ).forEach( function ( row ) {
			row.setAttribute( 'draggable', 'true' );

			row.addEventListener( 'dragstart', function () {
				dragged = row;
				row.classList.add( 'is-dragging' );
			} );

			row.addEventListener( 'dragend', function () {
				row.classList.remove( 'is-dragging' );
				dragged = null;
				persistOrder( container );
			} );

			row.addEventListener( 'dragover', function ( event ) {
				event.preventDefault();
				if ( ! dragged || dragged === row ) {
					return;
				}
				var box = row.getBoundingClientRect();
				var after = event.clientY > box.top + box.height / 2;
				row.parentNode.insertBefore( dragged, after ? row.nextSibling : row );
			} );
		} );
	}

	function persistOrder( container ) {
		var ids = Array.prototype.map.call(
			container.querySelectorAll( '.rv-addon-row' ),
			function ( row ) {
				return row.dataset.addonId;
			}
		);

		post( 'mhmrentiva_addon_reorder', { order: ids } ).then( function ( result ) {
			if ( ! result || ! result.success ) {
				// The server refused the whole batch, so the database still
				// holds the previous order; reloading is what puts the screen
				// back in step with it rather than leaving a lie on screen.
				report( ( result && result.data && result.data.message ) || cfg.i18n.genericError );
				window.setTimeout( function () {
					window.location.reload();
				}, 1200 );
			}
		} ).catch( function () {
			window.location.reload();
		} );
	}
} )();
