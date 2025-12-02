/**
 * Account Messages JavaScript
 * 
 * Handles messaging functionality in My Account area
 */
jQuery(document).ready(function ($) {
    // Prevent customer-messages.js from running (if loaded)
    if (typeof window.CustomerMessages !== 'undefined') {
        window.CustomerMessages.init = function () { };
    }

    // Check if configuration exists
    if (typeof mhmRentivaMessages === 'undefined') {
        return;
    }

    // Use REST API directly for My Account messages page
    var restUrl = mhmRentivaMessages.restUrl;
    var restNonce = mhmRentivaMessages.restNonce;
    var customerEmail = mhmRentivaMessages.customerEmail;
    var customerName = mhmRentivaMessages.customerName;

    // Validate variables
    if (!restUrl || !restNonce) {
        console.error('Messages: Missing REST API configuration');
        return;
    }

    const mhmMessages = {
        restUrl: restUrl,
        restNonce: restNonce,
        customerEmail: customerEmail || '',
        customerName: customerName || '',

        init: function () {
            this.loadMessages();
            this.bindEvents();
        },

        bindEvents: function () {
            const self = this;

            $('#new-message-btn').on('click', function () {
                $('#new-message-form').removeClass('hidden');
                $('#messages-list').addClass('hidden');
                $('#message-thread').addClass('hidden');
                // Load bookings when form opens
                self.loadBookings();
            });

            $('.close-form').on('click', function () {
                $('#new-message-form').addClass('hidden');
                $('#messages-list').removeClass('hidden');
            });

            $('.back-to-list').on('click', function () {
                $('#message-thread').addClass('hidden');
                $('#messages-list').removeClass('hidden');
            });

            $('#send-message-form').on('submit', function (e) {
                e.preventDefault();
                self.sendMessage();
            });

            $('#reply-form').on('submit', function (e) {
                e.preventDefault();
                self.sendReply();
            });

            // Mesaj item'ına tıklama event listener (delegated)
            $(document).on('click', '.message-item', function () {
                const $item = $(this);
                const threadId = $item.data('thread-id');
                const messageId = $item.data('message-id');

                if (threadId && messageId) {
                    self.loadThread(threadId, messageId);
                }
            });

            // Cancel reply button
            $(document).on('click', '.cancel-reply', function () {
                $('#thread-reply').addClass('hidden');
                $('#reply-message').val('');
            });

            // Close thread button
            $(document).on('click', '.close-thread-btn', function () {
                const $btn = $(this);
                const threadId = $btn.data('thread-id');

                if (!threadId) {
                    alert(mhmRentivaMessages.i18n.threadIdNotFound);
                    return;
                }

                const confirmMsg = mhmRentivaMessages.i18n.confirmClose;
                if (!confirm(confirmMsg)) {
                    return;
                }

                const originalText = $btn.text();
                $btn.prop('disabled', true).text(mhmRentivaMessages.i18n.closing);

                $.ajax({
                    url: self.restUrl + 'customer/messages/close',
                    method: 'POST',
                    data: {
                        thread_id: threadId
                    },
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                    },
                    success: function (response) {
                        if (response && response.success !== false) {
                            const successMsg = response.message || mhmRentivaMessages.i18n.messageClosed;
                            alert(successMsg);

                            // Reload thread to show closed status
                            self.loadThread(threadId, null);

                            // Reload messages list
                            self.loadMessages();
                        } else {
                            const errorMsg = response.error || mhmRentivaMessages.i18n.closeFailed;
                            alert(errorMsg);
                        }
                    },
                    error: function (xhr) {
                        let errorMsg = mhmRentivaMessages.i18n.closeFailed;

                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }

                        alert(errorMsg);
                    },
                    complete: function () {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
        },

        loadMessages: function () {
            const self = this;
            const $list = $('#messages-list');
            const loadingText = mhmRentivaMessages.i18n.loadingMessages;

            $list.html('<div class="loading">' + loadingText + '</div>');

            $.ajax({
                url: this.restUrl + 'customer/messages',
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function (response) {
                    // REST API returns data directly, not wrapped
                    // Check if response is an array (WP_REST_Response wraps data)
                    let messages = response;
                    if (response && typeof response === 'object' && response.messages) {
                        messages = response.messages;
                    } else if (Array.isArray(response)) {
                        messages = response;
                    }

                    if (messages && messages.length > 0) {
                        self.renderMessages(messages);
                    } else {
                        const noMsgText = mhmRentivaMessages.i18n.noMessages;
                        $list.html('<div class="no-messages">' + noMsgText + '</div>');
                    }
                },
                error: function (xhr, status, error) {
                    let errorMsg = mhmRentivaMessages.i18n.loadFailed;

                    // Try to parse error response
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    } else if (xhr.status === 401) {
                        errorMsg = mhmRentivaMessages.i18n.loginRequired;
                    } else if (xhr.status === 403) {
                        errorMsg = mhmRentivaMessages.i18n.permissionDenied;
                    }

                    $list.html('<div class="error">' + errorMsg + '</div>');

                    // Log for debugging
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('Messages load error:', {
                            status: xhr.status,
                            error: error,
                            response: xhr.responseJSON || xhr.responseText
                        });
                    }
                }
            });
        },

        renderMessages: function (messages) {
            const $list = $('#messages-list');

            if (messages.length === 0) {
                const noMsgText = mhmRentivaMessages.i18n.noMessages;
                $list.html('<div class="no-messages">' + noMsgText + '</div>');
                return;
            }

            let html = '<div class="messages-grid">';
            messages.forEach(function (message) {
                const dateDisplay = message.date_human || message.date || '';
                const dateFull = message.date_full || message.date || '';
                const isReply = message.parent_message_id && message.parent_message_id > 0;
                html += '<div class="message-item" data-thread-id="' + (message.thread_id || message.id) + '" data-message-id="' + message.id + '">';
                html += '<div class="message-header">';
                // Show ID only in main messages (no parent_message_id)
                if (!isReply) {
                    html += '<span class="message-id">#' + (message.id || '') + '</span>';
                } else {
                    html += '<span class="message-id" style="visibility: hidden;">—</span>'; // Placeholder for empty space
                }

                // New badge for unread admin replies
                if (message.has_unread_admin_reply) {
                    html += '<span class="status-badge-new">' + mhmRentivaMessages.i18n.new + '</span>';
                }

                html += '<span class="message-status status-' + (message.status || 'pending') + '">' + (message.status_label || '') + '</span>';
                html += '</div>';
                html += '<div class="message-subject">' + (message.subject || '') + '</div>';
                html += '<div class="message-meta">';
                // Category information
                if (message.category_label) {
                    html += '<span class="message-category category-' + (message.category || 'general') + '">' + (message.category_label || '') + '</span>';
                }
                // Date information - both relative and absolute (side by side two blocks)
                html += '<div class="message-date">';
                if (dateDisplay) {
                    html += '<span class="date-relative" title="' + (dateFull || '') + '">' + dateDisplay + '</span>';
                }
                if (dateFull) {
                    html += '<span class="date-full">' + dateFull + '</span>';
                }
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';

            $list.html(html);
        },

        loadThread: function (threadId, messageId) {
            const self = this;
            const $thread = $('#message-thread');
            const $threadMessages = $('#thread-messages');
            const loadingText = mhmRentivaMessages.i18n.loadingThread;

            // Hide list and show thread
            $('#messages-list').addClass('hidden');
            $('#new-message-form').addClass('hidden');
            $thread.removeClass('hidden');
            $threadMessages.html('<div class="loading">' + loadingText + '</div>');

            $.ajax({
                url: this.restUrl + 'customer/messages/thread/' + threadId,
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function (response) {
                    if (response && response.messages) {
                        // Add thread_id to response (if not exists)
                        if (!response.thread_id && threadId) {
                            response.thread_id = threadId;
                        }
                        self.renderThread(response, threadId);
                    } else {
                        const errorMsg = mhmRentivaMessages.i18n.threadLoadFailed;
                        $threadMessages.html('<div class="error">' + errorMsg + '</div>');
                    }
                },
                error: function (xhr) {
                    let errorMsg = mhmRentivaMessages.i18n.threadLoadFailed;

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }

                    $threadMessages.html('<div class="error">' + errorMsg + '</div>');
                }
            });
        },

        renderThread: function (threadData, threadId) {
            const $threadMessages = $('#thread-messages');
            const $threadSubject = $('#thread-subject');
            const $threadReply = $('#thread-reply');

            // Set subject
            if (threadData.subject) {
                $threadSubject.text(threadData.subject);
            }

            // Render messages
            if (!threadData.messages || threadData.messages.length === 0) {
                $threadMessages.html('<div class="no-messages">' + mhmRentivaMessages.i18n.noMessagesFound + '</div>');
                return;
            }

            let html = '<div class="thread-messages-list">';
            threadData.messages.forEach(function (message) {
                const isCustomer = message.message_type === 'customer_to_admin';
                const messageClass = isCustomer ? 'customer-message' : 'admin-message';
                const authorName = isCustomer ? (message.customer_name || mhmRentivaMessages.i18n.customer) : (message.admin_name || mhmRentivaMessages.i18n.administrator);

                html += '<div class="thread-message-item ' + messageClass + '">';
                html += '<div class="message-header">';
                html += '<strong>' + authorName + '</strong>';
                html += '<span class="message-date">' + (message.date_human || message.date || '') + '</span>';
                html += '</div>';
                html += '<div class="message-content">' + (message.content || '') + '</div>';
                html += '</div>';
            });
            html += '</div>';

            $threadMessages.html(html);

            // Show reply form if thread is open
            if (threadData.can_reply !== false && threadData.status !== 'closed') {
                $threadReply.removeClass('hidden');
                // Set thread_id for reply form - first from response, then parameter, then from messages
                const finalThreadId = threadData.thread_id || threadId || (threadData.messages[0] && threadData.messages[0].thread_id) || null;
                if (finalThreadId) {
                    $threadReply.find('form').data('thread-id', finalThreadId);
                } else {
                    console.error('Thread ID not found in response or parameters');
                }

                // Add close button if thread is open
                if (!threadData.threadActions) {
                    const closeBtn = $('<button type="button" class="btn btn-secondary close-thread-btn" style="margin-top: 15px;">' + mhmRentivaMessages.i18n.closeMessage + '</button>');
                    closeBtn.data('thread-id', finalThreadId);
                    $threadReply.after(closeBtn);
                }
            } else {
                $threadReply.addClass('hidden');
                // Thread is closed - show message
                if (threadData.status === 'closed') {
                    const closedMsg = $('<div class="thread-closed-notice" style="padding: 15px; background: #f0f0f0; border-radius: 6px; margin-top: 15px; text-align: center; color: #666;">' + mhmRentivaMessages.i18n.conversationClosed + '</div>');
                    $threadMessages.after(closedMsg);
                }
            }
        },

        sendMessage: function () {
            const self = this;
            const form = $('#send-message-form');
            const $submitBtn = form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            const formData = {
                category: $('#message-category').val(),
                subject: $('#message-subject').val(),
                message: $('#message-content').val(),
                priority: $('#message-priority').val() || 'normal',
                booking_id: parseInt($('#message-booking').val()) || 0
            };

            // Validation
            if (!formData.category || !formData.subject || !formData.message) {
                const errorMsg = mhmRentivaMessages.i18n.fillRequired;
                alert(errorMsg);
                return;
            }

            const sendingText = mhmRentivaMessages.i18n.sending;
            $submitBtn.prop('disabled', true).text(sendingText);

            $.ajax({
                url: this.restUrl + 'customer/messages',
                method: 'POST',
                data: formData,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function (response) {
                    const successMsg = mhmRentivaMessages.i18n.messageSent;
                    const errorMsg = mhmRentivaMessages.i18n.messageSendFailed;

                    if (response && response.success !== false) {
                        alert(successMsg);
                        form[0].reset();
                        $('#new-message-form').addClass('hidden');
                        $('#messages-list').removeClass('hidden');
                        self.loadMessages();
                    } else {
                        alert(response.error || errorMsg);
                    }
                },
                error: function (xhr) {
                    const errorMsg = mhmRentivaMessages.i18n.errorOccurred;
                    alert(errorMsg);
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        sendReply: function () {
            const self = this;
            const $form = $('#reply-form');
            const $replyMessage = $('#reply-message');
            const message = $replyMessage.val().trim();
            const threadId = $form.data('thread-id');

            if (!message) {
                const errorMsg = mhmRentivaMessages.i18n.enterReply;
                alert(errorMsg);
                return;
            }

            if (!threadId) {
                const errorMsg = mhmRentivaMessages.i18n.threadIdNotFound;
                alert(errorMsg);
                return;
            }

            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            const sendingText = mhmRentivaMessages.i18n.sending;

            $submitBtn.prop('disabled', true).text(sendingText);

            $.ajax({
                url: this.restUrl + 'customer/messages/reply',
                method: 'POST',
                data: {
                    thread_id: threadId,
                    message: message
                },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function (response) {
                    if (response && response.success !== false) {
                        const successMsg = mhmRentivaMessages.i18n.replySent;
                        alert(successMsg);

                        // Clear form
                        $replyMessage.val('');

                        // Reload thread to show new reply
                        self.loadThread(threadId, null);
                    } else {
                        const errorMsg = response.error || mhmRentivaMessages.i18n.replyFailed;
                        alert(errorMsg);
                    }
                },
                error: function (xhr) {
                    let errorMsg = mhmRentivaMessages.i18n.replyFailed;

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }

                    alert(errorMsg);
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        loadBookings: function () {
            const self = this;
            const $bookingSelect = $('#message-booking');

            // Don't reload if already populated
            if ($bookingSelect.find('option').length > 1) {
                return;
            }

            $.ajax({
                url: this.restUrl + 'customer/bookings',
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function (response) {
                    if (response && response.bookings && response.bookings.length > 0) {
                        // Clear existing options except the first one
                        $bookingSelect.find('option:not(:first)').remove();

                        // Add booking options
                        response.bookings.forEach(function (booking) {
                            $bookingSelect.append(
                                $('<option></option>')
                                    .attr('value', booking.id)
                                    .text(booking.label)
                            );
                        });
                    }
                },
                error: function (xhr) {
                    // Silent fail - bookings are optional
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('Failed to load bookings:', xhr);
                    }
                }
            });
        }
    };

    mhmMessages.init();
});
