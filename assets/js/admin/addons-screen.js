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
			} else {
				// Only on success, and only from the server. The row flips
				// optimistically because a row that waits feels broken; a
				// counter that moved optimistically would be asserting a total
				// it does not know yet and would have to be taken back on the
				// revert.
				applyStats( result.data && result.data.stats );
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

	/**
	 * Write the KPI band and the list counter from the server's figures.
	 *
	 * AddonStats owns these numbers and the toggle invalidates its cache, so
	 * they arrive already correct and already formatted -- two of the four are
	 * currency, which this file has no business assembling. Recomputing them
	 * here would be a second source of the same truth, free to disagree.
	 *
	 * Silently does nothing without a payload: an older response, or a future
	 * caller that has no stats to give, must not blank the counters.
	 */
	function applyStats( stats ) {
		if ( ! stats ) {
			return;
		}

		Object.keys( stats ).forEach( function ( key ) {
			var value = root.querySelector( '[data-stat="' + key + '"] .mhm-stat-card__value' );
			if ( value ) {
				value.textContent = String( stats[ key ] );
			}
		} );

		var share = root.querySelector( '[data-stat="active_addons"] .mhm-stat-card__sub' );
		if ( share && cfg.i18n.activeShare ) {
			// Substitute first, THEN collapse %% to %. These templates are
			// written for PHP's sprintf, where %% is how you get a literal
			// percent sign, and they reach us unprocessed. Order matters: the
			// Turkish string is "%%%s aktif", so collapsing first would eat the
			// escape and leave "%%s".
			share.textContent = cfg.i18n.activeShare
				.replace( '%s', String( stats.active_percentage ) )
				.replace( /%%/g, '%' );
		}

		var count = root.querySelector( '.rv-addon-count' );
		if ( count && cfg.i18n.countLabel ) {
			count.textContent = cfg.i18n.countLabel
				.replace( '%1$d', String( stats.active_addons ) )
				.replace( '%2$d', String( stats.total_addons ) );
		}
	}

	function applyState( button, row, on ) {
		button.dataset.enabled = on ? '1' : '0';
		button.textContent = on ? cfg.i18n.active : cfg.i18n.inactive;
		button.classList.toggle( 'is-on', on );

		// The label and the row already say the state to anyone who can see
		// them. aria-pressed is what says it to anyone who cannot: without it
		// this is a plain button, and activating it announces a click rather
		// than a change of state. It moves here, with the optimistic flip, so
		// it can never disagree with the label -- including on the revert.
		button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );

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

	/* ----------------------------------------------------------- inline price */

	root.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '.rv-addon-price-value' );
		if ( ! trigger || trigger.dataset.editing === '1' ) {
			return;
		}

		var row = trigger.closest( '.rv-addon-row' );
		var original = trigger.textContent;
		var input = document.createElement( 'input' );

		input.type = 'number';
		input.step = '0.01';
		input.min = '0';
		// The raw number, not the rendered string: that one carries a currency
		// symbol and locale separators and would parse back as NaN.
		input.value = trigger.dataset.price;
		input.className = 'rv-addon-price-input';

		trigger.dataset.editing = '1';
		trigger.replaceWith( input );
		input.focus();
		input.select();

		var settled = false;

		function finish( save ) {
			if ( settled ) {
				return;
			}
			settled = true;

			if ( ! save || input.value === trigger.dataset.price || input.value === '' ) {
				input.replaceWith( trigger );
				trigger.dataset.editing = '0';
				return;
			}

			var next = input.value;
			input.disabled = true;

			post( 'mhmrentiva_update_addon_price', {
				addon_id: row.dataset.addonId,
				price: next
			} ).then( function ( result ) {
				if ( result && result.success && result.data && result.data.formatted_price ) {
					trigger.textContent = result.data.formatted_price;
					trigger.dataset.price = next;
				} else {
					report( ( result && result.data && result.data.message ) || cfg.i18n.genericError );
				}
				input.replaceWith( trigger );
				trigger.dataset.editing = '0';
			} ).catch( function () {
				report( cfg.i18n.genericError );
				input.replaceWith( trigger );
				trigger.dataset.editing = '0';
			} );
		}

		input.addEventListener( 'blur', function () {
			finish( true );
		} );

		input.addEventListener( 'keydown', function ( keyEvent ) {
			if ( keyEvent.key === 'Enter' ) {
				keyEvent.preventDefault();
				finish( true );
			}
			if ( keyEvent.key === 'Escape' ) {
				keyEvent.preventDefault();
				finish( false );
			}
		} );
	} );

	/* ------------------------------------------------------------------ bulk */

	var bulkBar = root.querySelector( '.rv-addon-bulk' );

	if ( bulkBar ) {
		root.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( '.rv-addon-select-all' ) ) {
				root.querySelectorAll( '.rv-addon-select' ).forEach( function ( box ) {
					box.checked = event.target.checked;
				} );
			}

			if ( event.target.matches( '.rv-addon-select, .rv-addon-select-all' ) ) {
				refreshBulkState();
			}
		} );

		bulkBar.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.rv-addon-bulk-action' );
			if ( ! button || button.disabled ) {
				return;
			}
			runBulk( button.dataset.bulk );
		} );
	}

	function selectedIds() {
		return Array.prototype.filter.call(
			root.querySelectorAll( '.rv-addon-select' ),
			function ( box ) {
				return box.checked;
			}
		).map( function ( box ) {
			return box.closest( '.rv-addon-row' ).dataset.addonId;
		} );
	}

	function refreshBulkState() {
		var count = selectedIds().length;

		root.querySelectorAll( '.rv-addon-bulk-action' ).forEach( function ( button ) {
			button.disabled = count === 0;
		} );

		var label = root.querySelector( '.rv-addon-bulk-count' );
		if ( label ) {
			label.textContent = count ? cfg.i18n.selectedCount.replace( '%d', count ) : '';
		}
	}

	function runBulk( action ) {
		var ids = selectedIds();
		if ( ! ids.length ) {
			return;
		}

		if ( action === 'delete' && ! window.confirm( cfg.i18n.confirmBulkDelete.replace( '%d', ids.length ) ) ) {
			return;
		}

		root.querySelectorAll( '.rv-addon-bulk-action' ).forEach( function ( button ) {
			button.disabled = true;
		} );

		// One request per row rather than a new batch endpoint. Each reuses an
		// endpoint that is already guarded and already tested, and a partial
		// failure leaves the rows it did reach correctly changed instead of
		// rolling back work the operator asked for. The reload afterwards is
		// what puts the screen back in step either way.
		var work = ids.map( function ( id ) {
			if ( action === 'delete' ) {
				return post( 'mhmrentiva_addon_delete', { addon_id: id } );
			}
			return post( 'mhmrentiva_addon_toggle_enabled', {
				addon_id: id,
				enabled: action === 'enable' ? '1' : '0'
			} );
		} );

		Promise.all( work ).then( function () {
			window.location.reload();
		} ).catch( function () {
			window.location.reload();
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
