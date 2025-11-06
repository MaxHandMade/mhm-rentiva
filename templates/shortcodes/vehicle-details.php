<?php
/**
 * Vehicle Details Template
 * 
 * Clean template for vehicle detail page
 * 
 * @var int $vehicle_id Vehicle ID
 * @var object $vehicle WP_Post object
 * @var array $atts Shortcode parameters
 * @var string $title Vehicle title
 * @var string $content Vehicle content
 * @var array $featured_image Featured image
 * @var array $gallery Gallery images
 * @var string $brand Brand
 * @var string $model Model
 * @var string $year Year
 * @var string $fuel_type Fuel type
 * @var string $transmission Transmission
 * @var string $seats Seat count
 * @var string $doors Door count
 * @var float $price_per_day Daily price
 * @var string $currency_symbol Currency symbol
 * @var array $features Features
 * @var array $specifications Technical specifications
 * @var array $categories Categories
 * @var string $booking_url Booking URL
 * @var array $rating Rating information
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

<div class="rv-vehicle-details-wrapper">
    <div class="rv-vehicle-details">
        
        <!-- Left Column: Gallery and Meta Information -->
        <div class="rv-vehicle-gallery-section">
            
            <!-- Gallery -->
            <?php if (isset($atts['show_gallery']) && $atts['show_gallery'] && !empty($gallery)) : ?>
                <!-- Main Image -->
                <div class="rv-main-image">
                    <img src="<?php echo esc_url($featured_image['url'] ?? ''); ?>" 
                         alt="<?php echo esc_attr($title ?? ''); ?>"
                         class="rv-featured-image">
                    
                    <?php if (!empty($categories)) : ?>
                        <div class="rv-category-badge">
                            <?php echo esc_html($categories[0]['name']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Thumbnail Gallery -->
                <div class="rv-gallery-thumbnails">
                    <?php foreach ($gallery as $index => $image) : ?>
                        <div class="rv-thumbnail-item" data-index="<?php echo esc_attr($index); ?>">
                            <img src="<?php echo esc_url($image['url']); ?>" 
                                 alt="<?php echo esc_attr($image['alt']); ?>"
                                 data-large="<?php echo esc_url($image['url_large']); ?>"
                                 data-full="<?php echo esc_url($image['url_full']); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <!-- Only Main Image -->
                <div class="rv-main-image">
                    <img src="<?php echo esc_url($featured_image['url'] ?? ''); ?>" 
                         alt="<?php echo esc_attr($title ?? ''); ?>"
                         class="rv-featured-image">
                </div>
            <?php endif; ?>

            <!-- Vehicle Meta Information -->
            <?php if (($fuel_type ?? '') || ($seats ?? '') || ($mileage ?? '') || ($transmission ?? '') || ($year ?? '')) : ?>
                <div class="rv-vehicle-details-compact">
                    <div class="rv-details-grid-compact">
                        <?php if ($fuel_type ?? '') : ?>
                            <div class="rv-detail-item-compact">
                                <span class="rv-detail-icon-compact">⛽</span>
                                <span class="rv-detail-value-compact"><?php echo esc_html($fuel_type); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($seats ?? '') : ?>
                            <div class="rv-detail-item-compact">
                                <span class="rv-detail-icon-compact">👥</span>
                                <span class="rv-detail-value-compact"><?php echo esc_html($seats); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($mileage ?? '') : ?>
                            <div class="rv-detail-item-compact">
                                <span class="rv-detail-icon-compact">🕒</span>
                                <span class="rv-detail-value-compact"><?php echo esc_html(number_format($mileage, 0, ',', '.')); ?> km</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($transmission ?? '') : ?>
                            <div class="rv-detail-item-compact">
                                <span class="rv-detail-icon-compact">⚙️</span>
                                <span class="rv-detail-value-compact"><?php echo esc_html($transmission); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($year ?? '') : ?>
                            <div class="rv-detail-item-compact">
                                <span class="rv-detail-icon-compact">📅</span>
                                <span class="rv-detail-value-compact"><?php echo esc_html($year); ?></span>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endif; ?>

            <!-- Vehicle Description - Moved below the vehicle meta information -->
            <?php if ($content ?? '') : ?>
                <div class="rv-description">
                    <h3><?php esc_html_e('Vehicle Description', 'mhm-rentiva'); ?></h3>
                    <div class="rv-description-content">
                        <?php echo wp_kses_post($content); ?>
                    </div>
                </div>
            <?php endif; ?>


        </div>

        <!-- Right Column: Details -->
        <div class="rv-vehicle-info-section">
            
            <!-- Title and Price -->
            <div class="rv-header">
                <h1 class="rv-title"><?php echo esc_html($title ?? ''); ?></h1>
                
                <?php if (($atts['show_price'] ?? true) && ($price_per_day ?? 0)) : ?>
                    <div class="rv-price-badge">
                        <span class="rv-price-amount"><?php echo esc_html(($currency_symbol ?? '$') . number_format($price_per_day)); ?></span>
                        <span class="rv-price-period">/day</span>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Rating -->
            <div class="rv-rating">
                <?php if (($rating ?? []) && isset($rating['average']) && $rating['average'] > 0) : ?>
                    <div class="rv-stars">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <span class="rv-star <?php echo $i <= $rating['average'] ? 'filled' : ''; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <span class="rv-rating-text">
                        <?php echo esc_html(number_format($rating['average'], 1)); ?> 
                        (<?php echo esc_html($rating['count'] ?? 0); ?> <?php esc_html_e('reviews', 'mhm-rentiva'); ?>)
                    </span>
                <?php else : ?>
                    <div class="rv-stars">
                        <span class="rv-star">★</span>
                        <span class="rv-star">★</span>
                        <span class="rv-star">★</span>
                        <span class="rv-star">★</span>
                        <span class="rv-star">★</span>
                    </div>
                    <span class="rv-rating-text">
                        <?php esc_html_e('Not yet rated', 'mhm-rentiva'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Book Now Button -->
            <?php if ($atts['show_booking_button'] ?? true) : ?>
                <div class="rv-booking-action">
                    <a href="<?php echo esc_url($booking_url ?? ''); ?>" class="rv-btn-book">
                        <span class="rv-btn-text"><?php echo esc_html($atts['booking_btn_text'] ?? __('Book Now', 'mhm-rentiva')); ?></span>
                        <span class="rv-btn-arrow">→</span>
                    </a>
                </div>
            <?php endif; ?>


            <!-- Monthly Availability Calendar -->
            <?php if (($vehicle_id ?? 0) > 0) : ?>
            <div class="rv-monthly-calendar" data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>">
                <div class="rv-calendar-header">
                    <h3><?php esc_html_e('Availability Calendar', 'mhm-rentiva'); ?></h3>
                    <div class="rv-calendar-navigation">
                        <button class="rv-calendar-nav-btn" data-direction="prev" title="<?php esc_attr_e('Previous Month', 'mhm-rentiva'); ?>">
                            <span class="rv-nav-icon">‹</span>
                        </button>
                        <span class="rv-calendar-month-year" id="rv-current-month-year">
                            <?php echo esc_html(date_i18n('F Y')); ?>
                        </span>
                        <button class="rv-calendar-nav-btn" data-direction="next" title="<?php esc_attr_e('Next Month', 'mhm-rentiva'); ?>">
                            <span class="rv-nav-icon">›</span>
                        </button>
                    </div>
                </div>
                <div class="rv-calendar-container" id="rv-calendar-container">
                    <?php echo \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails::render_monthly_calendar($vehicle_id); ?>
                </div>
            </div>
            <?php endif; ?>


        </div>

    </div>
</div>