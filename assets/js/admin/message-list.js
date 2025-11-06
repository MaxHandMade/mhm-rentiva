/**
 * MHM Rentiva - Messages List
 * JavaScript functionality for messages list
 */

jQuery(document).ready(function ($) {
    'use strict';

    // Bulk actions
    var bulkActions = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Bulk action form submission
            $('#bulk-action-selector-top, #bulk-action-selector-bottom').on('change', function () {
                var action = $(this).val();
                if (action && action !== '-1') {
                    $('.bulk-actions .button').prop('disabled', false);
                } else {
                    $('.bulk-actions .button').prop('disabled', true);
                }
            });

            // Bulk action buttons
            $('.bulk-actions .button').on('click', function (e) {
                e.preventDefault();
                var action = $('#bulk-action-selector-top').val();
                var selectedItems = $('input[name="message_ids[]"]:checked');

                if (selectedItems.length === 0) {
                    showNotice(mhm_message_list_vars.no_items_selected, 'warning');
                    return;
                }

                if (action === 'mark_read') {
                    bulkActions.markAsRead(selectedItems);
                } else if (action === 'mark_unread') {
                    bulkActions.markAsUnread(selectedItems);
                } else if (action === 'change_status') {
                    bulkActions.changeStatus(selectedItems);
                } else if (action === 'delete') {
                    bulkActions.deleteMessages(selectedItems);
                }
            });

            // Select/deselect all
            $('#cb-select-all-1, #cb-select-all-2').on('change', function () {
                var isChecked = $(this).is(':checked');
                $('input[name="message_ids[]"]').prop('checked', isChecked);
                bulkActions.updateBulkActions();
            });

            // Individual selection
            $('input[name="message_ids[]"]').on('change', function () {
                bulkActions.updateBulkActions();
            });
        },

        updateBulkActions: function () {
            var selectedCount = $('input[name="message_ids[]"]:checked').length;
            var totalCount = $('input[name="message_ids[]"]').length;

            if (selectedCount > 0) {
                $('.bulk-actions .button').prop('disabled', false);
                $('.bulk-actions .selected-count').text(selectedCount + ' ' + mhm_message_list_vars.items_selected);
            } else {
                $('.bulk-actions .button').prop('disabled', true);
                $('.bulk-actions .selected-count').text('');
            }

            // Update select all checkbox
            if (selectedCount === totalCount) {
                $('#cb-select-all-1, #cb-select-all-2').prop('checked', true);
            } else if (selectedCount === 0) {
                $('#cb-select-all-1, #cb-select-all-2').prop('checked', false);
            } else {
                $('#cb-select-all-1, #cb-select-all-2').prop('indeterminate', true);
            }
        },

        markAsRead: function (selectedItems) {
            if (!confirm(mhm_message_list_vars.confirm_mark_read)) {
                return;
            }

            var messageIds = selectedItems.map(function () {
                return $(this).val();
            }).get();

            bulkActions.performBulkAction('mark_read', messageIds);
        },

        markAsUnread: function (selectedItems) {
            if (!confirm(mhm_message_list_vars.confirm_mark_unread)) {
                return;
            }

            var messageIds = selectedItems.map(function () {
                return $(this).val();
            }).get();

            bulkActions.performBulkAction('mark_unread', messageIds);
        },

        changeStatus: function (selectedItems) {
            const promptMsg = (mhm_message_list_vars.strings && mhm_message_list_vars.strings.selectNewStatus) ||
                'Select new status:\n1. pending\n2. answered\n3. closed\n4. spam';
            var newStatus = prompt(promptMsg);

            if (!newStatus || !['pending', 'answered', 'closed', 'spam'].includes(newStatus)) {
                return;
            }

            var messageIds = selectedItems.map(function () {
                return $(this).val();
            }).get();

            bulkActions.performBulkAction('change_status', messageIds, { status: newStatus });
        },

        deleteMessages: function (selectedItems) {
            if (!confirm(mhm_message_list_vars.confirm_delete)) {
                return;
            }

            var messageIds = selectedItems.map(function () {
                return $(this).val();
            }).get();

            bulkActions.performBulkAction('delete', messageIds);
        },

        performBulkAction: function (action, messageIds, extraData = {}) {
            var $button = $('.bulk-actions .button');
            var originalText = $button.text();

            $button.prop('disabled', true).text(mhm_message_list_vars.processing);

            var data = {
                action: 'mhm_messages_bulk_action',
                bulk_action: action,
                message_ids: messageIds,
                nonce: mhm_message_list_vars.nonce
            };

            // Ek verileri ekle
            Object.assign(data, extraData);

            $.ajax({
                url: mhm_message_list_vars.ajax_url,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        // Sayfayı yenile
                        location.reload();
                    } else {
                        showNotice(response.data || mhm_message_list_vars.error_occurred, 'error');
                    }
                },
                error: function () {
                    showNotice(mhm_message_list_vars.error_occurred, 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }
    };

    // Filtering and search
    var filtering = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Filter form
            $('.filter-controls form').on('submit', function (e) {
                e.preventDefault();
                filtering.applyFilters();
            });

            // Clear filters
            $('.filter-controls .clear-filters').on('click', function (e) {
                e.preventDefault();
                filtering.clearFilters();
            });

            // Quick filtering
            $('.filter-controls select').on('change', function () {
                filtering.applyFilters();
            });
        },

        applyFilters: function () {
            var form = $('.filter-controls form');
            var formData = form.serialize();

            // Update URL
            var url = new URL(window.location);
            var params = new URLSearchParams(formData);

            // Clear existing parameters
            url.searchParams.delete('status_filter');
            url.searchParams.delete('category_filter');
            url.searchParams.delete('s');

            // Add new parameters
            params.forEach(function (value, key) {
                if (value) {
                    url.searchParams.set(key, value);
                }
            });

            window.location.href = url.toString();
        },

        clearFilters: function () {
            var url = new URL(window.location);
            url.searchParams.delete('status_filter');
            url.searchParams.delete('category_filter');
            url.searchParams.delete('s');
            window.location.href = url.toString();
        }
    };

    // Message status update
    var statusUpdate = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Status change dropdowns
            $('.message-status-select').on('change', function () {
                var messageId = $(this).data('message-id');
                var newStatus = $(this).val();

                statusUpdate.updateMessageStatus(messageId, newStatus);
            });
        },

        updateMessageStatus: function (messageId, status) {
            $.ajax({
                url: mhm_message_list_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'mhm_message_status_update',
                    message_id: messageId,
                    status: status,
                    nonce: mhm_message_list_vars.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Status updated, reload page
                        location.reload();
                    } else {
                        showNotice(response.data || mhm_message_list_vars.error_occurred, 'error');
                    }
                },
                error: function () {
                    showNotice(mhm_message_list_vars.error_occurred, 'error');
                }
            });
        }
    };

    // Quick reply
    var quickReply = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Quick reply buttons
            $('.quick-reply-btn').on('click', function (e) {
                e.preventDefault();
                var messageId = $(this).data('message-id');
                quickReply.showQuickReplyForm(messageId);
            });

            // Reply form submission
            $('#quick-reply-form').on('submit', function (e) {
                e.preventDefault();
                quickReply.sendQuickReply();
            });

            // Reply form cancel
            $('.cancel-quick-reply').on('click', function (e) {
                e.preventDefault();
                quickReply.hideQuickReplyForm();
            });
        },

        showQuickReplyForm: function (messageId) {
            const strings = (mhm_message_list_vars.strings) || {};
            // Show quick reply form
            var form = $('<div id="quick-reply-modal" class="quick-reply-modal">' +
                '<div class="quick-reply-content">' +
                '<h3>' + (strings.quickReply || 'Quick Reply') + '</h3>' +
                '<form id="quick-reply-form">' +
                '<input type="hidden" name="message_id" value="' + messageId + '">' +
                '<div class="form-field">' +
                '<label for="quick-reply-message">' + (strings.yourReply || 'Your Reply') + ':</label>' +
                '<textarea id="quick-reply-message" name="message" rows="4" required></textarea>' +
                '</div>' +
                '<div class="form-actions">' +
                '<button type="submit" class="button button-primary">' + (strings.send || 'Send') + '</button>' +
                '<button type="button" class="button cancel-quick-reply">' + (strings.cancel || 'Cancel') + '</button>' +
                '</div>' +
                '</form>' +
                '</div>' +
                '</div>');

            $('body').append(form);
        },

        hideQuickReplyForm: function () {
            $('#quick-reply-modal').remove();
        },

        sendQuickReply: function () {
            var form = $('#quick-reply-form');
            var formData = form.serialize();

            $.ajax({
                url: mhm_message_list_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'mhm_message_reply',
                    ...formData,
                    nonce: mhm_message_list_vars.nonce
                },
                success: function (response) {
                    if (response.success) {
                        quickReply.hideQuickReplyForm();
                        // Reload page
                        location.reload();
                    } else {
                        showNotice(response.data || mhm_message_list_vars.error_occurred, 'error');
                    }
                },
                error: function () {
                    showNotice(mhm_message_list_vars.error_occurred, 'error');
                }
            });
        }
    };

    // Statistics cards animation
    var statsAnimation = {
        init: function () {
            this.animateStats();
        },

        animateStats: function () {
            $('.stat-card').each(function (index) {
                $(this).css('animation-delay', (index * 0.1) + 's');
            });
        }
    };

    // Message read marking
    var messageRead = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Mark as read when message links are clicked
            $('.message-subject a').on('click', function () {
                var messageId = $(this).closest('tr').find('input[name="message_ids[]"]').val();
                if (messageId) {
                    messageRead.markAsRead(messageId);
                }
            });
        },

        markAsRead: function (messageId) {
            $.ajax({
                url: mhm_message_list_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'mhm_message_mark_read',
                    message_id: messageId,
                    nonce: mhm_message_list_vars.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Remove read indicator
                        $('input[name="message_ids[]"][value="' + messageId + '"]')
                            .closest('tr')
                            .find('.unread-indicator')
                            .remove();
                    }
                }
            });
        }
    };

    // Initialize
    bulkActions.init();
    filtering.init();
    statusUpdate.init();
    quickReply.init();
    statsAnimation.init();
    messageRead.init();

    /**
     * Show notice message
     */
    function showNotice(message, type) {
        type = type || 'info';
        var noticeClass = 'notice-' + type;
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><p><strong>' + message + '</strong></p></div>');

        // Remove any existing notices first
        $('.notice').remove();

        // Add to body for better visibility
        $('body').append(notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            notice.fadeOut(500, function () {
                notice.remove();
            });
        }, 5000);
    }

    // Update statistics when page loads
    if (typeof mhm_message_list_vars !== 'undefined' && mhm_message_list_vars.auto_refresh) {
        setInterval(function () {
            // Auto-update statistics (optional)
        }, 30000); // Every 30 seconds
    }
});
