/**
 * Vehicle Details JavaScript
 * Handles calendar navigation and interactions
 */

(function ($) {
	'use strict';

	// Calendar navigation state
	let currentMonth = new Date().getMonth() + 1;
	let currentYear  = new Date().getFullYear();
	let vehicleId    = null;

	$( document ).ready(
		function () {
			// Get vehicle ID from the page
			vehicleId = getVehicleIdFromPage();

			if (vehicleId) {
				initCalendarNavigation();
			}

			// Initialize gallery functionality
			initGallery();
		}
	);

	/**
	 * Initialize calendar navigation
	 */
	function initCalendarNavigation() {
		$( '.rv-calendar-nav-btn' ).on(
			'click',
			function (e) {
				e.preventDefault();

				const direction = $( this ).data( 'direction' );

				if (direction === 'prev') {
					currentMonth--;
					if (currentMonth < 1) {
						currentMonth = 12;
						currentYear--;
					}
				} else if (direction === 'next') {
					currentMonth++;
					if (currentMonth > 12) {
						currentMonth = 1;
						currentYear++;
					}
				}

				updateCalendar();
			}
		);
	}

	/**
	 * Update calendar via AJAX
	 */
	function updateCalendar() {
		const $container  = $( '#rv-calendar-container' );
		const $monthYear  = $( '#rv-current-month-year' );
		const $navButtons = $( '.rv-calendar-nav-btn' );

		// Disable navigation buttons
		$navButtons.prop( 'disabled', true ).addClass( 'disabled' );

		// Show loading state with spinner
		$container.html(
			`
			< div class         = "rv-calendar-loading" >
				< div style     = "display: flex; flex-direction: column; align-items: center; gap: 10px;" >
					< div style = "width: 20px; height: 20px; border: 2px solid #0073E6; border-top: 2px solid transparent; border-radius: 50%; animation: spin 1s linear infinite;" > < / div >
					< span > ${mhm_rentiva_ajax.i18n ? .loading || 'Loading...'} < / span >
				< / div >
			< / div >
			`
		);

		// Add CSS animation for spinner
		if ( ! $( '#rv-spinner-style' ).length) {
			$( 'head' ).append(
				`
				< style id = "rv-spinner-style" >
					@keyframes spin {
						0 % { transform: rotate( 0deg ); }
						100 % { transform: rotate( 360deg ); }
					}
				< / style >
				`
			);
		}

		// Make AJAX request
		$.ajax(
			{
				url: mhm_rentiva_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_get_calendar',
					vehicle_id: vehicleId,
					month: currentMonth,
					year: currentYear,
					nonce: mhm_rentiva_ajax.nonce
				},
				success: function (response) {
					if (response.success) {
						$container.html( response.data.calendar_html );
						$monthYear.text( response.data.month_year );
					} else {
						$container.html( '<div class="rv-calendar-error">' + (mhm_rentiva_ajax.i18n ? .calendar_load_error || 'Calendar could not be loaded.') + '</div>' );
					}
				},
				error: function () {
					$container.html( '<div class="rv-calendar-error">' + (mhm_rentiva_ajax.i18n ? .calendar_load_error || 'Calendar could not be loaded.') + '</div>' );
				},
				complete: function () {
					// Re-enable navigation buttons
					$navButtons.prop( 'disabled', false ).removeClass( 'disabled' );
				}
			}
		);
	}

	/**
	 * Get vehicle ID from the page
	 */
	function getVehicleIdFromPage() {
		// Try to get from calendar data attribute
		const $calendar = $( '.rv-monthly-calendar' );
		if ($calendar.length) {
			const vehicleId = $calendar.data( 'vehicle-id' );
			if (vehicleId) {
				return vehicleId;
			}
		}

		// Try to get from body data attribute
		let vehicleId = $( 'body' ).data( 'vehicle-id' );
		if (vehicleId) {
			return vehicleId;
		}

		// Try to get from URL
		const url   = window.location.href;
		const match = url.match( /\/vehicles\/([^\/]+)/ );
		if (match) {
			// For now, we'll use a fallback approach
			// The server will handle the vehicle ID in the AJAX call
			return 'current'; // This will be handled by the server
		}

		return null;
	}

	/**
	 * Initialize gallery functionality
	 */
	function initGallery() {
		// Check if thumbnails exist
		const $thumbnails = $( '.rv-thumbnail-item' );

		if ($thumbnails.length === 0) {
			return;
		}

		// Handle thumbnail clicks
		$thumbnails.on(
			'click',
			function () {
				const $thumbnail    = $( this );
				const $img          = $thumbnail.find( 'img' );
				const largeImageUrl = $img.data( 'large' );

				// Update main image
				const $mainImage = $( '.rv-featured-image' );

				if ($mainImage.length && largeImageUrl) {
					// Add fade effect
					$mainImage.fadeOut(
						200,
						function () {
							$mainImage.attr( 'src', largeImageUrl );
							$mainImage.fadeIn( 200 );
						}
					);
				}

				// Update active thumbnail
				$thumbnails.removeClass( 'active' );
				$thumbnail.addClass( 'active' );
			}
		);

		// Set first thumbnail as active on load
		$thumbnails.first().addClass( 'active' );
	}

})( jQuery );