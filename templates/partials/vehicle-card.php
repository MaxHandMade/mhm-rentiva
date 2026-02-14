<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Vehicle Card Partial Template
 * 
 * Standardized vehicle card used across Grid, List, Search, and Featured modules.
 * 
 * @var array  $vehicle  Standardized vehicle data.
 * @var string $layout   'grid' or 'list'.
 * @var array  $atts     Shortcode/Block attributes for toggleable elements.
 * 
 * @package MHMRentiva
 */

use MHMRentiva\Helpers\Icons;

if (! defined('ABSPATH')) {
    exit;
}

// Defaults
$layout     = $layout ?? 'grid';
$card_class = 'mhm-vehicle-card mhm-card--' . esc_attr($layout);
$is_grid    = ($layout === 'grid');

// v1.0 APPROVED TOGGLES
// Toggles Normalization (Strict Boolean Conversion)
$normalize_toggle = function ($val) {
    if ($val === '0' || $val === 'false' || $val === 0 || $val === false) {
        return false;
    }
    return true;
};

$show_image       = $normalize_toggle($atts['show_image'] ?? true);
$show_features    = $normalize_toggle($atts['show_features'] ?? true);
$show_price       = $normalize_toggle($atts['show_price'] ?? true);
$show_title       = $normalize_toggle($atts['show_title'] ?? true);
$show_rating      = $normalize_toggle($atts['show_rating'] ?? true);
$show_booking     = $normalize_toggle($atts['show_booking_btn'] ?? true);
$booking_text     = $atts['booking_btn_text'] ?? __('Book Now', 'mhm-rentiva');

// v1.0 DISABLED TOGGLES (Hardcoded FALSE - Deferred to v1.1+)
$show_category     = false; // FROZEN: $atts['show_category'] ?? true
$show_brand        = false; // FROZEN: $atts['show_brand'] ?? false
// v1.3.2.4 PRODUCTIZATION
$show_title       = $normalize_toggle($atts['show_title'] ?? true);
$show_description = $normalize_toggle($atts['show_description'] ?? false);

// Force Description OFF for Grid
if ($is_grid) {
    $show_description = false;
}

$rating_count = intval($vehicle['rating']['count'] ?? 0);
// Default ON unless explicitly disabled
$user_wants_rating = $normalize_toggle($atts['show_rating'] ?? true);
$show_rating = $user_wants_rating && ($rating_count > 0);

// v1.3.3 Visibility Bridges (Handles _button vs _btn suffixes)
$show_fav     = $normalize_toggle($atts['show_favorite_button'] ?? ($atts['show_favorite_btn'] ?? true));
$show_compare = $normalize_toggle($atts['show_compare_button'] ?? ($atts['show_compare_btn'] ?? true));

$show_badges       = false; // FROZEN: $atts['show_badges'] ?? true
$show_availability = false; // FROZEN: $atts['show_availability'] ?? false

// Data extraction with safe fallbacks
$vehicle_id   = $vehicle['id'] ?? 0;
$permalink    = $vehicle['permalink'] ?? '#';
$title        = $vehicle['title'] ?? '';
$excerpt      = $vehicle['excerpt'] ?? '';
$image_url    = $vehicle['image']['url'] ?? '';
$image_alt    = $vehicle['image']['alt'] ?? $title;
$price_raw    = $vehicle['price']['raw'] ?? 0;
$price_fmt    = $vehicle['price']['formatted'] ?? '';
$is_available = $vehicle['availability']['is_available'] ?? true;
$status_text  = $vehicle['availability']['text'] ?? '';
$is_featured  = $vehicle['is_featured'] ?? false;
$is_favorite  = $vehicle['is_favorite'] ?? false;
$features     = $vehicle['features'] ?? array();
$allowed_svg = array(
    'svg'      => array(
        'class'           => true,
        'viewBox'         => true,
        'fill'            => true,
        'stroke'          => true,
        'stroke-width'    => true,
        'stroke-linecap'  => true,
        'stroke-linejoin' => true,
        'xmlns'           => true,
        'width'           => true,
        'height'          => true,
        'aria-hidden'     => true,
        'focusable'       => true,
        'role'            => true,
    ),
    'path'     => array(
        'd'               => true,
        'fill'            => true,
        'stroke'          => true,
        'stroke-width'    => true,
        'stroke-linecap'  => true,
        'stroke-linejoin' => true,
    ),
    'g'        => array('fill' => true, 'stroke' => true, 'class' => true),
    'circle'   => array('cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true),
    'rect'     => array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true),
    'line'     => array('x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true),
    'polyline' => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true),
    'polygon'  => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linejoin' => true),
);
// Check Service if missing in data object (Source of Truth)
if (class_exists('\MHMRentiva\Admin\Services\FavoritesService')) {
    $current_user = get_current_user_id();
    if ($current_user) {
        $is_favorite = \MHMRentiva\Admin\Services\FavoritesService::is_favorite($current_user, $vehicle_id);
    }
}

$is_in_compare = false;
if (class_exists('\MHMRentiva\Admin\Services\CompareService')) {
    $is_in_compare = \MHMRentiva\Admin\Services\CompareService::is_in_compare($vehicle_id);
}

