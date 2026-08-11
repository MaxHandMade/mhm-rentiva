/**
 * "Approve" row action on the Bookings list (Faz 2 Task 7).
 *
 * Fires the mhmrentiva_approve_booking AJAX endpoint from the row action
 * BookingColumns::add_approve_row_action() renders (post_row_actions, class
 * rv-bkl-approve). On success it swaps the row's own status pill text/class
 * and removes the Approve link -- nothing outside that one row is touched.
 * Deliberately NO optimistic KPI/chip count update: those come from a full
 * page load and stay that way until the next one (spec decision) -- a
 * single-row AJAX response is not the shape those aggregates are computed
 * from.
 */
jQuery( document ).ready( function ( $ ) {
	'use strict';

	if ( typeof pagenow === 'undefined' || pagenow !== 'edit-mhmrentiva_booking' ) {
		return;
	}

	var cfg  = window.mhmBookingApprove || {};
	var i18n = cfg.i18n || {};

	/**
	 * One aria-live region per table, created lazily and cached on the
	 * table element itself so a second click never adds a second region.
	 */
	function getLiveRegion( $table ) {
		var $region = $table.data( 'rvApproveLiveRegion' );
		if ( $region && $region.length ) {
			return $region;
		}

		$region = $( '<div class="screen-reader-text" aria-live="polite"></div>' );
		$table.before( $region );
		$table.data( 'rvApproveLiveRegion', $region );
		return $region;
	}

	function announce( $table, message ) {
		// Clear first so a repeated identical message still triggers a DOM
		// mutation assistive tech can pick up.
		var $region = getLiveRegion( $table );
		$region.text( '' );
		window.setTimeout(
			function () {
				$region.text( message );
			},
			50
		);
	}

	function clearRowError( $row ) {
		$row.find( '.rv-bkl-approve-error' ).remove();
	}

	function showRowError( $actionSpan, $row, message ) {
		clearRowError( $row );
		if ( ! message ) {
			return;
		}

		var $error = $( '<span class="rv-bkl-approve-error" role="alert"></span>' ).text( message );
		$actionSpan.after( $error );
		window.setTimeout(
			function () {
				$error.remove();
			},
			6000
		);
	}

	$( document ).on( 'click', '.rv-bkl-approve', function ( e ) {
		e.preventDefault();

		var $link       = $( this );
		var $actionSpan = $link.closest( '.mhmrentiva_approve' );
		var $row        = $link.closest( 'tr' );
		var $table      = $row.closest( 'table' );
		var bookingId   = parseInt( $link.data( 'booking-id' ), 10 ) || 0;

		if ( ! bookingId || $link.hasClass( 'is-busy' ) ) {
			return;
		}

		clearRowError( $row );
		$link.addClass( 'is-busy' ).text( i18n.approving || '' );

		$.post(
			cfg.ajaxUrl,
			{
				action:     'mhmrentiva_approve_booking',
				booking_id: bookingId,
				nonce:      cfg.nonce
			}
		).done( function ( response ) {
			if ( response && response.success ) {
				var status = ( response.data && response.data.status ) || 'confirmed';
				var label  = ( response.data && response.data.label ) || i18n.approve || '';

				$row.find( '.column-mhmrentiva_booking_status .badge' )
					.attr( 'class', 'badge status-' + status )
					.text( label );

				$actionSpan.remove();
				announce( $table, i18n.approved || '' );
				return;
			}

			var message = ( response && response.data && response.data.message ) || i18n.failed || '';
			$link.removeClass( 'is-busy' ).text( i18n.approve || '' );
			showRowError( $actionSpan, $row, message );
		} ).fail( function () {
			$link.removeClass( 'is-busy' ).text( i18n.approve || '' );
			showRowError( $actionSpan, $row, i18n.failed || '' );
		} );
	} );
} );
