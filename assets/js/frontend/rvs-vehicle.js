/**
 * Rentiva front-end standard -- vehicle page (`rentiva_vehicle_details`).
 *
 * Each [data-rvs-vehicle] root keeps its own state. Month navigation and the
 * booking card's window ask the server and drop in its partials (the same
 * ones the page rendered), so the page and a later change cannot disagree.
 * The gallery viewer and the phone's breakdown sheet are shared rvs dialogs
 * (rvs-dialog.js).
 *
 * Config: window.mhmRentivaVehicle = { ajax_url, nonce, i18n: { error, days_one, days_many } }.
 */
( function ( $ ) {
	'use strict';

	const DEBOUNCE = 300;

	function config() {
		return window.mhmRentivaVehicle || {};
	}

	function t( key, fallback ) {
		const i18n = config().i18n || {};
		return i18n[ key ] || fallback;
	}

	function dialog() {
		return window.mhmRentivaRvsDialog;
	}

	function stateOf( $root ) {
		let state = $root.data( 'rvsVehicleState' );
		if ( ! state ) {
			const first = $root.find( '[data-rvs-vd-months] [data-rvs-calendar]' ).first();
			state = {
				year: parseInt( first.attr( 'data-year' ), 10 ) || new Date().getFullYear(),
				month: parseInt( first.attr( 'data-month' ), 10 ) || new Date().getMonth() + 1,
				loading: false,
				queued: false,
				timer: null,
				gallery: null,
			};
			$root.data( 'rvsVehicleState', state );
		}
		return state;
	}

	// The dates and the time only: the car is picked up and returned at its
	// own point, which the server takes from the car (PR S) -- a location
	// field, should one turn up in the form, is not posted.
	const WINDOW_KEYS = [ 'pickup_date', 'pickup_time', 'return_date' ];

	function windowFields( $root ) {
		const out = {};
		$root.find( '[data-rvs-vd-window]' ).serializeArray().forEach( function ( item ) {
			if ( WINDOW_KEYS.indexOf( item.name ) !== -1 ) {
				out[ item.name ] = item.value;
			}
		} );
		return out;
	}

	function post( data, done, fail, always ) {
		$.ajax( {
			url: config().ajax_url,
			type: 'POST',
			dataType: 'json',
			data: Object.assign( { nonce: config().nonce }, data ),
			success: function ( response ) {
				if ( response && response.success ) {
					done( response.data || {} );
				} else {
					fail( response && response.data && response.data.message );
				}
			},
			error: function () {
				fail();
			},
			complete: always,
		} );
	}

	function showMessage( $root, text ) {
		const $message = $root.find( '[data-rvs-vd-message]' );
		$message.text( text || '' ).prop( 'hidden', ! text );
	}

	function setMonthsFrom( $root, state ) {
		const first = $root.find( '[data-rvs-vd-months] [data-rvs-calendar]' ).first();
		state.year = parseInt( first.attr( 'data-year' ), 10 ) || state.year;
		state.month = parseInt( first.attr( 'data-month' ), 10 ) || state.month;
	}

	/* Months ------------------------------------------------------------------ */

	function shiftMonth( $root, step ) {
		const state = stateOf( $root );
		let month = state.month + step;
		let year = state.year;
		if ( month < 1 ) {
			month = 12;
			year -= 1;
		} else if ( month > 12 ) {
			month = 1;
			year += 1;
		}
		post(
			Object.assign( windowFields( $root ), {
				action: 'mhmrentiva_vd_months',
				vehicle_id: String( $root.data( 'rvsVehicle' ) ),
				year: year,
				month: month,
			} ),
			function ( data ) {
				state.year = year;
				state.month = month;
				$root.find( '[data-rvs-vd-months]' ).html( data.html || '' );
			},
			function ( message ) {
				showMessage( $root, message || t( 'error', 'An error occurred' ) );
			}
		);
	}

	/* Booking card ------------------------------------------------------------- */

	function daysLabel( days ) {
		const format = days === 1 ? t( 'days_one', '%s day total' ) : t( 'days_many', '%s days total' );
		return format.replace( '%s', String( days ) );
	}

	// A Y-m-d date into a date field: the shared datepicker init shows it in the
	// site's format and keeps Y-m-d in the field it submits (posted by
	// windowFields() through the form's serialization).
	function setDate( $field, iso ) {
		const api = window.mhmRentivaDatepicker;
		if ( api ) {
			api.setValue( $field, iso );
		} else {
			$field.val( iso );
		}
	}

	// Until a quote for the visible window arrives (or after one fails), the
	// links must not carry the previous window to the booking form.
	function holdContinue( $root ) {
		$root.find( '[data-rvs-vd-continue]' ).attr( { 'aria-disabled': 'true', tabindex: '-1' } );
	}

	function applyQuote( $root, data ) {
		const state = stateOf( $root );
		// The server's window is the one quoted: show it in the fields -- unless
		// a newer change is queued, which must keep the visitor's latest choice.
		if ( data.window && ! state.queued ) {
			const $form = $root.find( '[data-rvs-vd-window]' );
			setDate( $form.find( '[data-rvs-pickup-date]' ), data.window.pickup_date );
			setDate( $form.find( '[data-rvs-return-date]' ), data.window.return_date );
			$form.find( '[data-rvs-pickup-time]' ).val( data.window.pickup_time );
			$root.find( '[data-rvs-return-time-display]' ).text( data.window.pickup_time );
		}
		$root.find( '[data-rvs-vd-total]' ).html( data.total_html || '' );
		$root.find( '[data-rvs-vd-days]' ).text( daysLabel( parseInt( data.days, 10 ) || 0 ) );
		$root.find( '[data-rvs-vd-breakdown]' ).html( data.breakdown_html || '' );
		$root.find( '[data-rvs-vd-breakdown-dialog] details' ).prop( 'open', true );
		if ( data.months_html ) {
			$root.find( '[data-rvs-vd-months]' ).html( data.months_html );
			setMonthsFrom( $root, state );
		}
		$root.find( '[data-rvs-vd-continue]' ).each( function () {
			this.setAttribute( 'href', data.continue_url || '#' );
			if ( data.available ) {
				this.removeAttribute( 'aria-disabled' );
				this.removeAttribute( 'tabindex' );
			} else {
				this.setAttribute( 'aria-disabled', 'true' );
				this.setAttribute( 'tabindex', '-1' );
			}
		} );
		showMessage( $root, data.available ? '' : data.message || '' );
	}

	function requestQuote( $root ) {
		const state = stateOf( $root );
		if ( state.loading ) {
			state.queued = true;
			return;
		}
		state.loading = true;
		$root.attr( 'aria-busy', 'true' );
		post(
			Object.assign( windowFields( $root ), {
				action: 'mhmrentiva_vd_quote',
				vehicle_id: String( $root.data( 'rvsVehicle' ) ),
			} ),
			function ( data ) {
				// A newer change is waiting: this answer is for a window the visitor
				// already left, so none of it is shown (the queued request follows).
				if ( ! state.queued ) {
					applyQuote( $root, data );
				}
			},
			function ( message ) {
				showMessage( $root, message || t( 'error', 'An error occurred' ) );
			},
			function () {
				state.loading = false;
				$root.removeAttr( 'aria-busy' );
				if ( state.queued ) {
					state.queued = false;
					requestQuote( $root );
				}
			}
		);
	}

	function scheduleQuote( $root ) {
		const state = stateOf( $root );
		clearTimeout( state.timer );
		state.timer = setTimeout( function () {
			requestQuote( $root );
		}, DEBOUNCE );
	}

	/* Gallery viewer ----------------------------------------------------------- */

	function galleryImages( $root ) {
		const raw = $root.find( '[data-rvs-gallery]' ).attr( 'data-rvs-gallery' );
		try {
			const list = JSON.parse( raw || '[]' );
			return Array.isArray( list ) ? list : [];
		} catch ( e ) {
			return [];
		}
	}

	function showImage( $root, index ) {
		const state = stateOf( $root );
		const images = galleryImages( $root );
		if ( ! images.length ) {
			return;
		}
		state.gallery = ( index + images.length ) % images.length;
		const image = images[ state.gallery ];
		const $viewer = $root.find( '[data-rvs-gallery-viewer]' );
		$viewer.find( '[data-rvs-gallery-img]' ).attr( { src: image.src, alt: image.alt || '' } );
		$viewer.find( '[data-rvs-gallery-count]' ).text( ( state.gallery + 1 ) + ' / ' + images.length );
	}

	function openSheet( el, opener, extra ) {
		if ( ! el || ! dialog() ) {
			return;
		}
		el.hidden = false;
		el.classList.add( 'is-open' );
		const title = el.querySelector( '[id]' );
		dialog().open( el, Object.assign( {
			opener: opener,
			labelledBy: title ? title.id : undefined,
			initialFocus: el.querySelector( '[data-rvs-dialog-close]' ) || undefined,
			onClose: function () {
				el.classList.remove( 'is-open' );
				el.hidden = true;
			},
		}, extra || {} ) );
	}

	function openGallery( $root, index, opener ) {
		const viewer = $root.find( '[data-rvs-gallery-viewer]' ).get( 0 );
		showImage( $root, index );
		openSheet( viewer, opener );
	}

	/* Wiring ------------------------------------------------------------------ */

	function init( $root ) {
		if ( $root.data( 'rvsVehicleReady' ) ) {
			return;
		}
		$root.data( 'rvsVehicleReady', true );
		stateOf( $root );

		$root.on( 'click', '[data-rvs-month-nav]', function () {
			shiftMonth( $root, parseInt( $( this ).attr( 'data-rvs-month-nav' ), 10 ) || 0 );
		} );
		$root.on( 'submit', '[data-rvs-vd-window]', function ( e ) {
			e.preventDefault();
		} );
		$root.on( 'change', '[data-rvs-vd-window] input, [data-rvs-vd-window] select', function () {
			if ( this.hasAttribute( 'data-rvs-pickup-time' ) ) {
				$root.find( '[data-rvs-return-time-display]' ).text( this.value );
			}
			holdContinue( $root );
			scheduleQuote( $root );
		} );
		$root.on( 'click', '[data-rvs-gallery-open]', function () {
			openGallery( $root, parseInt( $( this ).attr( 'data-rvs-gallery-open' ), 10 ) || 0, this );
		} );
		// Phone: the sideways strip's "n / N" follows the photo in view
		// (scroll does not bubble, so it is bound on the strip itself).
		$root.find( '[data-rvs-gallery-strip]' ).on( 'scroll', function () {
			const total = galleryImages( $root ).length;
			const width = this.clientWidth || 1;
			const index = Math.min( total, Math.round( this.scrollLeft / width ) + 1 );
			$root.find( '[data-rvs-gallery-counter]' ).text( index + ' / ' + total );
		} );
		$root.on( 'click', '[data-rvs-gallery-step]', function () {
			showImage( $root, stateOf( $root ).gallery + ( parseInt( $( this ).attr( 'data-rvs-gallery-step' ), 10 ) || 0 ) );
		} );
		$root.on( 'click', '[data-rvs-vd-breakdown-open]', function () {
			const sheet = $root.find( '[data-rvs-vd-breakdown-dialog]' ).get( 0 );
			$( sheet ).find( 'details' ).prop( 'open', true );
			openSheet( sheet, this );
		} );
		$root.on( 'click', '[data-rvs-dialog-close]', function () {
			const el = $( this ).closest( '.rvs-dialog' ).get( 0 );
			if ( dialog() && el ) {
				dialog().close( el );
			}
		} );
	}

	// Arrow keys step through the open gallery viewer.
	$( document ).on( 'keydown', function ( e ) {
		if ( ( e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' ) || ! dialog() ) {
			return;
		}
		const open = dialog().current();
		if ( ! open || ! open.hasAttribute( 'data-rvs-gallery-viewer' ) ) {
			return;
		}
		const $root = $( open ).closest( '[data-rvs-vehicle]' );
		e.preventDefault();
		showImage( $root, stateOf( $root ).gallery + ( e.key === 'ArrowRight' ? 1 : -1 ) );
	} );

	$( function () {
		$( '[data-rvs-vehicle]' ).each( function () {
			init( $( this ) );
		} );
	} );
}( jQuery ) );
