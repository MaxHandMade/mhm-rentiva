<?php if (!defined('ABSPATH')) {
    exit;
} ?>
<div class="refund-customer-email">
    <div class="intro" style="margin-bottom: 20px;">
        <p><?php
            /* translators: %s: booking ID */
            printf(esc_html__('Your refund for booking #%s has been processed.', 'mhm-rentiva'), esc_html($data['booking']['id'] ?? ''));
            ?></p>
    </div>

    <h2 style="color: #555; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e('Refund Details', 'mhm-rentiva'); ?></h2>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777; width: 40%;"><strong><?php esc_html_e('Booking No:', 'mhm-rentiva'); ?></strong></td>
            <td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;">#<?php echo esc_html((string)($data['booking']['id'] ?? '')); ?></td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e('Refund Amount:', 'mhm-rentiva'); ?></strong></td>
            <td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right; color: #28a745; font-weight: bold;"><?php echo esc_html($data['amount'] ?? ''); ?></td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e('Status:', 'mhm-rentiva'); ?></strong></td>
            <td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;"><?php
                                                                                            $status = $data['status'] ?? 'pending';
                                                                                            $status_labels = [
                                                                                                'pending' => esc_html__('Pending', 'mhm-rentiva'),
                                                                                                'completed' => esc_html__('Completed', 'mhm-rentiva'),
                                                                                                'processing' => esc_html__('Processing', 'mhm-rentiva'),
                                                                                            ];
                                                                                            echo esc_html($status_labels[$status] ?? ucfirst($status));
                                                                                            ?></td>
        </tr>
        <?php if (!empty($data['reason'])): ?>
            <tr>
                <td style="padding: 12px 0; color: #777;"><strong><?php esc_html_e('Reason:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 0; text-align: right;"><?php echo esc_html((string)$data['reason']); ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 6px; margin: 20px 0;">
        <p style="margin: 0;"><?php esc_html_e('The refund will be credited to your original payment method. Processing time may vary depending on your bank.', 'mhm-rentiva'); ?></p>
    </div>

    <p style="color: #666; font-size: 14px;"><?php esc_html_e('If you have any questions about this refund, please contact us.', 'mhm-rentiva'); ?></p>
</div>