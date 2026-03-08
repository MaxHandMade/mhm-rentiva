<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Vendor;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Licensing\Mode;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * [rentiva_vehicle_submit] Shortcode
 *
 * Renders a vehicle submission form for vendors. Handles AJAX via wp_ajax_mhm_vehicle_submit.
 */
final class VehicleSubmit extends AbstractShortcode
{
    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_vehicle_submit';
    }

    protected static function get_template_path(): string
    {
        return 'vendor/vehicle-submit';
    }

    protected static function get_default_attributes(): array
    {
        return array();
    }

    /**
     * Register the AJAX handler for vehicle submissions.
     */
    protected static function register_ajax_handlers(): void
    {
        add_action('wp_ajax_mhm_vehicle_submit', array(static::class, 'handle_ajax'));
    }

    /**
     * Prepare template data with access control checks.
     *
     * @param array $atts Shortcode attributes.
     * @return array Template data.
     */
    protected static function prepare_template_data(array $atts): array
    {
        if (! Mode::canUseVendorMarketplace()) {
            return array('pro_required' => true);
        }

        if (! is_user_logged_in()) {
            return array(
                'pro_required'   => false,
                'login_required' => true,
            );
        }

        $user      = wp_get_current_user();
        $is_vendor = in_array('rentiva_vendor', (array) $user->roles, true);

        if (! $is_vendor) {
            return array(
                'pro_required'   => false,
                'login_required' => false,
                'vendor_only'    => true,
            );
        }

        return array(
            'pro_required'   => false,
            'login_required' => false,
            'vendor_only'    => false,
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('mhm_vehicle_submit'),
        );
    }

    /**
     * Render shortcode output directly.
     *
     * @param array       $atts    Shortcode attributes.
     * @param string|null $content Shortcode content.
     * @return string Rendered HTML.
     */
    public static function render(array $atts = array(), ?string $content = null): string
    {
        $data = static::prepare_template_data($atts);

        if (! empty($data['pro_required'])) {
            return '<div class="mhm-vendor-notice mhm-vendor-notice--pro"><p>'
                . esc_html__('This feature requires Rentiva Pro.', 'mhm-rentiva')
                . '</p></div>';
        }

        if (! empty($data['login_required'])) {
            return '<div class="mhm-vendor-notice mhm-vendor-notice--warn"><p>'
                . esc_html__('Please log in to submit a vehicle.', 'mhm-rentiva')
                . '</p></div>';
        }

        if (! empty($data['vendor_only'])) {
            return '<div class="mhm-vendor-notice mhm-vendor-notice--info"><p>'
                . esc_html__('Only approved vendors can submit vehicles. Apply as a vendor first.', 'mhm-rentiva')
                . '</p></div>';
        }

        static::enqueue_assets();

        $current_year  = (int) gmdate('Y');
        $years         = range($current_year, 2000);
        $service_types = array(
            'rental'   => __('Car Rental', 'mhm-rentiva'),
            'transfer' => __('VIP Transfer', 'mhm-rentiva'),
            'both'     => __('Both (Rental & Transfer)', 'mhm-rentiva'),
        );

        ob_start();
        ?>
        <div class="mhm-vendor-apply-wrap">
            <div id="mhm-vehicle-submit-msg" class="mhm-vendor-notice" style="display:none"></div>
            <form id="mhm-vehicle-submit-form" class="mhm-vendor-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="mhm_vehicle_submit">
                <input type="hidden" name="mhm_vehicle_submit_nonce" value="<?php echo esc_attr($data['nonce']); ?>">

                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Vehicle Details', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__row">
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-make"><?php esc_html_e('Make (Brand)', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-make" name="make" required placeholder="<?php esc_attr_e('e.g. Toyota', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-model"><?php esc_html_e('Model', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-model" name="model" required placeholder="<?php esc_attr_e('e.g. Corolla', 'mhm-rentiva'); ?>">
                        </div>
                    </div>
                    <div class="mhm-vendor-form__row">
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-year"><?php esc_html_e('Year', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <select id="mhm-year" name="year" required>
                                <option value=""><?php esc_html_e('Select Year', 'mhm-rentiva'); ?></option>
                                <?php foreach ($years as $y) : ?>
                                    <option value="<?php echo esc_attr((string) $y); ?>"><?php echo esc_html((string) $y); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-service-type"><?php esc_html_e('Service Type', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <select id="mhm-service-type" name="service_type" required>
                                <option value=""><?php esc_html_e('Select Service', 'mhm-rentiva'); ?></option>
                                <?php foreach ($service_types as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mhm-vendor-form__row">
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-price"><?php esc_html_e('Daily Price (₺)', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="number" id="mhm-price" name="price_per_day" required min="1" step="1" placeholder="0">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-vehicle-city"><?php esc_html_e('Vehicle Location', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-vehicle-city" name="city" required placeholder="<?php esc_attr_e('e.g. Istanbul', 'mhm-rentiva'); ?>">
                        </div>
                    </div>
                </div>

                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Description', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__field">
                        <label for="mhm-description"><?php esc_html_e('Vehicle Description', 'mhm-rentiva'); ?></label>
                        <textarea id="mhm-description" name="description" rows="5" placeholder="<?php esc_attr_e('Describe your vehicle, features, condition...', 'mhm-rentiva'); ?>"></textarea>
                    </div>
                </div>

                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Photos', 'mhm-rentiva'); ?></h3>
                    <p class="mhm-vendor-form__hint"><?php esc_html_e('Upload up to 5 photos. First photo will be the main image. JPG or PNG, max 5MB each.', 'mhm-rentiva'); ?></p>
                    <div class="mhm-vendor-form__field">
                        <input type="file" name="photos[]" accept="image/*" multiple>
                    </div>
                </div>

                <div class="mhm-vendor-form__submit">
                    <button type="submit" class="mhm-vendor-form__btn mhm-vendor-form__btn--primary" id="mhm-vehicle-submit-btn">
                        <?php esc_html_e('Submit Vehicle for Review', 'mhm-rentiva'); ?>
                    </button>
                    <span class="mhm-vendor-form__spinner" id="mhm-vehicle-submit-spinner" style="display:none">
                        <?php esc_html_e('Submitting...', 'mhm-rentiva'); ?>
                    </span>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue CSS and JS assets for the vehicle submit form.
     *
     * @param array $atts Shortcode attributes (unused).
     */
    protected static function enqueue_assets(array $atts = array()): void
    {
        wp_enqueue_style(
            'mhm-rentiva-vendor-forms',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/vendor-forms.css',
            array(),
            MHM_RENTIVA_VERSION
        );
        wp_enqueue_script(
            'mhm-rentiva-vehicle-submit',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/vehicle-submit.js',
            array('jquery'),
            MHM_RENTIVA_VERSION,
            true
        );
        wp_localize_script('mhm-rentiva-vehicle-submit', 'mhmVehicleSubmit', array(
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'successMsg' => __('Your vehicle has been submitted for review! We will notify you once approved.', 'mhm-rentiva'),
            'errorMsg'   => __('Something went wrong. Please try again.', 'mhm-rentiva'),
        ));
    }

    /**
     * AJAX handler for vehicle submission with photo upload support.
     */
    public static function handle_ajax(): void
    {
        check_ajax_referer('mhm_vehicle_submit', 'mhm_vehicle_submit_nonce');

        if (! is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'mhm-rentiva')));
        }

        $user      = wp_get_current_user();
        $is_vendor = in_array('rentiva_vendor', (array) $user->roles, true);
        if (! $is_vendor) {
            wp_send_json_error(array('message' => __('Only approved vendors can submit vehicles.', 'mhm-rentiva')));
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $make          = sanitize_text_field(wp_unslash($_POST['make'] ?? ''));
        $model         = sanitize_text_field(wp_unslash($_POST['model'] ?? ''));
        $year          = (int) ($_POST['year'] ?? 0);
        $price_per_day = (float) ($_POST['price_per_day'] ?? 0.0);
        $description   = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $city          = sanitize_text_field(wp_unslash($_POST['city'] ?? ''));
        $service_type  = sanitize_key(wp_unslash($_POST['service_type'] ?? ''));
        // phpcs:enable

        if ($service_type !== '' && ! in_array($service_type, array('rental', 'transfer', 'both'), true)) {
            $service_type = '';
        }

        $title = trim(implode(' ', array_filter(array($make, $model, (string) $year))));
        if ($title === '') {
            $title = __('Untitled Vehicle', 'mhm-rentiva');
        }

        $post_id = wp_insert_post(array(
            'post_type'    => 'vehicle',
            'post_status'  => 'pending',
            'post_author'  => get_current_user_id(),
            'post_title'   => sanitize_text_field($title),
            'post_content' => $description,
        ), true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
        }

        update_post_meta($post_id, '_vehicle_review_status', 'pending_review');
        update_post_meta($post_id, '_mhm_rentiva_vehicle_make', $make);
        update_post_meta($post_id, '_mhm_rentiva_vehicle_model', $model);
        update_post_meta($post_id, '_mhm_rentiva_vehicle_year', $year);
        update_post_meta($post_id, '_mhm_rentiva_price_per_day', $price_per_day);
        update_post_meta($post_id, '_mhm_rentiva_vehicle_city', $city);
        update_post_meta($post_id, '_mhm_rentiva_service_type', $service_type);

        // Handle photo uploads
        if (! empty($_FILES['photos']['name'][0])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $photo_ids     = array();
            $thumbnail_set = false;
            $file_count    = min(5, count($_FILES['photos']['name']));

            for ($i = 0; $i < $file_count; $i++) {
                // Rebuild single-file array structure for each photo
                $_FILES['photo_single'] = array(
                    'name'     => $_FILES['photos']['name'][ $i ],
                    'type'     => $_FILES['photos']['type'][ $i ],
                    'tmp_name' => $_FILES['photos']['tmp_name'][ $i ],
                    'error'    => $_FILES['photos']['error'][ $i ],
                    'size'     => $_FILES['photos']['size'][ $i ],
                );

                $attachment_id = media_handle_upload('photo_single', $post_id);
                if (! is_wp_error($attachment_id)) {
                    $photo_ids[] = $attachment_id;
                    if (! $thumbnail_set) {
                        set_post_thumbnail($post_id, $attachment_id);
                        $thumbnail_set = true;
                    }
                }
            }

            if (! empty($photo_ids)) {
                update_post_meta($post_id, '_mhm_rentiva_vehicle_photos', $photo_ids);
            }
        }

        wp_send_json_success(array('vehicle_id' => $post_id));
    }
}
