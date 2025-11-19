<?php
/**
 * Payment History Page Template
 * 
 * Displays user's payment history
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../languages/');
    }
    mhm_rentiva_load_textdomain();
}

// Dynamic currency symbol (plugin settings)
use MHMRentiva\Admin\Settings\Core\SettingsCore;

$currency_code = SettingsCore::get('mhm_rentiva_currency', 'USD');
$currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

?>

<div class="mhm-rentiva-account-page">
    <?php echo \MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', ['navigation' => $navigation], true); ?>
    
    <div class="mhm-account-content">
        <div class="section-header">
            <h2><?php _e('Payment History', 'mhm-rentiva'); ?></h2>
            <span class="view-all-link">
                <?php
                /* translators: %d: total payments count. */
                printf(esc_html__('%d payments listed', 'mhm-rentiva'), count($payments));
                ?>
            </span>
        </div>

        <?php if (empty($payments)): ?>
            <!-- No payment records -->
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M12 1v6l3-3m-6 0l3 3"/>
                        <path d="M21 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17,21 7,21 7,13"/>
                        <polyline points="7,3 7,1 3,1"/>
                    </svg>
                </div>
                <h3><?php esc_html_e('No payment records yet', 'mhm-rentiva'); ?></h3>
                <p><?php esc_html_e('Your payment history will appear here when you make a reservation.', 'mhm-rentiva'); ?></p>
                <a href="<?php echo esc_url(apply_filters('mhm_rentiva/vehicles_page_url', \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list'))); ?>" class="btn btn-primary">
                    <?php esc_html_e('View Vehicles', 'mhm-rentiva'); ?>
                </a>
            </div>
        <?php else: ?>
            <!-- Payment history -->
            <div class="account-section">
                <!-- Header kept above -->

                <!-- Payment List -->
                <div class="payment-history-list">
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-item">
                            <div class="payment-header">
                                <div class="payment-info">
                                    <h3 class="payment-title">
                                        <?php echo esc_html($payment['booking_title']); ?>
                                    </h3>
                                    <span class="payment-date">
                                        <?php echo esc_html($payment['date']); ?>
                                    </span>
                                </div>
                                <div class="payment-amount">
                                    <span class="amount"><?php echo esc_html($currency_symbol); ?><?php echo esc_html(number_format_i18n((float)($payment['amount'] ?? 0), 2)); ?></span>
                                    <?php if ($payment['type'] === 'deposit'): ?>
                                        <span class="amount-type"><?php esc_html_e('(Deposit)', 'mhm-rentiva'); ?></span>
                                    <?php else: ?>
                                        <span class="amount-type"><?php esc_html_e('(Full Payment)', 'mhm-rentiva'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="payment-details">
                                <div class="payment-meta">
                                    <div class="meta-item">
                                        <span class="meta-label"><?php esc_html_e('Status:', 'mhm-rentiva'); ?></span>
                                        <span class="payment-status status-<?php echo esc_attr($payment['status']); ?>">
                                            <?php echo esc_html(ucfirst($payment['status'])); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($payment['method'])): ?>
                                        <div class="meta-item">
                                            <span class="meta-label"><?php esc_html_e('Payment Method:', 'mhm-rentiva'); ?></span>
                                            <span class="payment-method">
                                                <?php echo esc_html($payment['method']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($payment['gateway'])): ?>
                                        <div class="meta-item">
                                            <span class="meta-label"><?php esc_html_e('Payment Gateway:', 'mhm-rentiva'); ?></span>
                                            <span class="payment-gateway">
                                                <?php echo esc_html($payment['gateway']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($payment['type'] === 'deposit' && $payment['total'] > $payment['amount']): ?>
                                        <div class="meta-item">
                                            <span class="meta-label"><?php esc_html_e('Total Amount:', 'mhm-rentiva'); ?></span>
                                            <span class="total-amount">
                                                <?php echo esc_html($currency_symbol); ?><?php echo esc_html(number_format_i18n((float)($payment['total'] ?? 0), 2)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="payment-actions">
                                    <a href="<?php echo esc_url(add_query_arg(['endpoint' => 'booking-detail', 'booking_id' => (int)($payment['booking_id'] ?? 0)], \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url())); ?>" class="btn btn-secondary btn-sm">
                                        <?php esc_html_e('Booking Details', 'mhm-rentiva'); ?>
                                    </a>

                    <?php 
                    $receipt = $payment['receipt'] ?? [];
                    $has_receipt = !empty($receipt['attachment_id']);
                    ?>
                    <?php if (!$has_receipt): ?>
                        <label class="btn btn-primary btn-sm" style="margin-left:8px;">
                            <?php esc_html_e('Upload Receipt', 'mhm-rentiva'); ?>
                            <input type="file" accept="image/jpeg,image/png,application/pdf" class="mhm-upload-receipt" data-booking-id="<?php echo esc_attr($payment['booking_id']); ?>" style="display:none;">
                        </label>
                    <?php else: ?>
                        <a class="btn btn-secondary btn-sm" style="margin-left:8px;" href="<?php echo esc_url($receipt['url']); ?>" target="_blank" rel="noopener">
                            <?php esc_html_e('View Receipt', 'mhm-rentiva'); ?>
                        </a>
                        <?php if (($receipt['status'] ?? '') === 'rejected'): ?>
                            <label class="btn btn-primary btn-sm" style="margin-left:8px;">
                                <?php esc_html_e('Re-upload', 'mhm-rentiva'); ?>
                                <input type="file" accept="image/jpeg,image/png,application/pdf" class="mhm-upload-receipt" data-booking-id="<?php echo esc_attr($payment['booking_id']); ?>" style="display:none;">
                            </label>
                        <?php endif; ?>
                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

