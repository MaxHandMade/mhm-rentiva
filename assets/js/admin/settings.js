/**
 * MHM Rentiva - Settings Page Central JavaScript
 *
 * Handles general settings interactions, test connections, and tab resets.
 * Optimized to prevent double execution and provide visual feedback.    // 🚀 AUTO-DISMISS NOTICES (Toast logic)
 */
jQuery(document).ready(
	function ($) {
		'use strict';

		/**
		 * 🚀 AUTO-CONVERT WP NOTICES TO MODERN TOASTS
		 */
		const convertWPNoticesToToasts = function () {
			// Select standard WordPress notices that appear after saving
			const $wpNotices = $('.wrap .notice, .wrap .updated, .wrap .error, #setting-error-settings_updated').not('.inline');

			if ($wpNotices.length > 0) {
				$wpNotices.each(
					function () {
						const $notice = $(this);
						if ($notice.data('mhm-processed')) return;

						const message = $notice.find('p').text().trim() || $notice.text().trim();
						if (!message || message.length < 2) return;

						$notice.data('mhm-processed', true);
						let type = 'info';

						if ($notice.hasClass('error')) {
							type = 'error';
						} else if ($notice.hasClass('updated') || $notice.hasClass('success') || $notice.attr('id')?.includes('settings_updated')) {
							type = 'success';
						} else if ($notice.hasClass('warning')) {
							type = 'warning';
						}

						// Hide original WP notice
						$notice.css({ 'display': 'none', 'opacity': '0', 'position': 'absolute', 'z-index': '-1' });

						// Create modern Toast
						const icon = (type === 'success') ? '✓' : '!';
						const $notification = $(
							`<div class="rv-notification rv-notification--${type} mhm-admin-toast">
								<div class="rv-notification-body">
									<span class="rv-notification-icon-badge">${icon}</span>
									<span class="rv-notification-text">${message}</span>
								</div>
							</div>`
						);

						$('body').append($notification);

						// Show animation
						setTimeout(() => {
							$notification.addClass('rv-notification--show');
						}, 50);

						// Auto-hide
						setTimeout(
							function () {
								$notification.removeClass('rv-notification--show').fadeOut(
									500,
									function () {
										$(this).remove();
									}
								);
							},
							4000
						);
					}
				);
			}
		};

		// Polling for notices (important for dynamic or fast-loading notices)
		convertWPNoticesToToasts();
		setTimeout(convertWPNoticesToToasts, 300);
		setTimeout(convertWPNoticesToToasts, 1000);


		/**
		 * 1. Test Email Connection
		 * Sends a connection test email using AJAX.
		 */
		$(document).off('click', '.mhm-send-test-email').on(
			'click',
			'.mhm-send-test-email',
			function (e) {
				e.preventDefault();
				const $btn = $(this);
				const nonce = $btn.data('nonce');
				const originalHtml = $btn.html();

				if ($btn.prop('disabled')) {
					return;
				}

				// Button Loading State
				$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="vertical-align: middle;"></span> Sending...');

				$.ajax(
					{
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'mhm_rentiva_send_test_email_ajax',
							nonce: nonce,
							template_key: 'booking_created_admin' // General connection test template
						},
						success: function (response) {
							if (response.success) {
								showNotice(response.data || 'Test email sent successfully!', 'success');
							} else {
								showNotice('Error: ' + (response.data || 'Unknown error'), 'error');
							}
						},
						error: function () {
							showNotice('Connection error. Please check server logs.', 'error');
						},
						complete: function () {
							$btn.prop('disabled', false).html(originalHtml);
						}
					}
				);
			}
		);

		/**
		 * 2. Tab Reset Functionality
		 * Triggers a factory reset for the current tab's settings.
		 */
		$(document).off('click', '.mhm-reset-tab-settings').on(
			'click',
			'.mhm-reset-tab-settings',
			function (e) {
				e.preventDefault();
				const $btn = $(this);
				const tab = $btn.data('tab');

				// Safety: Ensure strings exist
				const strings = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings) ? window.mhmRentivaSettings.strings : {};
				const confirmMsg = strings.confirmResetTab || 'Are you sure you want to reset this tab\'s settings?';

				if (window.confirm(confirmMsg)) {
					$btn.prop('disabled', true).text(strings.resetting || 'Resetting...');

					// Use the URL from the button's href (provided by PHP for safety)
					window.location.href = $btn.attr('href');
				}
			}
		);

		/**
		 * 4. Accordion Toggle Logic (Frontend & Display Tab)
		 */
		$(document).off('click', '.mhm-accordion-header').on(
			'click',
			'.mhm-accordion-header',
			function () {
				const $header = $(this);
				const $content = $header.next('.mhm-accordion-content');
				const $icon = $header.find('.dashicons');

				// Toggle current
				$content.slideToggle(250);
				$header.toggleClass('active');

				// Swap Icons
				if ($icon.hasClass('dashicons-arrow-down')) {
					$icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
				} else {
					$icon.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
				}
			}
		);

		/**
		 * 5. Centralized Notice System
		 */
		function showNotice(message, type) {
			const noticeClass = 'notice-' + (type || 'info');
			const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border-left-width: 4px !important; display: none;"><p><strong>' + message + '</strong></p></div>');

			// Remove existing notices to avoid clutter
			$('.notice.is-dismissible').filter(':visible').fadeOut(
				200,
				function () {
					$(this).remove();
				}
			);

			$('body').append($notice);
			$notice.fadeIn(300);

			// Auto-dismiss after 5 seconds
			setTimeout(
				function () {
					$notice.fadeOut(
						500,
						function () {
							$(this).remove();
						}
					);
				},
				5000
			);
		}
	}
);
