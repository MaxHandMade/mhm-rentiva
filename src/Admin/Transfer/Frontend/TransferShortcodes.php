<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;

final class TransferShortcodes
{
    /**
     * Register shortcodes
     */
    public static function register(): void
    {
        add_shortcode('mhm_rentiva_transfer_search', [self::class, 'render_search_shortcode']);

        // Register AJAX actions for search
        add_action('wp_ajax_mhm_transfer_search', [self::class, 'handle_search_ajax']);
        add_action('wp_ajax_nopriv_mhm_transfer_search', [self::class, 'handle_search_ajax']);

        // Enqueue assets
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Enqueue assets
     */
    public static function enqueue_assets(): void
    {
        // Enqueue CSS
        if (file_exists(trailingslashit(dirname(__DIR__, 4)) . 'assets/css/transfer.css')) {
            wp_enqueue_style(
                'mhm-rentiva-transfer-css',
                plugins_url('assets/css/transfer.css', dirname(__DIR__, 4) . '/mhm-rentiva.php'),
                [],
                defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.0'
            );
        }

        // Enqueue JS
        if (file_exists(trailingslashit(dirname(__DIR__, 4)) . 'assets/js/mhm-rentiva-transfer.js')) {
            wp_enqueue_script(
                'mhm-rentiva-transfer', // Handle name updated as requested
                plugins_url('assets/js/mhm-rentiva-transfer.js', dirname(__DIR__, 4) . '/mhm-rentiva.php'),
                ['jquery'], // Dependency
                defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.0',
                true // In footer
            );

            // Localize Data
            wp_localize_script('mhm-rentiva-transfer', 'mhm_transfer_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('mhm_rentiva_transfer_nonce'),
                'cart_url' => function_exists('wc_get_cart_url') ? call_user_func('wc_get_cart_url') : '', // IDE silencing
                'i18n'     => [
                    'same_location_error' => __('Pick-up and Drop-off locations cannot be the same.', 'mhm-rentiva'),
                    'searching_text'      => __('Searching...', 'mhm-rentiva'),
                    'error_text'          => __('An error occurred. Please try again.', 'mhm-rentiva')
                ]
            ]);
        }
    }

    /**
     * Render Search Form Shortcode
     */
    public static function render_search_shortcode($atts): string
    {
        // Retrieve Locations
        global $wpdb;
        $table_locations = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        $locations = $wpdb->get_results("SELECT id, name, type FROM $table_locations WHERE is_active = 1 ORDER BY priority ASC, name ASC");

        ob_start();
?>
        <div class="mhm-transfer-search-wrapper">
            <form id="mhm-transfer-search-form">
                <div class="mhm-transfer-form-row">
                    <div class="mhm-transfer-form-group">
                        <label for="mhm-origin"><?php echo esc_html__('Pickup Location', 'mhm-rentiva'); ?></label>
                        <select name="origin_id" id="mhm-origin" required>
                            <option value=""><?php echo esc_html__('Select Location', 'mhm-rentiva'); ?></option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo esc_attr($loc->id); ?>"><?php echo esc_html($loc->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mhm-transfer-form-group">
                        <label for="mhm-destination"><?php echo esc_html__('Dropoff Location', 'mhm-rentiva'); ?></label>
                        <select name="destination_id" id="mhm-destination" required>
                            <option value=""><?php echo esc_html__('Select Location', 'mhm-rentiva'); ?></option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo esc_attr($loc->id); ?>"><?php echo esc_html($loc->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mhm-transfer-form-row">
                    <div class="mhm-transfer-form-group">
                        <label for="mhm-date"><?php echo esc_html__('Date', 'mhm-rentiva'); ?></label>
                        <input type="date" name="date" id="mhm-date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mhm-transfer-form-group">
                        <label for="mhm-time"><?php echo esc_html__('Time', 'mhm-rentiva'); ?></label>
                        <input type="time" name="time" id="mhm-time" required>
                    </div>
                </div>

                <div class="mhm-transfer-form-row">
                    <div class="mhm-transfer-form-group mhm-half">
                        <label><?php echo esc_html__('Adults', 'mhm-rentiva'); ?></label>
                        <input type="number" name="adults" value="1" min="1" required>
                    </div>
                    <div class="mhm-transfer-form-group mhm-half">
                        <label><?php echo esc_html__('Children', 'mhm-rentiva'); ?></label>
                        <input type="number" name="children" value="0" min="0">
                    </div>
                    <div class="mhm-transfer-form-group mhm-half">
                        <label><?php echo esc_html__('Big Bags', 'mhm-rentiva'); ?> <span style="color:red;">(*)</span></label>
                        <input type="number" name="luggage_big" value="0" min="0">
                    </div>
                    <div class="mhm-transfer-form-group mhm-half">
                        <label><?php echo esc_html__('Small Bags', 'mhm-rentiva'); ?> <span style="color:red;">(*)</span></label>
                        <input type="number" name="luggage_small" value="0" min="0">
                    </div>
                </div>

                <div class="mhm-transfer-form-submit">
                    <button type="submit" class="mhm-transfer-btn"><?php echo esc_html__('Search Transfer', 'mhm-rentiva'); ?></button>
                </div>

                <div class="mhm-transfer-luggage-info mt-3" style="font-size: 0.85rem; color: #6c757d; line-height: 1.4; margin-top: 15px;">
                    <p class="mb-1" style="margin-bottom: 5px;">
                        <strong class="text-danger" style="color:red;">*</strong>
                        <strong><?php echo esc_html__('Small Luggage:', 'mhm-rentiva'); ?></strong>
                        <?php echo esc_html__('Handbag, backpack or cabin size suitcase.', 'mhm-rentiva'); ?>
                    </p>
                    <p class="mb-0" style="margin-bottom: 0;">
                        <strong class="text-danger" style="color:red;">*</strong>
                        <strong><?php echo esc_html__('Big Luggage:', 'mhm-rentiva'); ?></strong>
                        <?php echo esc_html__('Medium or large check-in suitcase.', 'mhm-rentiva'); ?>
                    </p>
                </div>

                <div id="mhm-transfer-loading" style="display:none; text-align:center; padding:10px;">
                    <?php echo esc_html__('Searching...', 'mhm-rentiva'); ?>
                </div>
            </form>

            <div id="mhm-transfer-results"></div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX Search
     */
    public static function handle_search_ajax(): void
    {
        // Security Check
        check_ajax_referer('mhm_rentiva_transfer_nonce', 'security');

        $criteria = $_POST;

        $results = TransferSearchEngine::search($criteria);

        if (empty($results)) {
            wp_send_json_error(['message' => __('No vehicles found matching your criteria.', 'mhm-rentiva')]);
        }

        // We exclude action and security from criteria if present, just keep data
        // $transfer_meta_json = htmlspecialchars(json_encode($criteria), ENT_QUOTES, 'UTF-8'); // Moved inside loop

        // Initialize names
        global $wpdb;
        $origin_name = '';
        $destination_name = '';

        if (!empty($criteria['origin_id'])) {
            $origin_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}mhm_rentiva_transfer_locations WHERE id = %d", $criteria['origin_id']));
        }
        if (!empty($criteria['destination_id'])) {
            $destination_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}mhm_rentiva_transfer_locations WHERE id = %d", $criteria['destination_id']));
        }

