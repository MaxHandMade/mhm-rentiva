/**
 * Account Privacy JavaScript
 *
 * Handles privacy controls (GDPR) in My Account dashboard
 */
jQuery(document).ready(
	function ($) {

		// Check if configuration exists
		if (typeof mhmRentivaPrivacy === 'undefined') {
			return;
		}

		// Define ajaxurl for compatibility if not already defined
		var ajaxurl = mhmRentivaPrivacy.ajaxUrl;

		// Helper: Show Notification
		function showNotification(message, type) {
			MHMRentivaToast.show(message, { type: type || 'info' });
		}

		// Data Export
		$('#export-data').on(
			'click',
			function (e) {
				e.preventDefault();

				if (!confirm(mhmRentivaPrivacy.i18n.confirmExport)) {
					return;
				}

				var button = $(this);
				var originalText = button.text();

				button.prop('disabled', true).text(mhmRentivaPrivacy.i18n.exporting);

				$.ajax(
					{
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'mhmrentiva_data_export',
							nonce: mhmRentivaPrivacy.nonce
						},
						success: function (response) {
							if (response.success) {
								// Create download link
								var blob = new Blob([response.data], { type: 'application/json' });
								var url = window.URL.createObjectURL(blob);
								var a = document.createElement('a');
								a.href = url;
								a.download = 'my-data-export.json';
								document.body.appendChild(a);
								a.click();
								document.body.removeChild(a);
								window.URL.revokeObjectURL(url);

								showNotification(mhmRentivaPrivacy.i18n.exportSuccess, 'success');
							} else {
								showNotification(mhmRentivaPrivacy.i18n.error + ': ' + (response.data.message || mhmRentivaPrivacy.i18n.unknownError), 'error');
							}
						},
						error: function (xhr, status, error) {
							showNotification(mhmRentivaPrivacy.i18n.exportError + ': ' + error, 'error');
						},
						complete: function () {
							button.prop('disabled', false).text(originalText);
						}
					}
				);
			}
		);

		// Withdraw Consent
		$('#withdraw-consent').on(
			'click',
			function (e) {
				e.preventDefault();

				if (!confirm(mhmRentivaPrivacy.i18n.confirmWithdraw)) {
					return;
				}

				var button = $(this);
				var originalText = button.text();

				button.prop('disabled', true).text(mhmRentivaPrivacy.i18n.processing);

				$.ajax(
					{
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'mhmrentiva_consent_withdrawal',
							nonce: mhmRentivaPrivacy.nonce
						},
						success: function (response) {
							if (response.success) {
								showNotification(mhmRentivaPrivacy.i18n.withdrawSuccess, 'success');
								setTimeout(
									function () {
										location.reload();
									},
									1500
								);
							} else {
								showNotification(mhmRentivaPrivacy.i18n.error + ': ' + (response.data.message || mhmRentivaPrivacy.i18n.unknownError), 'error');
							}
						},
						error: function (xhr, status, error) {
							showNotification(mhmRentivaPrivacy.i18n.withdrawError + ': ' + error, 'error');
						},
						complete: function () {
							button.prop('disabled', false).text(originalText);
						}
					}
				);
			}
		);

		// Request Data Erasure. The add-on answers with a WordPress privacy
		// request (a confirmation e-mail; the site administrator processes it):
		// nothing is deleted here, so one confirm is enough, the server's own
		// message is shown and the visitor stays on the page.
		$('#delete-account').on(
			'click',
			function (e) {
				e.preventDefault();

				if (!confirm(mhmRentivaPrivacy.i18n.confirmErasure)) {
					return;
				}

				var button = $(this);
				var originalText = button.text();

				button.prop('disabled', true).text(mhmRentivaPrivacy.i18n.processing);

				$.ajax(
					{
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'mhmrentiva_data_deletion',
							nonce: mhmRentivaPrivacy.nonce
						},
						success: function (response) {
							var message = (response && response.data && response.data.message) ? response.data.message : '';
							if (response && response.success) {
								showNotification(message, 'success');
							} else {
								showNotification(mhmRentivaPrivacy.i18n.error + ': ' + (message || mhmRentivaPrivacy.i18n.unknownError), 'error');
							}
						},
						error: function (xhr, status, error) {
							showNotification(mhmRentivaPrivacy.i18n.error + ': ' + (error || mhmRentivaPrivacy.i18n.unknownError), 'error');
						},
						complete: function () {
							button.prop('disabled', false).text(originalText);
						}
					}
				);
			}
		);

	}
);
