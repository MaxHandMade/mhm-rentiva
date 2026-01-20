<?php

/**
 * Customer status change email template
 * 
 * @var WP_Post $message
 * @var array $meta
 * @var string $old_status_label
 * @var string $new_status_label
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain()
    {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../languages/');
    }
    mhm_rentiva_load_textdomain();
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?php esc_html_e('Message Status Updated', 'mhm-rentiva'); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #2271b1;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 20px;
            background: #f9f9f9;
        }

        .status-change {
            background: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #666;
        }

        .button {
            display: inline-block;
            background: #2271b1;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('Message Status Updated', 'mhm-rentiva'); ?></h1>
        </div>

        <div class="content">
            <p><?php esc_html_e('Hello', 'mhm-rentiva'); ?> <?php echo esc_html($meta['customer_name']); ?>,</p>

            <p><?php esc_html_e('The status of your message has been updated:', 'mhm-rentiva'); ?></p>

            <div class="status-change">
                <h4><?php echo esc_html($message->post_title); ?></h4>
                <p><strong><?php esc_html_e('Previous Status:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($old_status_label); ?></p>
                <p><strong><?php esc_html_e('New Status:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($new_status_label); ?></p>
                <p><strong><?php esc_html_e('Update Date:', 'mhm-rentiva'); ?></strong> <?php echo esc_html(current_time('d.m.Y H:i')); ?></p>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <?php
                // ✅ Use WooCommerce native approach instead of ShortcodeUrlManager
                $account_url = function_exists('wc_get_page_permalink')
                    ? wc_get_page_permalink('myaccount')
                    : home_url('/my-account/');
                ?>
                <a href="<?php echo esc_url($account_url . '?endpoint=messages'); ?>" class="button">
                    <?php esc_html_e('View My Messages', 'mhm-rentiva'); ?>
                </a>
            </div>
        </div>

        <div class="footer">
            <p><?php esc_html_e('This message was sent by the MHM Rentiva messaging system.', 'mhm-rentiva'); ?></p>
            <p><?php echo esc_html(get_bloginfo('name')); ?> - <?php echo esc_html(get_bloginfo('url')); ?></p>
        </div>
    </div>
</body>

</html>