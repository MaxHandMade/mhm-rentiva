<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        /* translators: %s: site name. */
        echo esc_html(sprintf(__('Welcome to %s', 'mhm-rentiva'), (string) ($data['site']['name'] ?? get_bloginfo('name'))));
        ?>
    </title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
        .cta-button { display: inline-block; background: #2196F3; color: white; padding: 12px 22px; text-decoration: none; border-radius: 6px; margin: 10px 0; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('Welcome!', 'mhm-rentiva'); ?></h1>
            <p style="margin-top: 15px; font-size: 14px; opacity: 0.9;"><?php echo esc_html($data['site']['name'] ?? get_bloginfo('name')); ?></p>
        </div>

        <div class="content">
            <p>
                <?php
                /* translators: %s: customer name. */
                echo esc_html(sprintf(__('Hello %s, thanks for joining us!', 'mhm-rentiva'), (string) ($data['customer']['name'] ?? '')));
                ?>
            </p>
            <p><?php esc_html_e('You can access your account anytime using the button below:', 'mhm-rentiva'); ?></p>
            <?php $account_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_my_account'); ?>
            <p style="text-align:center"><a class="cta-button" href="<?php echo esc_url($account_url); ?>"><?php esc_html_e('My Account', 'mhm-rentiva'); ?></a></p>
        </div>

        <div class="footer">
            <p><strong><?php echo esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_brand_name', get_bloginfo('name'))); ?></strong></p>
            <p><?php esc_html_e('We are happy to have you with us.', 'mhm-rentiva'); ?></p>
        </div>
    </div>
</body>
</html>


