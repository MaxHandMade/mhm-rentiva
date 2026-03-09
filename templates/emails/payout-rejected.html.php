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
    <p>
        <?php
        /* translators: %s: payout amount */
        echo esc_html(sprintf(
            __('Unfortunately, your payout request of %s has been declined.', 'mhm-rentiva'),
            (string) ($data['payout']['amount_formatted'] ?? '')
        ));
        ?>
    </p>
    <?php if (! empty($data['rejection']['reason'])) : ?>
        <p><strong><?php esc_html_e('Reason:', 'mhm-rentiva'); ?></strong> <?php echo esc_html((string) $data['rejection']['reason']); ?></p>
    <?php endif; ?>
    <p><?php esc_html_e('You may submit a new payout request once the issue has been resolved. If you believe this was an error, please contact our support team.', 'mhm-rentiva'); ?></p>
    <p style="text-align:center">
        <a class="cta-button" href="<?php echo esc_url((string) ($data['panel']['url'] ?? home_url('/panel/'))); ?>?tab=ledger" style="display:inline-block;background:#2271b1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
            <?php esc_html_e('View Ledger & Payouts', 'mhm-rentiva'); ?>
        </a>
    </p>
</div>