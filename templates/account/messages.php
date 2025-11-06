<?php
/**
 * My Account - Messages Template
 * 
 * @var WP_User $user
 * @var string $customer_email
 * @var string $customer_name
 * @var array $navigation
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../languages/');
    }
    mhm_rentiva_load_textdomain();
}

// Get message categories and priorities
$categories = [];
$priorities = [];
if (class_exists(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::class)) {
    $categories = \MHMRentiva\Admin\Messages\Settings\MessagesSettings::get_categories();
    $priorities = \MHMRentiva\Admin\Messages\Settings\MessagesSettings::get_priorities();
}

// Ensure variables are set (from template data)
$user = $user ?? wp_get_current_user();
$customer_email = $customer_email ?? ($user->user_email ?? '');
$customer_name = $customer_name ?? ($user->display_name ?? $user->user_login ?? '');
$navigation = $navigation ?? [];

// REST API URL - use helper for consistency
$rest_url = \MHMRentiva\Admin\Messages\Core\MessageUrlHelper::get_rest_url();
$rest_nonce = wp_create_nonce('wp_rest');
?>

<div class="mhm-rentiva-account-page">
    
    <!-- Account Navigation -->
    <?php echo \MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', ['navigation' => $navigation], true); ?>
    
    <!-- Messages Content -->
    <div class="mhm-account-content">
        <div class="mhm-messages-section">
            
            <!-- Header -->
            <div class="section-header">
                <h2><?php _e('Messages', 'mhm-rentiva'); ?></h2>
                <button type="button" id="new-message-btn" class="btn btn-primary">
                    <?php _e('New Message', 'mhm-rentiva'); ?>
                </button>
            </div>

            <!-- Messages List -->
            <div id="messages-list" class="messages-list">
                <div class="loading"><?php _e('Loading messages...', 'mhm-rentiva'); ?></div>
            </div>

            <!-- Message Thread View (Hidden by default) -->
            <div id="message-thread" class="message-thread hidden">
                <div class="thread-header">
                    <button type="button" class="back-to-list btn btn-secondary">
                        ← <?php _e('Back to Messages', 'mhm-rentiva'); ?>
                    </button>
                    <h3 id="thread-subject"></h3>
                </div>
                <div id="thread-messages" class="thread-messages"></div>
                <div id="thread-reply" class="thread-reply hidden">
                    <form id="reply-form">
                        <div class="form-group">
                            <label for="reply-message"><?php _e('Your Reply:', 'mhm-rentiva'); ?></label>
                            <textarea id="reply-message" name="message" rows="4" required></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php _e('Send Reply', 'mhm-rentiva'); ?>
                            </button>
                            <button type="button" class="btn btn-secondary cancel-reply">
                                <?php _e('Cancel', 'mhm-rentiva'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- New Message Form (Hidden by default) -->
            <div id="new-message-form" class="new-message-form hidden">
                <div class="form-header">
                    <h4><?php _e('Send New Message', 'mhm-rentiva'); ?></h4>
                    <button type="button" class="close-form">&times;</button>
                </div>
                <form id="send-message-form">
                    <div class="form-group">
                        <label for="message-category"><?php _e('Category:', 'mhm-rentiva'); ?></label>
                        <select id="message-category" name="category" required>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="general"><?php _e('General', 'mhm-rentiva'); ?></option>
                                <option value="support"><?php _e('Support', 'mhm-rentiva'); ?></option>
                                <option value="billing"><?php _e('Billing', 'mhm-rentiva'); ?></option>
                                <option value="technical"><?php _e('Technical', 'mhm-rentiva'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message-subject"><?php _e('Subject:', 'mhm-rentiva'); ?></label>
                        <input type="text" id="message-subject" name="subject" class="regular-text" required>
                    </div>

                    <div class="form-group">
                        <label for="message-content"><?php _e('Your Message:', 'mhm-rentiva'); ?></label>
                        <textarea id="message-content" name="message" rows="6" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="message-priority"><?php _e('Priority:', 'mhm-rentiva'); ?></label>
                        <select id="message-priority" name="priority" required>
                            <?php if (!empty($priorities)): ?>
                                <?php foreach ($priorities as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'normal'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="normal"><?php _e('Normal', 'mhm-rentiva'); ?></option>
                                <option value="high"><?php _e('High', 'mhm-rentiva'); ?></option>
                                <option value="urgent"><?php _e('Urgent', 'mhm-rentiva'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message-booking"><?php _e('Booking Association (Optional):', 'mhm-rentiva'); ?></label>
                        <select id="message-booking" name="booking_id">
                            <option value=""><?php _e('Select booking', 'mhm-rentiva'); ?></option>
                            <!-- Will be populated via AJAX if needed -->
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php _e('Send Message', 'mhm-rentiva'); ?>
                        </button>
                        <button type="button" class="btn btn-secondary close-form">
                            <?php _e('Cancel', 'mhm-rentiva'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Prevent customer-messages.js from running (if loaded)
    if (typeof window.CustomerMessages !== 'undefined') {
        window.CustomerMessages.init = function() {};
    }
    
    // Use REST API directly for My Account messages page
    var restUrl = <?php echo wp_json_encode($rest_url ?: '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var restNonce = <?php echo wp_json_encode($rest_nonce ?: '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var customerEmail = <?php echo wp_json_encode($customer_email ?: '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var customerName = <?php echo wp_json_encode($customer_name ?: '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    
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
        
        init: function() {
            this.loadMessages();
            this.bindEvents();
        },
        
        bindEvents: function() {
            const self = this;
            
            $('#new-message-btn').on('click', function() {
                $('#new-message-form').removeClass('hidden');
                $('#messages-list').addClass('hidden');
                $('#message-thread').addClass('hidden');
                // Load bookings when form opens
                self.loadBookings();
            });
            
            $('.close-form').on('click', function() {
                $('#new-message-form').addClass('hidden');
                $('#messages-list').removeClass('hidden');
            });
            
            $('.back-to-list').on('click', function() {
                $('#message-thread').addClass('hidden');
                $('#messages-list').removeClass('hidden');
            });
            
            $('#send-message-form').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });
            
            $('#reply-form').on('submit', function(e) {
                e.preventDefault();
                self.sendReply();
            });
            
            // Mesaj item'ına tıklama event listener (delegated)
            $(document).on('click', '.message-item', function() {
                const $item = $(this);
                const threadId = $item.data('thread-id');
                const messageId = $item.data('message-id');
                
                if (threadId && messageId) {
                    self.loadThread(threadId, messageId);
                }
            });
            
            // Cancel reply button
            $(document).on('click', '.cancel-reply', function() {
                $('#thread-reply').addClass('hidden');
                $('#reply-message').val('');
            });
            
            // Close thread button
            $(document).on('click', '.close-thread-btn', function() {
                const $btn = $(this);
                const threadId = $btn.data('thread-id');
                
                if (!threadId) {
                    alert(<?php echo wp_json_encode(__('Thread ID not found.', 'mhm-rentiva')); ?>);
                    return;
                }
                
                const confirmMsg = <?php echo wp_json_encode(__('Are you sure you want to close this conversation? You won\'t be able to send more messages.', 'mhm-rentiva')); ?>;
                if (!confirm(confirmMsg)) {
                    return;
                }
                
                const originalText = $btn.text();
                $btn.prop('disabled', true).text(<?php echo wp_json_encode(__('Closing...', 'mhm-rentiva')); ?>);
                
                $.ajax({
                    url: self.restUrl + 'customer/messages/close',
                    method: 'POST',
                    data: {
                        thread_id: threadId
                    },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                    },
                    success: function(response) {
                        if (response && response.success !== false) {
                            const successMsg = response.message || <?php echo wp_json_encode(__('Message closed successfully.', 'mhm-rentiva')); ?>;
                            alert(successMsg);
                            
                            // Reload thread to show closed status
                            self.loadThread(threadId, null);
                            
                            // Reload messages list
                            self.loadMessages();
                        } else {
                            const errorMsg = response.error || <?php echo wp_json_encode(__('Failed to close message.', 'mhm-rentiva')); ?>;
                            alert(errorMsg);
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = <?php echo wp_json_encode(__('Failed to close message.', 'mhm-rentiva')); ?>;
                        
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        
                        alert(errorMsg);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
        },
        
        loadMessages: function() {
            const self = this;
            const $list = $('#messages-list');
            const loadingText = <?php echo wp_json_encode(__('Loading messages...', 'mhm-rentiva')); ?>;
            
            $list.html('<div class="loading">' + loadingText + '</div>');
            
            $.ajax({
                url: this.restUrl + 'customer/messages',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function(response) {
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
                        const noMsgText = <?php echo wp_json_encode(__('No messages found yet.', 'mhm-rentiva')); ?>;
                        $list.html('<div class="no-messages">' + noMsgText + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = <?php echo wp_json_encode(__('Failed to load messages.', 'mhm-rentiva')); ?>;
                    
                    // Try to parse error response
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    } else if (xhr.status === 401) {
                        errorMsg = <?php echo wp_json_encode(__('Please login to access your messages.', 'mhm-rentiva')); ?>;
                    } else if (xhr.status === 403) {
                        errorMsg = <?php echo wp_json_encode(__('You do not have permission to access messages.', 'mhm-rentiva')); ?>;
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
        
        renderMessages: function(messages) {
            const $list = $('#messages-list');
            
            if (messages.length === 0) {
                const noMsgText = <?php echo wp_json_encode(__('No messages found yet.', 'mhm-rentiva')); ?>;
                $list.html('<div class="no-messages">' + noMsgText + '</div>');
                return;
            }
            
            let html = '<div class="messages-grid">';
            messages.forEach(function(message) {
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
                    html += '<span class="status-badge-new"><?php echo esc_js(__('New', 'mhm-rentiva')); ?></span>';
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
        
        loadThread: function(threadId, messageId) {
            const self = this;
            const $thread = $('#message-thread');
            const $threadMessages = $('#thread-messages');
            const loadingText = <?php echo wp_json_encode(__('Loading thread...', 'mhm-rentiva')); ?>;
            
            // Hide list and show thread
            $('#messages-list').addClass('hidden');
            $('#new-message-form').addClass('hidden');
            $thread.removeClass('hidden');
            $threadMessages.html('<div class="loading">' + loadingText + '</div>');
            
            $.ajax({
                url: this.restUrl + 'customer/messages/thread/' + threadId,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function(response) {
                    if (response && response.messages) {
                        // Add thread_id to response (if not exists)
                        if (!response.thread_id && threadId) {
                            response.thread_id = threadId;
                        }
                        self.renderThread(response, threadId);
                    } else {
                        const errorMsg = <?php echo wp_json_encode(__('Failed to load thread.', 'mhm-rentiva')); ?>;
                        $threadMessages.html('<div class="error">' + errorMsg + '</div>');
                    }
                },
                error: function(xhr) {
                    let errorMsg = <?php echo wp_json_encode(__('Failed to load thread.', 'mhm-rentiva')); ?>;
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    
                    $threadMessages.html('<div class="error">' + errorMsg + '</div>');
                }
            });
        },
        
        renderThread: function(threadData, threadId) {
            const $threadMessages = $('#thread-messages');
            const $threadSubject = $('#thread-subject');
            const $threadReply = $('#thread-reply');
            
            // Set subject
            if (threadData.subject) {
                $threadSubject.text(threadData.subject);
            }
            
            // Render messages
            if (!threadData.messages || threadData.messages.length === 0) {
                $threadMessages.html('<div class="no-messages"><?php echo esc_js(__('No messages found.', 'mhm-rentiva')); ?></div>');
                return;
            }
            
            let html = '<div class="thread-messages-list">';
            threadData.messages.forEach(function(message) {
                const isCustomer = message.message_type === 'customer_to_admin';
                const messageClass = isCustomer ? 'customer-message' : 'admin-message';
                const authorName = isCustomer ? (message.customer_name || '<?php echo esc_js(__('Customer', 'mhm-rentiva')); ?>') : (message.admin_name || '<?php echo esc_js(__('Administrator', 'mhm-rentiva')); ?>');
                
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
                    const closeBtn = $('<button type="button" class="btn btn-secondary close-thread-btn" style="margin-top: 15px;"><?php echo esc_js(__('Close Message', 'mhm-rentiva')); ?></button>');
                    closeBtn.data('thread-id', finalThreadId);
                    $threadReply.after(closeBtn);
                }
            } else {
                $threadReply.addClass('hidden');
                // Thread is closed - show message
                if (threadData.status === 'closed') {
                    const closedMsg = $('<div class="thread-closed-notice" style="padding: 15px; background: #f0f0f0; border-radius: 6px; margin-top: 15px; text-align: center; color: #666;"><?php echo esc_js(__('This conversation is closed.', 'mhm-rentiva')); ?></div>');
                    $threadMessages.after(closedMsg);
                }
            }
        },
        
        sendMessage: function() {
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
                const errorMsg = <?php echo wp_json_encode(__('Please fill in all required fields.', 'mhm-rentiva')); ?>;
                alert(errorMsg);
                return;
            }
            
            const sendingText = <?php echo wp_json_encode(__('Sending...', 'mhm-rentiva')); ?>;
            $submitBtn.prop('disabled', true).text(sendingText);
            
            $.ajax({
                url: this.restUrl + 'customer/messages',
                method: 'POST',
                data: formData,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function(response) {
                    const successMsg = <?php echo wp_json_encode(__('Message sent successfully.', 'mhm-rentiva')); ?>;
                    const errorMsg = <?php echo wp_json_encode(__('Message could not be sent.', 'mhm-rentiva')); ?>;
                    
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
                error: function(xhr) {
                    const errorMsg = <?php echo wp_json_encode(__('An error occurred. Please try again.', 'mhm-rentiva')); ?>;
                    alert(errorMsg);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        sendReply: function() {
            const self = this;
            const $form = $('#reply-form');
            const $replyMessage = $('#reply-message');
            const message = $replyMessage.val().trim();
            const threadId = $form.data('thread-id');
            
            if (!message) {
                const errorMsg = <?php echo wp_json_encode(__('Please enter your reply.', 'mhm-rentiva')); ?>;
                alert(errorMsg);
                return;
            }
            
            if (!threadId) {
                const errorMsg = <?php echo wp_json_encode(__('Thread ID not found.', 'mhm-rentiva')); ?>;
                alert(errorMsg);
                return;
            }
            
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            const sendingText = <?php echo wp_json_encode(__('Sending...', 'mhm-rentiva')); ?>;
            
            $submitBtn.prop('disabled', true).text(sendingText);
            
            $.ajax({
                url: this.restUrl + 'customer/messages/reply',
                method: 'POST',
                data: {
                    thread_id: threadId,
                    message: message
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function(response) {
                    if (response && response.success !== false) {
                        const successMsg = <?php echo wp_json_encode(__('Reply sent successfully.', 'mhm-rentiva')); ?>;
                        alert(successMsg);
                        
                        // Clear form
                        $replyMessage.val('');
                        
                        // Reload thread to show new reply
                        self.loadThread(threadId, null);
                    } else {
                        const errorMsg = response.error || <?php echo wp_json_encode(__('Failed to send reply.', 'mhm-rentiva')); ?>;
                        alert(errorMsg);
                    }
                },
                error: function(xhr) {
                    let errorMsg = <?php echo wp_json_encode(__('Failed to send reply.', 'mhm-rentiva')); ?>;
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    
                    alert(errorMsg);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        loadBookings: function() {
            const self = this;
            const $bookingSelect = $('#message-booking');
            
            // Don't reload if already populated
            if ($bookingSelect.find('option').length > 1) {
                return;
            }
            
            $.ajax({
                url: this.restUrl + 'customer/bookings',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.restNonce);
                },
                success: function(response) {
                    if (response && response.bookings && response.bookings.length > 0) {
                        // Clear existing options except the first one
                        $bookingSelect.find('option:not(:first)').remove();
                        
                        // Add booking options
                        response.bookings.forEach(function(booking) {
                            $bookingSelect.append(
                                $('<option></option>')
                                    .attr('value', booking.id)
                                    .text(booking.label)
                            );
                        });
                    }
                },
                error: function(xhr) {
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
</script>

