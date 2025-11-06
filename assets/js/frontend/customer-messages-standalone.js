/**
 * Customer Messages Standalone JavaScript
 */

jQuery(document).ready(function ($) {
    initializeCustomerMessages();
});

/**
 * Initialize customer messages functionality
 */
function initializeCustomerMessages() {
    // Initialize event listeners
    initializeEventListeners();

    // Load messages if on messages page
    if (typeof window.mhmCustomerMessages !== 'undefined') {
        loadMessages();
    }
}

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // New message button
    jQuery(document).on('click', '#new-message-btn', function () {
        alert('Yeni mesaj özelliği yakında eklenecek.');
    });

    // Message item click
    jQuery(document).on('click', '.message-item', function () {
        const messageId = jQuery(this).data('message-id');
        if (messageId) {
            viewMessage(messageId);
        }
    });

    // Back to list button
    jQuery(document).on('click', '.back-to-list', function () {
        showMessagesList();
    });

    // Close form button
    jQuery(document).on('click', '.close-form', function () {
        hideNewMessageForm();
    });

    // New message form submission
    jQuery('#send-message-form').on('submit', function (e) {
        e.preventDefault();
        sendNewMessage();
    });

    // Reply form submission
    jQuery('#reply-form').on('submit', function (e) {
        e.preventDefault();
        sendReply();
    });
}

/**
 * Load messages
 */
function loadMessages() {
    const messagesList = jQuery('#messages-list');

    // Show loading
    messagesList.html('<div class="loading">Mesajlar yükleniyor...</div>');

    // AJAX request to load messages
    jQuery.post(window.mhmCustomerMessages.ajaxUrl, {
        action: 'mhm_get_customer_messages',
        customer_email: window.mhmCustomerMessages.customerEmail,
        nonce: window.mhmCustomerMessages.nonce
    }, function (response) {
        if (response.success) {
            displayMessages(response.data.messages);
        } else {
            messagesList.html('<div class="error">Mesajlar yüklenirken hata oluştu: ' + response.data + '</div>');
        }
    }).fail(function () {
        messagesList.html('<div class="error">Mesajlar yüklenirken hata oluştu.</div>');
    });
}

/**
 * Display messages
 */
function displayMessages(messages) {
    const messagesList = jQuery('#messages-list');

    if (messages.length === 0) {
        messagesList.html('<div class="no-messages">Henüz mesajınız bulunmuyor.</div>');
        return;
    }

    let html = '';
    messages.forEach(function (message) {
        const unreadClass = message.is_read ? '' : 'unread';
        const statusClass = 'message-status-' + message.status;

        html += `
            <div class="message-item ${unreadClass}" data-message-id="${message.id}">
                <div class="message-subject">${escapeHtml(message.subject)}</div>
                <div class="message-meta">
                    <span class="message-date">${formatDate(message.created_at)}</span>
                    <span class="message-status ${statusClass}">${message.status}</span>
                    <span class="message-category">${escapeHtml(message.category)}</span>
                </div>
            </div>
        `;
    });

    messagesList.html(html);
}

/**
 * View message thread
 */
function viewMessage(messageId) {
    // Hide messages list
    jQuery('#messages-list').addClass('hidden');

    // Show message thread
    jQuery('#message-thread').removeClass('hidden');

    // Load message thread
    loadMessageThread(messageId);
}

/**
 * Load message thread
 */
function loadMessageThread(messageId) {
    const threadMessages = jQuery('#thread-messages');

    // Show loading
    threadMessages.html('<div class="loading">Mesaj thread\'i yükleniyor...</div>');

    // AJAX request to load thread
    jQuery.post(window.mhmCustomerMessages.ajaxUrl, {
        action: 'mhm_get_message_thread',
        message_id: messageId,
        customer_email: window.mhmCustomerMessages.customerEmail,
        nonce: window.mhmCustomerMessages.nonce
    }, function (response) {
        if (response.success) {
            displayMessageThread(response.data);
        } else {
            threadMessages.html('<div class="error">Mesaj thread\'i yüklenirken hata oluştu: ' + response.data + '</div>');
        }
    }).fail(function () {
        threadMessages.html('<div class="error">Mesaj thread\'i yüklenirken hata oluştu.</div>');
    });
}

/**
 * Display message thread
 */
function displayMessageThread(threadData) {
    // Update thread subject
    jQuery('#thread-subject').text(threadData.subject);

    // Display messages
    const threadMessages = jQuery('#thread-messages');
    let html = '';

    threadData.messages.forEach(function (message) {
        const messageClass = message.sender === 'customer' ? 'customer-message' : 'admin-message';

        html += `
            <div class="thread-message ${messageClass}">
                <div class="message-header">
                    <span class="sender">${message.sender === 'customer' ? 'Siz' : 'Destek'}</span>
                    <span class="timestamp">${formatDate(message.created_at)}</span>
                </div>
                <div class="message-content">${escapeHtml(message.content)}</div>
            </div>
        `;
    });

    threadMessages.html(html);

    // Show reply form if message is not closed
    if (threadData.status !== 'closed') {
        jQuery('#thread-reply').removeClass('hidden');
        jQuery('#thread-reply form')[0].reset();
        jQuery('#thread-reply form').data('message-id', threadData.id);
    } else {
        jQuery('#thread-reply').addClass('hidden');
    }

    // Scroll to bottom
    threadMessages.scrollTop(threadMessages[0].scrollHeight);
}

