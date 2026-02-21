<?php

/**
 * Selected Vehicle Summary Partial (Booking Context)
 * 
 * Specialized, compact layout for the booking form.
 * 
 * @var array $vehicle Standardized vehicle data (from prepare_selected_vehicle).
 * @var array $atts    Shortcode attributes.
 * 
 * @package MHMRentiva
 */

use MHMRentiva\Helpers\Icons;

if (! defined('ABSPATH')) {
    exit;
}

// SSOT Data Layer
include 'vehicle-card-base.php';

// Filter out unwanted actions for booking context
$show_compare = false;
$show_booking = false;
?>

<div class="rv-selected-vehicle" data-id="<?php echo esc_attr($vehicle_id); ?>">
    <!-- Media Section (35% on Desktop) -->
    <div class="rv-sv__media">
        <?php if ($image_url) : ?>
            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" class="rv-sv__img">
        <?php else : ?>
            <div class="rv-sv__placeholder">
                <?php Icons::render('car', ['width' => '48', 'height' => '48', 'style' => 'opacity: 0.1;']); ?>
            </div>
        <?php endif; ?>

        <?php if ($show_fav) : ?>
            <button class="rv-sv__favorite mhm-vehicle-favorite-btn <?php echo esc_attr($is_favorite ? 'is-active' : ''); ?>"
                data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
                data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_favorite')); ?>">
                <?php Icons::render('heart', ['class' => 'rv-heart-icon']); ?>
            </button>
        <?php endif; ?>
    </div>

    <!-- Content Section (65% on Desktop) -->
    <div class="rv-sv__content">
        <h3 class="rv-sv__title"><?php echo esc_html($title); ?></h3>

        <?php if ($show_rating && isset($vehicle['rating']['stars'])) : ?>
            <div class="rv-sv__rating">
                <span class="mhm-stars"><?php echo $vehicle['rating']['stars']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                        ?></span>
                <span class="mhm-rating-count">(<?php echo intval($rating_count); ?>)</span>
                <?php if (! empty($is_featured)) : ?>
                    <span class="rv-sv__badge"><?php esc_html_e('YENİ', 'mhm-rentiva'); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($show_features && ! empty($features)) : ?>
            <div class="rv-sv__meta">
                <?php
                $count = 0;
                foreach ($features as $feature) :
                    if ($count >= 6) break;
                    $feature_label = (string) ($feature['text'] ?? '');
                    $feature_svg   = isset($feature['svg']) ? (string) $feature['svg'] : '';
                ?>
                    <span class="rv-sv__chip" title="<?php echo esc_attr($feature_label); ?>">
                        <?php if ($feature_svg !== '') echo wp_kses($feature_svg, $allowed_svg_tags); ?>
                        <?php echo esc_html($feature_label); ?>
                    </span>
                <?php $count++;
                endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="rv-sv__price">
            <span class="rv-sv__price-amount"><?php echo esc_html($price_fmt); ?></span>
            <span class="rv-sv__price-period"><?php esc_html_e('/ gün', 'mhm-rentiva'); ?></span>
        </div>
    </div>
</div>