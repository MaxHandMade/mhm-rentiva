/**
 * MHM Rentiva Shortcode Pages Admin Scripts
 * 
 * Handles actions for the Shortcode Pages admin screen.
 * Uses event delegation and modernized fetch API.
 * 
 * @since 4.0.0
 */
(function ($) {
	'use strict';

	// Wait for DOM
	$(function () {
		const config = window.MHMRentivaShortcodes || {};

		if (!config.actions) {
			console.error('MHM Rentiva: Shortcode config missing.');
			return;
		}

		// Helper to create form data
		const setupFormData = (actionKey) => {
			const formData = new FormData();
			formData.append('action', config.actions[actionKey]);
			formData.append('nonce', config.nonces[actionKey]);
			return formData;
		};

		// ---------------------------------------------------------
		// Event Handlers
		// ---------------------------------------------------------

		// 1. Clear Cache
		$('#mhm-btn-clear-cache').on('click', async function (e) {
			e.preventDefault();
			if (!confirm(config.i18n.confirmClearCache)) return;

			const btn = $(this);
			btn.prop('disabled', true);

			try {
				const data = setupFormData('clearCache');
				const resp = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: data
				});
				const json = await resp.json();

				if (json.success) {
					location.reload();
				} else {
					alert(json.data?.message || 'Error');
					btn.prop('disabled', false);
				}
			} catch (err) {
				console.error(err);
				alert('Request failed.');
				btn.prop('disabled', false);
			}
		});

		// 2. Debug Search
		$('#mhm-btn-debug-search').on('click', async function (e) {
			e.preventDefault();
			const btn = $(this);
			btn.prop('disabled', true);

			try {
				const data = setupFormData('debugSearch');
				const resp = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: data
				});
				const json = await resp.json();

				if (json.success) {
					let msg = json.data.message + '\n\n';
					if (json.data.pages && json.data.pages.length > 0) {
						json.data.pages.forEach(p => msg += `ID: ${p.id} - ${p.title}\n`);
					}
					alert(msg);
				} else {
					alert(json.data?.message || 'Error');
				}
			} catch (err) {
				console.error(err);
				alert('Request failed.');
			} finally {
				btn.prop('disabled', false);
			}
		});

		// 3. Create Page (Delegated)
		$(document).on('click', '.mhm-btn-create-page', async function (e) {
			e.preventDefault();
			if (!confirm(config.i18n.confirmCreatePage)) return;

			const btn = $(this);
			const originalText = btn.text();
			const shortcode = btn.data('shortcode');

			btn.prop('disabled', true).text(config.i18n.creatingText);

			try {
				const data = setupFormData('createPage');
				data.append('shortcode', shortcode);

				const resp = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: data
				});
				const json = await resp.json();

				if (json.success) {
					// Direct redirection as per strict enforcement
					if (confirm(config.i18n.confirmGoToEditor)) {
						window.location.href = json.data.edit_url;
					} else {
						location.reload();
					}
				} else {
					alert(json.data?.message || 'Error');
					btn.prop('disabled', false).text(originalText);
				}
			} catch (err) {
				console.error(err);
				alert('Request failed.');
				btn.prop('disabled', false).text(originalText);
			}
		});

		// 4. Delete Page (Delegated)
		$(document).on('click', '.mhm-btn-delete-page', async function (e) {
			e.preventDefault();
			const btn = $(this);
			const pageId = btn.data('page-id');
			const title = btn.data('title');

			if (!confirm(config.i18n.confirmDeletePage + '\n' + title)) return;

			try {
				const data = setupFormData('deletePage');
				data.append('page_id', pageId);

				const resp = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: data
				});
				const json = await resp.json();

				if (json.success) {
					location.reload();
				} else {
					alert(json.data?.message || 'Error');
				}
			} catch (err) {
				console.error(err);
				alert('Request failed.');
			}
		});

		// 5. Reset All Pages
		$('#mhm-btn-reset-pages').on('click', async function (e) {
			e.preventDefault();
			if (!confirm(config.i18n.confirmReset)) return;

			const btn = $(this);
			btn.prop('disabled', true);

			try {
				const data = setupFormData('resetPages');
				const resp = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: data
				});
				const json = await resp.json();

				if (json.success) {
					alert(json.data.message);
					location.reload();
				} else {
					alert(json.data?.message || 'Error');
					btn.prop('disabled', false);
				}
			} catch (err) {
				console.error(err);
				alert('Request failed.');
				btn.prop('disabled', false);
			}
		});

	});

})(jQuery);
