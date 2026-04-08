/**
 * Vehicle Details JavaScript
 * Handles calendar navigation and interactions
 */

(function ($) {
	'use strict';

	// Calendar navigation state
	let currentMonth = new Date().getMonth() + 1;
	let currentYear = new Date().getFullYear();
	let vehicleId = null;

	$(document).ready(
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
		$(document).on(
			'click',
			'.rv-calendar-nav-btn',
			function (e) {
				e.preventDefault();

				const $btn = $(this);
				const direction = $btn.data('direction');

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
		const $container = $('#rv-calendar-container');
		const $monthYear = $('#rv-current-month-year');
		const $navButtons = $('.rv-calendar-nav-btn');

		// Use correct localized object
		const ajaxConfig = window.mhmRentivaVehicleDetails || {};

		// Disable navigation buttons
		$navButtons.prop('disabled', true).addClass('disabled');

		// Show loading overlay
		if (!$container.find('.rv-calendar-loading').length) {
			$container.append(
				`<div class="rv-calendar-loading">
					<div style="width: 24px; height: 24px; border: 3px solid #3182ce; border-top: 3px solid transparent; border-radius: 50%; animation: rv-spin 0.8s linear infinite;"></div>
				</div>`
			);
		}

		// Add CSS animation for spinner only once
		if (!$('#rv-spinner-style').length) {
			$('head').append(
				`<style id="rv-spinner-style">
					@keyframes rv-spin {
						0% { transform: rotate(0deg); }
						100% { transform: rotate(360deg); }
					}
				</style>`
			);
		}

		// Make AJAX request
		$.ajax(
			{
				url: ajaxConfig.ajaxUrl,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_get_calendar',
					vehicle_id: vehicleId,
					month: currentMonth,
					year: currentYear,
					nonce: ajaxConfig.nonce
				},
				success: function (response) {
					if (response.success && response.data) {
						// Smooth replace
						$container.fadeOut(100, function () {
							$container.html(response.data.calendar_html).fadeIn(100);
							$monthYear.text(response.data.month_year);
						});
					} else {
						$container.html('<div class="rv-calendar-error">' + (ajaxConfig.strings?.error || 'Veri yüklenemedi.') + '</div>');
					}
				},
				error: function () {
					$container.html('<div class="rv-calendar-error">' + (ajaxConfig.strings?.error || 'Bağlantı hatası.') + '</div>');
				},
				complete: function () {
					// Re-enable navigation buttons
					$navButtons.prop('disabled', false).removeClass('disabled');
				}
			}
		);
	}

	/**
	 * Get vehicle ID from the page
	 */
	function getVehicleIdFromPage() {
		// New mini-widget selector
		const $miniWidget = $('.rv-mini-calendar-widget');
		if ($miniWidget.length) {
			const vId = $miniWidget.data('vehicle-id');
			if (vId) return vId;
		}

		// Fallback to legacy selector
		const $calendar = $('.rv-monthly-calendar');
		if ($calendar.length) {
			const vId = $calendar.data('vehicle-id');
			if (vId) return vId;
		}

		// Try to get from body data
		let vId = $('body').data('vehicle-id');
		if (vId) return vId;

		return 'current';
	}

	/**
	 * Initialize gallery functionality
	 */
	function initGallery() {
		const $thumbnails = $('.rv-thumbnail-item');

		if ($thumbnails.length === 0) {
			return;
		}

		// Handle thumbnail clicks
		$(document).on(
			'click',
			'.rv-thumbnail-item',
			function () {
				const $thumbnail = $(this);
				const $img = $thumbnail.find('img');
				const largeImageUrl = $img.data('large');

				// Update main image
				const $mainImage = $('.rv-featured-image');

				if ($mainImage.length && largeImageUrl) {
					// Clear active class from all
					$('.rv-thumbnail-item').removeClass('active');
					$thumbnail.addClass('active');

					// Update main image with smooth transition
					$mainImage.css('opacity', '0.5');
					const tempImg = new Image();
					tempImg.src = largeImageUrl;
					tempImg.onload = function () {
						$mainImage.attr('src', largeImageUrl).css('opacity', '1');
					};
				}
			}
		);

		// Handle "All Photos" button — show hidden extra thumbnails
		$(document).on(
			'click',
			'.rv-vd2-gallery-btn',
			function () {
				const $btn = $(this);
				const $thumbsContainer = $('.rv-gallery-thumbnails');

				$thumbsContainer.addClass('rv-gallery-expanded');
				$btn.hide();
			}
		);
	}

})(jQuery);