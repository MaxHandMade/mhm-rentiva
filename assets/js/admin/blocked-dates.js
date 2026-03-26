/* global flatpickr */
( function ( $ ) {
	'use strict';

	var blockedDates = [];
	var blockedNotes = {}; // date → note text
	var fp           = null;

	function init() {
		var $hidden = $( '#mhm_blocked_dates_value' );
		if ( ! $hidden.length ) {
			return;
		}

		try {
			blockedDates = JSON.parse( $hidden.val() ) || [];
		} catch ( e ) {
			blockedDates = [];
		}

		try {
			var parsedNotes = JSON.parse( $( '#mhm_blocked_dates_notes_value' ).val() );
			blockedNotes = ( parsedNotes && ! Array.isArray( parsedNotes ) && typeof parsedNotes === 'object' ) ? parsedNotes : {};
		} catch ( e ) {
			blockedNotes = {};
		}

		fp = flatpickr( '#mhm_blocked_dates_picker', {
			mode: 'multiple',
			dateFormat: 'Y-m-d',
			inline: true,
			defaultDate: blockedDates.slice(),
			onChange: function ( selectedDates ) {
				blockedDates = selectedDates.map( function ( d ) {
					return flatpickr.formatDate( d, 'Y-m-d' );
				} );
				blockedDates.sort();

				// Remove notes for deselected dates
				Object.keys( blockedNotes ).forEach( function ( d ) {
					if ( blockedDates.indexOf( d ) === -1 ) {
						delete blockedNotes[ d ];
					}
				} );

				syncHiddenFields();
				renderChips();
			},
		} );

		renderChips();

		// Re-read all note inputs from DOM before form submission
		$( '#post' ).on( 'submit.blockedDates', function () {
			var notes = {};
			$( '.blocked-date-chip-note' ).each( function () {
				var date = $( this ).attr( 'data-date' );
				var note = $( this ).val().trim();
				if ( date && note ) {
					notes[ date ] = note;
				}
			} );
			blockedNotes = notes;
			syncHiddenFields();
		} );

		// Remove chip
		$( document ).on( 'click', '.blocked-date-chip-remove', function () {
			var date = $( this ).data( 'date' );
			blockedDates = blockedDates.filter( function ( d ) { return d !== date; } );
			delete blockedNotes[ date ];
			if ( fp ) {
				fp.setDate( blockedDates.slice(), false );
			}
			syncHiddenFields();
			renderChips();
		} );

		// Clear all
		$( document ).on( 'click', '#mhm-clear-all-blocked', function () {
			blockedDates = [];
			blockedNotes = {};
			if ( fp ) { fp.clear(); }
			syncHiddenFields();
			renderChips();
		} );

		// Apply to all vehicles
		$( document ).on( 'click', '#mhm-apply-blocked-to-all', function () {
			var $btn    = $( this );
			var $result = $( '#mhm-apply-result' );
			var nonce   = $( '#mhm_apply_to_all_nonce' ).val();
			var vid     = $( '#mhm_current_vehicle_id' ).val();

			if ( ! confirm( mhmBlockedDatesL10n.confirmApply ) ) {
				return;
			}

			$btn.prop( 'disabled', true ).addClass( 'is-loading' );
			$result.hide();

			$.post(
				window.ajaxurl || '/wp-admin/admin-ajax.php',
				{
					action:     'mhm_apply_blocked_dates_to_all',
					nonce:      nonce,
					vehicle_id: vid,
					dates:      JSON.stringify( blockedDates ),
					notes:      JSON.stringify( blockedNotes ),
				},
				function ( response ) {
					$btn.prop( 'disabled', false ).removeClass( 'is-loading' );
					if ( response.success ) {
						$result
							.text( mhmBlockedDatesL10n.appliedTo.replace( '%d', response.data.count ) )
							.removeClass( 'apply-result-error' )
							.addClass( 'apply-result-success' )
							.show();
					} else {
						$result
							.text( response.data || mhmBlockedDatesL10n.error )
							.removeClass( 'apply-result-success' )
							.addClass( 'apply-result-error' )
							.show();
					}
					setTimeout( function () { $result.fadeOut(); }, 4000 );
				}
			);
		} );

		// Remove from all vehicles
		$( document ).on( 'click', '#mhm-remove-blocked-from-all', function () {
			var $btn    = $( this );
			var $result = $( '#mhm-apply-result' );
			var nonce   = $( '#mhm_remove_from_all_nonce' ).val();
			var vid     = $( '#mhm_current_vehicle_id' ).val();

			if ( ! confirm( mhmBlockedDatesL10n.confirmRemove ) ) {
				return;
			}

			$btn.prop( 'disabled', true ).addClass( 'is-loading' );
			$result.hide();

			$.post(
				window.ajaxurl || '/wp-admin/admin-ajax.php',
				{
					action:     'mhm_remove_blocked_dates_from_all',
					nonce:      nonce,
					vehicle_id: vid,
					dates:      JSON.stringify( blockedDates ),
				},
				function ( response ) {
					$btn.prop( 'disabled', false ).removeClass( 'is-loading' );
					if ( response.success ) {
						$result
							.text( mhmBlockedDatesL10n.removedFrom.replace( '%d', response.data.count ) )
							.removeClass( 'apply-result-error' )
							.addClass( 'apply-result-success' )
							.show();
					} else {
						$result
							.text( response.data || mhmBlockedDatesL10n.error )
							.removeClass( 'apply-result-success' )
							.addClass( 'apply-result-error' )
							.show();
					}
					setTimeout( function () { $result.fadeOut(); }, 4000 );
				}
			);
		} );

		// Note input change (delegated — chips are re-rendered)
		$( document ).on( 'input change', '.blocked-date-chip-note', function () {
			var date = $( this ).attr( 'data-date' );
			var note = $( this ).val().trim();
			if ( date ) {
				if ( note ) {
					blockedNotes[ date ] = note;
				} else {
					delete blockedNotes[ date ];
				}
				syncHiddenFields();
			}
		} );
	}

	function syncHiddenFields() {
		$( '#mhm_blocked_dates_value' ).val( JSON.stringify( blockedDates ) );
		$( '#mhm_blocked_dates_notes_value' ).val( JSON.stringify( blockedNotes ) );
	}

	function formatDisplayDate( dateStr ) {
		var parts = dateStr.split( '-' );
		if ( parts.length !== 3 ) { return dateStr; }
		var d = new Date( parseInt( parts[0], 10 ), parseInt( parts[1], 10 ) - 1, parseInt( parts[2], 10 ) );
		if ( isNaN( d.getTime() ) ) { return dateStr; }
		return d.toLocaleDateString( document.documentElement.lang || 'tr-TR', {
			day: '2-digit', month: 'short', year: 'numeric',
		} );
	}

	function renderChips() {
		var $chips    = $( '#mhm-blocked-dates-chips' );
		var $empty    = $( '#mhm-blocked-empty' );
		var $badge    = $( '#mhm-blocked-count-badge' );
		var $num      = $( '#mhm-blocked-count-num' );
		var $clearBtn = $( '#mhm-clear-all-blocked' );

		$chips.find( '.blocked-date-chip' ).remove();

		if ( blockedDates.length === 0 ) {
			$empty.show();
			$badge.hide();
			$clearBtn.hide();
			$( '#mhm-apply-blocked-to-all' ).prop( 'disabled', true );
			$( '#mhm-remove-blocked-from-all' ).prop( 'disabled', true );
			return;
		}
		$( '#mhm-apply-blocked-to-all' ).prop( 'disabled', false );
		$( '#mhm-remove-blocked-from-all' ).prop( 'disabled', false );

		$empty.hide();
		$badge.show();
		$clearBtn.show();
		$num.text( blockedDates.length );

		blockedDates.forEach( function ( date ) {
			var safeDate  = $( '<span>' ).text( date ).html();
			var displayed = formatDisplayDate( date );
			var noteVal   = blockedNotes[ date ] || '';
			var safeNote  = $( '<span>' ).text( noteVal ).html();

			var chip = $(
				'<div class="blocked-date-chip">' +
					'<div class="blocked-date-chip-top">' +
						'<span class="blocked-date-chip-label">' +
							'<span class="dashicons dashicons-lock"></span>' +
							$( '<span>' ).text( displayed ).html() +
						'</span>' +
						'<button type="button" class="blocked-date-chip-remove" data-date="' + safeDate + '" title="Remove">&times;</button>' +
					'</div>' +
					'<div class="blocked-date-chip-note-wrap">' +
						'<input type="text" class="blocked-date-chip-note" data-date="' + safeDate + '" value="' + safeNote + '" placeholder="' + ( mhmBlockedDatesL10n.notePlaceholder || 'Add note... (optional)' ) + '" maxlength="120">' +
					'</div>' +
				'</div>'
			);
			$chips.append( chip );
		} );
	}

	$( document ).ready( init );
}( jQuery ) );
