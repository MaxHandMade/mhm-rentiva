/**
 * MHM Rentiva - Vehicles List JavaScript
 * Optimized for performance and theme compatibility
 * @since 4.6.4
 */

(function ($) {
	'use strict';

	const MHMRentivaVehiclesList = {
		initialized: false,

		init: function () {
			if (this.initialized) return;

			this.bindEvents();
			this.initialized = true;
		},

		bindEvents: function () {
			const self = this;
			const ns = '.mhmRentivaVehiclesList';

			// Clean up existing events
			$(document).off(ns);

			// Favorite button clicks - Handled globally by vehicle-interactions.js
			// $(document).on('click' + ns, '.mhm-card-favorite', ...);

			// Booking button clicks
			$(document).on('click' + ns, '.rv-btn-booking', function (e) {
				if ($(this).hasClass('rv-btn-disabled')) {
					e.preventDefault();
					return;
				}
				// Allow default link behavior for booking buttons
			});

			// Resize handler with debounce
			$(window).off('resize' + ns);
			$(window).on('resize' + ns, this.debounce(250, () => {
				this.handleResize();
			}));
		},

		/* Favorites handled globally
		handleFavoriteClick: function ($button) {
			// ...
		},
		*/

		handleResize: function () {
			// Placeholder for future responsive logic
		},

		showNotification: function (message, type = 'info') {
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
			setTimeout(() => {
				$notification.fadeOut(400, function () {
					$(this).remove();
				});
			}, 3500);
		},

		debounce: function (delay, callback) {
			let timeout;
			return function () {
				const context = this;
				const args = arguments;
				clearTimeout(timeout);
				timeout = setTimeout(() => callback.apply(context, args), delay);
			};
		}
	};

	// Initialize
	$(document).ready(() => MHMRentivaVehiclesList.init());

	// Re-init on AJAX content load if needed
	$(document).on('mhm_rentiva_content_loaded', () => MHMRentivaVehiclesList.init());

})(jQuery);
