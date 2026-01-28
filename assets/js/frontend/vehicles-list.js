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

			// Favorite button clicks
			$(document).on('click' + ns, '.rv-vehicle-card__favorite', function (e) {
				e.preventDefault();
				self.handleFavoriteClick($(this));
			});

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

		handleFavoriteClick: function ($button) {
			const vehicleId = $button.data('vehicle-id');
			const $icon = $button.find('.rv-heart-icon');
			const strings = mhmRentivaVehiclesList.strings || {};

			if (!vehicleId) {
				this.showNotification(strings.invalid_vehicle_id || 'Invalid vehicle ID', 'error');
				return;
			}

			$button.prop('disabled', true);
			$icon.addClass('is-loading');

			$.ajax({
				url: mhmRentivaVehiclesList.ajaxUrl,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_toggle_favorite',
					vehicle_id: vehicleId,
					nonce: mhmRentivaVehiclesList.nonce
				},
				success: (response) => {
					if (response.success) {
						const { action, message } = response.data;

						if (action === 'added') {
							$button.addClass('is-favorited');
							$button.find('svg').attr('fill', 'currentColor');
						} else {
							$button.removeClass('is-favorited');
							$button.find('svg').attr('fill', 'none');
						}

						this.showNotification(message, 'success');
						$(document).trigger('mhm_rentiva_favorite_toggled', [vehicleId, action]);
					} else {
						this.showNotification(response.data.message || strings.error || 'An error occurred', 'error');
					}
				},
				error: () => {
					this.showNotification(strings.connection_error || 'Connection error', 'error');
				},
				complete: () => {
					$button.prop('disabled', false);
					$icon.removeClass('is-loading');
				}
			});
		},

		handleResize: function () {
			// Placeholder for future responsive logic
		},

		showNotification: function (message, type = 'info') {
			const $notification = $(`<div class="rv-notification rv-notification--${type}">${message}</div>`);
			$('body').append($notification);

			setTimeout(() => $notification.addClass('rv-notification--show'), 100);

			setTimeout(() => {
				$notification.removeClass('rv-notification--show');
				setTimeout(() => $notification.remove(), 300);
			}, 3000);
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
