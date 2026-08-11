/* Auto-submit filters on the Bookings list screen */
(function ($) {
	'use strict';

	$(
		function () {
			if (typeof pagenow === 'undefined' || pagenow !== 'edit-mhmrentiva_booking') {
				return;
			}

			var $form = $( '#posts-filter' );
			if ( ! $form.length) {
				return;
			}

			// Layout relocation (Faz 1a): admin_notices output prints ABOVE the
			// page <h1>; the approved mockup order is title → toolbar → KPI band
			// → chips → table → calendar. Core relocates only `.notice` elements,
			// so move our blocks below the header marker ourselves, and push the
			// heavy monthly calendar BELOW the list table.
			var $marker = $( '.wp-header-end' );
			if ($marker.length) {
				$( '.rv-bkl-chips' ).first().insertAfter( $marker );
				$( '.mhm-stats-grid' ).first().insertAfter( $marker );
				$( '.rv-bkl-toolbar' ).first().insertAfter( $marker );
				// Faz 2 view-switch toggle — inserted last so it lands
				// topmost (title → toolbar/toggle → KPI band → chips → content).
				$( '.rv-view-toggle' ).first().insertAfter( $marker );

				// The toggle shares the Pro toolbar's flex row when a
				// subscriber actually rendered one. Lite ships no subscriber,
				// so `.rv-bkl-toolbar` is absent from the DOM entirely in
				// that case (BookingColumns::toolbar_actions() prints
				// nothing without one) and the toggle stands alone.
				var $toggle  = $( '.rv-view-toggle' ).first();
				var $toolbar = $( '.rv-bkl-toolbar' ).first();
				if ($toggle.length && $toolbar.length) {
					$toggle.add( $toolbar ).wrapAll( '<div class="rv-bkl-toolbar-row"></div>' );
				}
			}
			var $calendar = $( '.mhm-calendars.booking-calendar-page' ).first();
			if ($calendar.length) {
				$calendar.insertAfter( $form );
			}

			// On select change → submit (dates, status, payment, gateway)
			$form.on(
				'change',
				'select[name="m"], select[name="mhmrentiva_payment_status"], select[name="mhmrentiva_payment_gateway"]',
				function () {
					$form.trigger( 'submit' );
				}
			);

			// On Enter in Booking ID / License Plate → submit
			$form.on(
				'keydown',
				'input[name="mhmrentiva_booking_id"], input[name="mhmrentiva_license_plate"]',
				function (e) {
					if (e.key === 'Enter') {
						e.preventDefault();
						$form.trigger( 'submit' );
					}
				}
			);

			// Also when Search Booking is clicked, ensure all fields are included (default is already yes)
			$( '#search-submit' ).on(
				'click',
				function () {
					// nothing extra; keep for clarity
				}
			);
		}
	);
})( jQuery );
