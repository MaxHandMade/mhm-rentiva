<?php

/**
 * Admin message email template
 * 
 * @var WP_Post $message
 * @var array $meta
 */

if (!defined('ABSPATH')) {
    exit;
}


?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?php esc_html_e('New Customer Message', 'mhm-rentiva'); ?></title>
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

        .message-details {
            background: white;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #2271b1;
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
            <h1><?php esc_html_e('New Customer Message', 'mhm-rentiva'); ?></h1>
        </div>

        <div class="content">
            <div class="message-details">
                <h3><?php echo esc_html($message->post_title); ?></h3>
                <p><strong><?php esc_html_e('Customer:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($meta['customer_name']); ?> (<?php echo esc_html($meta['customer_email']); ?>)</p>
                <p><strong><?php esc_html_e('Category:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($category_label); ?></p>
                <p><strong><?php esc_html_e('Date:', 'mhm-rentiva'); ?></strong> <?php echo esc_html(get_the_date('d.m.Y H:i', $message->ID)); ?></p>

                <?php if ($meta['booking_id']): ?>
                    <p><strong><?php esc_html_e('Related Booking:', 'mhm-rentiva'); ?></strong>
                        <a href="<?php echo esc_url(\MHMRentiva\Admin\Messages\Core\MessageUrlHelper::get_booking_edit_url((int) $meta['booking_id'])); ?>">
                            #<?php echo esc_html($meta['booking_id']); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>

            <div class="message-content">
                <h4><?php esc_html_e('Message Content:', 'mhm-rentiva'); ?></h4>
                <div style="background: white; padding: 15px; border-radius: 4px; border: 1px solid #ddd;">
                    <?php echo wp_kses_post($message->post_content ?? ''); ?>
                </div>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="<?php echo esc_url(\MHMRentiva\Admin\Messages\Core\MessageUrlHelper::get_message_view_url($message->ID)); ?>" class="button">
                    <?php esc_html_e('View and Reply to Message', 'mhm-rentiva'); ?>
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