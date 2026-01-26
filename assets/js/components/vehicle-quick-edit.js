/**
 * Vehicle Quick Edit JavaScript
 * Loads existing values in quick edit form
 */

(function ($) {
	'use strict';

	$( document ).ready(
		function () {
			// Run when quick edit form is opened
			$( document ).on(
				'click',
				'.editinline',
				function () {
					var post_id = $( this ).closest( 'tr' ).attr( 'id' ).replace( 'post-', '' );
					var $row    = $( '#post-' + post_id );

					// Get existing values and load into form fields
					setTimeout(
						function () {
							// License plate
							var license_plate = $row.find( '.column-mhm_license_plate' ).text().trim();
							if (license_plate !== '—') {
								$( '.mhm_license_plate' ).val( license_plate );
							}

							// Price/Day
							var price_per_day = $row.find( '.column-mhm_price_per_day' ).text().trim();
							if (price_per_day !== '—') {
								// Get numeric value (extract only digits, removing all formatting and currency symbols)
								// This handles formats like "2.000 ₺", "1,000 $", "500 €", etc.
								var numeric_price = price_per_day.replace( /[^\d]/g, '' ); // Keep only digits
								$( '.mhm_price_per_day' ).val( numeric_price );
							}

							// Seats
							var seats = $row.find( '.column-mhm_seats' ).text().trim();
							if (seats !== '—') {
								$( '.mhm_seats' ).val( seats );
							}

							// Transmission - Get labels from localized data
							var transmission = $row.find( '.column-mhm_transmission' ).text().trim();
							if (transmission !== '—') {
								var transmission_value = 'auto'; // default
								const labels           = (window.mhmVehicleQuickEdit && window.mhmVehicleQuickEdit.labels) || {};
								const manualLabel      = labels.manual || 'Manual';
								const autoLabel        = labels.auto || 'Automatic';

								if (transmission === manualLabel) {
									transmission_value = 'manual';
								} else if (transmission === autoLabel) {
									transmission_value = 'auto';
								}
								$( '.mhm_transmission' ).val( transmission_value );
							}

							// Fuel
							var fuel_type = $row.find( '.column-mhm_fuel_type' ).text().trim();
							if (fuel_type !== '—') {
								var fuel_value = 'petrol'; // default
								const labels   = (window.mhmVehicleQuickEdit && window.mhmVehicleQuickEdit.labels) || {};

								if (fuel_type === (labels.diesel || 'Diesel')) {
									fuel_value = 'diesel';
								} else if (fuel_type === (labels.hybrid || 'Hybrid')) {
									fuel_value = 'hybrid';
								} else if (fuel_type === (labels.electric || 'Electric')) {
									fuel_value = 'electric';
								} else if (fuel_type === (labels.petrol || 'Petrol')) {
									fuel_value = 'petrol';
								}
								$( '.mhm_fuel_type' ).val( fuel_value );
							}

							// Available
							var availableElement = $row.find( '.column-mhm_available span.vehicle-status' );
							if (availableElement.length) {
								var availableValue = availableElement.data( 'status' );
								if (availableValue) {
									$( '.mhm_available' ).val( availableValue );
								}
							}

						},
						100
					);
				}
			);

			// Calendar navigation functions
			initVehicleCalendarNavigation();
		}
	);

	// Calendar navigation functions
	function initVehicleCalendarNavigation() {
		$( document ).on(
			'click',
			'.calendar-nav-btn',
			function (e) {
				e.preventDefault();

				const action     = $( this ).data( 'action' );
				const currentUrl = new URL( window.location.href );
				let currentMonth = parseInt( currentUrl.searchParams.get( 'month' ) ) || new Date().getMonth() + 1;
				let currentYear  = parseInt( currentUrl.searchParams.get( 'year' ) ) || new Date().getFullYear();

				if (action === 'prev') {
					currentMonth--;
					if (currentMonth < 1) {
						currentMonth = 12;
						currentYear--;
					}
				} else if (action === 'next') {
					currentMonth++;
					if (currentMonth > 12) {
						currentMonth = 1;
						currentYear++;
					}
				}

				// Update URL parameters
				currentUrl.searchParams.set( 'month', currentMonth );
				currentUrl.searchParams.set( 'year', currentYear );

				// Reload page
				window.location.href = currentUrl.toString();
			}
		);
	}

	// Add global functions to window object
	window.MHMRentivaVehicle = {
		initVehicleCalendarNavigation: initVehicleCalendarNavigation
	};
})( jQuery );
