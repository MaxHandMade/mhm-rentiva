<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(sprintf(__('Reply: %s', 'mhm-rentiva'), (string)($data['message']['subject'] ?? ''))); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; text-align:center; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 24px; }
        .pre { white-space: pre-wrap; background:#f8f9fa; border-radius:6px; padding:12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('We replied to your message', 'mhm-rentiva'); ?></h1>
        </div>
        <div class="content">
            <p><strong><?php esc_html_e('Subject:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($data['message']['subject'] ?? ''); ?></p>
            <div class="pre"><?php echo esc_html($data['message']['reply'] ?? ''); ?></div>
        </div>
    </div>
</body>
</html>


