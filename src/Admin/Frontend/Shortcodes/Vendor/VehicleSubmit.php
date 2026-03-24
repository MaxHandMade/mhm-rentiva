<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Vendor;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Licensing\Mode;



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

        $current_year = (int) gmdate('Y');
        $min_year     = (int) get_option('mhm_vehicle_min_year', 1990);
        $years        = range($current_year, $min_year);

        $transmissions = array(
            'auto'      => __('Automatic', 'mhm-rentiva'),
            'manual'    => __('Manual', 'mhm-rentiva'),
            'semi_auto' => __('Semi-Automatic', 'mhm-rentiva'),
            'cvt'       => __('CVT', 'mhm-rentiva'),
        );

        $fuel_types = array(
            'petrol'   => __('Petrol', 'mhm-rentiva'),
            'diesel'   => __('Diesel', 'mhm-rentiva'),
            'hybrid'   => __('Hybrid', 'mhm-rentiva'),
            'electric' => __('Electric', 'mhm-rentiva'),
            'lpg'      => __('LPG', 'mhm-rentiva'),
        );

        $service_types = array(
            'rental'   => __('Car Rental Only', 'mhm-rentiva'),
            'transfer' => __('Transfer Only', 'mhm-rentiva'),
            'both'     => __('Both (Rental & Transfer)', 'mhm-rentiva'),
        );

        $features = array(
            'air_conditioning'  => __('Air Conditioning', 'mhm-rentiva'),
            'power_steering'    => __('Power Steering', 'mhm-rentiva'),
            'abs_brakes'        => __('ABS Brakes', 'mhm-rentiva'),
            'airbags'           => __('Airbags', 'mhm-rentiva'),
            'central_locking'   => __('Central Locking', 'mhm-rentiva'),
            'electric_windows'  => __('Electricli Pencereler', 'mhm-rentiva'),
            'power_mirrors'     => __('Electricli Aynalar', 'mhm-rentiva'),
            'fog_lights'        => __('Fog Lights', 'mhm-rentiva'),
            'cruise_control'    => __('Cruise Control', 'mhm-rentiva'),
            'bluetooth'         => __('Bluetooth', 'mhm-rentiva'),
            'usb_port'          => __('USB Port', 'mhm-rentiva'),
            'navigation'        => __('Navigation', 'mhm-rentiva'),
            'sunroof'           => __('Sunroof', 'mhm-rentiva'),
            'leather_seats'     => __('Leather Seats', 'mhm-rentiva'),
            'heated_seats'      => __('Heated Seats', 'mhm-rentiva'),
        );

        $equipment = array(
            'spare_tire'       => __('Spare Tire', 'mhm-rentiva'),
            'jack'             => __('Jack', 'mhm-rentiva'),
            'first_aid_kit'    => __('First Aid Kit', 'mhm-rentiva'),
            'fire_extinguisher' => __('Fire Extinguisher', 'mhm-rentiva'),
            'warning_triangle' => __('Warning Triangle', 'mhm-rentiva'),
            'jumper_cables'    => __('Jumper Cables', 'mhm-rentiva'),
            'ice_scraper'      => __('Ice Scraper', 'mhm-rentiva'),
            'car_cover'        => __('Car Cover', 'mhm-rentiva'),
            'child_seat'       => __('Child Seat', 'mhm-rentiva'),
            'gps_tracker'      => __('GPS Tracker', 'mhm-rentiva'),
            'dashcam'          => __('Dashcam', 'mhm-rentiva'),
            'phone_holder'     => __('Phone Holder', 'mhm-rentiva'),
            'charger'          => __('Charger', 'mhm-rentiva'),
            'cleaning_kit'     => __('Cleaning Kit', 'mhm-rentiva'),
            'emergency_kit'    => __('Emergency Kit', 'mhm-rentiva'),
        );

        ob_start();
        ?>
        <div class="mhm-vendor-apply-wrap">
            <div id="mhm-vehicle-submit-msg" class="mhm-vendor-notice" style="display:none"></div>
            <form id="mhm-vehicle-submit-form" class="mhm-vendor-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="mhm_vehicle_submit">
                <input type="hidden" name="mhm_vehicle_submit_nonce" value="<?php echo esc_attr($data['nonce']); ?>">

                <!-- Section 1: Vehicle Details -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Vehicle Details', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__row">
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-brand"><?php esc_html_e('Make (Brand)', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-brand" name="brand" required placeholder="<?php esc_attr_e('e.g. Toyota', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-model"><?php esc_html_e('Model', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-model" name="model" required placeholder="<?php esc_attr_e('e.g. Corolla', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-year"><?php esc_html_e('Model Year', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <select id="mhm-year" name="year" required>
                                <option value=""><?php esc_html_e('Select Year', 'mhm-rentiva'); ?></option>
                                <?php foreach ($years as $y) : ?>
                                    <option value="<?php echo esc_attr((string) $y); ?>"><?php echo esc_html((string) $y); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-color"><?php esc_html_e('Color', 'mhm-rentiva'); ?></label>
                            <input type="text" id="mhm-color" name="color" placeholder="<?php esc_attr_e('e.g. White', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-vehicle-city"><?php esc_html_e('Location / City', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-vehicle-city" name="city" required placeholder="<?php esc_attr_e('e.g. Istanbul', 'mhm-rentiva'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Price & Technical Details -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Price & Technical Details', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__row mhm-vendor-form__grid-3">
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-price"><?php esc_html_e('Daily Rate', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="number" id="mhm-price" name="price_per_day" required min="1" step="1" placeholder="0">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-mileage"><?php esc_html_e('Mileage', 'mhm-rentiva'); ?></label>
                            <input type="text" id="mhm-mileage" name="mileage" placeholder="<?php esc_attr_e('e.g. 45000', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-license-plate"><?php esc_html_e('License Plate', 'mhm-rentiva'); ?></label>
                            <input type="text" id="mhm-license-plate" name="license_plate" placeholder="<?php esc_attr_e('ör. 34ABC123', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-seats"><?php esc_html_e('Seats', 'mhm-rentiva'); ?></label>
                            <input type="number" id="mhm-seats" name="seats" min="1" value="5">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-doors"><?php esc_html_e('Doors', 'mhm-rentiva'); ?></label>
                            <input type="number" id="mhm-doors" name="doors" min="2" value="4">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-transmission"><?php esc_html_e('Transmission', 'mhm-rentiva'); ?></label>
                            <select id="mhm-transmission" name="transmission">
                                <?php foreach ($transmissions as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-fuel-type"><?php esc_html_e('Fuel Type', 'mhm-rentiva'); ?></label>
                            <select id="mhm-fuel-type" name="fuel_type">
                                <?php foreach ($fuel_types as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-engine-size"><?php esc_html_e('Engine Size (L)', 'mhm-rentiva'); ?></label>
                            <input type="number" id="mhm-engine-size" name="engine_size" min="0" step="0.1" placeholder="0.0">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-deposit"><?php esc_html_e('Deposit %', 'mhm-rentiva'); ?></label>
                            <input type="number" id="mhm-deposit" name="deposit" min="0" max="100" value="10">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Service Type -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Service Type', 'mhm-rentiva'); ?></h3>
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

                <!-- Section 4: Vehicle Features -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Vehicle Features', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__checkboxes mhm-vendor-form__checkboxes--grid-5">
                        <?php foreach ($features as $val => $label) : ?>
                            <label class="mhm-vendor-form__checkbox">
                                <input type="checkbox" name="features[]" value="<?php echo esc_attr($val); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section 5: Vehicle Equipment -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Vehicle Equipment', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__checkboxes mhm-vendor-form__checkboxes--grid-5">
                        <?php foreach ($equipment as $val => $label) : ?>
                            <label class="mhm-vendor-form__checkbox">
                                <input type="checkbox" name="equipment[]" value="<?php echo esc_attr($val); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section 6: Photos -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Photos', 'mhm-rentiva'); ?></h3>
                    <?php $max_photos = (int) get_option('mhm_vehicle_max_photos', 10); ?>
                    <p class="mhm-vendor-form__hint"><?php echo esc_html(sprintf(
                        /* translators: %d: max number of photos */
                        __('Upload up to %d photos. The first photo will be the main image.', 'mhm-rentiva'),
                        $max_photos
                    )); ?></p>
                    <div class="mhm-vendor-form__field">
                        <input type="file" name="photos[]" accept="image/*" multiple>
                    </div>
                </div>

                <!-- Section 7: Submit -->
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
        $brand         = sanitize_text_field(wp_unslash($_POST['brand'] ?? ''));
        $model         = sanitize_text_field(wp_unslash($_POST['model'] ?? ''));
        $year          = (int) ($_POST['year'] ?? 0);
        $color         = sanitize_text_field(wp_unslash($_POST['color'] ?? ''));
        $price_per_day = (float) ($_POST['price_per_day'] ?? 0.0);
        $mileage       = sanitize_text_field(wp_unslash($_POST['mileage'] ?? ''));
        $license_plate = strtoupper(sanitize_text_field(wp_unslash($_POST['license_plate'] ?? '')));
        $seats         = max(1, (int) ($_POST['seats'] ?? 5));
        $doors         = max(2, (int) ($_POST['doors'] ?? 4));
        $transmission  = sanitize_key(wp_unslash($_POST['transmission'] ?? ''));
        $fuel_type     = sanitize_key(wp_unslash($_POST['fuel_type'] ?? ''));
        $engine_size   = (float) ($_POST['engine_size'] ?? 0.0);
        $deposit       = min(100, max(0, (float) ($_POST['deposit'] ?? 10.0)));
        $service_type  = sanitize_key(wp_unslash($_POST['service_type'] ?? 'rental'));
        $city          = sanitize_text_field(wp_unslash($_POST['city'] ?? ''));
        $description   = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        // phpcs:enable

        // Validate allowed values.
        $allowed_transmissions = array('auto', 'manual', 'semi_auto', 'cvt');
        $allowed_fuels         = array('petrol', 'diesel', 'hybrid', 'electric', 'lpg');
        $allowed_services      = array('rental', 'transfer', 'both');
        if (! in_array($transmission, $allowed_transmissions, true)) {
            $transmission = 'auto';
        }
        if (! in_array($fuel_type, $allowed_fuels, true)) {
            $fuel_type = 'petrol';
        }
        if (! in_array($service_type, $allowed_services, true)) {
            $service_type = 'rental';
        }

        // Features and equipment arrays.
        $allowed_features = array(
            'air_conditioning',
            'power_steering',
            'abs_brakes',
            'airbags',
            'central_locking',
            'electric_windows',
            'power_mirrors',
            'fog_lights',
            'cruise_control',
            'bluetooth',
            'usb_port',
            'navigation',
            'sunroof',
            'leather_seats',
            'heated_seats',
        );
        $allowed_equipment = array(
            'spare_tire',
            'jack',
            'first_aid_kit',
            'fire_extinguisher',
            'warning_triangle',
            'jumper_cables',
            'ice_scraper',
            'car_cover',
            'child_seat',
            'gps_tracker',
            'dashcam',
            'phone_holder',
            'charger',
            'cleaning_kit',
            'emergency_kit',
        );

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_features  = isset($_POST['features'])  ? (array) wp_unslash($_POST['features'])  : array();
        $raw_equipment = isset($_POST['equipment']) ? (array) wp_unslash($_POST['equipment']) : array();
        // phpcs:enable
        $features  = array_values(array_intersect(array_map('sanitize_key', $raw_features), $allowed_features));
        $equipment = array_values(array_intersect(array_map('sanitize_key', $raw_equipment), $allowed_equipment));

        $title = trim(implode(' ', array_filter(array($brand, $model, $year > 0 ? (string) $year : ''))));
        if ($title === '') {
            $title = __('Untitled Vehicle', 'mhm-rentiva');
        }

        $post_id = wp_insert_post(
            array(
                'post_type'    => 'vehicle',
                'post_status'  => 'pending',
                'post_author'  => get_current_user_id(),
                'post_title'   => sanitize_text_field($title),
                'post_content' => $description,
            ),
            true
        );

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
        }

        update_post_meta($post_id, '_vehicle_review_status', 'pending_review');
        update_post_meta($post_id, '_mhm_rentiva_brand', $brand);
        update_post_meta($post_id, '_mhm_rentiva_model', $model);
        update_post_meta($post_id, '_mhm_rentiva_year', $year);
        update_post_meta($post_id, '_mhm_rentiva_color', $color);
        update_post_meta($post_id, '_mhm_rentiva_price_per_day', $price_per_day);
        update_post_meta($post_id, '_mhm_rentiva_mileage', $mileage);
        update_post_meta($post_id, '_mhm_rentiva_license_plate', $license_plate);
        update_post_meta($post_id, '_mhm_rentiva_seats', $seats);
        update_post_meta($post_id, '_mhm_rentiva_doors', $doors);
        update_post_meta($post_id, '_mhm_rentiva_transmission', $transmission);
        update_post_meta($post_id, '_mhm_rentiva_fuel_type', $fuel_type);
        update_post_meta($post_id, '_mhm_rentiva_engine_size', $engine_size);
        update_post_meta($post_id, '_mhm_rentiva_deposit', $deposit);
        update_post_meta($post_id, '_rentiva_vehicle_service_type', $service_type);
        update_post_meta($post_id, '_mhm_rentiva_vehicle_city', $city);
        update_post_meta($post_id, '_mhm_rentiva_features', $features);
        update_post_meta($post_id, '_mhm_rentiva_equipment', $equipment);

        // Handle photo uploads.
        if (! empty($_FILES['photos']['name'][0])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $gallery_images = array();
            $thumbnail_set  = false;
            $file_count     = min((int) get_option('mhm_vehicle_max_photos', 10), count($_FILES['photos']['name']));

            for ($i = 0; $i < $file_count; $i++) {
                // Rebuild single-file array structure for each photo.
                $_FILES['photo_single'] = array(
                    'name'     => $_FILES['photos']['name'][ $i ],
                    'type'     => $_FILES['photos']['type'][ $i ],
                    'tmp_name' => $_FILES['photos']['tmp_name'][ $i ],
                    'error'    => $_FILES['photos']['error'][ $i ],
                    'size'     => $_FILES['photos']['size'][ $i ],
                );

                $attachment_id = media_handle_upload('photo_single', $post_id);
                if (! is_wp_error($attachment_id)) {
                    if (! $thumbnail_set) {
                        set_post_thumbnail($post_id, $attachment_id);
                        $thumbnail_set = true;
                    }
                    $gallery_images[] = array(
                        'id'    => $attachment_id,
                        'url'   => wp_get_attachment_url($attachment_id),
                        'alt'   => '',
                        'title' => '',
                    );
                }
            }

            if (! empty($gallery_images)) {
                update_post_meta($post_id, '_mhm_rentiva_gallery_images', wp_json_encode($gallery_images));
            }
        }

        wp_send_json_success(array('vehicle_id' => $post_id));
    }
}
