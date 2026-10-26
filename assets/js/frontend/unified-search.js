/**
 * Search strip (front-end standard, Aşama 2 PR 3).
 *
 * The date fields are jQuery UI datepickers (datepicker-init.js, `.js-datepicker`):
 * they show the site's date format and submit Y-m-d, so dates are read and
 * written through window.mhmRentivaDatepicker (Y-m-d either way).
 * This script keeps the return date at least the minimum rental after pickup,
 * makes the return time follow the pickup time (the booking counts whole days)
 * and mirrors the pickup location into the drop-off field until drop-off
 * elsewhere ships. Hooks are data-rvs-* attributes inside [data-rvs-search].
 */
( function ( $ ) {
	'use strict';

	function minDays() {
		const s = window.mhmUnifiedSearch && window.mhmUnifiedSearch.settings;
		return ( s && parseInt( s.minRentalDays, 10 ) ) || 1;
	}

	function parseDate( value ) {
		const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( value || '' );
		return m ? new Date( +m[ 1 ], +m[ 2 ] - 1, +m[ 3 ] ) : null;
	}

	// The field's date as Y-m-d, whatever format it shows.
	function dateOf( $field ) {
		const api = window.mhmRentivaDatepicker;
		return api ? api.value( $field ) : $field.val();
	}

	function setDate( $field, iso ) {
		const api = window.mhmRentivaDatepicker;
		if ( api ) {
			api.setValue( $field, iso );
		} else {
			$field.val( iso );
		}
	}

	function formatDate( date ) {
		const pad = ( n ) => String( n ).padStart( 2, '0' );
		return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() );
	}

	/**
	 * The earliest return for the strip's pickup date; a return before it moves
	 * up to it (an empty return stays empty -- dates may be optional).
	 */
	function constrainReturn( $strip ) {
		const pickup = parseDate( dateOf( $strip.find( '[data-rvs-pickup-date]' ) ) );
		if ( ! pickup ) {
			return;
		}
		const earliest = new Date( pickup.getTime() );
		earliest.setDate( earliest.getDate() + minDays() );

		const $return = $strip.find( '[data-rvs-return-date]' );
		if ( $.fn.datepicker && $return.hasClass( 'hasDatepicker' ) ) {
			$return.datepicker( 'option', 'minDate', earliest );
		}
		const current = parseDate( dateOf( $return ) );
		if ( current && current < earliest ) {
			setDate( $return, formatDate( earliest ) );
		}
	}

	function syncTime( $strip ) {
		const time = $strip.find( '[data-rvs-pickup-time]' ).val();
		if ( ! time ) {
			return;
		}
		$strip.find( '[data-rvs-return-time-display]' ).text( time );
		$strip.find( '[data-rvs-return-time]' ).val( time );
	}

	function mirrorDropoff( $strip ) {
		const value = $strip.find( '[data-rvs-pickup-location]' ).val() || '';
		$strip.find( '[data-rvs-dropoff]' ).val( value );
		$strip.find( '[data-rvs-dropoff-value]' ).val( value );
	}

	$( document ).on( 'change', '[data-rvs-search] [data-rvs-pickup-date]', function () {
		constrainReturn( $( this ).closest( '[data-rvs-search]' ) );
	} );
	$( document ).on( 'change', '[data-rvs-search] [data-rvs-pickup-time]', function () {
		syncTime( $( this ).closest( '[data-rvs-search]' ) );
	} );
	$( document ).on( 'change', '[data-rvs-search] [data-rvs-pickup-location]', function () {
		mirrorDropoff( $( this ).closest( '[data-rvs-search]' ) );
	} );
	// The return datepicker learns its minimum as soon as it opens.
	$( document ).on( 'focus', '[data-rvs-search] [data-rvs-return-date]', function () {
		constrainReturn( $( this ).closest( '[data-rvs-search]' ) );
	} );

	$( function () {
		$( '[data-rvs-search]' ).each( function () {
			const $strip = $( this );
			syncTime( $strip );
			mirrorDropoff( $strip );
		} );
	} );
}( jQuery ) );
