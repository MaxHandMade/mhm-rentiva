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

$wrapper_class = 'mhm-rentiva-account-page';
if (empty($navigation)) {
    $wrapper_class .= ' mhm-integrated';
}
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">

    <!-- Account Navigation -->
    <?php if (!empty($navigation)): ?>
        <?php echo \MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', ['navigation' => $navigation], true); ?>
    <?php endif; ?>

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