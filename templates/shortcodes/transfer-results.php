<?php

/**
 * Transfer Search Results Template
 * 
 * @var array $data {
 *     @var array $results Search results from TransferSearchEngine.
 *     @var array $criteria Search criteria used.
 *     @var string $origin_name Name of the origin location.
 *     @var string $destination_name Name of the destination location.
 * }
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

// Variables are extracted directly from prepare_template_data() return array:
// $results, $criteria, $origin_name, $destination_name, $atts
// (Templates::render uses extract() so no $data wrapper needed)
$results          = $results ?? array();
$criteria         = $criteria ?? array();
$origin_name      = $origin_name ?? '';
$destination_name = $destination_name ?? '';

/**
 * Local helper to format price
 */
$format_price = function (float $price, string $currency = '') {
    if (function_exists('wc_price')) {
        return wc_price($price);
    }
    return $currency . number_format($price, 2);
};

// Visibility Controls
$show_summary_route        = $atts['show_summary_route'] ?? true;
$show_summary_date         = $atts['show_summary_date'] ?? true;
$show_summary_pax          = $atts['show_summary_pax'] ?? true;

$show_image                = $atts['show_image'] ?? true;
$show_category             = $atts['show_category'] ?? true;
$show_title                = $atts['show_title'] ?? true;
$show_price                = $atts['show_price'] ?? true;
$show_booking_btn          = $atts['show_booking_btn'] ?? true;
$show_vehicle_details      = $atts['show_vehicle_details'] ?? true;
$show_luggage_info         = $atts['show_luggage_info'] ?? true;
$show_passenger_count      = $atts['show_passenger_count'] ?? true;
$show_route_info           = $atts['show_route_info'] ?? true;

// v1.3.3 Visibility Bridges
$fav_val = $atts['show_favorite_button'] ?? ($atts['show_favorite_btn'] ?? true);
$show_fav = ($fav_val !== '0' && $fav_val !== 'false' && $fav_val !== false);

$comp_val = $atts['show_compare_button'] ?? ($atts['show_compare_btn'] ?? true);
$show_compare = ($comp_val !== '0' && $comp_val !== 'false' && $comp_val !== false);
?>

