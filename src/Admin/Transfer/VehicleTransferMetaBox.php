<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer;

if (!defined('ABSPATH')) {
    exit;
}

final class VehicleTransferMetaBox
{
    /**
     * Register meta box
     */
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'add_meta_box']);
        add_action('save_post', [self::class, 'save_meta_box']);
    }

    /**
     * Add meta box to Vehicle post type
     */
    public static function add_meta_box(): void
    {
        add_meta_box(
            'mhm_rentiva_vehicle_transfer_settings',
            __('Transfer Settings (VIP Module)', 'mhm-rentiva'),
            [self::class, 'render_meta_box'],
            'vehicle',
            'side',
            'default'
        );
    }

    /**
     * Render Meta Box content
     */
    public static function render_meta_box(\WP_Post $post): void
    {
        // Add nonce for security
        wp_nonce_field('mhm_vehicle_transfer_settings_nonce', 'mhm_vehicle_transfer_settings_nonce');

        // Retrieve existing values
        $service_type = get_post_meta($post->ID, '_mhm_vehicle_service_type', true) ?: 'rental';
        $max_pax = get_post_meta($post->ID, '_mhm_transfer_max_pax', true) ?: '';
        $luggage_score = get_post_meta($post->ID, '_mhm_transfer_max_luggage_score', true) ?: '';

?>
        <div class="mhm-meta-box-content">
            <!-- Service Type -->
            <p>
                <label for="mhm_vehicle_service_type"><strong><?php echo esc_html__('Service Type', 'mhm-rentiva'); ?></strong></label><br>
                <select name="mhm_vehicle_service_type" id="mhm_vehicle_service_type" style="width:100%;">
                    <option value="rental" <?php selected($service_type, 'rental'); ?>><?php echo esc_html__('Rental Only', 'mhm-rentiva'); ?></option>
                    <option value="transfer" <?php selected($service_type, 'transfer'); ?>><?php echo esc_html__('Transfer Only', 'mhm-rentiva'); ?></option>
                    <option value="both" <?php selected($service_type, 'both'); ?>><?php echo esc_html__('Both (Rental & Transfer)', 'mhm-rentiva'); ?></option>
                </select>
            </p>

            <hr>

            <!-- Transfer Capacity -->
            <div id="mhm-transfer-fields" style="<?php echo ($service_type === 'rental') ? 'display:none;' : ''; ?>">
                <p>
                    <label for="mhm_transfer_max_pax"><strong><?php echo esc_html__('Max Passengers', 'mhm-rentiva'); ?></strong></label><br>
                    <input type="number" name="mhm_transfer_max_pax" id="mhm_transfer_max_pax" value="<?php echo esc_attr($max_pax); ?>" style="width:100%;" min="1">
                    <span class="description"><?php echo esc_html__('Net capacity excluding driver.', 'mhm-rentiva'); ?></span>
                </p>

                <p>
                    <label for="mhm_transfer_max_luggage_score"><strong><?php echo esc_html__('Luggage Score Capacity', 'mhm-rentiva'); ?></strong></label><br>
                    <input type="number" name="mhm_transfer_max_luggage_score" id="mhm_transfer_max_luggage_score" value="<?php echo esc_attr($luggage_score); ?>" style="width:100%;" step="0.5" min="0">
                    <br>
                    <span class="description" style="color:#666; font-size:12px;">
                        <?php echo esc_html__('Validations: Small Bag = 1, Big Bag = 2.5 points.', 'mhm-rentiva'); ?>
                    </span>
                </p>
            </div>

            <script>
                jQuery(document).ready(function($) {
                    $('#mhm_vehicle_service_type').on('change', function() {
                        if ($(this).val() === 'rental') {
                            $('#mhm-transfer-fields').slideUp();
                        } else {
                            $('#mhm-transfer-fields').slideDown();
                        }
                    });
                });
            </script>
        </div>
<?php
    }

    /**
     * Save Meta Box data
     */
    public static function save_meta_box(int $post_id): void
    {
        // Check if our nonce is set.
        if (!isset($_POST['mhm_vehicle_transfer_settings_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['mhm_vehicle_transfer_settings_nonce'], 'mhm_vehicle_transfer_settings_nonce')) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save Service Type
        if (isset($_POST['mhm_vehicle_service_type'])) {
            update_post_meta($post_id, '_mhm_vehicle_service_type', sanitize_text_field($_POST['mhm_vehicle_service_type']));
        }

        // Save Max Pax
        if (isset($_POST['mhm_transfer_max_pax'])) {
            update_post_meta($post_id, '_mhm_transfer_max_pax', intval($_POST['mhm_transfer_max_pax']));
        }

        // Save Luggage Score
        if (isset($_POST['mhm_transfer_max_luggage_score'])) {
            update_post_meta($post_id, '_mhm_transfer_max_luggage_score', floatval($_POST['mhm_transfer_max_luggage_score']));
        }
    }
}
