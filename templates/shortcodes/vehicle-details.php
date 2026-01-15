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
    function mhm_rentiva_load_textdomain()
    {
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

                    <!-- Availability Badge (Overlay) -->
                    <?php if (isset($is_available) && !$is_available) : ?>
                        <div class="rv-status-badge rv-status-badge--unavailable">
                            <?php echo esc_html(!empty($status_text) ? $status_text : __('Out of Order', 'mhm-rentiva')); ?>
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
            <?php
            $card_features = $card_features ?? [];
            if (!empty($card_features)) :
            ?>
                <div class="rv-vehicle-details-compact">
                    <div class="rv-details-grid-compact">
                        <?php foreach ($card_features as $feature) : ?>
                            <div class="rv-detail-item-compact">
                                <span class="rv-detail-icon-compact">
                                    <?php if ($feature['icon'] === 'fuel') : ?>
                                        <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 2h3l2 6h3l2-6h3l2 6h3l1 6H4l1-6z" />
                                            <path d="M6 8h12" />
                                            <path d="M6 12h12" />
                                        </svg>
                                    <?php elseif ($feature['icon'] === 'gear') : ?>
                                        <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="3" />
                                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                                        </svg>
                                    <?php elseif ($feature['icon'] === 'people') : ?>
                                        <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                            <circle cx="9" cy="7" r="4" />
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                        </svg>
                                    <?php elseif ($feature['icon'] === 'calendar') : ?>
                                        <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                    <?php elseif ($feature['icon'] === 'speedometer') : ?>
                                        <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10" />
                                            <path d="M12 6v6l4 2" />
                                        </svg>
                                    <?php else : ?>
                                        <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                    <?php endif; ?>
                                </span>
                                <span class="rv-detail-value-compact"><?php echo esc_html($feature['text']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (($fuel_type ?? '') || ($seats ?? '') || ($mileage ?? '') || ($transmission ?? '') || ($year ?? '')) : ?>
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
                        <span class="rv-price-period"><?php esc_html_e('/day', 'mhm-rentiva'); ?></span>
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
                    <?php if (isset($is_available) && !$is_available) : ?>
                        <button class="rv-btn-book disabled" disabled>
                            <span class="rv-btn-text"><?php echo esc_html($atts['booking_btn_text'] ?? __('Book Now', 'mhm-rentiva')); ?></span>
                        </button>
                    <?php else : ?>
                        <a href="<?php echo esc_url($booking_url ?? ''); ?>" class="rv-btn-book">
                            <span class="rv-btn-text"><?php echo esc_html($atts['booking_btn_text'] ?? __('Book Now', 'mhm-rentiva')); ?></span>
                            <span class="rv-btn-arrow">→</span>
                        </a>
                    <?php endif; ?>
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