<?php
/**
 * Contact Form Template
 * 
 * @var array $args Template data
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../languages/');
    }
    mhm_rentiva_load_textdomain();
}

// Get template data
$atts = $args['atts'] ?? [];
$form_config = $args['form_config'] ?? [];
$vehicles = $args['vehicles'] ?? [];
$priorities = $args['priorities'] ?? [];
$email_recipients = $args['email_recipients'] ?? [];
$current_user = $args['current_user'] ?? null;

// Shortcode parameters
$type = $atts['type'] ?? 'general';
$title = !empty($atts['title']) ? $atts['title'] : ($form_config['title'] ?? esc_html__('Contact Form', 'mhm-rentiva'));
$description = !empty($atts['description']) ? $atts['description'] : ($form_config['description'] ?? esc_html__('Get in touch with us.', 'mhm-rentiva'));
$show_phone = $atts['show_phone'] ?? '1';
$show_company = $atts['show_company'] ?? '0';
$show_vehicle_selector = $atts['show_vehicle_selector'] ?? '0';
$show_priority = $atts['show_priority'] ?? '0';
$show_attachment = $atts['show_attachment'] ?? '1';
$show_captcha = $atts['show_captcha'] ?? '1';
$theme = $atts['theme'] ?? 'default';
$class = $atts['class'] ?? '';

// Simple math question for CAPTCHA
$captcha_num1 = rand(1, 10);
$captcha_num2 = rand(1, 10);
$captcha_answer = $captcha_num1 + $captcha_num2;

// Store CAPTCHA answer using transient API (WordPress standard)
$captcha_key = 'mhm_captcha_' . wp_generate_password(12, false);
set_transient($captcha_key, $captcha_answer, 10 * MINUTE_IN_SECONDS);
?>

<div class="rv-contact-form rv-theme-<?php echo esc_attr($theme); ?> <?php echo esc_attr($class); ?>" 
     data-form-type="<?php echo esc_attr($type); ?>">
    
    <div class="rv-contact-header">
        <h3 class="rv-contact-title">
            <span class="dashicons <?php echo esc_attr($form_config['icon'] ?? 'dashicons-email-alt'); ?>"></span>
            <?php echo esc_html($title); ?>
        </h3>
        <p class="rv-contact-description">
            <?php echo esc_html($description); ?>
        </p>
    </div>

    <div class="rv-contact-form-container">
        <form id="rv-contact-form" class="rv-form" enctype="multipart/form-data">
            <?php wp_nonce_field('mhm_rentiva_contact_nonce', 'rv_contact_nonce'); ?>
            
            <input type="hidden" name="type" value="<?php echo esc_attr($type); ?>">
            <input type="hidden" name="auto_reply" value="<?php echo esc_attr($atts['auto_reply'] ?? '1'); ?>">

            <div class="rv-form-row">
                <div class="rv-form-group">
                    <label for="rv-contact-name" class="rv-form-label">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php echo esc_html__('Full Name', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
                    </label>
                    <input type="text" id="rv-contact-name" name="name" class="rv-form-input" 
                           value="<?php echo esc_attr($current_user->display_name ?? ''); ?>" required>
                </div>

                <div class="rv-form-group">
                    <label for="rv-contact-email" class="rv-form-label">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php echo esc_html__('Email', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
                    </label>
                    <input type="email" id="rv-contact-email" name="email" class="rv-form-input" 
                           value="<?php echo esc_attr($current_user->user_email ?? ''); ?>" required>
                </div>
            </div>

            <?php if ($show_phone === '1') : ?>
                <div class="rv-form-row">
                    <div class="rv-form-group">
                        <label for="rv-contact-phone" class="rv-form-label">
                            <span class="dashicons dashicons-phone"></span>
                            <?php echo esc_html__('Phone', 'mhm-rentiva'); ?>
                            <?php if ($type === 'booking') : ?><span class="rv-required">*</span><?php endif; ?>
                        </label>
                        <input type="tel" id="rv-contact-phone" name="phone" class="rv-form-input" 
                               placeholder="+90 538 556 41 58">
                    </div>

                    <?php if ($show_company === '1') : ?>
                        <div class="rv-form-group">
                            <label for="rv-contact-company" class="rv-form-label">
                                <span class="dashicons dashicons-building"></span>
                                <?php echo esc_html__('Company', 'mhm-rentiva'); ?>
                            </label>
                            <input type="text" id="rv-contact-company" name="company" class="rv-form-input" 
                                   placeholder="<?php echo esc_attr__('Company name', 'mhm-rentiva'); ?>">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($show_vehicle_selector === '1' && !empty($vehicles)) : ?>
                <div class="rv-form-group">
                    <label for="rv-contact-vehicle" class="rv-form-label">
                        <span class="dashicons dashicons-car"></span>
                        <?php echo esc_html__('Vehicle', 'mhm-rentiva'); ?>
                    </label>
                    <select id="rv-contact-vehicle" name="vehicle_id" class="rv-form-select">
                        <option value=""><?php echo esc_html__('Select vehicle...', 'mhm-rentiva'); ?></option>
                        <?php foreach ($vehicles as $vehicle) : ?>
                            <option value="<?php echo esc_attr($vehicle['id']); ?>">
                                <?php echo esc_html($vehicle['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($type === 'booking') : ?>
                <div class="rv-form-group">
                    <label for="rv-contact-preferred-date" class="rv-form-label">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo esc_html__('Preferred Date', 'mhm-rentiva'); ?>
                    </label>
                    <input type="date" id="rv-contact-preferred-date" name="preferred_date" class="rv-form-input">
                </div>
            <?php endif; ?>

            <?php if ($show_priority === '1' && !empty($priorities)) : ?>
                <div class="rv-form-group">
                    <label for="rv-contact-priority" class="rv-form-label">
                        <span class="dashicons dashicons-flag"></span>
                        <?php echo esc_html__('Priority', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
                    </label>
                    <select id="rv-contact-priority" name="priority" class="rv-form-select" required>
                        <option value=""><?php echo esc_html__('Select priority...', 'mhm-rentiva'); ?></option>
                        <?php foreach ($priorities as $key => $priority) : ?>
                            <option value="<?php echo esc_attr($key); ?>" 
                                    data-color="<?php echo esc_attr($priority['color']); ?>">
                                <?php echo esc_html($priority['label']); ?> - <?php echo esc_html($priority['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($type === 'feedback') : ?>
                <div class="rv-form-group">
                    <label class="rv-form-label">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php echo esc_html__('Rating', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
                    </label>
                    <div class="rv-rating-container">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <label class="rv-rating-star">
                                <input type="radio" name="rating" value="<?php echo $i; ?>" required>
                                <span class="dashicons dashicons-star-filled"></span>
                            </label>
                        <?php endfor; ?>
                        <span class="rv-rating-label"><?php echo esc_html__('Rate us', 'mhm-rentiva'); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="rv-form-group">
                <label for="rv-contact-message" class="rv-form-label">
                    <span class="dashicons dashicons-edit"></span>
                    <?php echo esc_html__('Message', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
                </label>
                <textarea id="rv-contact-message" name="message" class="rv-form-textarea" rows="6" 
                          placeholder="<?php echo esc_attr__('Write your message here...', 'mhm-rentiva'); ?>" required></textarea>
            </div>

            <?php if ($show_attachment === '1') : ?>
                <div class="rv-form-group">
                    <label for="rv-contact-attachment" class="rv-form-label">
                        <span class="dashicons dashicons-paperclip"></span>
                        <?php echo esc_html__('Attachment', 'mhm-rentiva'); ?>
                    </label>
                    <div class="rv-file-upload-container">
                        <input type="file" id="rv-contact-attachment" name="attachment" class="rv-file-input" 
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                        <label for="rv-contact-attachment" class="rv-file-upload-label">
                            <span class="dashicons dashicons-cloud-upload"></span>
                            <?php echo esc_html__('Choose File', 'mhm-rentiva'); ?>
                        </label>
                        <div class="rv-file-info" style="display: none;">
                            <span class="rv-file-name"></span>
                            <button type="button" class="rv-file-remove">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>
                    <small class="rv-file-help">
                        <?php echo esc_html__('Supported formats: JPG, PNG, GIF, PDF, DOC, DOCX (Max: ', 'mhm-rentiva'); ?>
                        <?php echo esc_html(size_format(wp_max_upload_size())); ?>)
                    </small>
                </div>
            <?php endif; ?>

            <?php if ($show_captcha === '1') : ?>
                <div class="rv-form-group">
                    <label for="rv-contact-captcha" class="rv-form-label">
                        <span class="dashicons dashicons-shield"></span>
                        <?php echo esc_html__('Security Code', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
                    </label>
                    <div class="rv-captcha-container">
                        <div class="rv-captcha-question">
                            <?php echo esc_html($captcha_num1); ?> + <?php echo esc_html($captcha_num2); ?> = ?
                        </div>
                        <input type="hidden" name="captcha_key" value="<?php echo esc_attr($captcha_key); ?>">
                        <input type="number" id="rv-contact-captcha" name="captcha" class="rv-form-input rv-captcha-input" 
                               placeholder="<?php echo esc_attr__('Result', 'mhm-rentiva'); ?>" required>
                    </div>
                </div>
            <?php endif; ?>

            <div class="rv-form-actions">
                <button type="submit" class="rv-button rv-button-primary rv-submit-button">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php echo esc_html__('Send Message', 'mhm-rentiva'); ?>
                </button>
                <button type="button" class="rv-button rv-button-secondary rv-reset-button">
                    <span class="dashicons dashicons-image-rotate"></span>
                    <?php echo esc_html__('Reset', 'mhm-rentiva'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Loading State -->
    <div class="rv-contact-loading" style="display: none;">
        <div class="rv-loading-spinner"></div>
        <p><?php echo esc_html__('Sending...', 'mhm-rentiva'); ?></p>
    </div>

    <!-- Success Message -->
    <div class="rv-contact-success" style="display: none;">
        <div class="rv-success-content">
            <span class="dashicons dashicons-yes-alt"></span>
            <h4><?php echo esc_html__('Your Message Has Been Sent!', 'mhm-rentiva'); ?></h4>
            <p class="rv-success-message"></p>
            <div class="rv-success-actions">
                <button type="button" class="rv-button rv-button-primary rv-new-message-button">
                    <?php echo esc_html__('Send New Message', 'mhm-rentiva'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    <div class="rv-contact-error" style="display: none;">
        <div class="rv-error-content">
            <span class="dashicons dashicons-warning"></span>
            <h4><?php echo esc_html__('Error Occurred!', 'mhm-rentiva'); ?></h4>
            <p class="rv-error-message"></p>
            <ul class="rv-error-list"></ul>
        </div>
    </div>

    <!-- Contact Info -->
    <div class="rv-contact-info">
        <h4><?php echo esc_html__('Contact Information', 'mhm-rentiva'); ?></h4>
        <div class="rv-contact-details">
            <div class="rv-contact-item">
                <span class="dashicons dashicons-email-alt"></span>
                <span><?php echo esc_html($email_recipients[0] ?? get_option('admin_email')); ?></span>
            </div>
            <div class="rv-contact-item">
                <span class="dashicons dashicons-phone"></span>
                <span><?php echo esc_html(get_option('mhm_rentiva_phone', '+90 538 556 41 58')); ?></span>
            </div>
            <div class="rv-contact-item">
                <span class="dashicons dashicons-clock"></span>
                <span><?php echo esc_html__('24/7 Support', 'mhm-rentiva'); ?></span>
            </div>
        </div>
    </div>
</div>

<?php
// ⭐ JavaScript localization removed - ContactForm Controller handles asset loading
// Assets are enqueued via ContactForm::enqueue_assets() method
// Localized data is provided via ContactForm::get_localized_strings() method
?>