<div class="mhm-transfer-results-page">
    <?php if ($show_summary_route) : ?>
        <div class="mhm-transfer-results__summary">
            <div class="mhm-transfer-results__summary-info">
                <h2 class="mhm-transfer-results__summary-route">
                    <?php echo esc_html($origin_name); ?>
                    <span class="mhm-transfer-card__route-arrow">&rarr;</span>
                    <?php echo esc_html($destination_name); ?>
                </h2>
                <?php if ($show_summary_date || $show_summary_pax) : ?>
                    <div class="mhm-transfer-results__summary-date">
                        <?php if ($show_summary_date) : ?>
                            <span class="rv-info-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM7 11h5v5H7z" />
                                </svg>
                                <?php echo esc_html($criteria['date'] ?? ''); ?>
                            </span>
                            <span class="rv-info-item" style="margin-left: 15px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" />
                                </svg>
                                <?php echo esc_html($criteria['time'] ?? ''); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($show_summary_pax) : ?>
                            <span class="rv-info-item" style="margin-left: 15px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 1.34 5 3s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
                                </svg>
                                <?php echo esc_html((string)(($criteria['adults'] ?? 0) + ($criteria['children'] ?? 0))); ?> <?php esc_html_e('Pax', 'mhm-rentiva'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($results)) : ?>
        <div class="mhm-transfer-results__empty">
            <div class="mhm-transfer-results__empty-icon"><span class="dashicons dashicons-info"></span></div>
            <h3 class="mhm-transfer-results__empty-title"><?php esc_html_e('No transfers found', 'mhm-rentiva'); ?></h3>
            <p class="mhm-transfer-results__empty-text"><?php esc_html_e('No transfers found for the selected criteria. Please try different options.', 'mhm-rentiva'); ?></p>
        </div>
    <?php else : ?>
        <div class="mhm-transfer-results rv-unified-search-results">
            <?php foreach ($results as $item) :
                $vehicle_id   = $item['id'] ?? 0;
                $title        = $item['title'] ?? '';
                $image_url    = $item['image'] ?? '';
                $price        = (float)($item['price'] ?? 0);
                $currency     = $item['currency'] ?? '';
                $category     = $item['category'] ?? '';
                $max_pax      = $item['max_pax'] ?? '';
                $luggage_cap  = $item['luggage_capacity'] ?? '';
                $duration     = $item['duration'] ?? '';
                $distance     = $item['distance'] ?? '';
            ?>
                <div class="mhm-transfer-card" data-vehicle-id="<?php echo esc_attr((string)$vehicle_id); ?>">
                    <div class="mhm-card-header">
                        <?php if ($image_url) : ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" class="mhm-transfer-card__image" loading="lazy">
                        <?php else : ?>
                            <div class="rv-no-image">
                                <span class="dashicons dashicons-format-image"></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($category) : ?>
                            <span class="mhm-transfer-card__category"><?php echo esc_html($category); ?></span>
                        <?php endif; ?>

                        <div class="mhm-card-actions-overlay">
                            <?php if ($show_fav) : ?>
                                <?php
                                $is_favorite = false;
                                if (class_exists('\MHMRentiva\Admin\Services\FavoritesService')) {
                                    $current_user = get_current_user_id();
                                    if ($current_user) {
                                        $is_favorite = \MHMRentiva\Admin\Services\FavoritesService::is_favorite($current_user, $vehicle_id);
                                    }
                                }
                                ?>
                                <button class="mhm-card-favorite mhm-vehicle-favorite-btn <?php echo $is_favorite ? 'is-active' : ''; ?>"
                                    data-vehicle-id="<?php echo esc_attr((string)$vehicle_id); ?>"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_favorite')); ?>"
                                    title="<?php echo $is_favorite ? esc_attr__("Remove from Favorites", 'mhm-rentiva') : esc_attr__("Add to Favorites", 'mhm-rentiva'); ?>"
                                    aria-label="<?php echo $is_favorite ? __("Remove from Favorites", 'mhm-rentiva') : __("Add to Favorites", 'mhm-rentiva'); ?>">
                                    <svg class="mhm-heart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                </button>
                            <?php endif; ?>

                            <?php if ($show_compare) : ?>
                                <?php
                                $is_in_compare = false;
                                if (class_exists('\MHMRentiva\Admin\Services\CompareService')) {
                                    $is_in_compare = \MHMRentiva\Admin\Services\CompareService::is_in_compare($vehicle_id);
                                }
                                ?>
                                <button class="mhm-card-compare mhm-vehicle-compare-btn <?php echo $is_in_compare ? 'is-active active' : ''; ?>"
                                    data-vehicle-id="<?php echo esc_attr((string)$vehicle_id); ?>"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_compare')); ?>"
                                    aria-label="<?php echo $is_in_compare ? __("Remove Compare", 'mhm-rentiva') : __("Compare", 'mhm-rentiva'); ?>">
                                    <span class="dashicons dashicons-randomize"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mhm-transfer-card__info">
                        <?php if ($show_title) : ?>
                            <h3 class="mhm-transfer-card__title"><?php echo esc_html($title); ?></h3>
                        <?php endif; ?>

                        <?php if ($show_passenger_count || $show_luggage_info || $show_route_info || $show_vehicle_details) : ?>
                            <div class="mhm-transfer-card__meta">
                                <?php if ($max_pax && $show_passenger_count) : ?>
                                    <div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Max Passengers', 'mhm-rentiva'); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 1.34 5 3s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
                                        </svg>
                                        <span><?php echo esc_html((string)$max_pax); ?> <?php esc_html_e('Pax', 'mhm-rentiva'); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($luggage_cap && $show_luggage_info) : ?>
                                    <div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Luggage Capacity', 'mhm-rentiva'); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M17 6h-2V3c0-.55-.45-1-1-1h-4c-.55 0-1 .45-1 1v3H7c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zM9 4h6v2H9V4zm10 15H5V8h14v11z" />
                                        </svg>
                                        <span><?php echo esc_html((string)$luggage_cap); ?> <?php esc_html_e('Luggage', 'mhm-rentiva'); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($distance && $show_route_info) : ?>
                                    <div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Distance', 'mhm-rentiva'); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                                        </svg>
                                        <span><?php echo esc_html((string)$distance); ?> km</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($duration && $show_vehicle_details) : ?>
                                    <div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Duration', 'mhm-rentiva'); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" />
                                        </svg>
                                        <span><?php echo esc_html((string)$duration); ?> <?php esc_html_e('min', 'mhm-rentiva'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mhm-transfer-card__footer">
                            <?php if ($show_price) : ?>
                                <div class="mhm-transfer-card__price">
                                    <span class="mhm-transfer-card__price-amount"><?php echo $format_price($price, $currency); ?></span>
                                    <span class="mhm-transfer-card__price-period"><?php esc_html_e('/total', 'mhm-rentiva'); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($show_booking_btn) : ?>
                                <button class="mhm-transfer-card__btn js-mhm-transfer-book"
                                    data-vehicle-id="<?php echo esc_attr((string)$vehicle_id); ?>"
                                    data-price="<?php echo esc_attr((string)$price); ?>"
                                    data-origin-id="<?php echo esc_attr((string)($criteria['origin_id'] ?? '')); ?>"
                                    data-destination-id="<?php echo esc_attr((string)($criteria['destination_id'] ?? '')); ?>"
                                    data-date="<?php echo esc_attr($criteria['date'] ?? ''); ?>"
                                    data-time="<?php echo esc_attr($criteria['time'] ?? ''); ?>">
                                    <?php esc_html_e('Book Now', 'mhm-rentiva'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>