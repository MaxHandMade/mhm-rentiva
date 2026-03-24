/**
 * Customer Messages JavaScript
 * Customer message panel functions
 */

(function ($) {
    'use strict';

    const CustomerMessages = {
        init: function () {
            this.customerEmail = window.mhmCustomerMessages?.customerEmail || '';
            this.customerName = window.mhmCustomerMessages?.customerName || '';
            this.ajaxUrl = window.mhmCustomerMessages?.ajaxUrl || '';
            this.nonce = window.mhmCustomerMessages?.nonce || '';

            this.bindEvents();
            this.loadMessages();
            this.initializeAutoRefresh();
        },

        bindEvents: function () {
            // New message button
            $('#new-message-btn').on('click', this.showNewMessageForm);

            // Close form buttons
            $('.close-form').on('click', this.hideForms);

            // Back to list button
            $('.back-to-list').on('click', this.showMessagesList);

            // Cancel reply button
            $('.cancel-reply').on('click', this.hideReplyForm);

            // Form submissions
            $('#send-message-form').on('submit', this.handleNewMessageSubmit);
            $('#reply-form').on('submit', this.handleReplySubmit);

            // Message item clicks
            $(document).on('click', '.message-item', this.handleMessageClick);

            // Auto-save draft
            $(document).on('input', 'textarea[name="message"]', this.autoSaveDraft);

            // Real-time notifications
            this.initializeNotifications();
        },

        loadMessages: function () {
            const $messagesList = $('#messages-list');

            // Loading state
            $messagesList.html('<div class="loading">' + (window.mhmCustomerMessages?.strings?.loading_messages || 'Loading messages...') + '</div>');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_customer_get_messages',
                    customer_email: this.customerEmail,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderMessages(response.data.messages);
                        this.updateUnreadCount(response.data.unread_count);
                    } else {
                        this.showError(response.data || 'Messages could not be loaded');
                    }
                },
                error: () => {
                    this.showError('An error occurred. Please try again.');
                }
            });
        },

        renderMessages: function (messages) {
            const $list = $('#messages-list');

            if (messages.length === 0) {
                $list.html('<div class="no-messages">' + (window.mhmCustomerMessages?.strings?.no_messages || 'No messages found yet.') + '</div>');
                return;
            }

            let html = '';
            messages.forEach((message) => {
                const unreadClass = message.is_read ? '' : 'unread';
                const timeAgo = this.formatTimeAgo(message.date);

                html += `
                    <div class="message-item ${unreadClass}" 
                         data-thread-id="${message.thread_id}" 
                         data-message-id="${message.id}">
                        <div class="message-info">
                            <div class="message-subject">${this.escapeHtml(message.subject)}</div>
                            <div class="message-meta">
                                <span class="category">${this.escapeHtml(message.category_label)}</span>
                                <span class="status status-${message.status}">${this.escapeHtml(message.status_label)}</span>
                                <span class="date">${timeAgo}</span>
                            </div>
                        </div>
                        <div class="message-preview">${this.escapeHtml(message.preview)}</div>
                    </div>
                `;
            });

            $list.html(html);
        },

        handleMessageClick: function (e) {
            const $item = $(e.currentTarget);
            const threadId = $item.data('thread-id');

            this.loadThread(threadId);
        },

        loadThread: function (threadId) {
            const $threadContainer = $('#message-thread');
            const $messagesList = $('#messages-list');

            // Loading state
            $threadContainer.find('#thread-messages').html('<div class="loading">' + (window.mhmCustomerMessages?.strings?.loading_thread || 'Loading thread...') + '</div>');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_customer_get_thread',
                    thread_id: threadId,
                    customer_email: this.customerEmail,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderThread(response.data);
                        $messagesList.hide();
                        $threadContainer.show();
                    } else {
                        this.showError(response.data || 'Thread could not be loaded');
                    }
                },
                error: () => {
                    this.showError('An error occurred. Please try again.');
                }
            });
        },

        renderThread: function (data) {
            const $threadContainer = $('#message-thread');
            const $messagesContainer = $('#thread-messages');

            // Update thread subject
            $('#thread-subject').text(this.escapeHtml(data.subject));

            // Render messages
            let html = '';
            data.messages.forEach((message) => {
                const messageClass = message.message_type === 'customer_to_admin' ? 'customer' : 'admin';
                const authorName = message.message_type === 'customer_to_admin'
                    ? this.customerName
                    : (window.mhmCustomerMessages?.strings?.administrator || 'Administrator');
                const timeAgo = this.formatTimeAgo(message.date);

                html += `
                    <div class="thread-message ${messageClass}">
                        <div class="message-header">
                            <strong>${this.escapeHtml(authorName)}</strong>
                            <span class="message-date">${timeAgo}</span>
                        </div>
                        <div class="message-content">${this.escapeHtml(message.content)}</div>
                    </div>
                `;
            });

            $messagesContainer.html(html);

            // Show/hide reply form
            if (data.can_reply) {
                $('#thread-reply').show();
            } else {
                $('#thread-reply').hide();
            }

            // Thread ID'yi data attribute'a kaydet
            $threadContainer.data('thread-id', data.thread_id);
        },

        handleNewMessageSubmit: function (e) {
            e.preventDefault();

            const $form = $(e.target);
            const formData = new FormData($form[0]);
            const $submitBtn = $form.find('button[type="submit"]');

            // Loading state
            $submitBtn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_customer_send_message',
                    customer_email: this.customerEmail,
                    customer_name: this.customerName,
                    category: formData.get('category'),
                    subject: formData.get('subject'),
                    message: formData.get('message'),
                    booking_id: formData.get('booking_id'),
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Your message has been sent successfully.');
                        this.hideForms();
                        this.loadMessages();
                        $form[0].reset();
                    } else {
                        this.showError(response.data || 'Message could not be sent.');
                    }
                },
                error: () => {
                    this.showError('An error occurred. Please try again.');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).text('Send Message');
                }
            });
        },

        handleReplySubmit: function (e) {
            e.preventDefault();

            const $form = $(e.target);
            const threadId = $('#message-thread').data('thread-id');
            const messageContent = $form.find('[name="message"]').val();
            const $submitBtn = $form.find('button[type="submit"]');

            if (!messageContent.trim()) {
                this.showError('Please write a message.');
                return;
            }

            // Loading state
            $submitBtn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_customer_send_reply',
                    thread_id: threadId,
                    customer_email: this.customerEmail,
                    message: messageContent,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Your reply has been sent successfully.');
                        this.loadThread(threadId);
                        $form.find('[name="message"]').val('');
                        $('#thread-reply').hide();
                    } else {
                        this.showError(response.data || 'Reply could not be sent.');
                    }
                },
                error: () => {
                    this.showError('An error occurred. Please try again.');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).text('Send');
                }
            });
        },

        showNewMessageForm: function () {
            $('#new-message-form').slideDown();
            $('#messages-list').slideUp();
        },

        hideForms: function () {
            $('#new-message-form').slideUp();
            $('#message-thread').slideUp();
            $('#messages-list').slideDown();
        },

        showMessagesList: function () {
            $('#message-thread').slideUp();
            $('#messages-list').slideDown();
        },

        hideReplyForm: function () {
            $('#thread-reply').slideUp();
        },

        updateUnreadCount: function (count) {
            const $counter = $('#messages-unread-count');
            if (count > 0) {
                $counter.text(count).show();
            } else {
                $counter.hide();
            }
        },

        autoSaveDraft: function (e) {
            const $textarea = $(e.target);
            const content = $textarea.val();

            // Debounce - 3 saniye bekleyip kaydet
            clearTimeout(this.draftTimeout);
            this.draftTimeout = setTimeout(() => {
                this.saveDraft(content);
            }, 3000);
        },

        saveDraft: function (content) {
            if (!content.trim()) {
                return;
            }

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_customer_save_draft',
                    content: content,
                    customer_email: this.customerEmail,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showDraftIndicator();
                    }
                }
            });
        },

        showDraftIndicator: function () {
            let $indicator = $('.draft-indicator');

            if ($indicator.length === 0) {
                $indicator = $('<div class="draft-indicator">' + (window.mhmCustomerMessages?.strings?.draft_saved || 'Draft saved') + '</div>');
                $('.form-actions').append($indicator);
            }

            $indicator.fadeIn().delay(2000).fadeOut();
        },

        initializeAutoRefresh: function () {
            // Check messages every 30 seconds
            setInterval(() => {
                this.checkForNewMessages();
            }, 30000);
        },

        checkForNewMessages: function () {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_customer_check_new_messages',
                    customer_email: this.customerEmail,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success && response.data.has_new) {
                        this.showNewMessageNotification();
                        this.updateUnreadCount(response.data.unread_count);

                        // If message list is visible, refresh
                        if ($('#messages-list').is(':visible')) {
                            this.loadMessages();
                        }
                    }
                }
            });
        },

        showNewMessageNotification: function () {
            // Browser notification
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Yeni Mesaj', {
                    body: 'Size yeni bir mesaj geldi.',
                    icon: window.location.origin + '/wp-content/plugins/mhm-rentiva/assets/images/mhm-logo.png'
                });
            }

            // In-page notification
            this.showSuccess('You have a new message!', 5000);
        },

        initializeNotifications: function () {
            // Browser notification izni iste
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        },

        showSuccess: function (message, duration = 3000) {
            this.showNotification(message, 'success', duration);
        },

        showError: function (message, duration = 5000) {
            this.showNotification(message, 'error', duration);
        },

        showNotification: function (message, type, duration) {
            MHMRentivaToast.show(message, {
                type: type,
                duration: duration || (type === 'error' ? 5000 : 3000)
            });
        },

        // Utility functions
        formatTimeAgo: function (dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) return window.mhmRentivaCustomerMessages?.strings?.just_now || 'Just now';
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' ' + (window.mhmRentivaCustomerMessages?.strings?.minutes_ago || 'minutes ago');
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' ' + (window.mhmRentivaCustomerMessages?.strings?.hours_ago || 'hours ago');
            if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + ' ' + (window.mhmRentivaCustomerMessages?.strings?.days_ago || 'days ago');

            return date.toLocaleDateString(window.mhmRentivaCustomerMessages?.locale || 'en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },

        escapeHtml: function (text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };

            return text.replace(/[&<>"']/g, (m) => map[m]);
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        CustomerMessages.init();
    });

    // Export for global access
    window.CustomerMessages = CustomerMessages;

})(jQuery);
