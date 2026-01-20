<?php if (!defined('ABSPATH')) {
    exit;
} ?>
<div class="message-replied-customer-email">
    <div class="content">
        <p><strong><?php esc_html_e('Subject:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($data['message']['subject'] ?? ''); ?></p>
        <div class="pre" style="white-space: pre-wrap; background:#f8f9fa; border-radius:6px; padding:12px;"><?php echo esc_html($data['message']['reply'] ?? ''); ?></div>
    </div>
</div>