<?php

/**
 * My Account - Account Details Template
 * 
 * @var WP_User $user
 * @var string $phone
 * @var string $address
 * @var array $navigation
 */

if (!defined('ABSPATH')) {
    exit;
}



// Get customer management settings
use MHMRentiva\Admin\Settings\Core\SettingsCore;

$profile_editable = SettingsCore::get('mhm_rentiva_customer_profile_editable', '1');
$password_min_length = SettingsCore::get('mhm_rentiva_customer_password_min_length', 8);
$password_special = SettingsCore::get('mhm_rentiva_customer_password_require_special', '0');

// If profile editing is disabled, show message
if ($profile_editable !== '1') {
    echo '<div class="mhm-rentiva-account-page"><div class="mhm-account-content"><div class="section-header"><h2>' . esc_html__('Account Details', 'mhm-rentiva') . '</h2><p>' . esc_html__('Profile editing is currently disabled.', 'mhm-rentiva') . '</p></div></div></div>';
    return;
}
?>

<div class="mhm-rentiva-account-page">

    <!-- Account Navigation -->
    <?php if (!empty($navigation)): ?>
        <?php echo \MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', ['navigation' => $navigation], true); ?>
    <?php endif; ?>

    <!-- Account Details Content -->
    <div class="mhm-account-content">

        <div class="section-header">
            <h2><?php _e('Account Details', 'mhm-rentiva'); ?></h2>
            <span class="view-all-link"><?php esc_html_e('Update your account information and preferences', 'mhm-rentiva'); ?></span>
        </div>

        <form id="mhm-account-details-form" class="account-details-form" method="post">

            <!-- Personal Information -->
            <div class="form-section">
                <h3><?php esc_html_e('Personal Information', 'mhm-rentiva'); ?></h3>

                <div class="form-row">
                    <div class="form-field">
                        <label for="display_name"><?php esc_html_e('Display Name', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                        <input type="text"
                            id="display_name"
                            name="display_name"
                            value="<?php echo esc_attr($user->display_name); ?>"
                            required>
                    </div>
                </div>

                <div class="form-row form-row-cols-2">
                    <div class="form-field">
                        <label for="first_name"><?php esc_html_e('First Name', 'mhm-rentiva'); ?></label>
                        <input type="text"
                            id="first_name"
                            name="first_name"
                            value="<?php echo esc_attr($user->first_name); ?>">
                    </div>

                    <div class="form-field">
                        <label for="last_name"><?php esc_html_e('Last Name', 'mhm-rentiva'); ?></label>
                        <input type="text"
                            id="last_name"
                            name="last_name"
                            value="<?php echo esc_attr($user->last_name); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="user_email"><?php esc_html_e('Email Address', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                        <input type="email"
                            id="user_email"
                            name="user_email"
                            value="<?php echo esc_attr($user->user_email); ?>"
                            required
                            readonly>
                        <small class="field-help"><?php esc_html_e('Contact support to change your email address.', 'mhm-rentiva'); ?></small>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-section">
                <h3><?php esc_html_e('Contact Information', 'mhm-rentiva'); ?></h3>

                <div class="form-row">
                    <div class="form-field">
                        <label for="phone"><?php esc_html_e('Phone Number', 'mhm-rentiva'); ?></label>
                        <input type="tel"
                            id="phone"
                            name="phone"
                            value="<?php echo esc_attr($phone); ?>"
                            placeholder="+90 (538) 556 41 58">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="address"><?php esc_html_e('Address', 'mhm-rentiva'); ?></label>
                        <textarea id="address"
                            name="address"
                            rows="3"
                            placeholder="<?php esc_attr_e('Your address...', 'mhm-rentiva'); ?>"><?php echo esc_textarea($address); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Password Change -->
            <div class="form-section">
                <h3><?php esc_html_e('Password Change', 'mhm-rentiva'); ?></h3>

                <div class="form-row">
                    <div class="form-field">
                        <label for="current_password"><?php esc_html_e('Current Password', 'mhm-rentiva'); ?></label>
                        <input type="password"
                            id="current_password"
                            name="current_password"
                            autocomplete="off">
                        <small class="field-help"><?php esc_html_e('Leave blank to keep current password', 'mhm-rentiva'); ?></small>
                    </div>
                </div>

                <div class="form-row form-row-cols-2">
                    <div class="form-field">
                        <label for="new_password"><?php esc_html_e('New Password', 'mhm-rentiva'); ?></label>
                        <input type="password"
                            id="new_password"
                            name="new_password"
                            minlength="<?php echo esc_attr($password_min_length); ?>"
                            <?php if ($password_special === '1'): ?>
                            pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{" . esc_attr($password_min_length) . ",}$"
                            title="<?php esc_attr_e('Password must contain at least one lowercase letter, one uppercase letter, one number, and one special character', 'mhm-rentiva'); ?>"
                            <?php endif; ?>
                            autocomplete="new-password">
                        <div class="password-requirements">
                            <small>
                                <?php
                                /* translators: %d: minimum password length. */
                                printf(esc_html__('Minimum %d characters', 'mhm-rentiva'), $password_min_length);
                                ?>
                                <?php if ($password_special === '1'): ?>
                                    <?php esc_html_e('including uppercase, lowercase, number, and special character', 'mhm-rentiva'); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="confirm_password"><?php esc_html_e('Confirm New Password', 'mhm-rentiva'); ?></label>
                        <input type="password"
                            id="confirm_password"
                            name="confirm_password"
                            autocomplete="new-password"
                            autocomplete="new-password">
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?php esc_html_e('Save Changes', 'mhm-rentiva'); ?>
                </button>
                <button type="reset" class="btn btn-secondary">
                    <?php esc_html_e('Cancel', 'mhm-rentiva'); ?>
                </button>
            </div>

            <?php wp_nonce_field('mhm_rentiva_update_account', 'mhm_account_nonce'); ?>

        </form>

        <!-- Messages -->
        <div class="account-messages is-hidden"></div>

    </div><!-- .mhm-account-content -->

</div><!-- .mhm-rentiva-account-page -->