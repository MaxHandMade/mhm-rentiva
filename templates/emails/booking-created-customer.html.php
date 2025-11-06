<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($data['booking']['title'] ?? ''); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .booking-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .account-section { background: #e3f2fd; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #2196F3; }
        .account-section h3 { margin: 0 0 15px 0; color: #1976d2; }
        .new-user-box { background: #fff; padding: 15px; border-radius: 6px; margin: 15px 0; border: 2px dashed #2196F3; }
        .tip-box { background: #fff3cd; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #ffc107; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
        .cta-button { display: inline-block; background: #2196F3; color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: bold; font-size: 16px; }
        .cta-button:hover { background: #1976d2; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('Booking Confirmed!', 'mhm-rentiva'); ?></h1>
            <p><?php printf(esc_html__('Dear %s, your booking has been successfully created.', 'mhm-rentiva'), esc_html($data['customer']['name'] ?? '')); ?></p>
            <p style="margin-top: 15px; font-size: 14px; opacity: 0.9;"><?php echo esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_brand_name', get_bloginfo('name'))); ?></p>
        </div>
        
        <div class="content">
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
                
                <?php 
                // Payment information
                $payment_type = $data['booking']['payment_type'] ?? '';
                $deposit_amount = $data['booking']['deposit_amount'] ?? 0;
                $remaining_amount = $data['booking']['remaining_amount'] ?? 0;
                $payment_method = $data['booking']['payment_method'] ?? '';
                $payment_status = $data['booking']['payment_status'] ?? '';
                
                if ($payment_type === 'deposit' && $deposit_amount > 0): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Deposit Amount:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?><?php echo esc_html(number_format($deposit_amount, 2)); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Remaining Amount:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value"><?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?><?php echo esc_html(number_format($remaining_amount, 2)); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Payment Method:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value">
                        <?php 
                        $payment_methods = [
                            'credit_card' => esc_html__('Credit Card', 'mhm-rentiva'),
                            'bank_transfer' => esc_html__('Bank Transfer', 'mhm-rentiva'),
                            'cash' => esc_html__('Cash', 'mhm-rentiva'),
                            'offline' => esc_html__('Offline Payment', 'mhm-rentiva')
                        ];
                        echo esc_html($payment_methods[$payment_method] ?? ucfirst($payment_method));
                        ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><?php esc_html_e('Payment Status:', 'mhm-rentiva'); ?></span>
                    <span class="detail-value">
                        <?php 
                        $status_colors = [
                            'completed' => '#28a745',
                            'pending' => '#ffc107',
                            'failed' => '#dc3545'
                        ];
                        $status_texts = [
                            'completed' => esc_html__('Completed', 'mhm-rentiva'),
                            'pending' => esc_html__('Pending', 'mhm-rentiva'),
                            'failed' => esc_html__('Failed', 'mhm-rentiva')
                        ];
                        $color = $status_colors[$payment_status] ?? '#6c757d';
                        $text = $status_texts[$payment_status] ?? ucfirst($payment_status);
                        ?>
                        <span style="color: <?php echo esc_attr($color); ?>; font-weight: bold;"><?php echo esc_html($text); ?></span>
                    </span>
                </div>
            </div>
            
            <?php if ($payment_method === 'bank_transfer' || $payment_method === 'offline'): ?>
            <!-- Offline/Bank Payment Information -->
            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;">
                <h3 style="margin: 0 0 15px 0; color: #856404;">⚠️ <?php esc_html_e('Payment Information', 'mhm-rentiva'); ?></h3>
                
                <?php
                // Get dynamic bank account details
                $settings = get_option('mhm_rentiva_settings', []);
                $bank_accounts = $settings['mhm_rentiva_offline_accounts'] ?? '';
                
                if (!empty($bank_accounts)): ?>
                <div style="background: white; padding: 15px; border-radius: 6px; margin: 15px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;"><?php esc_html_e('Bank Details', 'mhm-rentiva'); ?></h4>
                    <div style="font-family: monospace; font-size: 14px; line-height: 1.8;">
                        <?php echo nl2br(esc_html($bank_accounts)); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="background: #f8d7da; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #dc3545;">
                    <h4 style="margin: 0 0 10px 0; color: #721c24;">⚠️ <?php esc_html_e('Important Notice', 'mhm-rentiva'); ?></h4>
                    <p style="margin: 0; color: #721c24; font-weight: bold;">
                        <?php esc_html_e('You must complete your payment within 30 minutes. Otherwise, your reservation will be automatically cancelled.', 'mhm-rentiva'); ?>
                    </p>
                    <?php 
                    // Show payment deadline
                    $payment_deadline = $data['booking']['payment_deadline'] ?? '';
                    if ($payment_deadline): ?>
                    <p style="margin: 10px 0; color: #721c24; font-size: 14px;">
                        <strong><?php esc_html_e('Payment Deadline:', 'mhm-rentiva'); ?></strong> 
                        <?php echo esc_html(date('d.m.Y H:i', strtotime($payment_deadline))); ?>
                    </p>
                    <?php endif; ?>
                    <p style="margin: 10px 0 0 0; color: #721c24; font-size: 13px;">
                        <?php esc_html_e('After making the payment, you can send the receipt via email or notify us by phone.', 'mhm-rentiva'); ?>
                    </p>
                </div>
            </div>
            <?php else: ?>
            <p><?php esc_html_e('Thank you for your booking. We will have the vehicle ready for pickup on the specified date.', 'mhm-rentiva'); ?></p>
            <?php endif; ?>
            
            <?php
            // User account check
            $customer_email = $data['customer']['email'] ?? '';
            $user = get_user_by('email', $customer_email);
            $is_new_user = false;
            $account_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_my_account');
            $reset_url = '';
            
            if (!$user && !empty($customer_email)) {
                // New user created
                $is_new_user = true;
                $user = get_user_by('email', $customer_email);
                
                if ($user) {
                    $reset_key = get_password_reset_key($user);
                    if (!is_wp_error($reset_key)) {
                        $reset_url = network_site_url(
                            "wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($customer_email),
                            'login'
                        );
                    }
                }
            }
            ?>
            
            <!-- Account Access Section -->
            <div class="account-section">
                <h3>
                    🔐 <?php echo $is_new_user ? 
                        esc_html__('Your Account Has Been Created!', 'mhm-rentiva') : 
                        esc_html__('Access Your Account', 'mhm-rentiva'); ?>
                </h3>
                
                <?php if ($is_new_user && !empty($reset_url)): ?>
                    <p><?php esc_html_e('We have automatically created an account for you to manage your bookings and view your rental history.', 'mhm-rentiva'); ?></p>
                    
                    <div class="new-user-box">
                        <p style="margin: 0 0 10px 0;">
                            <strong><?php esc_html_e('Your Username:', 'mhm-rentiva'); ?></strong><br>
                            <?php echo esc_html($customer_email); ?>
                        </p>
                        <p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
                            <?php esc_html_e('Please set your password using the button below:', 'mhm-rentiva'); ?>
                        </p>
                    </div>
                    
                    <div style="text-align: center; margin: 20px 0;">
                        <a href="<?php echo esc_url($reset_url); ?>" class="cta-button">
                            🔐 <?php esc_html_e('Set Your Password', 'mhm-rentiva'); ?>
                        </a>
                    </div>
                    
                    <p style="font-size: 12px; color: #999; text-align: center; margin: 10px 0 0 0;">
                        <?php esc_html_e('This link expires in 24 hours.', 'mhm-rentiva'); ?>
                    </p>
                    
                <?php else: ?>
                    <p><?php esc_html_e('You can view and manage your booking from your account dashboard.', 'mhm-rentiva'); ?></p>
                    
                    <div style="text-align: center; margin: 20px 0;">
                        <a href="<?php echo esc_url($account_url); ?>" class="cta-button">
                            📊 <?php esc_html_e('My Account', 'mhm-rentiva'); ?>
                        </a>
                    </div>
                    
                    <p style="font-size: 13px; color: #666; text-align: center; margin: 10px 0 0 0;">
                        <?php esc_html_e('Login with your registered email and password', 'mhm-rentiva'); ?>
                    </p>
                <?php endif; ?>
                
                <div class="tip-box">
                    <p style="margin: 0; font-size: 13px; color: #856404;">
                        💡 <strong><?php esc_html_e('Tip:', 'mhm-rentiva'); ?></strong> 
                        <?php esc_html_e('Save your login credentials for faster bookings in the future!', 'mhm-rentiva'); ?>
                    </p>
                </div>
            </div>
            
        </div>
        
        <div class="footer">
            <p><strong><?php echo esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_brand_name', get_bloginfo('name'))); ?></strong></p>
            <p><?php esc_html_e('This email was sent automatically. Please do not reply.', 'mhm-rentiva'); ?></p>
            <p style="font-size: 12px; color: #999; margin-top: 10px;">
                <?php printf(esc_html__('If you have any questions, please contact us at %s', 'mhm-rentiva'), 
                    '<a href="mailto:' . esc_attr(\MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email()) . '">' . esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email()) . '</a>'); ?>
            </p>
        </div>
    </div>
</body>
</html>
