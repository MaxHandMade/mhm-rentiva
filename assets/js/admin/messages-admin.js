/**
 * Messages Admin JavaScript
 * Admin panel message management functions
 */

(function ($) {
	'use strict';

	const MessagesAdmin = {
		init: function () {
			this.bindEvents();
			this.initializeStatusSelects();
			this.initializeBulkActions();
		},

		bindEvents: function () {
			const self = this;

			// Status change
			$(document).on('change', '#message-status-select', this.handleStatusChange);

			// Bulk actions
			$(document).on('click', '.bulk-action-btn', this.handleBulkAction);

			// Reply form - always use delegate binding to handle dynamically loaded forms
			$(document).on(
				'submit',
				'#message-reply-form',
				function (e) {
					if (window.mhm_rentiva_config && window.mhm_rentiva_config.debug) {
						console.log('[MessagesAdmin] Form submit triggered');
					}
					return self.handleReplySubmit.call(self, e);
				}
			);

			// Also try to bind if form already exists (for immediate binding)
			const $replyForm = $('#message-reply-form');
			if ($replyForm.length > 0 && window.mhm_rentiva_config && window.mhm_rentiva_config.debug) {
				console.log('[MessagesAdmin] Immediate form binding');
			}

			// Auto-save draft
			$(document).on('input', '#reply_message', this.autoSaveDraft);

			// Keyboard shortcuts
			$(document).on('keydown', this.handleKeyboardShortcuts);

			// Reopen message button
			$(document).on('click', '#reopen-message-btn', this.handleReopenMessage);
		},

		initializeStatusSelects: function () {
			$('.message-status-select').each(
				function () {
					const $select = $(this);
					const messageId = $select.data('message-id');

					// Status change animation
					$select.on(
						'change',
						function () {
							MessagesAdmin.updateMessageStatus(messageId, $(this).val());
						}
					);
				}
			);
		},

		initializeBulkActions: function () {
			// Bulk action dropdown changed, show status dropdown
			$('#doaction, #doaction2').on(
				'change',
				function () {
					const selectedAction = $(this).val();
					const $statusContainer = $('#bulk-status-container');

					if (selectedAction === 'change_status') {
						// Status dropdown container doesn't exist, create it
						if ($statusContainer.length === 0) {
							const $statusSelect = $('<select name="bulk_status" id="bulk_status" style="margin-left: 5px; margin-right: 10px;">');

							// Status options (from mhmMessagesAdmin.strings or add directly)
							if (mhmMessagesAdmin && mhmMessagesAdmin.statuses) {
								$.each(
									mhmMessagesAdmin.statuses,
									function (key, label) {
										$statusSelect.append($('<option></option>').attr('value', key).text(label));
									}
								);
							} else {
								// Fallback - add directly
								$statusSelect.append($('<option></option>').attr('value', 'pending').text('Pending'));
								$statusSelect.append($('<option></option>').attr('value', 'answered').text('Answered'));
								$statusSelect.append($('<option></option>').attr('value', 'closed').text('Closed'));
							}

							const $container = $('<div id="bulk-status-container" class="alignleft actions" style="margin-top: 10px; margin-left: 10px;"></div>');
							$container.append($('<label>New Status: </label>'));
							$container.append($statusSelect);

							// Bulk actions dropdown'ının yanına ekle
							$('.tablenav.top .actions').append($container);
						}
						$statusContainer.show();
					} else {
						$statusContainer.hide();
					}
				}
			);
		},

		handleStatusChange: function (e) {
			const $select = $(e.target);
			const messageId = $select.data('message-id');
			const newStatus = $select.val();

			MessagesAdmin.updateMessageStatus(messageId, newStatus);
		},

		updateMessageStatus: function (messageId, status) {
			const $select = $('[data-message-id="' + messageId + '"]');
			const originalValue = $select.data('original-value');

			// Loading state
			$select.prop('disabled', true);

			$.ajax(
				{
					url: mhmMessagesAdmin.ajax_url,
					type: 'POST',
					data: {
						action: 'mhm_message_status_update',
						message_id: messageId,
						status: status,
						nonce: mhmMessagesAdmin.nonce
					},
					success: function (response) {
						if (response.success) {
							MessagesAdmin.showNotification(
								response.data.message,
								'success'
							);

							// Update status label
							const $statusSpan = $('.message-status[data-message-id="' + messageId + '"]');
							$statusSpan.text(response.data.status_label)
								.removeClass()
								.addClass('message-status status-' + response.data.status);

							// Clear cache
							MessagesAdmin.clearCache();

						} else {
							MessagesAdmin.showNotification(
								response.data || mhmMessagesAdmin.strings.error,
								'error'
							);

							// Revert old value
							$select.val(originalValue);
						}
					},
					error: function () {
						MessagesAdmin.showNotification(
							mhmMessagesAdmin.strings.error,
							'error'
						);

						// Revert old value
						$select.val(originalValue);
					},
					complete: function () {
						$select.prop('disabled', false);
					}
				}
			);
		},

		handleBulkAction: function (e) {
			e.preventDefault();

			const $button = $(e.target);
			const action = $button.data('action');
			const $selectedItems = $('input[name="message_ids[]"]:checked');

			if ($selectedItems.length === 0) {
				MessagesAdmin.showNotification(
					mhmMessagesAdmin.strings.no_items_selected,
					'warning'
				);
				return;
			}

			if (!MessagesAdmin.confirmBulkAction(action, $selectedItems.length)) {
				return;
			}

			MessagesAdmin.performBulkAction(action, $selectedItems);
		},

		confirmBulkAction: function (action, count) {
			const messages = {
				'mark_read': mhmMessagesAdmin.strings.confirm_mark_read,
				'mark_unread': mhmMessagesAdmin.strings.confirm_mark_unread,
				'delete': mhmMessagesAdmin.strings.confirm_delete
			};

			const message = messages[action];
			if (message) {
				return confirm(message.replace('%d', count));
			}

			return true;
		},

		performBulkAction: function (action, $items) {
			const itemIds = $items.map(
				function () {
					return $(this).val();
				}
			).get();

			const $form = $items.closest('form');
			const $submitBtn = $form.find('.bulk-action-btn[data-action="' + action + '"]');

			// Loading state
			$submitBtn.prop('disabled', true).text(mhmMessagesAdmin.strings.processing);

			$.ajax(
				{
					url: mhmMessagesAdmin.ajax_url,
					type: 'POST',
					data: {
						action: 'mhm_messages_bulk_action',
						bulk_action: action,
						message_ids: itemIds,
						nonce: mhmMessagesAdmin.nonce
					},
					success: function (response) {
						if (response.success) {
							MessagesAdmin.showNotification(
								response.data.message,
								'success'
							);

							// Refresh page
							setTimeout(
								function () {
									location.reload();
								},
								1500
							);

						} else {
							MessagesAdmin.showNotification(
								response.data || mhmMessagesAdmin.strings.error,
								'error'
							);
						}
					},
					error: function () {
						MessagesAdmin.showNotification(
							mhmMessagesAdmin.strings.error,
							'error'
						);
					},
					complete: function () {
						const processText = (mhmMessagesAdmin.strings && mhmMessagesAdmin.strings.process) || 'Process';
						$submitBtn.prop('disabled', false).text(
							$submitBtn.data('original-text') || processText
						);
					}
				}
			);
		},

		handleReplySubmit: function (e) {
			e.preventDefault();
			e.stopPropagation();

			if (window.mhm_rentiva_config && window.mhm_rentiva_config.debug) {

			}

			const $form = $(e.target);
			const $submitBtn = $form.find('button[type="submit"]');

			// Check if mhmMessagesAdmin is defined
			if (typeof mhmMessagesAdmin === 'undefined') {
				console.error('[MessagesAdmin] mhmMessagesAdmin is not defined!');
				alert('JavaScript configuration error. Please refresh the page.');
				return false;
			}

			// Loading state
			$submitBtn.prop('disabled', true).text(mhmMessagesAdmin.strings.processing);

			// Get TinyMCE content if editor exists
			let messageContent = '';
			if (typeof tinymce !== 'undefined' && tinymce.get('reply_message')) {
				messageContent = tinymce.get('reply_message').getContent();
			} else {
				messageContent = $('#reply_message').val();
			}

			// Get form data
			const formData = $form.serializeArray();

			// Replace nonce field name and add message content
			const ajaxData = {
				action: 'mhm_message_reply',
				nonce: mhmMessagesAdmin.nonce
			};

			// Add all form fields
			$.each(
				formData,
				function (i, field) {
					if (field.name === 'mhm_message_reply_nonce') {
						// Skip nonce field, we add it separately
						return;
					}
					if (field.name === 'message') {
						// Replace message with TinyMCE content
						ajaxData.message = messageContent;
					} else {
						ajaxData[field.name] = field.value;
					}
				}
			);

			if (window.mhm_rentiva_config && window.mhm_rentiva_config.debug) {

			}

			$.ajax(
				{
					url: mhmMessagesAdmin.ajax_url,
					type: 'POST',
					data: ajaxData,
					success: function (response) {
						if (window.mhm_rentiva_config && window.mhm_rentiva_config.debug) {
							console.log('[MessagesAdmin] AJAX response raw:', response);
						}

						if (response.success) {
							MessagesAdmin.showNotification(
								response.data.message,
								'success'
							);

							// Clear form
							$form[0].reset();

							// Clear TinyMCE editor
							if (typeof tinymce !== 'undefined' && tinymce.get('reply_message')) {
								tinymce.get('reply_message').setContent('');
							}

							// Refresh page
							setTimeout(
								function () {
									location.reload();
								},
								1500
							);

						} else {
							console.error('[MessagesAdmin] AJAX error:', response.data);
							MessagesAdmin.showNotification(
								response.data || mhmMessagesAdmin.strings.error,
								'error'
							);
						}
					},
					error: function (xhr, status, error) {
						console.error(
							'[MessagesAdmin] AJAX request failed:',
							{
								status: status,
								error: error,
								xhr: xhr,
								responseText: xhr.responseText
							}
						);
						MessagesAdmin.showNotification(
							mhmMessagesAdmin.strings.error + ' (' + error + ')',
							'error'
						);
					},
					complete: function () {
						const sendReplyText = (mhmMessagesAdmin.strings && mhmMessagesAdmin.strings.sendReply) || 'Send Reply';
						$submitBtn.prop('disabled', false).text(sendReplyText);
					}
				}
			);
		},

		autoSaveDraft: function (e) {
			const $textarea = $(e.target);
			const content = $textarea.val();

			// Debounce - wait 2 seconds and save
			clearTimeout(this.draftTimeout);
			this.draftTimeout = setTimeout(
				function () {
					MessagesAdmin.saveDraft(content);
				},
				2000
			);
		},

		saveDraft: function (content) {
			if (!content.trim()) {
				return;
			}

			$.ajax(
				{
					url: mhmMessagesAdmin.ajax_url,
					type: 'POST',
					data: {
						action: 'mhm_message_save_draft',
						content: content,
						nonce: mhmMessagesAdmin.nonce
					},
					success: function (response) {
						if (response.success) {
							// Draft saved indicator
							MessagesAdmin.showDraftIndicator();
						}
					}
				}
			);
		},

		showDraftIndicator: function () {
			let $indicator = $('.draft-indicator');
			const draftSavedText = (mhmMessagesAdmin.strings && mhmMessagesAdmin.strings.draftSaved) || 'Draft saved';

			if ($indicator.length === 0) {
				$indicator = $('<div class="draft-indicator">' + draftSavedText + '</div>');
				$('.form-actions').append($indicator);
			}

			$indicator.fadeIn().delay(2000).fadeOut();
		},

		handleKeyboardShortcuts: function (e) {
			// Ctrl+S - Save draft
			if (e.ctrlKey && e.key === 's') {
				e.preventDefault();
				const content = $('#reply_message').val();
				if (content.trim()) {
					MessagesAdmin.saveDraft(content);
				}
			}

			// Esc - Close modal
			if (e.key === 'Escape') {
				$('.modal, .overlay').fadeOut();
			}
		},

		handleReopenMessage: function (e) {
			e.preventDefault();
			const $btn = $(e.target);
			const messageId = $btn.data('message-id');

			if (!messageId) {
				MessagesAdmin.showNotification('Invalid message ID.', 'error');
				return;
			}

			if (typeof mhmMessagesAdmin === 'undefined' || !mhmMessagesAdmin.reopen_nonce) {
				MessagesAdmin.showNotification('JavaScript configuration error. Please refresh the page.', 'error');
				return;
			}

			const originalText = $btn.text();
			$btn.prop('disabled', true).text('Reopening...');

			$.ajax(
				{
					url: mhmMessagesAdmin.ajax_url,
					type: 'POST',
					data: {
						action: 'mhm_message_reopen',
						message_id: messageId,
						nonce: mhmMessagesAdmin.reopen_nonce
					},
					success: function (response) {
						if (response.success) {
							MessagesAdmin.showNotification(
								response.data.message || 'Message reopened successfully.',
								'success'
							);

							// Reload page to show updated status
							setTimeout(
								function () {
									location.reload();
								},
								1500
							);
						} else {
							MessagesAdmin.showNotification(
								response.data || 'Failed to reopen message.',
								'error'
							);
							$btn.prop('disabled', false).text(originalText);
						}
					},
					error: function (xhr, status, error) {
						MessagesAdmin.showNotification(
							'Failed to reopen message. Please try again.',
							'error'
						);
						$btn.prop('disabled', false).text(originalText);
					}
				}
			);
		},

		showNotification: function (message, type) {
			const $notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

			$('.wrap h1').after($notification);

			// Auto-dismiss after 5 seconds
			setTimeout(
				function () {
					$notification.fadeOut(
						function () {
							$(this).remove();
						}
					);
				},
				5000
			);

			// Manual dismiss
			$notification.on(
				'click',
				'.notice-dismiss',
				function () {
					$notification.fadeOut(
						function () {
							$(this).remove();
						}
					);
				}
			);
		},

		clearCache: function () {
			$.ajax(
				{
					url: mhmMessagesAdmin.ajax_url,
					type: 'POST',
					data: {
						action: 'mhm_clear_message_cache',
						nonce: mhmMessagesAdmin.nonce
					}
				}
			);
		},

		// Utility functions
		formatDate: function (dateString) {
			const date = new Date(dateString);
			const locale = (mhmMessagesAdmin.locale) || 'en-US';
			return date.toLocaleDateString(
				locale,
				{
					year: 'numeric',
					month: 'short',
					day: 'numeric',
					hour: '2-digit',
					minute: '2-digit'
				}
			);
		},

		formatTimeAgo: function (dateString) {
			const date = new Date(dateString);
			const now = new Date();
			const diffInSeconds = Math.floor((now - date) / 1000);
			const strings = (mhmMessagesAdmin.strings) || {};

			if (diffInSeconds < 60) {
				return strings.justNow || 'Just now';
			}
			if (diffInSeconds < 3600) {
				return Math.floor(diffInSeconds / 60) + ' ' + (strings.minutesAgo || 'minutes ago');
			}
			if (diffInSeconds < 86400) {
				return Math.floor(diffInSeconds / 3600) + ' ' + (strings.hoursAgo || 'hours ago');
			}
			if (diffInSeconds < 2592000) {
				return Math.floor(diffInSeconds / 86400) + ' ' + (strings.daysAgo || 'days ago');
			}

			return MessagesAdmin.formatDate(dateString);
		},

		highlightSearchTerm: function (text, term) {
			if (!term) {
				return text;
			}

			const regex = new RegExp('(' + term + ')', 'gi');
			return text.replace(regex, '<mark>$1</mark>');
		}
	};

	// Initialize when document is ready
	$(document).ready(
		function () {
			if (window.mhm_rentiva_config && window.mhm_rentiva_config.debug) {

			}
			MessagesAdmin.init();
			if (window.mhm_rentiva_config && window.mhm_rentiva_config.debug) {

			}
		}
	);

	// Export for global access
	window.MessagesAdmin = MessagesAdmin;

})(jQuery);