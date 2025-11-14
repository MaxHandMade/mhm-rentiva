<?php
/**
 * My Favorites Page Template
 * 
 * Displays user's favorite vehicles
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../languages/');
    }
    mhm_rentiva_load_textdomain();
}

$user_id = get_current_user_id();
$favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true) ?: [];

?>

<div class="mhm-rentiva-account-page">
    <?php echo \MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', ['navigation' => $navigation], true); ?>
    
    <div class="mhm-account-content">
        <div class="section-header">
            <h2><?php _e('My Favorite Vehicles', 'mhm-rentiva'); ?></h2>
            <span class="view-all-link"><?php printf(esc_html__('%d vehicles in your favorites', 'mhm-rentiva'), count($favorites)); ?></span>
        </div>

        <?php if (empty($favorites)): ?>
            <!-- No favorites -->
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </div>
                <h3><?php esc_html_e('No favorite vehicles yet', 'mhm-rentiva'); ?></h3>
                <p><?php esc_html_e('You can add vehicles to your favorites by clicking the heart icon on vehicles you like.', 'mhm-rentiva'); ?></p>
                <a href="<?php 
                    $vehicles_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
                    if (!$vehicles_url) {
                        $vehicles_url = get_post_type_archive_link('vehicle');
                        if (!$vehicles_url) {
                            $vehicles_url = home_url('/');
                        }
                    }
                    echo esc_url($vehicles_url);
                ?>" class="btn btn-primary">
                    <?php esc_html_e('View Vehicles', 'mhm-rentiva'); ?>
                </a>
            </div>
        <?php else: ?>
            <!-- Favorite vehicles -->
            <div class="account-section">

                <!-- Vehicle List - Vehicles List Shortcode Structure -->
                <div class="rv-vehicles-grid-container">
                    <div class="rv-vehicles-grid rv-vehicles-grid--columns-<?php echo isset($columns) ? (int) $columns : 3; ?>">
                        <?php foreach ($favorites as $vehicle_id): 
                            $vehicle_post = get_post($vehicle_id);
                            if (!$vehicle_post || $vehicle_post->post_status !== 'publish') {
                                continue;
                            }
                            
                            // Get vehicle information
                            $vehicle_data = MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_data($vehicle_id);
                            if (!$vehicle_data) {
                                continue;
                            }
                        ?>
                            <?php 
                                $is_favorite = true;
                                $favorite_class = $is_favorite ? 'is-favorited' : '';
                                $image_url = $vehicle_data['image'] ?: MHM_RENTIVA_PLUGIN_URL . 'assets/images/no-image.png';
                                $rating_count = (int) ($vehicle_data['rating']['count'] ?? 0);
                                $rating_stars = $vehicle_data['rating']['stars'] ?? '';
                            ?>
                            <div class="rv-vehicle-card rv-vehicle-card--grid" data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>">
                                <!-- Favorite Button -->
                                <button class="rv-vehicle-card__favorite <?php echo esc_attr($favorite_class); ?>" data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>" aria-label="<?php esc_attr_e('Remove from favorites', 'mhm-rentiva'); ?>">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                    </svg>
                                </button>

                                <!-- Rating Overlay -->
                                <?php if ($rating_count > 0): ?>
                                    <div class="rv-vehicle-card__rating-overlay">
                                        <span class="rv-stars"><?php echo esc_html($rating_stars); ?></span>
                                        <span class="rv-rating-count">(<?php echo esc_html($rating_count); ?>)</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Vehicle Image -->
                                <div class="rv-vehicle-card__image">
                                    <a href="<?php echo esc_url(get_permalink($vehicle_id)); ?>">
                                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($vehicle_post->post_title); ?>" loading="lazy">
                                    </a>
                                </div>

                                <!-- Vehicle Content -->
                                <div class="rv-vehicle-card__content">
                                    <div class="rv-vehicle-card__header">
                                        <h3 class="rv-vehicle-card__title"><a href="<?php echo esc_url(get_permalink($vehicle_id)); ?>"><?php echo esc_html($vehicle_post->post_title); ?></a></h3>
                                    </div>

                                    <?php if (!empty($vehicle_data['features'])): ?>
                                        <div class="rv-vehicle-card__features">
                                            <?php foreach ($vehicle_data['features'] as $feature): ?>
                                                <div class="rv-feature-item">
                                                    <?php
                                                    $icon_type = $feature['icon'] ?? '';
                                                    switch ($icon_type) {
                                                        case 'fuel':
                                                            ?>
                                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M3 2h3l2 6h3l2-6h3l2 6h3l1 6H4l1-6z"/>
                                                                <path d="M6 8h12"/>
                                                                <path d="M6 12h12"/>
                                                            </svg>
                                                            <?php
                                                            break;
                                                        case 'gear':
                                                            ?>
                                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <circle cx="12" cy="12" r="3"/>
                                                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                                            </svg>
                                                            <?php
                                                            break;
                                                        case 'people':
                                                            ?>
                                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                                <circle cx="9" cy="7" r="4"/>
                                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                                            </svg>
                                                            <?php
                                                            break;
                                                        case 'calendar':
                                                            ?>
                                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                                <line x1="16" y1="2" x2="16" y2="6"/>
                                                                <line x1="8" y1="2" x2="8" y2="6"/>
                                                                <line x1="3" y1="10" x2="21" y2="10"/>
                                                            </svg>
                                                            <?php
                                                            break;
                                                        case 'speedometer':
                                                            ?>
                                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <circle cx="12" cy="12" r="10"/>
                                                                <path d="M12 6v6l4 2"/>
                                                            </svg>
                                                            <?php
                                                            break;
                                                        default:
                                                            ?>
                                                            <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <polyline points="20 6 9 17 4 12"/>
                                                            </svg>
                                                            <?php
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="rv-feature-text"><?php echo esc_html($feature['text'] ?? $feature['name'] ?? ''); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="rv-vehicle-card__footer">
                                        <div class="rv-vehicle-card__price">
                                            <span class="rv-price-amount"><?php echo esc_html($vehicle_data['price']['formatted'] ?? 'N/A'); ?></span>
                                            <span class="rv-price-period"><?php esc_html_e('/day', 'mhm-rentiva'); ?></span>
                                        </div>
                                        <a href="<?php echo esc_url(get_permalink($vehicle_id)); ?>" class="rv-btn-booking"><?php esc_html_e('Book Now', 'mhm-rentiva'); ?></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <!-- Clear Button -->
            <div class="form-actions">
                <button type="button" id="clear-all-favorites" class="btn btn-secondary">
                    <?php esc_html_e('Clear All Favorites', 'mhm-rentiva'); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

