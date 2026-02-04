<?php

/**
 * Shortcode Template: Featured Vehicles
 *
 * @var array $vehicles
 * @var array $atts
 */

if (! defined('ABSPATH')) {
    exit;
}

$columns = (int) ($atts['columns'] ?? 3);
$layout  = $atts['layout'] ?? 'slider';
?>

<div class="mhm-rentiva-featured-wrapper mhm-layout-<?php echo esc_attr($layout); ?>"
    data-columns="<?php echo esc_attr($columns); ?>"
    data-autoplay="<?php echo esc_attr($atts['autoplay']); ?>"
    data-interval="<?php echo esc_attr($atts['interval']); ?>">

    <?php if (! empty($atts['title'])): ?>
        <h2 class="mhm-rentiva-featured-title"><?php echo esc_html($atts['title']); ?></h2>
    <?php endif; ?>

    <?php if (empty($vehicles)): ?>
        <p class="mhm-rentiva-no-vehicles"><?php esc_html_e('No featured vehicles found.', 'mhm-rentiva'); ?></p>
    <?php else: ?>

        <?php if ($layout === 'slider'): ?>
            <div class="swiper mhm-featured-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($vehicles as $vehicle): ?>
                        <div class="swiper-slide">
                            <?php
                            // Render individual card
                            // Ideally, we reuse a card template part, but here is inline for now
                            ?>
                            <div class="mhm-vehicle-card">
                                <div class="mhm-vehicle-image">
                                    <a href="<?php echo esc_url($vehicle['permalink']); ?>">
                                        <?php if ($vehicle['thumbnail_url']): ?>
                                            <img src="<?php echo esc_url($vehicle['thumbnail_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="mhm-no-image-placeholder"></div>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="mhm-vehicle-content">
                                    <h3 class="mhm-vehicle-title">
                                        <a href="<?php echo esc_url($vehicle['permalink']); ?>"><?php echo esc_html($vehicle['title']); ?></a>
                                    </h3>
                                    <div class="mhm-vehicle-meta">
                                        <?php if ($vehicle['fuel']): ?>
                                            <span class="mhm-meta-item icon-fuel"><?php echo esc_html($vehicle['fuel']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($vehicle['transmission']): ?>
                                            <span class="mhm-meta-item icon-transmission"><?php echo esc_html($vehicle['transmission']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mhm-vehicle-footer">
                                        <div class="mhm-vehicle-price">
                                            <span class="amount"><?php echo esc_html($vehicle['price']); ?></span>
                                            <span class="currency">TL</span>
                                            <span class="period">/ <?php esc_html_e('Day', 'mhm-rentiva'); ?></span>
                                        </div>
                                        <a href="<?php echo esc_url($vehicle['permalink']); ?>" class="mhm-btn mhm-btn-primary mhm-btn-sm">
                                            <?php esc_html_e('Rent Now', 'mhm-rentiva'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        <?php else: ?>
            <!-- Grid Layout -->
            <div class="mhm-featured-grid" style="--mhm-columns: <?php echo esc_attr($columns); ?>">
                <?php foreach ($vehicles as $vehicle): ?>
                    <div class="mhm-featured-grid-item">
                        <!-- Same card structure as above, repeated for now -->
                        <div class="mhm-vehicle-card">
                            <!-- Image -->
                            <div class="mhm-vehicle-image">
                                <a href="<?php echo esc_url($vehicle['permalink']); ?>">
                                    <?php if ($vehicle['thumbnail_url']): ?>
                                        <img src="<?php echo esc_url($vehicle['thumbnail_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" loading="lazy">
                                    <?php endif; ?>
                                </a>
                            </div>
                            <!-- Content -->
                            <div class="mhm-vehicle-content">
                                <h3 class="mhm-vehicle-title"><?php echo esc_html($vehicle['title']); ?></h3>
                                <div class="mhm-vehicle-footer">
                                    <span class="price"><?php echo esc_html($vehicle['price']); ?></span>
                                    <a href="<?php echo esc_url($vehicle['permalink']); ?>" class="mhm-btn"><?php esc_html_e('Details', 'mhm-rentiva'); ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>