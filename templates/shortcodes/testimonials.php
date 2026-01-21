<?php

/**
 * Testimonials Template
 * 
 * Shows customer reviews and ratings
 */

if (!defined('ABSPATH')) {
    exit;
}



// Get template variables
$atts = $atts ?? [];
$testimonials = $testimonials ?? [];
$total_count = $total_count ?? 0;
$has_testimonials = $has_testimonials ?? false;


// Shortcode özelliklerini al
$limit = intval($atts['limit'] ?? apply_filters('mhm_rentiva/testimonials/limit', 5));
$show_rating = ($atts['show_rating'] ?? apply_filters('mhm_rentiva/testimonials/show_rating', '1')) === '1';
$show_date = ($atts['show_date'] ?? apply_filters('mhm_rentiva/testimonials/show_date', '1')) === '1';
$show_vehicle = ($atts['show_vehicle'] ?? apply_filters('mhm_rentiva/testimonials/show_vehicle', '1')) === '1';
$show_customer = ($atts['show_customer'] ?? apply_filters('mhm_rentiva/testimonials/show_customer', '1')) === '1';
$layout = $atts['layout'] ?? apply_filters('mhm_rentiva/testimonials/layout', 'grid');
$columns = intval($atts['columns'] ?? apply_filters('mhm_rentiva/testimonials/columns', 3));
$auto_rotate = ($atts['auto_rotate'] ?? apply_filters('mhm_rentiva/testimonials/auto_rotate', '0')) === '1';
$class = $atts['class'] ?? apply_filters('mhm_rentiva/testimonials/class', '');
?>

<div class="rv-testimonials rv-layout-<?php echo esc_attr($layout); ?> rv-columns-<?php echo esc_attr($columns); ?> <?php echo esc_attr($class); ?>"
    data-limit="<?php echo esc_attr($limit); ?>"
    data-layout="<?php echo esc_attr($layout); ?>"
    data-auto-rotate="<?php echo esc_attr($auto_rotate ? '1' : '0'); ?>">

    <?php if ($has_testimonials): ?>

        <!-- Testimonials Header -->
        <div class="rv-testimonials-header">
            <h3 class="rv-testimonials-title">
                <?php echo esc_html__('Customer Reviews', 'mhm-rentiva'); ?>
            </h3>
            <div class="rv-testimonials-count">
                <?php
                printf(
                    /* translators: %d placeholder. */
                    esc_html(_n('%d review', '%d reviews', $total_count, 'mhm-rentiva')),
                    $total_count
                );
                ?>
            </div>
        </div>

        <!-- Testimonials Container -->
        <div class="rv-testimonials-container">
            <?php if ($layout === 'carousel'): ?>
                <!-- Carousel Layout -->
                <div class="rv-testimonials-carousel">
                    <div class="rv-carousel-wrapper">
                        <div class="rv-carousel-track">
                            <?php foreach ($testimonials as $testimonial): ?>
                                <div class="rv-testimonial-item rv-carousel-slide">
                                    <div class="rv-testimonial-content">
                                        <!-- Rating -->
                                        <?php if ($show_rating && $testimonial['rating'] > 0): ?>
                                            <div class="rv-testimonial-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <span class="rv-star <?php echo $i <= $testimonial['rating'] ? 'filled' : 'empty'; ?>">
                                                        <span class="dashicons dashicons-star-filled"></span>
                                                    </span>
                                                <?php endfor; ?>
                                                <span class="rv-rating-text">(<?php echo esc_html($testimonial['rating']); ?>/5)</span>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Review Text -->
                                        <div class="rv-testimonial-text">
                                            <blockquote>
                                                "<?php echo esc_html($testimonial['review']); ?>"
                                            </blockquote>
                                        </div>

                                        <!-- Customer Info -->
                                        <div class="rv-testimonial-meta">
                                            <?php if ($show_customer && !empty($testimonial['customer_name'])): ?>
                                                <div class="rv-customer-name">
                                                    <strong><?php echo esc_html($testimonial['customer_name']); ?></strong>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($show_vehicle && !empty($testimonial['vehicle_name'])): ?>
                                                <div class="rv-vehicle-name">
                                                    <span class="dashicons dashicons-car"></span>
                                                    <?php echo esc_html($testimonial['vehicle_name']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($show_date): ?>
                                                <div class="rv-review-date">
                                                    <span class="dashicons dashicons-calendar-alt"></span>
                                                    <?php echo esc_html(date_i18n(get_option('date_format', 'd.m.Y'), strtotime($testimonial['date']))); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Carousel Controls -->
                    <button class="rv-carousel-prev" aria-label="<?php echo esc_attr__('Previous', 'mhm-rentiva'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <button class="rv-carousel-next" aria-label="<?php echo esc_attr__('Next', 'mhm-rentiva'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>

                    <!-- Carousel Indicators -->
                    <div class="rv-carousel-indicators">
                        <?php for ($i = 0; $i < count($testimonials); $i++): ?>
                            <button class="rv-carousel-indicator <?php echo $i === 0 ? 'active' : ''; ?>"
                                data-slide="<?php echo esc_attr($i); ?>"></button>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Grid/List Layout -->
                <div class="rv-testimonials-<?php echo esc_attr($layout); ?>" data-columns="<?php echo esc_attr($columns); ?>">
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="rv-testimonial-item">
                            <div class="rv-testimonial-content">
                                <!-- Rating -->
                                <?php if ($show_rating && $testimonial['rating'] > 0): ?>
                                    <div class="rv-testimonial-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="rv-star <?php echo $i <= $testimonial['rating'] ? 'filled' : 'empty'; ?>">
                                                <span class="dashicons dashicons-star-filled"></span>
                                            </span>
                                        <?php endfor; ?>
                                        <span class="rv-rating-text">(<?php echo esc_html($testimonial['rating']); ?>/5)</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Review Text -->
                                <div class="rv-testimonial-text">
                                    <blockquote>
                                        "<?php echo esc_html($testimonial['review']); ?>"
                                    </blockquote>
                                </div>

                                <!-- Customer Info -->
                                <div class="rv-testimonial-meta">
                                    <?php if ($show_customer && !empty($testimonial['customer_name'])): ?>
                                        <div class="rv-customer-name">
                                            <strong><?php echo esc_html($testimonial['customer_name']); ?></strong>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($show_vehicle && !empty($testimonial['vehicle_name'])): ?>
                                        <div class="rv-vehicle-name">
                                            <span class="dashicons dashicons-car"></span>
                                            <?php echo esc_html($testimonial['vehicle_name']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($show_date): ?>
                                        <div class="rv-review-date">
                                            <span class="dashicons dashicons-calendar-alt"></span>
                                            <?php echo esc_html(date_i18n(get_option('date_format', 'd.m.Y'), strtotime($testimonial['date']))); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Load More Button -->
        <?php if (count($testimonials) < $total_count): ?>
            <div class="rv-testimonials-load-more">
                <button class="rv-load-more-btn" data-page="1">
                    <?php echo esc_html__('Load More Reviews', 'mhm-rentiva'); ?>
                    <span class="rv-loading-spinner" style="display: none;">
                        <span class="dashicons dashicons-update"></span>
                    </span>
                </button>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- No Testimonials -->
        <div class="rv-no-testimonials">
            <div class="rv-no-testimonials-icon">
                <span class="dashicons dashicons-format-quote"></span>
            </div>
            <h4><?php echo esc_html__('No Reviews Yet', 'mhm-rentiva'); ?></h4>
            <p><?php echo esc_html__('Be the first to leave a review!', 'mhm-rentiva'); ?></p>
        </div>
    <?php endif; ?>
</div>