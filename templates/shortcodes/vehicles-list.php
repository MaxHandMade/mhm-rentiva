<?php
/**
 * Vehicles List Template - LIST LAYOUT ONLY
 * 
 * @var array $atts Shortcode attributes
 * @var array $vehicles Vehicle data array
 * @var int $total_vehicles Total vehicle count
 * @var bool $has_vehicles Whether vehicles exist
 * @var string $layout_class Layout CSS class
 * @var string $columns_class Columns CSS class
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

// Get template data
$atts = $atts ?? [];
$vehicles = $vehicles ?? [];
$total_vehicles = $total_vehicles ?? 0;
$has_vehicles = $has_vehicles ?? false;
$layout_class = $layout_class ?? 'rv-vehicles-list';
$columns_class = $columns_class ?? 'rv-vehicles-list--columns-1';
$wrapper_class = $wrapper_class ?? '';
$booking_url = $booking_url ?? \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_booking_form');

?>

<div class="rv-vehicles-list-container <?php echo esc_attr($wrapper_class); ?>">

    <?php if ($has_vehicles): ?>
        
        <!-- Vehicles List - ONLY LIST LAYOUT -->
        <div class="rv-vehicles-list__wrapper <?php echo esc_attr($layout_class . ' ' . $columns_class); ?>" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            
            <?php foreach ($vehicles as $vehicle): ?>
                <div class="rv-vehicle-card rv-vehicle-card--list" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
                    
                    <!-- Left Side: Image -->
                    <?php 
                    $show_images_global = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_show_images', '1') === '1';
                    $show_images_shortcode = ($atts['show_images'] ?? null);
                    // Global setting takes priority, shortcode can override only if explicitly set
                    $show_images_final = $show_images_shortcode !== null ? ($show_images_shortcode === '1') : $show_images_global;
                    ?>
                    <?php if ($show_images_final): ?>
                    <div class="rv-vehicle-card__image">
                        <a href="<?php echo esc_url($vehicle['permalink']); ?>" class="rv-vehicle-card__image-link">
                            <img src="<?php echo esc_url($vehicle['image_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" loading="lazy" class="rv-vehicle-card__img">
                        </a>
                        
                        <?php if (($atts['show_rating'] ?? '1') === '1' && $vehicle['rating']['count'] > 0): ?>
                            <!-- Star Rating Overlay -->
                            <div class="rv-vehicle-card__rating-overlay">
                                <span class="rv-stars"><?php echo esc_html($vehicle['rating']['stars']); ?></span>
                                <span class="rv-rating-count">(<?php echo esc_html($vehicle['rating']['count']); ?>)</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Right Side: Content -->
                    <div class="rv-vehicle-card__content">
                        
                        <!-- Top Section: Title and Favorite -->
                        <div class="rv-vehicle-card__header">
                            <div class="rv-vehicle-card__title-section">
                                <?php if (($atts['show_title'] ?? '1') === '1'): ?>
                                <h3 class="rv-vehicle-card__title">
                                    <a href="<?php echo esc_url($vehicle['permalink']); ?>" class="rv-vehicle-card__title-link">
                                        <?php echo esc_html($vehicle['title']); ?>
                                    </a>
                                </h3>
                                <?php endif; ?>
                                <?php if (($atts['show_category'] ?? '1') === '1' && !empty($vehicle['category'])): ?>
                                    <span class="rv-vehicle-card__category"><?php echo esc_html($vehicle['category']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="rv-vehicle-card__header-actions">
                                <?php 
                                // Availability Badge Logic
                                $show_availability_global = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_show_availability', '1') === '1';
                                $show_availability_shortcode = ($atts['show_availability'] ?? null);
                                $show_availability_final = $show_availability_shortcode !== null ? ($show_availability_shortcode === '1') : $show_availability_global;

                                // Check availability
                                if (is_array($vehicle['availability'])) {
                                    $is_available = $vehicle['availability']['is_available'] ?? true;
                                    $status_text_from_data = $vehicle['availability']['text'] ?? null;
                                } else {
                                    $is_available = (bool) $vehicle['availability'];
                                    $status_text_from_data = null;
                                }
                                
                                // Always show if unavailable, otherwise respect setting
                                if (!$is_available || $show_availability_final): 
                                    $status_class = $is_available ? 'available' : 'unavailable';
                                    if ($status_text_from_data) {
                                        $status_text = $status_text_from_data;
                                    } else {
                                        $status_text = $is_available ? __('Available', 'mhm-rentiva') : __('Unavailable', 'mhm-rentiva');
                                    }
                                    
                                    // Only show 'Available' text if strictly requested by setting. 
                                    // Always show 'Unavailable' badge.
                                    if (!$is_available || $show_availability_final):
                                ?>
                                    <span class="rv-badge rv-badge--<?php echo esc_attr($status_class); ?> rv-badge--list-view" style="margin-right: 10px;">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                <?php 
                                    endif;
                                endif; 
                                ?>

                                <?php if (($atts['show_favorite_btn'] ?? '1') === '1'): ?>
                                <?php 
                                $is_favorite = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::is_favorite($vehicle['id']);
                                $favorite_class = $is_favorite ? 'is-favorited' : '';
                                ?>
                                <button class="rv-vehicle-card__favorite <?php echo esc_attr($favorite_class); ?>" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>" aria-label="<?php esc_attr_e('Add to favorites', 'mhm-rentiva'); ?>">
                                    <svg class="rv-heart-icon" width="20" height="20" viewBox="0 0 24 24" fill="<?php echo $is_favorite ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                    </svg>
                                </button>
                            <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Middle Section: Features -->
                        <?php 
                        $show_features_global = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_show_features', '1') === '1';
                        $show_features_shortcode = ($atts['show_features'] ?? null);
                        // Global setting takes priority, shortcode can override only if explicitly set
                        $show_features_final = $show_features_shortcode !== null ? ($show_features_shortcode === '1') : $show_features_global;
                        ?>
                        <?php if ($show_features_final && !empty($vehicle['features'])): ?>
                            <div class="rv-vehicle-card__features">
                                <?php foreach ($vehicle['features'] as $feature): ?>
                                    <div class="rv-feature-item">
                                        <?php if ($feature['icon'] === 'fuel'): ?>
                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M3 2h3l2 6h3l2-6h3l2 6h3l1 6H4l1-6z"/>
                                                <path d="M6 8h12"/>
                                                <path d="M6 12h12"/>
                                            </svg>
                                        <?php elseif ($feature['icon'] === 'gear'): ?>
                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="3"/>
                                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                            </svg>
                                        <?php elseif ($feature['icon'] === 'people'): ?>
                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                            </svg>
                                        <?php elseif ($feature['icon'] === 'calendar'): ?>
                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                <line x1="16" y1="2" x2="16" y2="6"/>
                                                <line x1="8" y1="2" x2="8" y2="6"/>
                                                <line x1="3" y1="10" x2="21" y2="10"/>
                                            </svg>
                                        <?php elseif ($feature['icon'] === 'speedometer'): ?>
                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <path d="M12 6v6l4 2"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        <?php endif; ?>
                                        <span class="rv-feature-text"><?php echo esc_html($feature['value'] ?? $feature['text'] ?? ''); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Availability Status (Previously at bottom, now moved to header) -->
                        <?php 
                        /* 
                           Availability badge moved to header section (see lines 87-98) as per user request. 
                           The code block here is removed to avoid duplication.
                        */
                        ?>
                        
                        <!-- Bottom Section: Price and Button -->
                        <?php if (($atts['show_price'] ?? '1') === '1' || ($atts['show_booking_btn'] ?? '1') === '1'): ?>
                            <div class="rv-vehicle-card__footer">
                                <?php if (($atts['show_price'] ?? '1') === '1'): ?>
                                    <div class="rv-vehicle-card__price">
                                        <span class="rv-price-amount"><?php echo esc_html($vehicle['price']['formatted']); ?></span>
                                        <span class="rv-price-period"><?php echo esc_html(esc_html__('/day', 'mhm-rentiva')); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (($atts['show_booking_btn'] ?? '1') === '1'): ?>
                                    <div class="rv-vehicle-card__actions">
                                        <?php 
                                        $btn_class = 'rv-btn rv-btn-primary rv-btn-booking';
                                        $btn_href = esc_url(add_query_arg('vehicle_id', $vehicle['id'], $booking_url));
                                        $btn_attrs = '';
                                        
                                        if (!$is_available) {
                                            $btn_class .= ' rv-btn-disabled';
                                            $btn_href = 'javascript:void(0);';
                                            $btn_attrs = 'aria-disabled="true" tabindex="-1" style="opacity: 0.6; pointer-events: none; cursor: not-allowed;"';
                                        }
                                        ?>
                                        <a href="<?php echo $btn_href; ?>" class="<?php echo esc_attr($btn_class); ?>" <?php echo $btn_attrs; ?> data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
                                            <?php echo esc_html($atts['booking_btn_text'] ?? esc_html__('Book Now', 'mhm-rentiva')); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        
        <!-- No Vehicles Message -->
        <div class="rv-vehicles-list__empty">
            <p><?php echo esc_html(esc_html__('No vehicles found yet.', 'mhm-rentiva')); ?></p>
        </div>

    <?php endif; ?>

</div>