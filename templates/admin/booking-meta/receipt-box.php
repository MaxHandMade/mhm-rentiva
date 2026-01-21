<?php

/**
 * Receipt Box Template
 * 
 * @var int $attach_id
 * @var string $url
 * @var string $status
 * @var string $note
 */

if (!defined('ABSPATH')) {
    exit;
}


?>

<div class="mhm-receipt-box">
    <?php wp_nonce_field('mhm_rentiva_receipt_action', 'mhm_receipt_nonce'); ?>

    <?php if ($attach_id && $url): ?>
        <p>
            <strong><?php esc_html_e('Receipt file:', 'mhm-rentiva'); ?></strong><br />
            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                <?php esc_html_e('View / Download', 'mhm-rentiva'); ?>
            </a>
        </p>
    <?php else: ?>
        <p><?php esc_html_e('No receipt uploaded.', 'mhm-rentiva'); ?></p>
    <?php endif; ?>

    <p>
        <strong><?php esc_html_e('Status:', 'mhm-rentiva'); ?></strong>
        <?php echo esc_html($status ?: esc_html__('-', 'mhm-rentiva')); ?>
    </p>

    <p>
        <label for="mhm_receipt_note">
            <strong><?php esc_html_e('Admin Note', 'mhm-rentiva'); ?></strong>
        </label><br />
        <textarea id="mhm_receipt_note" name="mhm_receipt_note" rows="3" style="width:100%">
            <?php echo esc_textarea($note); ?>
        </textarea>
    </p>

    <p>
        <button type="submit" name="mhm_receipt_action" value="approve" class="button button-primary">
            <?php esc_html_e('Approve Receipt', 'mhm-rentiva'); ?>
        </button>
        <button type="submit" name="mhm_receipt_action" value="reject" class="button">
            <?php esc_html_e('Reject Receipt', 'mhm-rentiva'); ?>
        </button>
    </p>
</div>