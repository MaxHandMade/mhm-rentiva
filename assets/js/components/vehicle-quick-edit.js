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
							var license_plate = $row.find( '.column-mhmrentiva_license_plate' ).text().trim();
							if (license_plate !== '—') {
								$( '.mhmrentiva_license_plate' ).val( license_plate );
							}

							// Price/Day
							var price_per_day = $row.find( '.column-mhmrentiva_price_per_day' ).text().trim();
							if (price_per_day !== '—') {
								// Get numeric value (extract only digits, removing all formatting and currency symbols)
								// This handles formats like "2.000 ₺", "1,000 $", "500 €", etc.
								var numeric_price = price_per_day.replace( /[^\d]/g, '' ); // Keep only digits
								$( '.mhmrentiva_price_per_day' ).val( numeric_price );
							}

							// Seats
							var seats = $row.find( '.column-mhmrentiva_seats' ).text().trim();
							if (seats !== '—') {
								$( '.mhmrentiva_seats' ).val( seats );
							}

							// Transmission - Get labels from localized data
							var transmission = $row.find( '.column-mhmrentiva_transmission' ).text().trim();
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
								$( '.mhmrentiva_transmission' ).val( transmission_value );
							}

							// Fuel
							var fuel_type = $row.find( '.column-mhmrentiva_fuel_type' ).text().trim();
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
								$( '.mhmrentiva_fuel_type' ).val( fuel_value );
							}

							// Available
							var availableElement = $row.find( '.column-mhmrentiva_available span.vehicle-status' );
							if (availableElement.length) {
								var availableValue = availableElement.data( 'status' );
								if (availableValue) {
									$( '.mhmrentiva_available' ).val( availableValue );
								}
							}

							// Location
							var locationId = $row.find( '.column-mhmrentiva_location span' ).data( 'location-id' );
							if (locationId !== undefined) {
								$( '.mhmrentiva_location' ).val( String( locationId ) );
							}

							// Featured
							var featuredLabels = (window.mhmVehicleQuickEdit && window.mhmVehicleQuickEdit.labels) || {};
							var featuredText   = $row.find( '.column-mhmrentiva_featured' ).text().trim();
							var yesLabel       = featuredLabels.yes || 'Yes';
							$( '.mhmrentiva_featured' ).prop( 'checked', featuredText === yesLabel );

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

				// Param names must match VehicleColumns::PUBLIC_QUERY_VARS -- the PHP
				// side reads them through get_query_var(), which only answers for
				// names registered on WordPress's query_vars whitelist. Bare
				// month/year cannot be registered there (an unprefixed `month`
				// would collide globally and `year` is already a core query var).
				const action     = $( this ).data( 'action' );
				const currentUrl = new URL( window.location.href );
				let currentMonth = parseInt( currentUrl.searchParams.get( 'mhmrentiva_month' ) ) || new Date().getMonth() + 1;
				let currentYear  = parseInt( currentUrl.searchParams.get( 'mhmrentiva_year' ) ) || new Date().getFullYear();

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
				currentUrl.searchParams.set( 'mhmrentiva_month', currentMonth );
				currentUrl.searchParams.set( 'mhmrentiva_year', currentYear );

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
