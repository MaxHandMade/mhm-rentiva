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
?>

<div class="mhm-transfer-results-page">
    <div class="mhm-transfer-results__summary">
        <div class="mhm-transfer-results__summary-info">
            <h2 class="mhm-transfer-results__summary-route">
                <?php echo esc_html($origin_name); ?>
                <span class="mhm-transfer-card__route-arrow">&rarr;</span>
                <?php echo esc_html($destination_name); ?>
            </h2>
            <div class="mhm-transfer-results__summary-date">
                <span class="rv-info-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM7 11h5v5H7z" />
                    </svg>
                    <?php echo esc_html($criteria['date']); ?>
                </span>
                <span class="rv-info-item" style="margin-left: 15px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" />
                    </svg>
                    <?php echo esc_html($criteria['time']); ?>
                </span>
                <span class="rv-info-item" style="margin-left: 15px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 1.34 5 3s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
                    </svg>
                    <?php echo esc_html((string)(($criteria['adults'] ?? 0) + ($criteria['children'] ?? 0))); ?> <?php esc_html_e('Pax', 'mhm-rentiva'); ?>
                </span>
            </div>
        </div>
    </div>

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
                    </div>

                    <div class="mhm-transfer-card__info">
                        <h3 class="mhm-transfer-card__title"><?php echo esc_html($title); ?></h3>

                        <div class="mhm-transfer-card__meta">
                            <?php if ($max_pax) : ?>
                                <div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Max Passengers', 'mhm-rentiva'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 1.34 5 3s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
                                    </svg>
                                    <span><?php echo esc_html((string)$max_pax); ?> <?php esc_html_e('Pax', 'mhm-rentiva'); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($luggage_cap) : ?>
                                <div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Luggage Capacity', 'mhm-rentiva'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17 6h-2V3c0-.55-.45-1-1-1h-4c-.55 0-1 .45-1 1v3H7c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zM9 4h6v2H9V4zm10 15H5V8h14v11z" />
                                    </svg>
                                    <span><?php echo esc_html((string)$luggage_cap); ?> <?php esc_html_e('Luggage', 'mhm-rentiva'); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($distance) : ?>
                                <div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Distance', 'mhm-rentiva'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                                    </svg>
                                    <span><?php echo esc_html((string)$distance); ?> km</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($duration) : ?>
                                <div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Duration', 'mhm-rentiva'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" />
                                    </svg>
                                    <span><?php echo esc_html((string)$duration); ?> <?php esc_html_e('min', 'mhm-rentiva'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mhm-transfer-card__footer">
                            <div class="mhm-transfer-card__price">
                                <span class="mhm-transfer-card__price-amount"><?php echo $format_price($price, $currency); ?></span>
                                <span class="mhm-transfer-card__price-period"><?php esc_html_e('/total', 'mhm-rentiva'); ?></span>
                            </div>
                            <button class="mhm-transfer-card__btn js-mhm-transfer-book"
                                data-vehicle-id="<?php echo esc_attr((string)$vehicle_id); ?>"
                                data-price="<?php echo esc_attr((string)$price); ?>"
                                data-origin-id="<?php echo esc_attr((string)($criteria['origin_id'] ?? '')); ?>"
                                data-destination-id="<?php echo esc_attr((string)($criteria['destination_id'] ?? '')); ?>"
                                data-date="<?php echo esc_attr($criteria['date'] ?? ''); ?>"
                                data-time="<?php echo esc_attr($criteria['time'] ?? ''); ?>">
                                <?php esc_html_e('Book Now', 'mhm-rentiva'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>