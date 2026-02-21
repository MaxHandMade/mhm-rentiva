<?php

declare(strict_types=1);
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Vehicle Card Partial Template
 *
 * @var array  $vehicle  Standardized vehicle data.
 * @var string $layout   'grid' or 'list'.
 * @var array  $atts     Shortcode/Block attributes.
 */

use MHMRentiva\Helpers\Icons;

if (! defined('ABSPATH')) {
    exit;
}

// Logic Layer (SSOT)
require 'vehicle-card-base.php';

// Context-Specific Defaults
$layout     = $layout ?? 'grid';
$card_class = 'mhm-vehicle-card mhm-card--' . esc_attr($layout);
$is_grid    = ( $layout === 'grid' );
$btn_class  = 'mhm-btn-booking';

if ($is_grid) {
    $show_description = false;
}

if (! $is_available) {
    $btn_class .= ' is-disabled';
}
?>

<div class="<?php echo esc_attr($card_class); ?>" data-id="<?php echo esc_attr($vehicle_id); ?>">
    <?php if ($show_image) : ?>
        <div class="mhm-card-image">
            <a href="<?php echo esc_url($permalink); ?>" class="mhm-card-link" aria-hidden="true" tabindex="-1">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" loading="lazy">
                <?php else : ?>
                    <div class="mhm-placeholder-image">
                        <div class="mhm-card-image-placeholder">
                            <?php
                            Icons::render('car', [
                                'class'  => 'mhm-placeholder-svg',
                                'width'  => '48',
                                'height' => '48',
                                'style'  => 'opacity: 0.3;',
                            ]);
                            ?>
                        </div>
                    <?php endif; ?>
            </a>

            <div class="mhm-card-actions-overlay">
                <?php if ($show_fav) : ?>
                    <button class="mhm-card-favorite mhm-vehicle-favorite-btn <?php echo esc_attr($is_favorite ? 'is-active' : ''); ?>"
                        data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_favorite')); ?>"
                        title="<?php echo $is_favorite ? esc_attr__('Remove from Favorites', 'mhm-rentiva') : esc_attr__('Add to Favorites', 'mhm-rentiva'); ?>"
                        aria-label="<?php echo $is_favorite ? esc_attr__('Remove from Favorites', 'mhm-rentiva') : esc_attr__('Add to Favorites', 'mhm-rentiva'); ?>">
                        <?php Icons::render('heart', [ 'class' => 'mhm-heart-icon' ]); ?>
                        <span class="text-label sr-only"><?php echo $is_favorite ? esc_html__('Remove from Favorites', 'mhm-rentiva') : esc_html__('Add to Favorites', 'mhm-rentiva'); ?></span>
                    </button>
                <?php endif; ?>

                <?php if ($show_compare) : ?>
                    <button class="mhm-card-compare mhm-vehicle-compare-btn <?php echo esc_attr($is_in_compare ? 'is-active active' : ''); ?>"
                        data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_compare')); ?>"
                        title="<?php esc_attr_e('Compare', 'mhm-rentiva'); ?>"
                        aria-label="<?php esc_attr_e('Compare', 'mhm-rentiva'); ?>">
                        <?php Icons::render('compare', [ 'class' => 'mhm-compare-icon' ]); ?>
                        <span class="text-label sr-only"><?php esc_html_e('Compare', 'mhm-rentiva'); ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="mhm-card-content">

        <div class="mhm-content-main">
            <div class="mhm-card-meta-top">
                <?php if ($show_category && $category_name) : ?>
                    <div class="mhm-card-category" data-testid="mhm-category">
                        <?php echo esc_html($category_name); ?>
                    </div>
                <?php endif; ?>

                <?php if ($show_brand && $brand_name) : ?>
                    <div class="mhm-card-brand" data-testid="mhm-brand">
                        <?php echo esc_html($brand_name); ?>
                    </div>
                <?php endif; ?>
            </div>

            <h3 class="mhm-card-title">
                <?php if ($show_title) : ?>
                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($vehicle_title); ?></a>
                <?php endif; ?>
            </h3>

            <?php if ($show_description && ! empty($excerpt)) : ?>
                <div class="mhm-card-description">
                    <?php echo esc_html($excerpt); ?>
                </div>
            <?php endif; ?>

            <?php if ($show_rating && isset($vehicle['rating']['stars']) && (int) ( $vehicle['rating']['count'] ?? 0 ) > 0) : ?>
                <div class="mhm-card-rating" data-testid="mhm-rating" title="
                <?php
                /* translators: %s: average vehicle rating. */
                echo esc_attr(sprintf(esc_html__('Rated %s out of 5', 'mhm-rentiva'), (string) $vehicle['rating']['average']));
                ?>
                                                                                ">
                    <span class="mhm-stars">
                        <?php
                        echo $vehicle['rating']['stars']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                    </span>
                    <span class="mhm-rating-count">(<?php echo intval($vehicle['rating']['count']); ?>)</span>
                    <?php if (! empty($vehicle['rating']['confidence_label'])) : ?>
                        <span class="mhm-rating-confidence mhm-confidence--<?php echo esc_attr($vehicle['rating']['confidence_key']); ?>"
                            title="<?php echo esc_attr($vehicle['rating']['confidence_tooltip'] ?? ''); ?>">
                            <?php echo esc_html($vehicle['rating']['confidence_label']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($show_features && ! empty($features)) : ?>
                <div class="mhm-card-features">
                    <?php
                    $limit = 6;
                    $count = 0;
                    foreach ($features as $feature) :
                        if ($count >= $limit) {
                            break;
                        }
                        $feature_label = (string) ( $feature['text'] ?? $feature['value'] ?? '' );
                        $feature_svg   = isset($feature['svg']) ? (string) $feature['svg'] : '';
                        if ($feature_svg !== '') {
                            $replaced_svg = preg_replace('/<svg\b/', '<svg aria-hidden="true" focusable="false"', $feature_svg, 1);
                            $feature_svg  = $replaced_svg ? $replaced_svg : $feature_svg;
                        }
						?>
                        <span class="mhm-feature-chip" title="<?php echo esc_attr($feature_label); ?>" aria-label="<?php echo esc_attr($feature_label); ?>">
                            <?php
                            if ($feature_svg !== '') {
                                echo wp_kses($feature_svg, $allowed_svg);
                            }
                            ?>
                            <?php echo esc_html($feature_label); ?>
                        </span>
						<?php
                        ++$count;
                    endforeach;
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="mhm-card-footer">
            <?php if ($show_price) : ?>
                <div class="mhm-card-price" data-testid="mhm-price">
                    <span class="mhm-price-amount"><?php echo esc_html($price_fmt); ?></span>
                    <span class="mhm-price-period"><?php esc_html_e('/day', 'mhm-rentiva'); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($show_booking) : ?>
                <a href="<?php echo esc_url($btn_url); ?>" class="<?php echo esc_attr($btn_class); ?>">
                    <?php echo esc_html($booking_text); ?>
                </a>
            <?php endif; ?>
        </div>

    </div>
</div>