<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(sprintf(__('Booking #%s Status Updated', 'mhm-rentiva'), (string)($data['booking']['id'] ?? ''))); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 24px; }
        .detail-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('Booking Status Changed (Admin Copy)', 'mhm-rentiva'); ?></h1>
        </div>
        <div class="content">
            <div class="detail-row"><span class="detail-label"><?php esc_html_e('Booking No:', 'mhm-rentiva'); ?></span><span class="detail-value">#<?php echo esc_html((string)($data['booking']['id'] ?? '')); ?></span></div>
            <div class="detail-row"><span class="detail-label"><?php esc_html_e('Customer:', 'mhm-rentiva'); ?></span><span class="detail-value"><?php echo esc_html($data['customer']['name'] ?? ''); ?> (<?php echo esc_html($data['customer']['email'] ?? ''); ?>)</span></div>
            <div class="detail-row"><span class="detail-label"><?php esc_html_e('Old Status:', 'mhm-rentiva'); ?></span><span class="detail-value"><?php echo esc_html($data['status_change']['old_status_label'] ?? ($data['status_change']['old_status'] ?? '')); ?></span></div>
            <div class="detail-row"><span class="detail-label"><?php esc_html_e('New Status:', 'mhm-rentiva'); ?></span><span class="detail-value"><?php echo esc_html($data['status_change']['new_status_label'] ?? ($data['status_change']['new_status'] ?? '')); ?></span></div>
        </div>
    </div>
</body>
</html>


