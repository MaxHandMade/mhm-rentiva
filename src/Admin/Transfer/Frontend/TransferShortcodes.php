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
        // Enqueue only if shortcode is present or we need it globally?? 
        // For now, let's enqueue generally or check has_shortcode if possible.
        // But since this is a static method hooked to wp_enqueue_scripts, we can just enqueue.
        // We'll trust WP to handle dependencies.

        if (file_exists(trailingslashit(dirname(__DIR__, 4)) . 'assets/css/transfer.css')) {
            wp_enqueue_style(
                'mhm-rentiva-transfer-css',
                plugins_url('assets/css/transfer.css', dirname(__DIR__, 4) . '/mhm-rentiva.php'),
                [],
                defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.0'
            );
        }

        // We might need JS for AJAX handling
        // For now, inline JS in the shortcode output is sufficient for a simple implementation,
        // or we can add a transfer.js file. Given the request asked for CSS, we'll focus on that.
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
                            <option value=""><?php echo esc_html__('Select Pickup', 'mhm-rentiva'); ?></option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo esc_attr($loc->id); ?>"><?php echo esc_html($loc->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mhm-transfer-form-group">
                        <label for="mhm-destination"><?php echo esc_html__('Dropoff Location', 'mhm-rentiva'); ?></label>
                        <select name="destination_id" id="mhm-destination" required>
                            <option value=""><?php echo esc_html__('Select Dropoff', 'mhm-rentiva'); ?></option>
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
                        <label><?php echo esc_html__('Big Bags', 'mhm-rentiva'); ?></label>
                        <input type="number" name="luggage_big" value="0" min="0">
                        <small>2.5 pts</small>
                    </div>
                    <div class="mhm-transfer-form-group mhm-half">
                        <label><?php echo esc_html__('Small Bags', 'mhm-rentiva'); ?></label>
                        <input type="number" name="luggage_small" value="0" min="0">
                        <small>1 pt</small>
                    </div>
                </div>

                <div class="mhm-transfer-form-submit">
                    <button type="submit" class="mhm-transfer-btn"><?php echo esc_html__('Search Transfer', 'mhm-rentiva'); ?></button>
                </div>

                <div id="mhm-transfer-loading" style="display:none; text-align:center; padding:10px;">
                    <?php echo esc_html__('Searching...', 'mhm-rentiva'); ?>
                </div>
            </form>

            <div id="mhm-transfer-results"></div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#mhm-transfer-search-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = $(this).serialize();

                    // Show loading
                    $('#mhm-transfer-loading').show();
                    $('#mhm-transfer-results').empty();

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData + '&action=mhm_transfer_search',
                        success: function(response) {
                            $('#mhm-transfer-loading').hide();
                            if (response.success) {
                                $('#mhm-transfer-results').html(response.data);
                            } else {
                                $('#mhm-transfer-results').html('<div class="mhm-error">' + response.data + '</div>');
                            }
                        },
                        error: function() {
                            $('#mhm-transfer-loading').hide();
                            $('#mhm-transfer-results').html('<div class="mhm-error"><?php echo esc_js(__('Search failed. Please try again.', 'mhm-rentiva')); ?></div>');
                        }
                    });
                });
            });

            // Handle Book Now Click
            $(document).on('click', '.mhm-transfer-book-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                var vehicleId = btn.data('vehicle-id');
                var formData = $('#mhm-transfer-search-form').serialize(); // Get original search criteria

                btn.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'mhm-rentiva')); ?>');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData + '&action=mhm_transfer_add_to_cart&vehicle_id=' + vehicleId,
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            alert(response.data);
                            btn.prop('disabled', false).text('<?php echo esc_js(__('Book Now', 'mhm-rentiva')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Booking failed. Please try again.', 'mhm-rentiva')); ?>');
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Book Now', 'mhm-rentiva')); ?>');
                    }
                });
            });
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX Search
     */
    public static function handle_search_ajax(): void
    {
        // Inputs are sanitized inside the engine or here? 
        // Engine sanitizes them, but good practice to double check or pass raw array and let engine handle.
        // TransferSearchEngine uses sanitize_text_field.

        $criteria = $_POST;

        $results = TransferSearchEngine::search($criteria);

        if (empty($results)) {
            wp_send_json_error(__('No vehicles found matching your criteria.', 'mhm-rentiva'));
        }

        ob_start();
    ?>
        <div class="mhm-transfer-results-grid">
            <?php foreach ($results as $vehicle): ?>
                <div class="mhm-transfer-card">
                    <div class="mhm-transfer-card-image">
                        <img src="<?php echo esc_url($vehicle['image']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>">
                    </div>
                    <div class="mhm-transfer-card-content">
                        <h3><?php echo esc_html($vehicle['title']); ?></h3>
                        <div class="mhm-transfer-features">
                            <span><i class="dashicons dashicons-groups"></i> <?php echo esc_html($vehicle['max_pax']); ?> Pax</span>
                            <span><i class="dashicons dashicons-portfolio"></i> <?php echo esc_html($vehicle['luggage_capacity']); ?> Pts</span>
                            <span><i class="dashicons dashicons-clock"></i> <?php echo esc_html($vehicle['duration']); ?> min</span>
                        </div>
                        <div class="mhm-transfer-price">
                            <strong><?php echo wc_price($vehicle['price']); ?></strong>
                        </div>
                        <button class="mhm-transfer-book-btn" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>"><?php echo esc_html__('Book Now', 'mhm-rentiva'); ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
<?php
        $html = ob_get_clean();

        wp_send_json_success($html);
    }
}
