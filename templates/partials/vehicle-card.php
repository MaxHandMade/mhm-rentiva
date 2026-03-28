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

<div class="<?php echo esc_attr($card_class); ?>" data-id="<?php echo esc_attr($vehicle_id); ?>" data-testid="vehicle-card">
    <?php if ($show_image) : ?>
        <div class="mhm-card-image">
            <a href="<?php echo esc_url($permalink); ?>" class="mhm-card-link" aria-hidden="true" tabindex="-1">
                <?php if ($image_url) : ?>
                    <?php
                    $srcset = $image_id ? wp_get_attachment_image_srcset($image_id, 'large') : '';
                    $sizes  = $image_id ? '(max-width: 782px) 100vw, (max-width: 900px) 180px, 220px' : '';
                    ?>
                    <img src="<?php echo esc_url($image_url); ?>"
                         alt="<?php echo esc_attr($image_alt); ?>"
                         loading="lazy"
                         <?php if ($srcset) : ?>srcset="<?php echo esc_attr($srcset); ?>" sizes="<?php echo esc_attr($sizes); ?>"<?php endif; ?>>
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
                        data-testid="vehicle-favorite-btn"
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
                        data-testid="vehicle-compare-btn"
                        title="<?php esc_attr_e('Compare', 'mhm-rentiva'); ?>"
                        aria-label="<?php esc_attr_e('Compare', 'mhm-rentiva'); ?>">
                        <?php Icons::render('compare', [ 'class' => 'mhm-compare-icon' ]); ?>
                        <span class="text-label sr-only"><?php esc_html_e('Compare', 'mhm-rentiva'); ?></span>
                    </button>
                <?php endif; ?>

                <?php if ($is_vendor_vehicle) : ?>
                    <div class="mhm-card-vendor-badge" data-testid="mhm-vendor-badge" title="<?php esc_attr_e('This vehicle is provided by an authorized dealer.', 'mhm-rentiva'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" width="12" height="12"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php esc_html_e('Dealer', 'mhm-rentiva'); ?>
                    </div>
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

            <?php if ($show_location && $location_name !== '') : ?>
                <div class="mhm-card-location" data-testid="mhm-vehicle-location">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <span><?php echo esc_html($location_name); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($show_rating && isset($vehicle['rating']['stars'])) : ?>
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
                <a href="<?php echo esc_url($btn_url); ?>" class="<?php echo esc_attr($btn_class); ?>" data-testid="vehicle-book-btn">
                    <?php echo esc_html($booking_text); ?>
                </a>
            <?php endif; ?>
        </div>

    </div>
</div>