/**
 * Show messages list
 */
function showMessagesList() {
    jQuery('#message-thread').addClass('hidden');
    jQuery('#messages-list').removeClass('hidden');
    jQuery('#new-message-form').addClass('hidden');
}

/**
 * Hide new message form
 */
function hideNewMessageForm() {
    jQuery('#new-message-form').addClass('hidden');
}

/**
 * Send new message
 */
function sendNewMessage() {
    const form = jQuery('#send-message-form');
    const formData = form.serialize();

    // Add action and nonce
    const data = formData + '&action=mhm_send_customer_message&nonce=' + window.mhmCustomerMessages.nonce;

    // Disable form
    form.find('button[type="submit"]').prop('disabled', true).text('Gönderiliyor...');

    // AJAX request
    jQuery.post(window.mhmCustomerMessages.ajaxUrl, data, function (response) {
        if (response.success) {
            showNotification('Mesajınız başarıyla gönderildi.', 'success');
            form[0].reset();
            hideNewMessageForm();
            loadMessages(); // Reload messages
        } else {
            showNotification('Mesaj gönderilirken hata oluştu: ' + response.data, 'error');
        }
    }).fail(function () {
        showNotification('Mesaj gönderilirken hata oluştu.', 'error');
    }).always(function () {
        // Re-enable form
        form.find('button[type="submit"]').prop('disabled', false).text('Gönder');
    });
}

/**
 * Send reply
 */
function sendReply() {
    const form = jQuery('#reply-form');
    const messageId = form.data('message-id');
    const message = form.find('#reply-message').val().trim();

    if (message === '') {
        showNotification('Lütfen bir mesaj yazın.', 'warning');
        return;
    }

    // Disable form
    form.find('button[type="submit"]').prop('disabled', true).text('Gönderiliyor...');

    // AJAX request
    jQuery.post(window.mhmCustomerMessages.ajaxUrl, {
        action: 'mhm_send_customer_reply',
        message_id: messageId,
        message: message,
        customer_email: window.mhmCustomerMessages.customerEmail,
        nonce: window.mhmCustomerMessages.nonce
    }, function (response) {
        if (response.success) {
            showNotification('Yanıtınız başarıyla gönderildi.', 'success');
            form[0].reset();
            loadMessageThread(messageId); // Reload thread
        } else {
            showNotification('Yanıt gönderilirken hata oluştu: ' + response.data, 'error');
        }
    }).fail(function () {
        showNotification('Yanıt gönderilirken hata oluştu.', 'error');
    }).always(function () {
        // Re-enable form
        form.find('button[type="submit"]').prop('disabled', false).text('Yanıt Gönder');
    });
}

/**
 * Format date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;

    // Less than 1 minute
    if (diff < 60000) {
        return 'Az önce';
    }

    // Less than 1 hour
    if (diff < 3600000) {
        const minutes = Math.floor(diff / 60000);
        return minutes + ' dakika önce';
    }

    // Less than 1 day
    if (diff < 86400000) {
        const hours = Math.floor(diff / 3600000);
        return hours + ' saat önce';
    }

    // Less than 1 week
    if (diff < 604800000) {
        const days = Math.floor(diff / 86400000);
        return days + ' ' + (window.mhmRentivaCustomerMessages?.strings?.days_ago || 'days ago');
    }

    // More than 1 week - show full date
    return date.toLocaleDateString(window.mhmRentivaCustomerMessages?.locale || 'en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}

/**
 * Show notification
 */
function showNotification(message, type) {
    // Create notification element
    const notification = jQuery('<div class="notification notification-' + type + '">' + message + '</div>');

    // Add styles
    notification.css({
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '12px 20px',
        borderRadius: '4px',
        color: 'white',
        fontWeight: '500',
        zIndex: '9999',
        maxWidth: '400px',
        wordWrap: 'break-word'
    });

    // Set background color based on type
    const colors = {
        'success': '#28a745',
        'error': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8'
    };

    notification.css('backgroundColor', colors[type] || colors.info);

    // Add to page
    jQuery('body').append(notification);

    // Auto dismiss after 5 seconds
    setTimeout(function () {
        notification.fadeOut(function () {
            notification.remove();
        });
    }, 5000);

    // Add click to dismiss
    notification.on('click', function () {
        jQuery(this).fadeOut(function () {
            jQuery(this).remove();
        });
    });
}
