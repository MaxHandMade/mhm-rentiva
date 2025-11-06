<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(sprintf(__('Booking #%s Reminder', 'mhm-rentiva'), (string)($data['booking']['id'] ?? ''))); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .booking-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('Upcoming Booking Reminder', 'mhm-rentiva'); ?></h1>
            <p style="margin-top: 15px; font-size: 14px; opacity: 0.9;">#<?php echo esc_html($data['booking']['id'] ?? ''); ?> · <?php echo esc_html($data['site']['name'] ?? get_bloginfo('name')); ?></p>
        </div>

        <div class="content">
            <p><?php esc_html_e('Just a friendly reminder for your upcoming booking. Here are the details:', 'mhm-rentiva'); ?></p>
            <div class="booking-details">
                <div class="detail-row"><span class="detail-label"><?php esc_html_e('Vehicle:', 'mhm-rentiva'); ?></span><span class="detail-value"><?php echo esc_html($data['vehicle']['title'] ?? ''); ?></span></div>
                <div class="detail-row"><span class="detail-label"><?php esc_html_e('Pickup Date:', 'mhm-rentiva'); ?></span><span class="detail-value"><?php echo esc_html($data['booking']['pickup_date'] ?? ''); ?></span></div>
                <div class="detail-row"><span class="detail-label"><?php esc_html_e('Return Date:', 'mhm-rentiva'); ?></span><span class="detail-value"><?php echo esc_html($data['booking']['return_date'] ?? ''); ?></span></div>
            </div>
            <p><?php esc_html_e('Please arrive on time and bring your required documents.', 'mhm-rentiva'); ?></p>
        </div>

        <div class="footer">
            <p><strong><?php echo esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_brand_name', get_bloginfo('name'))); ?></strong></p>
            <p><?php esc_html_e('If you have any questions, please reply to this email.', 'mhm-rentiva'); ?></p>
        </div>
    </div>
</body>
</html>


