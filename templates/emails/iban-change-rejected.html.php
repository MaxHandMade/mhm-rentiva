<?php if (! defined('ABSPATH')) {
    exit;
} ?>
<div class="content">
    <p>
        <?php
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
        /* translators: %s: vendor display name */
        echo esc_html(sprintf(__('Hello %s,', 'mhm-rentiva'), (string) ($data['vendor']['name'] ?? '')));
        ?>
    </p>
    <p><?php esc_html_e('Your IBAN change request has been reviewed but was not approved. Your payouts will continue to be sent to your previously registered bank account.', 'mhm-rentiva'); ?></p>
    <p><?php esc_html_e('If you believe this was an error or would like to submit a corrected request, please update your payment settings from your dashboard.', 'mhm-rentiva'); ?></p>
    <p style="text-align:center">
        <a class="cta-button" href="<?php echo esc_url((string) ($data['panel']['url'] ?? home_url('/panel/'))); ?>?tab=settings" style="display:inline-block;background:#2271b1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
            <?php esc_html_e('View Payment Settings', 'mhm-rentiva'); ?>
        </a>
    </p>
</div>