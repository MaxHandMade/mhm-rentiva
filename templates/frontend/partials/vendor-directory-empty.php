<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $atts */
/** @var array<string, mixed> $query_args */

$has_filters = !empty($query_args['city']) || !empty($query_args['badge']) || (int) ( $query_args['min_rating'] ?? 0 ) > 0;

if ($has_filters) {
    $message   = __('No vendors match these filters.', 'mhm-rentiva');
    $clear_url = home_url('/' . \MHMRentiva\Admin\Vendor\Directory\VendorDirectoryUrlBase::resolve() . '/');
} else {
    $message   = !empty($atts['empty_message'])
        ? (string) $atts['empty_message']
        : __('No vendors registered yet. Coming soon!', 'mhm-rentiva');
    $message   = (string) apply_filters('mhm_rentiva_vendor_directory_empty_message', $message);
    $clear_url = '';
}
?>
<div class="mhm-vendor-directory-empty">
    <p class="mhm-vendor-directory-empty-message"><?php echo esc_html($message); ?></p>
    <?php if ($clear_url !== '') : ?>
        <a class="mhm-vendor-directory-empty-clear" href="<?php echo esc_url($clear_url); ?>">
            <?php echo esc_html__('Clear filters', 'mhm-rentiva'); ?>
        </a>
    <?php endif; ?>
</div>
