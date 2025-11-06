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
                                            <?php foreach (array_slice($vehicle_data['features'], 0, 3) as $feature): ?>
                                                <div class="rv-feature-item">
                                                    <span class="rv-icon"><?php echo $feature['icon'] ?? '✓'; ?></span>
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

