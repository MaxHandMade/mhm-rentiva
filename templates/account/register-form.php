<?php
/**
 * Register Form Template
 * 
 * Variables:
 * - string $redirect - Redirect URL after registration
 * - bool   $show_login_link - Show login link
 * - string $login_url - Login page URL
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

// Get customer management settings
use MHMRentiva\Admin\Settings\Core\SettingsCore;

$registration_enabled = SettingsCore::get('mhm_rentiva_customer_registration_enabled', '1');
$phone_required = SettingsCore::get('mhm_rentiva_customer_phone_required', '0');
$terms_required = SettingsCore::get('mhm_rentiva_customer_terms_required', '0');
$terms_text = SettingsCore::get('mhm_rentiva_customer_terms_text', __('I accept the terms of use and privacy policy.', 'mhm-rentiva'));

// ⭐ If the saved text is the default English text, use the translated version
if ($terms_text === 'I accept the terms of use and privacy policy.') {
    $terms_text = __('I accept the terms of use and privacy policy.', 'mhm-rentiva');
}

$password_min_length = SettingsCore::get('mhm_rentiva_customer_password_min_length', 8);
$password_special = SettingsCore::get('mhm_rentiva_customer_password_require_special', '0');

// If registration is disabled, show message
if ($registration_enabled !== '1') {
    echo '<div class="mhm-rentiva-register-page"><div class="register-container"><div class="register-header"><h2>' . esc_html__('Registration Disabled', 'mhm-rentiva') . '</h2><p>' . esc_html__('Customer registration is currently disabled.', 'mhm-rentiva') . '</p></div></div></div>';
    return;
}

$redirect = esc_url($redirect ?? '/');
$show_login_link = $show_login_link ?? true;
$login_url = esc_url(SettingsCore::get('mhm_rentiva_login_url', $login_url ?? wp_login_url()));
?>

<div class="mhm-rentiva-register-page">
    
    <div class="register-container">
        
        <div class="register-header">
            <h2><?php esc_html_e('Create Account', 'mhm-rentiva'); ?></h2>
            <p><?php esc_html_e('Fill out the form below to create a new account.', 'mhm-rentiva'); ?></p>
        </div>
        
        <div class="register-form-wrapper">

    <form id="mhm-rentiva-register-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
        <?php wp_nonce_field('register_user', 'register_nonce'); ?>
        <input type="hidden" name="action" value="mhm_rentiva_register">
        
        <div class="rv-form-row rv-form-row-two">
            <div class="rv-form-group">
                <label for="rv_first_name" class="rv-form-label">
                    <?php esc_html_e('First Name', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
                </label>
                <input 
                    type="text" 
                    id="rv_first_name" 
                    name="first_name" 
                    class="rv-form-input" 
                    required
                    placeholder="<?php esc_attr_e('Enter your first name', 'mhm-rentiva'); ?>"
                    autocomplete="given-name"
                >
            </div>
            
            <div class="rv-form-group">
                <label for="rv_last_name" class="rv-form-label">
                    <?php esc_html_e('Last Name', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
                </label>
                <input 
                    type="text" 
                    id="rv_last_name" 
                    name="last_name" 
                    class="rv-form-input" 
                    required
                    placeholder="<?php esc_attr_e('Enter your last name', 'mhm-rentiva'); ?>"
                    autocomplete="family-name"
                >
            </div>
        </div>

        <div class="rv-form-group">
            <label for="rv_username" class="rv-form-label">
                <?php esc_html_e('Username', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
            </label>
            <input 
                type="text" 
                id="rv_username" 
                name="user_login" 
                class="rv-form-input" 
                required
                placeholder="<?php esc_attr_e('Choose a username', 'mhm-rentiva'); ?>"
                autocomplete="username"
            >
        </div>

        <div class="rv-form-group">
            <label for="rv_email" class="rv-form-label">
                <?php esc_html_e('Email Address', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
            </label>
            <input 
                type="email" 
                id="rv_email" 
                name="user_email" 
                class="rv-form-input" 
                required
                placeholder="<?php esc_attr_e('example@email.com', 'mhm-rentiva'); ?>"
                autocomplete="email"
            >
        </div>

        <div class="rv-form-group">
            <label for="rv_phone" class="rv-form-label">
                <?php esc_html_e('Phone Number', 'mhm-rentiva'); ?>
                <?php if ($phone_required === '1'): ?><span class="rv-required">*</span><?php endif; ?>
            </label>
            <input 
                type="tel" 
                id="rv_phone" 
                name="phone" 
                class="rv-form-input" 
                <?php if ($phone_required === '1'): ?>required<?php endif; ?>
                placeholder="<?php esc_attr_e('+90 (538) 556 41 58', 'mhm-rentiva'); ?>"
                autocomplete="tel"
            >
        </div>

        <div class="rv-form-group">
            <label for="rv_password" class="rv-form-label">
                <?php esc_html_e('Password', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
            </label>
            <input 
                type="password" 
                id="rv_password" 
                name="pass1" 
                class="rv-form-input" 
                required
                placeholder="<?php esc_attr_e('Choose a strong password', 'mhm-rentiva'); ?>"
                minlength="<?php echo esc_attr($password_min_length); ?>"
                autocomplete="new-password"
                <?php if ($password_special === '1'): ?>
                pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{" . esc_attr($password_min_length) . ",}$"
                title="<?php esc_attr_e('Password must contain at least one lowercase letter, one uppercase letter, one number, and one special character', 'mhm-rentiva'); ?>"
                <?php endif; ?>
            >
            <div class="password-requirements">
                <small>
                    <?php printf(esc_html__('Minimum %d characters', 'mhm-rentiva'), $password_min_length); ?>
                    <?php if ($password_special === '1'): ?>
                        <?php esc_html_e('including uppercase, lowercase, number, and special character', 'mhm-rentiva'); ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>

        <div class="rv-form-group">
            <label for="rv_password_confirm" class="rv-form-label">
                <?php esc_html_e('Confirm Password', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
            </label>
            <input 
                type="password" 
                id="rv_password_confirm" 
                name="pass2" 
                class="rv-form-input" 
                required
                placeholder="<?php esc_attr_e('Re-enter your password', 'mhm-rentiva'); ?>"
                autocomplete="new-password"
                autocomplete="new-password"
            >
        </div>

        <?php if ($terms_required === '1'): ?>
        <div class="rv-form-group rv-form-group-checkbox">
            <label>
                <input type="checkbox" name="terms_accepted" required>
                <?php 
                // Replace {privacy_policy} with actual link
                $display_text = str_replace(
                    '{privacy_policy}', 
                    '<a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank">' . esc_html__('Privacy Policy', 'mhm-rentiva') . '</a>',
                    esc_html($terms_text)
                );
                echo $display_text;
                ?>
            </label>
        </div>
        <?php endif; ?>

        <?php 
        // Data consent checkbox (GDPR compliance)
        $data_consent_required = SettingsCore::get('mhm_rentiva_customer_data_consent', '0');
        if ($data_consent_required === '1'): 
        ?>
        <div class="rv-form-group rv-form-group-checkbox">
            <label>
                <input type="checkbox" name="data_consent" value="1" required>
                <?php esc_html_e('I consent to the processing of my personal data for the purpose of providing rental services and communication.', 'mhm-rentiva'); ?>
            </label>
        </div>
        <?php endif; ?>

        <div class="rv-form-group rv-form-group-checkbox">
            <label>
                <input type="checkbox" name="newsletter" value="1">
                <?php esc_html_e('I want to be informed about campaigns and opportunities.', 'mhm-rentiva'); ?>
            </label>
        </div>

        <div class="rv-form-actions">
            <input type="submit" value="<?php esc_attr_e('Create Account', 'mhm-rentiva'); ?>" class="rv-btn rv-btn-primary rv-btn-full">
        </div>

        <?php if ($show_login_link): ?>
        <div class="register-footer">
            <p>
                <?php esc_html_e('Already have an account?', 'mhm-rentiva'); ?> 
                <a href="<?php echo esc_url($login_url); ?>">
                    <?php esc_html_e('Sign in', 'mhm-rentiva'); ?>
                </a>
            </p>
        </div>
        <?php endif; ?>
        </div>
    </form>
    </div>
</div>


