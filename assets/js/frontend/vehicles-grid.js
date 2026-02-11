/**
 * Vehicles Grid JavaScript
 *
 * Custom JavaScript for grid layout - only supports grid layout
 */

(function ($) {
	'use strict';

	// Global namespace
	window.MHMRentivaVehiclesGrid = {
		initialized: false,

		init: function () {
			if (this.initialized) {
				return;
			}

			this.bindEvents();
			this.initLazyLoading();
			this.initialized = true;

		},

		bindEvents: function () {
			var self = this;

			// Favorite button clicks - Handled globally by vehicle-interactions.js
			// .rv-vehicle-card__favorite does not exist in vehicle-card.php

			// Booking button clicks
			$(document).on(
				'click',
				'.rv-btn-booking',
				function (e) {
					self.handleBookingClick($(this));
				}
			);

			// Card clicks for analytics
			$(document).on(
				'click',
				'.rv-vehicle-card',
				function (e) {
					if (!$(e.target).closest('.rv-vehicle-card__favorite, .rv-btn-booking').length) {
						self.handleCardClick($(this));
					}
				}
			);

			// Window resize
			$(window).on(
				'resize',
				$.debounce(
					250,
					function () {
						self.handleResize();
					}
				)
			);
		},

		/* Favorites handled globally
		handleFavoriteClick: function ($button) {
			// ...
		},
		*/

		handleBookingClick: function ($button) {
			var href = $button.attr('href');
			var vehicleId = $button.closest('.rv-vehicle-card').data('vehicle-id');

			// Analytics tracking
			if (typeof gtag !== 'undefined') {
				gtag(
					'event',
					'booking_click',
					{
						'vehicle_id': vehicleId,
						'event_category': 'conversion',
						'event_label': 'grid_booking_button'
					}
				);
			}

			// Track in console for debugging
		},

		handleCardClick: function ($card) {
			var vehicleId = $card.data('vehicle-id');
			var vehicleTitle = $card.find('.rv-vehicle-card__title a').text();

			// Analytics tracking
			if (typeof gtag !== 'undefined') {
				gtag(
					'event',
					'vehicle_card_click',
					{
						'vehicle_id': vehicleId,
						'vehicle_title': vehicleTitle,
						'event_category': 'engagement',
						'event_label': 'grid_card'
					}
				);
			}

		},

		initLazyLoading: function () {
			if ('IntersectionObserver' in window) {
				var imageObserver = new IntersectionObserver(
					function (entries, observer) {
						entries.forEach(
							function (entry) {
								if (entry.isIntersecting) {
									var img = entry.target;
									img.src = img.dataset.src || img.src;
									img.classList.remove('lazy');
									imageObserver.unobserve(img);
								}
							}
						);
					},
					{
						rootMargin: '50px 0px',
						threshold: 0.01
					}
				);

				// Observe all images
				document.querySelectorAll('.rv-vehicle-card__image img[data-src]').forEach(
					function (img) {
						imageObserver.observe(img);
					}
				);
			}
		},

		handleResize: function () {
			// Grid responsive adjustments if needed
			var windowWidth = $(window).width();

			// Update grid gaps based on screen size
			$('.rv-vehicles-grid').each(
				function () {
					var $grid = $(this);

					if (windowWidth <= 480) {
						$grid.addClass('rv-vehicles-grid--gap-small');
					} else if (windowWidth >= 1200) {
						$grid.addClass('rv-vehicles-grid--gap-large');
					} else {
						$grid.removeClass('rv-vehicles-grid--gap-small rv-vehicles-grid--gap-large');
					}
				}
			);
		},

		showNotification: function (message, type) {
			type = type || 'info';

			// Remove existing notifications if any
			$('.rv-notification').remove();

			const icon = type === 'success' ? '✓' : '!';
			const $notification = $(`
				<div class="rv-notification rv-notification--show rv-notification--${type}">
					<div class="rv-notification-body">
						<span class="rv-notification-icon-badge">${icon}</span>
						<span class="rv-notification-text">${message}</span>
					</div>
				</div>
			`);

			$('body').append($notification);

			// Auto-hide after 3.5 seconds
			setTimeout(function () {
				$notification.fadeOut(400, function () {
					$(this).remove();
				});
			}, 3500);
		},

		refresh: function () {
			// Refresh grid data
			location.reload();
		},

		filter: function (criteria) {
			// Filter vehicles by criteria
			// Implementation would go here
		},

		sort: function (orderBy, order) {
			// Sort vehicles
			// Implementation would go here
		}
	};

	// Debounce utility function
	$.debounce = function (delay, fn) {
		var timer;
		return function () {
			var context = this;
			var args = arguments;
			clearTimeout(timer);
			timer = setTimeout(
				function () {
					fn.apply(context, args);
				},
				delay
			);
		};
	};

	// Initialize when document is ready
	$(document).ready(
		function () {
			MHMRentivaVehiclesGrid.init();
		}
	);

	// Re-initialize on AJAX content load using MutationObserver (modern alternative)
	if (typeof MutationObserver !== 'undefined') {
		var observer = new MutationObserver(
			function (mutations) {
				mutations.forEach(
					function (mutation) {
						if (mutation.addedNodes.length) {
							mutation.addedNodes.forEach(
								function (node) {
									if (node.nodeType === 1) { // Element node
										var $node = $(node);
										if ($node.hasClass('rv-vehicles-grid') || $node.find('.rv-vehicles-grid').length) {
											MHMRentivaVehiclesGrid.init();
										}
									}
								}
							);
						}
					}
				);
			}
		);

		// Start observing
		observer.observe(
			document.body,
			{
				childList: true,
				subtree: true
			}
		);
	}

})(jQuery);
