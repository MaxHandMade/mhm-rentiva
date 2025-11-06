<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(sprintf(__('Refund for Booking #%s', 'mhm-rentiva'), (string)($data['booking']['id'] ?? ''))); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .content { padding: 30px; }
        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; background:#e8f5e9; color:#2e7d32; font-weight:600; font-size:12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('Refund Processed', 'mhm-rentiva'); ?></h1>
            <p style="margin-top: 8px; opacity:.9; font-size:14px;">#<?php echo esc_html((string)($data['booking']['id'] ?? '')); ?> · <?php echo esc_html($data['site']['name'] ?? get_bloginfo('name')); ?></p>
        </div>

        <div class="content">
            <p><?php esc_html_e('We have processed your refund for the booking below.', 'mhm-rentiva'); ?></p>

            <div class="detail-row">
                <span class="detail-label"><?php esc_html_e('Amount:', 'mhm-rentiva'); ?></span>
                <span class="detail-value"><span class="badge"><?php echo esc_html($data['amount'] ?? ''); ?></span></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><?php esc_html_e('Status:', 'mhm-rentiva'); ?></span>
                <span class="detail-value"><?php echo esc_html(ucfirst((string)($data['status'] ?? ''))); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><?php esc_html_e('Booking No:', 'mhm-rentiva'); ?></span>
                <span class="detail-value">#<?php echo esc_html((string)($data['booking']['id'] ?? '')); ?></span>
            </div>

            <?php if (!empty($data['reason'])): ?>
            <p style="margin-top:16px;"><strong><?php esc_html_e('Reason:', 'mhm-rentiva'); ?></strong> <?php echo esc_html((string)$data['reason']); ?></p>
            <?php endif; ?>

            <p style="margin-top:20px; font-size:13px; color:#666;"><?php esc_html_e('The amount will be reflected in your account depending on your bank’s processes.', 'mhm-rentiva'); ?></p>
        </div>

        <div class="footer">
            <p><strong><?php echo esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_brand_name', get_bloginfo('name'))); ?></strong></p>
            <p><?php esc_html_e('If you have any questions, please reply to this email.', 'mhm-rentiva'); ?></p>
        </div>
    </div>
</body>
</html>


