/* Auto-submit filters on the Bookings list screen */
(function ($) {
	'use strict';

	$(
		function () {
			if (typeof pagenow === 'undefined' || pagenow !== 'edit-vehicle_booking') {
				return;
			}

			var $form = $( '#posts-filter' );
			if ( ! $form.length) {
				return;
			}

			// On select change → submit (dates, status, payment, gateway)
			$form.on(
				'change',
				'select[name="m"], select[name="mhm_booking_status"], select[name="mhm_payment_status"], select[name="mhm_payment_gateway"]',
				function () {
					$form.trigger( 'submit' );
				}
			);

			// On Enter in Booking ID / License Plate → submit
			$form.on(
				'keydown',
				'input[name="mhm_booking_id"], input[name="mhm_license_plate"]',
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
