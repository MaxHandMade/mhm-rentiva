<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main render template for [rentiva_vendor_directory].
 *
 * Available variables:
 *   - $atts       : normalized attributes
 *   - $query_args : query args (city, badge, min_rating, sort, paged, per_page)
 *   - $data       : Provider result {vendors, total_count, pagination, city_pool}
 */

$wrapper_class = 'mhm-rentiva-vendor-directory mhm-vendor-directory';
if (!empty($atts['class'])) {
    $wrapper_class .= ' ' . $atts['class'];
}
$wrapper_id = !empty($atts['id']) ? ' id="' . esc_attr( (string) $atts['id']) . '"' : '';
?>
<div class="<?php echo esc_attr($wrapper_class); ?>"<?php echo $wrapper_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above ?>>

    <?php if ($atts['show_breadcrumb'] === 'yes') : ?>
        <?php require __DIR__ . '/partials/vendor-directory-breadcrumb.php'; ?>
    <?php endif; ?>

    <?php if ($atts['show_filter_bar'] === 'yes') : ?>
        <?php require __DIR__ . '/partials/vendor-directory-filter-bar.php'; ?>
    <?php endif; ?>

    <?php if (empty($data['vendors'])) : ?>
        <?php require __DIR__ . '/partials/vendor-directory-empty.php'; ?>
    <?php else : ?>
        <div class="mhm-vendor-directory-grid">
            <?php foreach ($data['vendors'] as $vendor) : ?>
                <?php require __DIR__ . '/partials/vendor-directory-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($atts['show_pagination'] === 'yes' && $data['pagination']['total_pages'] > 1) : ?>
            <?php require __DIR__ . '/partials/vendor-directory-pagination.php'; ?>
        <?php endif; ?>
    <?php endif; ?>

</div>
