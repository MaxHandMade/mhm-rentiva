<?php if (!defined('ABSPATH')) {
    exit;
} ?>
<div class="booking-created-admin-email">
    <div class="intro">
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <strong><?php esc_html_e('Attention:', 'mhm-rentiva'); ?></strong> <?php esc_html_e('A new reservation request has been received. Please check from the admin panel.', 'mhm-rentiva'); ?>
        </div>
    </div>

    <div class="content">
        <h2 style="color: #555; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e('Customer Information', 'mhm-rentiva'); ?></h2>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #e3f2fd; border-radius: 8px;">
            <tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; color: #1565c0; width: 35%;"><strong><?php esc_html_e('Name:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; text-align: right;"><?php echo esc_html($data['customer']['name'] ?? ''); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; color: #1565c0;"><strong><?php esc_html_e('Email:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; text-align: right;"><?php echo esc_html($data['customer']['email'] ?? ''); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 15px; color: #1565c0;"><strong><?php esc_html_e('Phone:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; text-align: right;"><?php echo esc_html($data['customer']['phone'] ?? ''); ?></td>
            </tr>
        </table>

        <h2 style="color: #555; border-bottom: 2px solid #f8f9fa; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e('Reservation Details', 'mhm-rentiva'); ?></h2>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #f8f9fa; border-radius: 8px;">
            <tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555; width: 35%;"><strong><?php esc_html_e('Reservation No:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">#<?php echo esc_html($data['booking']['id'] ?? ''); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e('Vehicle:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo esc_html($data['vehicle']['title'] ?? ''); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e('Pickup Date:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo esc_html($data['booking']['pickup_date'] ?? ''); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e('Return Date:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo esc_html($data['booking']['return_date'] ?? ''); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e('Rental Period:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo esc_html($data['booking']['rental_days'] ?? ''); ?> <?php esc_html_e('days', 'mhm-rentiva'); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e('Total Amount:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right; color: #28a745; font-weight: bold;"><?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?><?php echo esc_html(number_format((float)($data['booking']['total_price'] ?? 0), 2)); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 15px; color: #555;"><strong><?php esc_html_e('Payment Status:', 'mhm-rentiva'); ?></strong></td>
                <td style="padding: 12px 15px; text-align: right;"><?php
                                                                    $payment_status = $data['booking']['payment_status'] ?? 'unknown';
                                                                    $status_text = [
                                                                        'pending' => esc_html__('Payment Pending', 'mhm-rentiva'),
                                                                        'completed' => esc_html__('Completed', 'mhm-rentiva'),
                                                                        'failed' => esc_html__('Failed', 'mhm-rentiva'),
                                                                        'cancelled' => esc_html__('Cancelled', 'mhm-rentiva'),
                                                                        'refunded' => esc_html__('Refunded', 'mhm-rentiva'),
                                                                    ];
                                                                    echo esc_html($status_text[$payment_status] ?? ucfirst($payment_status));
                                                                    ?></td>
            </tr>
        </table>

        <div style="text-align: center;">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=vehicle_booking')); ?>" style="display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0;"><?php esc_html_e('Manage Reservation', 'mhm-rentiva'); ?></a>
        </div>
    </div>
</div>