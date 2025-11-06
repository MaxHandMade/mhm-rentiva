<?php
/**
 * My Account - Login Form Template
 * 
 * @var string $redirect
 * @var bool $show_register_link
 * @var string $register_url
 * @var string $lost_password_url
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
?>

<div class="mhm-rentiva-login-page">
    
    <div class="login-container">
        
        <div class="login-header">
            <h2><?php esc_html_e('Login to Your Account', 'mhm-rentiva'); ?></h2>
            <p><?php esc_html_e('Access your bookings and manage your reservations', 'mhm-rentiva'); ?></p>
        </div>
        
        <div class="login-form-wrapper">
            <?php
            // WordPress standard login form
            $login_args = [
                'echo' => true,
                'redirect' => !empty($redirect) ? esc_url($redirect) : esc_url(\MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url()),
                'form_id' => 'mhm-rentiva-login-form',
                'label_username' => esc_html__('Email or Username', 'mhm-rentiva'),
                'label_password' => esc_html__('Password', 'mhm-rentiva'),
                'label_remember' => esc_html__('Remember Me', 'mhm-rentiva'),
                'label_log_in' => esc_html__('Login', 'mhm-rentiva'),
                'id_username' => 'user_login',
                'id_password' => 'user_pass',
                'id_remember' => 'rememberme',
                'id_submit' => 'wp-submit',
                'remember' => true,
                'value_username' => '',
                'value_remember' => false,
            ];
            
            ob_start();
            wp_login_form($login_args);
            $login_form = ob_get_clean();
            
            // Customize form
            echo $login_form;
            ?>
        </div>
        
        <div class="login-footer">
            <div class="login-links">
                <a href="<?php echo esc_url($lost_password_url); ?>" class="lost-password-link">
                    <?php esc_html_e('Lost your password?', 'mhm-rentiva'); ?>
                </a>
                
                <?php if ($show_register_link && get_option('users_can_register')): ?>
                    <span class="separator">|</span>
                    <a href="<?php echo esc_url($register_url); ?>" class="register-link">
                        <?php esc_html_e('Create an account', 'mhm-rentiva'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($show_register_link && get_option('users_can_register')): ?>
        <div class="register-promo">
            <div class="promo-content">
                <h3><?php esc_html_e('New Customer?', 'mhm-rentiva'); ?></h3>
                <p><?php esc_html_e('Create an account to manage your bookings, save favorite vehicles, and enjoy faster checkouts.', 'mhm-rentiva'); ?></p>
                <a href="<?php echo esc_url($register_url); ?>" class="btn btn-outline">
                    <?php esc_html_e('Create Account', 'mhm-rentiva'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
    </div><!-- .login-container -->
    
</div><!-- .mhm-rentiva-login-page -->

