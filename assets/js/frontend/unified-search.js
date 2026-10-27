/**
 * Search strip (front-end standard, Aşama 2 PR 3).
 *
 * The date fields are jQuery UI datepickers (datepicker-init.js, `.js-datepicker`):
 * they show the site's date format and submit Y-m-d, so dates are read and
 * written through window.mhmRentivaDatepicker (Y-m-d either way).
 * This script keeps the return date at least the minimum rental after pickup
 * (the shared rule, mhmRentivaDatepicker.constrainReturn),
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

	/**
	 * The return is at least the minimum rental after pickup: the shared rule
	 * (window.mhmRentivaDatepicker.constrainReturn, the vehicle card uses it too).
	 */
	function constrainReturn( $strip ) {
		const api = window.mhmRentivaDatepicker;
		if ( api && api.constrainReturn ) {
			api.constrainReturn( $strip.find( '[data-rvs-pickup-date]' ), $strip.find( '[data-rvs-return-date]' ), minDays() );
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