        ob_start();
    ?>
        <div class="mhm-transfer-results-grid">
            <?php foreach ($results as $vehicle):
                // Prepare Transfer Meta per Vehicle
                $vehicle_meta = $criteria;
                $vehicle_meta['price'] = $vehicle['price'];
                $vehicle_meta['duration'] = $vehicle['duration'];
                $vehicle_meta['distance'] = $vehicle['distance'];
                $vehicle_meta['origin_name'] = $origin_name;
                $vehicle_meta['destination_name'] = $destination_name;
                $transfer_meta_json = htmlspecialchars(json_encode($vehicle_meta), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="mhm-transfer-card">
                    <div class="mhm-transfer-card-image">
                        <img src="<?php echo esc_url($vehicle['image']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>">
                    </div>
                    <div class="mhm-transfer-card-content">
                        <h3><?php echo esc_html($vehicle['title']); ?></h3>
                        <div class="mhm-transfer-features">
                            <span><i class="dashicons dashicons-groups"></i> <?php echo esc_html($vehicle['max_pax']); ?> <?php echo esc_html__('Person', 'mhm-rentiva'); ?></span>
                            <?php
                            /* Baggage info removed as per request
                            $luggage_display = $vehicle['luggage_capacity'];
                            if (isset($vehicle['max_big_luggage']) && isset($vehicle['max_small_luggage']) && ($vehicle['max_big_luggage'] !== '' || $vehicle['max_small_luggage'] !== '')) {
                                $luggage_display = (int)$vehicle['max_big_luggage'] + (int)$vehicle['max_small_luggage'];
                            }
                            ?>
                            <span><i class="dashicons dashicons-portfolio"></i> <?php echo esc_html($luggage_display); ?> <?php echo esc_html__('Luggage', 'mhm-rentiva'); ?></span>
                            */ ?>
                            <span><i class="dashicons dashicons-clock"></i> <?php echo esc_html($vehicle['duration']); ?> <?php echo esc_html__('min', 'mhm-rentiva'); ?></span>
                        </div>
                        <div class="mhm-transfer-price">
                            <strong><?php echo wc_price($vehicle['price']); ?></strong>
                        </div>
                        <button class="mhm-transfer-book-btn"
                            data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>"
                            data-transfer-meta="<?php echo $transfer_meta_json; ?>">
                            <?php echo esc_html__('Book Now', 'mhm-rentiva'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
<?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }
}
