<?php
/**
 * Payment Box Template
 * 
 * @var string $payStatus
 * @var float $amount
 * @var string $currency
 * @var string $payment_type
 * @var float $deposit_amount
 * @var float $remaining_amount
 * @var string $gatewayLabel
 * @var string $pi
 * @var string $gateway
 * @var string $paytrOid
 * @var string $refundId
 * @var string $refundSt
 * @var float $refunded
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../languages/');
    }
    mhm_rentiva_load_textdomain();
}
?>

<div class="mhm-payment-box">
    <p>
        <strong><?php esc_html_e('Payment Status', 'mhm-rentiva'); ?>:</strong> 
        <?php echo esc_html($payStatus ?: esc_html__('unpaid', 'mhm-rentiva')); ?>
    </p>
    
    <p>
        <strong><?php esc_html_e('Amount', 'mhm-rentiva'); ?>:</strong> 
        <?php echo esc_html(number_format_i18n($amount / 100, 2)); ?> 
        <?php echo esc_html(strtoupper($currency)); ?>
    </p>
    
    <?php if ($payment_type === 'deposit' && $deposit_amount > 0): ?>
        <p>
            <strong><?php esc_html_e('Deposit Amount', 'mhm-rentiva'); ?>:</strong> 
            <?php echo esc_html(number_format_i18n($deposit_amount, 2)); ?> 
            <?php echo esc_html(strtoupper($currency)); ?>
        </p>
        
        <p>
            <strong><?php esc_html_e('Remaining Amount', 'mhm-rentiva'); ?>:</strong> 
            <?php echo esc_html(number_format_i18n($remaining_amount, 2)); ?> 
            <?php echo esc_html(strtoupper($currency)); ?>
        </p>
    <?php endif; ?>
    
    <p>
        <strong><?php esc_html_e('Payment Method', 'mhm-rentiva'); ?>:</strong> 
        <?php echo esc_html(strtoupper($gatewayLabel)); ?>
    </p>
    
    <p>
        <strong><?php esc_html_e('Stripe Payment Intent', 'mhm-rentiva'); ?>:</strong> 
        <?php echo $pi ? '<code>' . esc_html($pi) . '</code>' : esc_html__('—', 'mhm-rentiva'); ?>
    </p>
    
    <?php if ($gateway === 'paytr'): ?>
        <p>
            <strong><?php esc_html_e('PayTR Merchant OID', 'mhm-rentiva'); ?>:</strong> 
            <?php echo $paytrOid ? '<code>' . esc_html($paytrOid) . '</code>' : esc_html__('—', 'mhm-rentiva'); ?>
        </p>
    <?php endif; ?>
    
    <?php if ($refundId): ?>
        <p>
            <strong><?php esc_html_e('Last Refund', 'mhm-rentiva'); ?>:</strong><br/>
            <?php echo esc_html(sprintf('%s – %s (%s %s)', $refundId, $refundSt ?: esc_html__('-', 'mhm-rentiva'), number_format_i18n($refunded / 100, 2), $currency)); ?>
        </p>
    <?php endif; ?>
</div>
