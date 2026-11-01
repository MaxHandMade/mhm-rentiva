/**
 * Rentiva front-end standard -- availability calendar (`rentiva_availability_calendar`).
 *
 * Each [data-rvs-availability] root keeps its own state and reads its settings
 * from its own data attributes, so two calendars on a page stay apart. Months,
 * the vehicle card and the total all come from the server (CalendarView): this
 * script formats no date, computes no price and builds no URL.
 *
 * The page's HTML may be cached for guests, so the months are asked for again on
 * load. A day's selected look is set here, also after a month change: the server
 * draws every day unselected, the buttons' aria-pressed tells which are chosen.
 *
 * Config: window.mhmRentivaAvailability = { ajax_url, nonce, strings: { error, loading, pickReturn } }.
 */
( function ( $ ) {
	'use strict';

	const ROOT = '[data-rvs-availability]';
	const PREVIEW = '[data-rv-render="preview"]';
	const STATE = 'rvsAvailabilityState';

	function config() {
		return window.mhmRentivaAvailability || {};
	}

	function t( key ) {
		return ( config().strings || {} )[ key ] || '';
	}

	function dialog() {
		return window.mhmRentivaRvsDialog;
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
			error: function ( xhr ) {
				const body = xhr && xhr.responseJSON;
				fail( body && body.data && body.data.message );
			},
			complete: always,
		} );
	}

	// A `Y-m` moved by whole months; arithmetic only, nothing shown to the visitor.
	function stepMonth( month, step ) {
		const parts = String( month ).split( '-' );
		const total = parseInt( parts[ 0 ], 10 ) * 12 + parseInt( parts[ 1 ], 10 ) - 1 + step;
		return Math.floor( total / 12 ) + '-' + String( ( total % 12 ) + 1 ).padStart( 2, '0' );
	}

	/* Selection and panel ------------------------------------------------------ */

	function markSelection( $root, state ) {
		const last = state.end || state.start;
		$root.find( '.rvs-cal__btn' ).each( function () {
			const date = this.getAttribute( 'data-date' );
			const chosen = '' !== state.start && date >= state.start && date <= last;
			this.setAttribute( 'aria-pressed', chosen ? 'true' : 'false' );
			$( this ).closest( '.rvs-cal__day' )
				.toggleClass( 'is-selected', chosen )
				.toggleClass( 'is-available', ! chosen )
				.toggleClass( 'is-range-start', date === state.start )
				.toggleClass( 'is-range-end', date === last );
		} );
	}

	/**
	 * The whole panel, drawn from the state: nothing of an older answer survives.
	 * Stops at the first line that applies, so an error wins over a status.
	 */
	function renderPanel( $root, state ) {
		const quote = state.quote;
		let notice = '';
		let isError = false;
		if ( state.error ) {
			notice = state.error;
			isError = true;
		} else if ( state.quoting ) {
			notice = t( 'loading' );
		} else if ( quote && quote.message ) {
			notice = quote.message;
			isError = ! quote.available;
		} else if ( state.start && ! state.end ) {
			notice = t( 'pickReturn' );
		}

		const range = quote ? quote.range_label || '' : '';
		const time = quote ? quote.time_hint || '' : '';
		const total = state.pricing && quote && quote.priced ? quote.total_html || '' : '';
		const url = state.booking && quote && quote.available ? quote.continue_url || '' : '';

		// Visible before its text changes, so the live region is heard.
		$root.find( '[data-rvs-cal-panel]' ).prop( 'hidden', ! ( notice || range || time || total || url ) );
		$root.find( '[data-rvs-cal-range]' ).text( range );
		$root.find( '[data-rvs-cal-time]' ).text( time );
		// Server HTML (PriceDisplay), like the months.
		$root.find( '[data-rvs-cal-total]' ).html( total );

		const $message = $root.find( '[data-rvs-cal-message]' );
		if ( $message.text() !== notice ) {
			$message.text( notice );
		}
		if ( isError ) {
			$message.attr( 'data-state', 'error' );
		} else {
			$message.removeAttr( 'data-state' );
		}

		$root.find( '[data-rvs-cal-book]' ).attr( 'href', url || '#' ).prop( 'hidden', ! url );
	}

	function clearSelection( state ) {
		state.quoteSeq += 1;
		state.start = '';
		state.end = '';
		state.quote = null;
		state.quoting = false;
		state.error = '';
	}

	function requestQuote( $root, state ) {
		state.quoteSeq += 1;
		const seq = state.quoteSeq;
		state.quote = null;
		state.quoting = true;
		state.error = '';
		renderPanel( $root, state );

		post(
			{
				action: 'mhmrentiva_calendar_quote',
				vehicle_id: String( state.vehicleId ),
				start_date: state.start,
				end_date: state.end,
			},
			function ( data ) {
				if ( seq !== state.quoteSeq ) {
					return;
				}
				state.quoting = false;
				state.quote = data;
				renderPanel( $root, state );
			},
			function ( message ) {
				if ( seq !== state.quoteSeq ) {
					return;
				}
				state.quoting = false;
				state.error = message || t( 'error' );
				renderPanel( $root, state );
			}
		);
	}

	function selectDay( $root, state, date ) {
		if ( state.start && ! state.end && date === state.start ) {
			// The same day again: it stays the start, there is still no range.
			state.error = '';
		} else if ( state.start && ! state.end && date > state.start ) {
			state.end = date;
			markSelection( $root, state );
			requestQuote( $root, state );
			return;
		} else {
			clearSelection( state );
			state.start = date;
		}
		markSelection( $root, state );
		renderPanel( $root, state );
	}

	/* Months ------------------------------------------------------------------- */

	function setPrevAllowed( $root, allowed ) {
		const $prev = $root.find( '[data-rvs-cal-nav="-1"]' );
		// A disabled button drops the keyboard's focus: hand it to the next one.
		if ( ! allowed && $prev.is( document.activeElement ) ) {
			$root.find( '[data-rvs-cal-nav="1"]' ).trigger( 'focus' );
		}
		$prev.prop( 'disabled', ! allowed );
	}

	function monthsRequest( state, month ) {
		return {
			action: 'mhmrentiva_calendar_months',
			vehicle_id: String( state.vehicleId ),
			start_month: month,
			months_to_show: state.monthsToShow,
			week_start: state.weekStart,
		};
	}

	function applyMonths( $root, state, data ) {
		$root.find( '[data-rvs-cal-months]' ).html( data.html || '' );
		if ( data.start_month ) {
			state.month = data.start_month;
			$root.attr( 'data-start-month', data.start_month );
		}
		state.target = state.month;
		setPrevAllowed( $root, !! data.prev_allowed );
		markSelection( $root, state );
	}

	function loadMonths( $root, state, month ) {
		state.monthSeq += 1;
		const seq = state.monthSeq;
		state.target = month;
		$root.attr( 'aria-busy', 'true' );

		post(
			monthsRequest( state, month ),
			function ( data ) {
				if ( seq !== state.monthSeq ) {
					return;
				}
				applyMonths( $root, state, data );
				state.error = '';
				renderPanel( $root, state );
			},
			function ( message ) {
				if ( seq !== state.monthSeq ) {
					return;
				}
				state.target = state.month;
				state.error = message || t( 'error' );
				renderPanel( $root, state );
			},
			function () {
				if ( seq === state.monthSeq ) {
					$root.removeAttr( 'aria-busy' );
				}
			}
		);
	}

	/* Vehicle ------------------------------------------------------------------ */

	function applyCard( $root, state, card ) {
		$root.find( '[data-rvs-cal-name]' ).text( card.title || '' );
		if ( state.pricing ) {
			// Server HTML (PriceDisplay), like the total.
			$root.find( '[data-rvs-cal-price]' ).html( card.price_html || '' );
		}

		let $image = $root.find( '[data-rvs-cal-image]' );
		if ( ! card.image ) {
			$image.remove();
			return;
		}
		if ( ! $image.length ) {
			$image = $( document.createElement( 'img' ) )
				.addClass( 'rvs-availability__image' )
				.attr( 'data-rvs-cal-image', '' )
				.prependTo( $root.find( '.rvs-availability__vehicle' ) );
		}
		$image.attr( { src: card.image, alt: card.title || '' } );
	}

	/**
	 * Card and months change together or not at all, so the name never sits over
	 * another car's days. While this runs the old calendar takes no clicks.
	 */
	function switchVehicle( $root, state, vehicleId ) {
		const next = Object.assign( {}, state, { vehicleId: vehicleId } );
		let card = null;
		let months = null;
		let failed = false;
		let failure = '';
		let pending = 2;

		const onFail = function ( message ) {
			failed = true;
			failure = failure || message || '';
		};
		const finish = function () {
			pending -= 1;
			if ( pending > 0 ) {
				return;
			}
			state.switching = false;
			$root.removeAttr( 'aria-busy' );
			if ( failed || ! card || ! months || false === card.bookable ) {
				state.error = failure || t( 'error' );
				renderPanel( $root, state );
				return;
			}
			state.vehicleId = vehicleId;
			$root.attr( 'data-vehicle-id', String( vehicleId ) );
			$root.find( '[data-rvs-cal-option]' ).each( function () {
				this.setAttribute( 'aria-pressed', parseInt( this.getAttribute( 'data-rvs-cal-option' ), 10 ) === vehicleId ? 'true' : 'false' );
			} );
			applyCard( $root, state, card );
			applyMonths( $root, state, months );
			renderPanel( $root, state );
		};

		clearSelection( state );
		// A month answer still on its way belongs to the old car.
		state.monthSeq += 1;
		state.target = state.month;
		state.switching = true;
		$root.attr( 'aria-busy', 'true' );
		markSelection( $root, state );
		renderPanel( $root, state );

		post(
			{ action: 'mhmrentiva_get_vehicle_info', vehicle_id: String( vehicleId ) },
			function ( data ) {
				card = data;
			},
			onFail,
			finish
		);
		post(
			monthsRequest( next, state.month ),
			function ( data ) {
				months = data;
			},
			onFail,
			finish
		);
	}

	function openDialog( $root, opener ) {
		const el = $root.find( '[data-rvs-cal-dialog]' ).get( 0 );
		if ( ! el || ! dialog() ) {
			return;
		}
		el.hidden = false;
		el.classList.add( 'is-open' );
		const title = el.querySelector( '.rvs-dialog__title' );
		dialog().open( el, {
			opener: opener,
			labelledBy: title && title.id ? title.id : undefined,
			initialFocus: el.querySelector( '[data-rvs-cal-option][aria-pressed="true"]' ) || el.querySelector( '[data-rvs-dialog-close]' ) || undefined,
			onClose: function () {
				el.classList.remove( 'is-open' );
				el.hidden = true;
			},
		} );
	}

	function closeDialog( el ) {
		if ( el && dialog() ) {
			dialog().close( el );
		}
	}

	/* Wiring ------------------------------------------------------------------- */

	function init( $root ) {
		if ( $root.data( STATE ) || $root.closest( PREVIEW ).length ) {
			return;
		}
		const month = String( $root.attr( 'data-start-month' ) || '' );
		const state = {
			vehicleId: parseInt( $root.attr( 'data-vehicle-id' ), 10 ) || 0,
			month: month,
			target: month,
			monthsToShow: parseInt( $root.attr( 'data-months-to-show' ), 10 ) || 1,
			weekStart: parseInt( $root.attr( 'data-week-start' ), 10 ) || 0,
			pricing: '1' === $root.attr( 'data-show-pricing' ),
			booking: '1' === $root.attr( 'data-show-booking-button' ),
			start: '',
			end: '',
			quote: null,
			quoting: false,
			quoteSeq: 0,
			monthSeq: 0,
			error: '',
			switching: false,
		};
		$root.data( STATE, state );

		$root.find( '[data-rvs-cal-switch]' ).attr( 'aria-haspopup', 'dialog' );

		// The button, not the cell: the td carries data-date too, also on a booked day.
		$root.on( 'click', '.rvs-cal__btn', function () {
			if ( ! state.switching ) {
				selectDay( $root, state, this.getAttribute( 'data-date' ) );
			}
		} );
		$root.on( 'click', '[data-rvs-cal-nav]', function () {
			if ( ! state.switching ) {
				loadMonths( $root, state, stepMonth( state.target, parseInt( this.getAttribute( 'data-rvs-cal-nav' ), 10 ) || 0 ) );
			}
		} );
		$root.on( 'click', '[data-rvs-cal-switch]', function () {
			openDialog( $root, this );
		} );
		$root.on( 'click', '[data-rvs-cal-option]', function () {
			const vehicleId = parseInt( this.getAttribute( 'data-rvs-cal-option' ), 10 ) || 0;
			closeDialog( this.closest( '[data-rvs-cal-dialog]' ) );
			if ( vehicleId && vehicleId !== state.vehicleId && ! state.switching ) {
				switchVehicle( $root, state, vehicleId );
			}
		} );
		$root.on( 'click', '[data-rvs-cal-dialog] [data-rvs-dialog-close]', function () {
			closeDialog( this.closest( '[data-rvs-cal-dialog]' ) );
		} );
		// The dimmed area around the panel.
		$root.on( 'click', '[data-rvs-cal-dialog]', function ( e ) {
			if ( e.target === this ) {
				closeDialog( this );
			}
		} );

		loadMonths( $root, state, month );
	}

	function initAll() {
		$( ROOT ).each( function () {
			init( $( this ) );
		} );
	}

	window.mhmRentivaAvailabilityCalendar = { init: initAll };

	$( initAll );
}( jQuery ) );
