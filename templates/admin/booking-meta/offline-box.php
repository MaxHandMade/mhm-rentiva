<?php
/**
 * Offline Payment Box Template
 * 
 * @var string $offlineStatus
 * @var string $offlineNote
 * @var int $receiptId
 * @var string $receiptUrl
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

<div class="mhm-offline-box">
    <p>
        <strong><?php esc_html_e('Offline Payment Status', 'mhm-rentiva'); ?>:</strong> 
        <?php echo esc_html($offlineStatus ?: esc_html__('pending', 'mhm-rentiva')); ?>
    </p>
    
    <?php if ($offlineNote): ?>
        <p>
            <strong><?php esc_html_e('Admin Note', 'mhm-rentiva'); ?>:</strong><br/>
            <?php echo esc_html($offlineNote); ?>
        </p>
    <?php endif; ?>
    
    <?php if ($receiptId && $receiptUrl): ?>
        <p>
            <strong><?php esc_html_e('Receipt', 'mhm-rentiva'); ?>:</strong><br/>
            <a href="<?php echo esc_url($receiptUrl); ?>" target="_blank" rel="noopener">
                <?php esc_html_e('View Receipt', 'mhm-rentiva'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>