// Button Logic
$btn_class = 'mhm-btn-booking';
$booking_base_url = $vehicle['booking_url'] ?? ($atts['booking_url'] ?? '');
$btn_url          = add_query_arg('vehicle_id', $vehicle_id, $booking_base_url);
if (! $is_available) {
    $btn_class .= ' is-disabled';
    $btn_url    = 'javascript:void(0);';
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
                            <?php Icons::render('car', ['class' => 'mhm-placeholder-svg', 'width' => '48', 'height' => '48', 'style' => 'opacity: 0.3;']); ?>
                        </div>
                    <?php endif; ?>
            </a>

            <div class="mhm-card-actions-overlay">
                <?php if ($show_fav) : ?>
                    <button class="mhm-card-favorite mhm-vehicle-favorite-btn <?php echo esc_attr($is_favorite ? 'is-active' : ''); ?>"
                        data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_favorite')); ?>"
                        title="<?php echo $is_favorite ? esc_attr__("Remove from Favorites", 'mhm-rentiva') : esc_attr__("Add to Favorites", 'mhm-rentiva'); ?>"
                        aria-label="<?php echo $is_favorite ? esc_attr__("Remove from Favorites", 'mhm-rentiva') : esc_attr__("Add to Favorites", 'mhm-rentiva'); ?>">
                        <?php Icons::render('heart', ['class' => 'mhm-heart-icon']); ?>
                        <span class="text-label sr-only"><?php echo $is_favorite ? esc_html__("Remove from Favorites", 'mhm-rentiva') : esc_html__("Add to Favorites", 'mhm-rentiva'); ?></span>
                    </button>
                <?php endif; ?>

                <?php if ($show_compare) : ?>
                    <button class="mhm-card-compare mhm-vehicle-compare-btn <?php echo esc_attr($is_in_compare ? 'is-active active' : ''); ?>"
                        data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_compare')); ?>"
                        title="<?php esc_attr_e("Compare", 'mhm-rentiva'); ?>"
                        aria-label="<?php esc_attr_e("Compare", 'mhm-rentiva'); ?>">
                        <?php Icons::render('compare', ['class' => 'mhm-compare-icon']); ?>
                        <span class="text-label sr-only"><?php esc_html_e("Compare", 'mhm-rentiva'); ?></span>
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
                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                <?php endif; ?>
            </h3>

            <?php if ($show_description && ! empty($excerpt)) : ?>
                <div class="mhm-card-description">
                    <?php echo esc_html($excerpt); ?>
                </div>
            <?php endif; ?>

            <?php if ($show_rating && isset($vehicle['rating']['stars'])) : ?>
                <div class="mhm-card-rating" data-testid="mhm-rating" title="<?php
                                                                                /* translators: %s: average vehicle rating. */
                                                                                echo esc_attr(sprintf(esc_html__('Rated %s out of 5', 'mhm-rentiva'), (string) $vehicle['rating']['average']));
                                                                                ?>">
                    <span class="mhm-stars"><?php echo $vehicle['rating']['stars']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                            ?></span>
                    <span class="mhm-rating-count">(<?php echo intval($vehicle['rating']['count']); ?>)</span>
                    <?php if (! empty($vehicle['rating']['confidence_label'])) : ?>
                        <span class="mhm-rating-confidence mhm-confidence--<?php echo esc_attr($vehicle['rating']['confidence_key']); ?>"
                            title="<?php echo esc_attr($vehicle['rating']['confidence_tooltip'] ?? ''); ?>">
                            <?php echo esc_html($vehicle['rating']['confidence_label']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($is_grid && $show_price) : ?>
                <div class="mhm-card-price" data-testid="mhm-price">
                    <span class="mhm-price-amount"><?php echo esc_html($price_fmt); ?></span>
                    <span class="mhm-price-period"><?php esc_html_e('/day', 'mhm-rentiva'); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($show_features && ! empty($features)) : ?>
                <div class="mhm-card-features">
                    <?php
                    $limit = $is_grid ? 4 : 6;
                    $count = 0;
                    foreach ($features as $feature) :
                        if ($count >= $limit) break;
                        $feature_label = (string) ($feature['text'] ?? $feature['value'] ?? '');
                        $feature_svg   = isset($feature['svg']) ? (string) $feature['svg'] : '';
                        if ($feature_svg !== '') {
                            $feature_svg = preg_replace('/<svg\b/', '<svg aria-hidden="true" focusable="false"', $feature_svg, 1) ?: $feature_svg;
                        }
                    ?>
                        <span class="mhm-feature-chip" title="<?php echo esc_attr($feature_label); ?>" aria-label="<?php echo esc_attr($feature_label); ?>">
                            <?php if ($feature_svg !== '') echo wp_kses($feature_svg, $allowed_svg); ?>
                            <?php echo esc_html($feature_label); ?>
                        </span>
                    <?php $count++;
                    endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (! $is_grid) : // List View Sidebar 
        ?>
            <div class="mhm-content-actions">
                <?php if ($show_price) : ?>
                    <div class="mhm-card-price">
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
        <?php elseif ($show_booking) : // Grid Footer 
        ?>
            <div class="mhm-card-footer">
                <a href="<?php echo esc_url($btn_url); ?>" class="<?php echo esc_attr($btn_class); ?>">
                    <?php echo esc_html($booking_text); ?>
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>
