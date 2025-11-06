<?php
/**
 * Customer reply email template
 * 
 * @var WP_Post $message
 * @var array $meta
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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php esc_html_e('Support Reply', 'mhm-rentiva'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2271b1; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .message-content { background: white; padding: 15px; margin: 15px 0; border-radius: 4px; border: 1px solid #ddd; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .button { display: inline-block; background: #2271b1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('Support Reply', 'mhm-rentiva'); ?></h1>
        </div>

        <div class="content">
            <p><?php esc_html_e('Hello', 'mhm-rentiva'); ?> <?php echo esc_html($meta['customer_name']); ?>,</p>

            <p><?php esc_html_e('You have received a reply to your message:', 'mhm-rentiva'); ?></p>

            <div class="message-content">
                <h4><?php echo esc_html($message->post_title); ?></h4>
                <?php echo wp_kses_post($message->post_content ?? ''); ?>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="<?php 
                $base_url = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();
                $messages_url = add_query_arg('endpoint', 'messages', $base_url);
                echo esc_url($messages_url); 
                ?>" class="button">
                    <?php esc_html_e('View My Messages', 'mhm-rentiva'); ?>
                </a>
            </div>

            <p><?php esc_html_e('This reply was sent automatically. Please do not reply directly.', 'mhm-rentiva'); ?></p>
        </div>

        <div class="footer">
            <p><?php esc_html_e('This message was sent by the MHM Rentiva messaging system.', 'mhm-rentiva'); ?></p>
            <p><?php echo esc_html(get_bloginfo('name')); ?> - <?php echo esc_html(get_bloginfo('url')); ?></p>
        </div>
    </div>
</body>
</html>
