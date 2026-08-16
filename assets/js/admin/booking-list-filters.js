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

			// No layout work here any more. Every block on this screen — the
			// toolbar row, the KPI band, the chip strip and the Calendar face —
			// prints in its final position from ListScreenLayout's server-side
			// seams. This file used to re-parent all of them at DOMContentLoaded,
			// which is what made the screen visibly jump on every load.

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
