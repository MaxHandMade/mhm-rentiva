<?php
// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../languages/');
    }
    mhm_rentiva_load_textdomain();
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        /* translators: %s: booking ID. */
        printf(esc_html__('New Reservation - #%s', 'mhm-rentiva'), esc_html($data['booking']['id'] ?? ''));
        ?>
    </title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .alert { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .booking-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .customer-info { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
        .cta-button { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('New Reservation Request', 'mhm-rentiva'); ?></h1>
            <p>
                <?php
                /* translators: %s: booking ID. */
                printf(esc_html__('Reservation #%s created', 'mhm-rentiva'), esc_html($data['booking']['id'] ?? ''));
                ?>
            </p>
            <p style="margin-top: 15px; font-size: 14px; opacity: 0.9;"><?php echo esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_brand_name', get_bloginfo('name'))); ?></p>
        </div>
        
        <div class="content">
            <div class="alert">
                <strong><?php esc_html_e('Attention:', 'mhm-rentiva'); ?></strong> <?php esc_html_e('A new reservation request has been received. Please check from the admin panel.', 'mhm-rentiva'); ?>
            </div>
            
            <h2><?php esc_html_e('Customer Information', 'mhm-rentiva'); ?></h2>
            <div class="customer-info">
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Name:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html($data['customer']['name'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Email:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html($data['customer']['email'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Phone:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html($data['customer']['phone'] ?? ''); ?></span>
                </div>
            </div>
            
            <h2><?php esc_html_e('Reservation Details', 'mhm-rentiva'); ?></h2>
            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Reservation No:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value">#<?php echo esc_html($data['booking']['id'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Vehicle:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html($data['vehicle']['title'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Pickup Date:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html($data['booking']['pickup_date'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Return Date:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html($data['booking']['return_date'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Rental Period:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html($data['booking']['rental_days'] ?? ''); ?> <?php esc_html_e('days', 'mhm-rentiva'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Total Amount:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?><?php echo esc_html(number_format($data['booking']['total_price'] ?? 0, 2)); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Payment Status:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php 
                        $payment_status = $data['booking']['payment_status'] ?? 'unknown';
                        $status_text = [
                            'pending' => esc_html__('Payment Pending', 'mhm-rentiva'),
                            'completed' => esc_html__('Completed', 'mhm-rentiva'),
                            'failed' => esc_html__('Failed', 'mhm-rentiva'),
                            'cancelled' => esc_html__('Cancelled', 'mhm-rentiva'),
                            'refunded' => esc_html__('Refunded', 'mhm-rentiva'),
                        ];
                        echo esc_html($status_text[$payment_status] ?? ucfirst($payment_status));
                    ?></span>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=vehicle_booking')); ?>" class="cta-button"><?php esc_html_e('Manage Reservation', 'mhm-rentiva'); ?></a>
            </div>
        </div>
        
        <div class="footer">
            <p><strong><?php echo esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_brand_name', get_bloginfo('name'))); ?></strong> <?php esc_html_e('Admin Notification', 'mhm-rentiva'); ?></p>
            <p><?php esc_html_e('This email was sent automatically.', 'mhm-rentiva'); ?></p>
        </div>
    </div>
</body>
</html>
