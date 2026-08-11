/* Vehicle list screen — layout relocation (Faz 1b).
 *
 * admin_notices output prints ABOVE the page <h1>; the approved mockup
 * order is title → KPI band → chips → table → calendar. Core relocates
 * only `.notice` elements, so move our blocks below the header marker
 * ourselves and push the monthly calendar BELOW the list table. Degrades
 * safely: missing marker or calendar leaves the blocks where they were.
 */
(function ($) {
	'use strict';

	$(
		function () {
			if (typeof pagenow === 'undefined' || pagenow !== 'edit-mhmrentiva_vehicle') {
				return;
			}

			var $marker = $( '.wp-header-end' );
			if ($marker.length) {
				$( '.rv-vhl-chips' ).first().insertAfter( $marker );
				$( '.mhm-stats-grid' ).first().insertAfter( $marker );
				// Faz 2 view-switch toggle — inserted last so it lands
				// topmost (title → toggle → KPI → chips → table → calendar).
				$( '.rv-view-toggle' ).first().insertAfter( $marker );
			}

			var $form     = $( '#posts-filter' );
			var $calendar = $( '.mhm-calendars' ).first();
			if ($form.length && $calendar.length) {
				$calendar.insertAfter( $form );
			}

			// Cards face (Faz 2 Task 6) — same relocation as the calendar
			// face above; mutually exclusive with it, so only one ever
			// exists in the DOM at a time.
			var $cards = $( '.rv-vhl-cards' ).first();
			if ($form.length && $cards.length) {
				$cards.insertAfter( $form );
			}
		}
	);
})( jQuery );
