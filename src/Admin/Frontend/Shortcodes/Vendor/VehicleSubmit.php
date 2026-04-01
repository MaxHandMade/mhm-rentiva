<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Vendor;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Utilities\CityHelper;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Transfer\Engine\LocationProvider;



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
        add_action('wp_ajax_nopriv_mhm_vehicle_submit', array(static::class, 'handle_ajax'));
        add_action('wp_ajax_mhm_vehicle_get_edit_data', array(static::class, 'handle_get_edit_data'));
        add_action('wp_ajax_mhm_vehicle_update', array(static::class, 'handle_update'));
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

        // Fetch vehicle categories for the dropdown.
        $categories = get_terms(array(
            'taxonomy'   => 'vehicle_category',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));
        if (is_wp_error($categories)) {
            $categories = array();
        }

        // Fetch vendor's city.
        $vendor_city = get_user_meta(get_current_user_id(), '_mhm_rentiva_vendor_city', true);
        $vendor_city = is_string($vendor_city) ? trim($vendor_city) : '';

        // Fetch rental pickup locations filtered by vendor's city.
        $rental_locations = $vendor_city !== '' ? LocationProvider::get_by_city($vendor_city, 'rental') : array();

        // Fetch transfer locations filtered by vendor's city.
        $transfer_locations = array();
        $transfer_routes    = array();

        if ($vendor_city !== '') {
            $transfer_locations = LocationProvider::get_by_city($vendor_city, 'transfer');

            if (! empty($transfer_locations)) {
                $city_location_ids = array_map(function ($loc) {
                    return (int) $loc->id;
                }, $transfer_locations);

                global $wpdb;
                $routes_table = $wpdb->prefix . 'rentiva_transfer_routes';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $rt_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $routes_table));
                if ($rt_exists !== $routes_table) {
                    $routes_table = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
                }
                $loc_table = $wpdb->prefix . 'rentiva_transfer_locations';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $lt_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $loc_table));
                if ($lt_exists !== $loc_table) {
                    $loc_table = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
                }

                $placeholders = implode(',', array_fill(0, count($city_location_ids), '%d'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                $transfer_routes = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT r.id, r.origin_id, r.destination_id, r.base_price, r.min_price, r.max_price,
                                o.name AS origin_name, d.name AS destination_name
                         FROM {$routes_table} r
                         LEFT JOIN {$loc_table} o ON r.origin_id = o.id
                         LEFT JOIN {$loc_table} d ON r.destination_id = d.id
                         WHERE r.origin_id IN ({$placeholders}) AND r.destination_id IN ({$placeholders})
                         ORDER BY o.name ASC, d.name ASC",
                        array_merge($city_location_ids, $city_location_ids)
                    )
                );
                if (! is_array($transfer_routes)) {
                    $transfer_routes = array();
                }
            }
        }

        return array(
            'pro_required'       => false,
            'login_required'     => false,
            'vendor_only'        => false,
            'ajax_url'           => admin_url('admin-ajax.php'),
            'nonce'              => wp_create_nonce('mhm_vehicle_submit'),
            'categories'         => $categories,
            'rental_locations'   => $rental_locations,
            'transfer_locations' => $transfer_locations,
            'transfer_routes'    => $transfer_routes,
            'vendor_city'        => $vendor_city,
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

        $min_photos   = (int) get_option('mhm_vehicle_min_photos', 4);
        $max_photos   = (int) get_option('mhm_vehicle_max_photos', 8);
        $categories         = $data['categories'] ?? array();
        $transfer_locations = $data['transfer_locations'] ?? array();
        $transfer_routes    = $data['transfer_routes'] ?? array();

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
            'electric_windows'  => __('Electric Windows', 'mhm-rentiva'),
            'power_mirrors'     => __('Electric Mirrors', 'mhm-rentiva'),
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
                            <label for="mhm-color"><?php esc_html_e('Color', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-color" name="color" required placeholder="<?php esc_attr_e('e.g. White', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-vehicle-city"><?php esc_html_e('Location / City', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <?php if (! empty($data['vendor_city'])) : ?>
                                <input type="text" id="mhm-vehicle-city" name="city" required
                                       value="<?php echo esc_attr($data['vendor_city']); ?>" readonly class="mhm-vendor-form__readonly">
                                <p class="mhm-vendor-form__hint"><?php esc_html_e('City is set based on your vendor profile. Contact admin to change.', 'mhm-rentiva'); ?></p>
                            <?php else : ?>
                                <?php echo CityHelper::render_select('city', 'mhm-vehicle-city', '', array('required' => true)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                        </div>
                        <?php if (! empty($data['rental_locations'])) : ?>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-location-id"><?php esc_html_e('Pickup Location', 'mhm-rentiva'); ?></label>
                            <select id="mhm-location-id" name="location_id">
                                <option value=""><?php esc_html_e('Select Pickup Location', 'mhm-rentiva'); ?></option>
                                <?php foreach ($data['rental_locations'] as $loc) : ?>
                                    <option value="<?php echo esc_attr((string) $loc->id); ?>"><?php echo esc_html($loc->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mhm-vendor-form__hint"><?php esc_html_e('The main pickup point for this vehicle in your city.', 'mhm-rentiva'); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (! empty($categories)) : ?>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-category"><?php esc_html_e('Vehicle Category', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <select id="mhm-category" name="vehicle_category" required>
                                <option value=""><?php esc_html_e('Select Category', 'mhm-rentiva'); ?></option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr((string) $cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 1b: Short Description -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Short Description', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__field">
                        <label for="mhm-description"><?php esc_html_e('Vehicle Description', 'mhm-rentiva'); ?></label>
                        <textarea id="mhm-description" name="description" rows="4" placeholder="<?php esc_attr_e('Briefly describe the vehicle — condition, highlights, any extras included.', 'mhm-rentiva'); ?>"></textarea>
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
                            <label for="mhm-mileage"><?php esc_html_e('Mileage', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-mileage" name="mileage" required placeholder="<?php esc_attr_e('e.g. 45000', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-license-plate"><?php esc_html_e('License Plate', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-license-plate" name="license_plate" required placeholder="<?php esc_attr_e('e.g. 34ABC123', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-seats"><?php esc_html_e('Seats', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="number" id="mhm-seats" name="seats" required min="1" value="5">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-doors"><?php esc_html_e('Doors', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="number" id="mhm-doors" name="doors" required min="2" value="4">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-transmission"><?php esc_html_e('Transmission', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <select id="mhm-transmission" name="transmission" required>
                                <?php foreach ($transmissions as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-fuel-type"><?php esc_html_e('Fuel Type', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <select id="mhm-fuel-type" name="fuel_type" required>
                                <?php foreach ($fuel_types as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-engine-size"><?php esc_html_e('Engine Size (L)', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="number" id="mhm-engine-size" name="engine_size" required min="0.1" step="0.1" placeholder="0.0">
                        </div>
                        <!-- Deposit is set by site default (admin-controlled) -->
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

                <!-- Section 3b: Transfer Locations & Routes (shown when transfer/both selected) -->
                <?php if (! empty($transfer_locations) || ! empty($transfer_routes)) : ?>
                <div class="mhm-vendor-form__section mhm-vendor-form__section--transfer" id="mhm-transfer-section" style="display:none">
                    <h3><?php esc_html_e('Transfer Service Settings', 'mhm-rentiva'); ?></h3>

                    <!-- Vendor city (read-only) -->
                    <div class="mhm-vendor-form__field">
                        <label><?php esc_html_e('Service City', 'mhm-rentiva'); ?></label>
                        <input type="text" value="<?php echo esc_attr($data['vendor_city'] ?? ''); ?>" disabled>
                        <p class="mhm-vendor-form__hint"><?php esc_html_e('Your city is set during vendor application. Contact admin to change.', 'mhm-rentiva'); ?></p>
                    </div>

                    <!-- Transfer Capacity -->
                    <div class="mhm-vendor-form__field-row" style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-transfer-max-pax"><?php esc_html_e('Max Passengers', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="number" id="mhm-transfer-max-pax" name="transfer_max_pax" min="1" max="50" placeholder="<?php esc_attr_e('Excluding driver', 'mhm-rentiva'); ?>">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-transfer-luggage-score"><?php esc_html_e('Luggage Score Capacity', 'mhm-rentiva'); ?></label>
                            <input type="number" id="mhm-transfer-luggage-score" name="transfer_luggage_score" min="0" step="0.5">
                            <p class="mhm-vendor-form__hint"><?php esc_html_e('Small Bag = 1 pt, Big Bag = 2.5 pt', 'mhm-rentiva'); ?></p>
                        </div>
                    </div>
                    <div class="mhm-vendor-form__field-row" style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-transfer-max-big"><?php esc_html_e('Max Big Luggage', 'mhm-rentiva'); ?></label>
                            <input type="number" id="mhm-transfer-max-big" name="transfer_max_big_luggage" min="0">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-transfer-max-small"><?php esc_html_e('Max Small Luggage', 'mhm-rentiva'); ?></label>
                            <input type="number" id="mhm-transfer-max-small" name="transfer_max_small_luggage" min="0">
                        </div>
                    </div>

                    <!-- Service Locations -->
                    <?php if (! empty($transfer_locations)) : ?>
                    <div class="mhm-vendor-form__field">
                        <label><?php esc_html_e('Service Locations', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                        <p class="mhm-vendor-form__hint"><?php esc_html_e('Select the locations where you can provide transfer service.', 'mhm-rentiva'); ?></p>
                        <div class="mhm-vendor-form__checkboxes mhm-vendor-form__checkboxes--grid-4">
                            <?php foreach ($transfer_locations as $loc) : ?>
                                <label class="mhm-vendor-form__checkbox">
                                    <input type="checkbox" name="transfer_locations[]" value="<?php echo esc_attr((string) $loc->id); ?>">
                                    <?php echo esc_html($loc->name); ?>
                                    <?php if (! empty($loc->type)) : ?>
                                        <small class="mhm-vendor-form__checkbox-hint">(<?php echo esc_html($loc->type); ?>)</small>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Transfer Routes with Price Input -->
                    <?php if (! empty($transfer_routes)) : ?>
                    <div class="mhm-vendor-form__field">
                        <label><?php esc_html_e('Transfer Routes', 'mhm-rentiva'); ?></label>
                        <p class="mhm-vendor-form__hint"><?php esc_html_e('Select routes and set your price for each.', 'mhm-rentiva'); ?></p>
                        <div class="mhm-vendor-form__routes-list">
                            <?php foreach ($transfer_routes as $route) :
                                $min_p = (float) $route->min_price;
                                $max_p = (float) $route->max_price;
                                $price_hint = '';
                                if ($min_p > 0 && $max_p > 0) {
                                    $price_hint = sprintf(__('Range: %s – %s', 'mhm-rentiva'), number_format($min_p, 0, ',', '.') . ' ₺', number_format($max_p, 0, ',', '.') . ' ₺');
                                } elseif ($min_p > 0) {
                                    $price_hint = sprintf(__('Min: %s', 'mhm-rentiva'), number_format($min_p, 0, ',', '.') . ' ₺');
                                } elseif ($max_p > 0) {
                                    $price_hint = sprintf(__('Max: %s', 'mhm-rentiva'), number_format($max_p, 0, ',', '.') . ' ₺');
                                }
                            ?>
                                <div class="mhm-vendor-form__route-item" style="display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid #eee;">
                                    <label class="mhm-vendor-form__checkbox" style="flex:1;">
                                        <input type="checkbox" name="transfer_routes[]" value="<?php echo esc_attr((string) $route->id); ?>" class="mhm-route-checkbox" data-route-id="<?php echo esc_attr((string) $route->id); ?>">
                                        <?php echo esc_html($route->origin_name . ' → ' . $route->destination_name); ?>
                                    </label>
                                    <div class="mhm-vendor-form__route-price" style="width:140px;">
                                        <input type="number" name="route_prices[<?php echo esc_attr((string) $route->id); ?>]"
                                               step="0.01"
                                               <?php if ($min_p > 0) : ?>min="<?php echo esc_attr((string) $min_p); ?>"<?php endif; ?>
                                               <?php if ($max_p > 0) : ?>max="<?php echo esc_attr((string) $max_p); ?>"<?php endif; ?>
                                               placeholder="<?php esc_attr_e('Price', 'mhm-rentiva'); ?>"
                                               disabled
                                               class="mhm-route-price-input"
                                               data-route-id="<?php echo esc_attr((string) $route->id); ?>">
                                        <?php if ($price_hint) : ?>
                                            <small class="mhm-vendor-form__checkbox-hint"><?php echo esc_html($price_hint); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <script>
                    (function(){
                        document.querySelectorAll('.mhm-route-checkbox').forEach(function(cb){
                            cb.addEventListener('change', function(){
                                var routeId = this.dataset.routeId;
                                var priceInput = document.querySelector('.mhm-route-price-input[data-route-id="'+routeId+'"]');
                                if(priceInput){
                                    priceInput.disabled = !this.checked;
                                    if(!this.checked) priceInput.value = '';
                                }
                            });
                        });
                    })();
                    </script>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

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

                <!-- Section 6: Vehicle Registration Document -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Vehicle Registration Document', 'mhm-rentiva'); ?> <span class="required">*</span></h3>
                    <p class="mhm-vendor-form__hint"><?php esc_html_e('Upload a clear photo or scan of the vehicle registration certificate (ruhsat). This document is required for verification and will only be visible to the site administrator.', 'mhm-rentiva'); ?></p>
                    <div class="mhm-vendor-form__field">
                        <input type="file" name="vehicle_registration" accept="image/*,.pdf" required>
                    </div>
                </div>

                <!-- Section 6b: Vehicle Insurance -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Vehicle Insurance', 'mhm-rentiva'); ?> <span class="required">*</span></h3>
                    <p class="mhm-vendor-form__hint"><?php esc_html_e('Upload a clear photo or scan of the current vehicle insurance policy (kasko/trafik sigortası). This document is required for verification and will only be visible to the site administrator.', 'mhm-rentiva'); ?></p>
                    <div class="mhm-vendor-form__field">
                        <input type="file" name="vehicle_insurance" accept="image/*,.pdf" required>
                    </div>
                </div>

                <!-- Section 7: Photos -->
                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Photos', 'mhm-rentiva'); ?> <span class="required">*</span></h3>
                    <p class="mhm-vendor-form__hint"><?php echo esc_html(sprintf(
                        /* translators: 1: min photos, 2: max photos */
                        __('Upload between %1$d and %2$d photos. The first photo will be the main image.', 'mhm-rentiva'),
                        $min_photos,
                        $max_photos
                    )); ?></p>
                    <div class="mhm-vendor-form__field">
                        <input type="file" name="photos[]" accept="image/*" multiple required
                               data-min-photos="<?php echo esc_attr((string) $min_photos); ?>"
                               data-max-photos="<?php echo esc_attr((string) $max_photos); ?>">
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
        <script>
        /* Transfer section toggle — inline to avoid script-load-order issues on dashboard pages. */
        (function(){
            var el = document.getElementById('mhm-service-type');
            var section = document.getElementById('mhm-transfer-section');
            if (!el || !section) return;
            function toggle() {
                var v = el.value;
                section.style.display = (v === 'transfer' || v === 'both') ? 'block' : 'none';
            }
            el.addEventListener('change', toggle);
            toggle(); // apply on load in case of pre-selected value
        })();
        </script>
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
        wp_enqueue_style('select2', null); // WC registers this
        wp_enqueue_script('selectWoo');     // WC registers this
        wp_enqueue_script(
            'mhm-rentiva-vehicle-submit',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/vehicle-submit.js',
            array('jquery', 'selectWoo'),
            MHM_RENTIVA_VERSION,
            true
        );
        wp_localize_script('mhm-rentiva-vehicle-submit', 'mhmVehicleSubmit', array(
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('mhm_vehicle_submit'),
            'successMsg' => __('Your vehicle has been submitted for review! We will notify you once approved.', 'mhm-rentiva'),
            'errorMsg'   => __('Something went wrong. Please try again.', 'mhm-rentiva'),
            'min_photos' => (int) get_option('mhm_vehicle_min_photos', 4),
            'max_photos' => (int) get_option('mhm_vehicle_max_photos', 8),
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
        $deposit       = (float) get_option('mhm_rentiva_deposit_percentage', 20);
        $service_type  = sanitize_key(wp_unslash($_POST['service_type'] ?? 'rental'));
        $city          = sanitize_text_field(wp_unslash($_POST['city'] ?? ''));
        $location_id   = absint($_POST['location_id'] ?? 0);
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

        // Category.
        $category_id = (int) ($_POST['vehicle_category'] ?? 0);

        // Transfer locations & routes (only relevant for transfer/both).
        $raw_transfer_locations = isset($_POST['transfer_locations']) ? (array) wp_unslash($_POST['transfer_locations']) : array();
        $transfer_location_ids  = array_map('absint', $raw_transfer_locations);
        $raw_transfer_routes    = isset($_POST['transfer_routes']) ? (array) wp_unslash($_POST['transfer_routes']) : array();
        $transfer_route_ids     = array_map('absint', $raw_transfer_routes);

        // Photo count validation (server-side).
        $min_photos = (int) get_option('mhm_vehicle_min_photos', 4);
        $max_photos_limit = (int) get_option('mhm_vehicle_max_photos', 8);
        $photo_count = ! empty($_FILES['photos']['name'][0]) ? count(array_filter($_FILES['photos']['name'])) : 0;
        if ($photo_count < $min_photos) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %d: minimum photo count */
                    __('Please upload at least %d photos.', 'mhm-rentiva'),
                    $min_photos
                ),
            ));
        }
        if ($photo_count > $max_photos_limit) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %d: maximum photo count */
                    __('You can upload a maximum of %d photos.', 'mhm-rentiva'),
                    $max_photos_limit
                ),
            ));
        }

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

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('Failed to create vehicle listing.', 'mhm-rentiva')));
            return;
        }

        // --- Listing Fee Payment Gate ---
        if ( \MHMRentiva\Admin\Vehicle\ListingFeeManager::requires_payment( 'new' ) ) {
            wp_update_post( array(
                'ID'          => $post_id,
                'post_status' => 'draft',
            ) );
            update_post_meta( $post_id, '_vehicle_review_status', 'awaiting_payment' );

            $checkout_url = \MHMRentiva\Admin\Vehicle\ListingFeeManager::add_to_cart( $post_id, 'new' );
            wp_send_json_success( array(
                'vehicle_id'       => $post_id,
                'requires_payment' => true,
                'checkout_url'     => $checkout_url,
            ) );
            return;
        }

        update_post_meta($post_id, '_vehicle_review_status', 'pending_review');

        // Notify admin about new vehicle submission.
        do_action('mhm_rentiva_vendor_vehicle_submitted', $post_id, get_current_user_id());

        // Assign vehicle category.
        if ($category_id > 0) {
            wp_set_object_terms($post_id, $category_id, 'vehicle_category');
        }

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
        if ($location_id > 0) {
            update_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID, $location_id);
        }
        update_post_meta($post_id, '_mhm_rentiva_features', $features);
        update_post_meta($post_id, '_mhm_rentiva_equipment', $equipment);

        // Save transfer locations and routes for transfer/both service types.
        if (in_array($service_type, array('transfer', 'both'), true)) {
            if (! empty($transfer_location_ids)) {
                update_post_meta($post_id, '_mhm_rentiva_transfer_locations', $transfer_location_ids);
            }
            if (! empty($transfer_route_ids)) {
                update_post_meta($post_id, '_mhm_rentiva_transfer_routes', $transfer_route_ids);
            }
        }

        // Transfer capacity fields.
        if (in_array($service_type, ['transfer', 'both'], true)) {
            update_post_meta($post_id, '_rentiva_transfer_max_pax', absint($_POST['transfer_max_pax'] ?? 0));
            update_post_meta($post_id, '_rentiva_transfer_max_luggage_score', floatval($_POST['transfer_luggage_score'] ?? 0));
            update_post_meta($post_id, '_rentiva_vehicle_max_big_luggage', absint($_POST['transfer_max_big_luggage'] ?? 0));
            update_post_meta($post_id, '_rentiva_vehicle_max_small_luggage', absint($_POST['transfer_max_small_luggage'] ?? 0));

            // Vendor route prices — validate against selected routes and admin min/max.
            $raw_prices  = isset($_POST['route_prices']) ? (array) wp_unslash($_POST['route_prices']) : array();
            $route_prices = array();
            if (! empty($raw_prices) && ! empty($transfer_route_ids)) {
                $allowed_route_ids = array_flip($transfer_route_ids);
                // Fetch route min/max limits.
                global $wpdb;
                $rt = $wpdb->prefix . 'rentiva_transfer_routes';
                $placeholders_r = implode(',', array_fill(0, count($transfer_route_ids), '%d'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                $route_limits = $wpdb->get_results(
                    $wpdb->prepare("SELECT id, min_price, max_price FROM {$rt} WHERE id IN ({$placeholders_r})", $transfer_route_ids),
                    OBJECT_K
                );
                foreach ($raw_prices as $route_id => $price) {
                    $rid = absint($route_id);
                    $p   = floatval($price);
                    if ($rid > 0 && $p > 0 && isset($allowed_route_ids[$rid])) {
                        // Clamp to admin-defined range.
                        $limit = $route_limits[$rid] ?? null;
                        if ($limit) {
                            $min = (float) $limit->min_price;
                            $max = (float) $limit->max_price;
                            if ($min > 0 && $p < $min) {
                                $p = $min;
                            }
                            if ($max > 0 && $p > $max) {
                                $p = $max;
                            }
                        }
                        $route_prices[$rid] = $p;
                    }
                }
            }
            update_post_meta($post_id, '_mhm_rentiva_transfer_route_prices', wp_json_encode($route_prices));
        }

        // Handle vehicle registration document upload.
        if (! empty($_FILES['vehicle_registration']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $reg_attachment_id = media_handle_upload('vehicle_registration', $post_id);
            if (! is_wp_error($reg_attachment_id)) {
                update_post_meta($post_id, '_mhm_rentiva_vehicle_registration_doc', $reg_attachment_id);
            }
        }

        // Handle vehicle insurance document upload.
        if (! empty($_FILES['vehicle_insurance']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $ins_attachment_id = media_handle_upload('vehicle_insurance', $post_id);
            if (! is_wp_error($ins_attachment_id)) {
                update_post_meta($post_id, '_mhm_rentiva_vehicle_insurance_doc', $ins_attachment_id);
            }
        }

        // Handle photo uploads.
        if (! empty($_FILES['photos']['name'][0])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $gallery_images = array();
            $thumbnail_set  = false;
            $file_count     = min($max_photos_limit, count($_FILES['photos']['name']));

            for ($i = 0; $i < $file_count; $i++) {
                // Rebuild single-file array structure for each photo.
                $_FILES['photo_single'] = array(
                    'name'     => $_FILES['photos']['name'][ $i ],
                    'type'     => $_FILES['photos']['type'][ $i ],
                    'tmp_name' => $_FILES['photos']['tmp_name'][ $i ],
                    'error'    => $_FILES['photos']['error'][ $i ],
                    'size'     => $_FILES['photos']['size'][ $i ],
                );

                $allowed_mimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
                $file_info = wp_check_filetype_and_ext(
                    $_FILES['photos']['tmp_name'][ $i ],
                    $_FILES['photos']['name'][ $i ]
                );
                if (empty($file_info['type']) || !in_array($file_info['type'], $allowed_mimes, true)) {
                    continue;
                }
                if (!@getimagesize($_FILES['photos']['tmp_name'][ $i ])) {
                    continue;
                }

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

    /**
     * AJAX handler: return vehicle data for edit form.
     */
    public static function handle_get_edit_data(): void
    {
        check_ajax_referer('mhm_vehicle_submit', 'nonce');

        $vehicle_id = (int) ($_GET['vehicle_id'] ?? 0);
        $post       = get_post($vehicle_id);

        if (! $post || $post->post_type !== 'vehicle') {
            wp_send_json_error(array('message' => __('Invalid vehicle.', 'mhm-rentiva')));
        }

        if ((int) $post->post_author !== get_current_user_id()) {
            wp_send_json_error(array('message' => __('You do not own this vehicle.', 'mhm-rentiva')));
        }

        $meta = function (string $key) use ($vehicle_id) {
            return get_post_meta($vehicle_id, '_mhm_rentiva_' . $key, true);
        };

        $gallery_raw = (string) get_post_meta($vehicle_id, '_mhm_rentiva_gallery_images', true);
        $gallery      = json_decode($gallery_raw, true);
        if (! is_array($gallery)) {
            $gallery = array();
        }

        wp_send_json_success(array(
            'id'                 => $vehicle_id,
            'description'        => $post->post_content,
            'location_id'        => (int) get_post_meta($vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID, true),
            'brand'              => $meta('brand'),
            'model'              => $meta('model'),
            'year'               => $meta('year'),
            'color'              => $meta('color'),
            'city'               => $meta('vehicle_city'),
            'price_per_day'      => $meta('price_per_day'),
            'mileage'            => $meta('mileage'),
            'license_plate'      => $meta('license_plate'),
            'seats'              => $meta('seats'),
            'doors'              => $meta('doors'),
            'transmission'       => $meta('transmission'),
            'fuel_type'          => $meta('fuel_type'),
            'engine_size'        => $meta('engine_size'),
            'service_type'       => get_post_meta($vehicle_id, '_rentiva_vehicle_service_type', true),
            'features'           => $meta('features') ?: array(),
            'equipment'          => $meta('equipment') ?: array(),
            'transfer_locations' => $meta('transfer_locations') ?: array(),
            'transfer_routes'    => $meta('transfer_routes') ?: array(),
            'transfer_max_pax'          => (int) get_post_meta($vehicle_id, '_rentiva_transfer_max_pax', true),
            'transfer_luggage_score'    => (float) get_post_meta($vehicle_id, '_rentiva_transfer_max_luggage_score', true),
            'transfer_max_big_luggage'  => (int) get_post_meta($vehicle_id, '_rentiva_vehicle_max_big_luggage', true),
            'transfer_max_small_luggage'=> (int) get_post_meta($vehicle_id, '_rentiva_vehicle_max_small_luggage', true),
            'transfer_route_prices'     => json_decode(get_post_meta($vehicle_id, '_mhm_rentiva_transfer_route_prices', true) ?: '{}', true),
            'category_id'        => (int) (wp_get_post_terms($vehicle_id, 'vehicle_category', array('fields' => 'ids'))[0] ?? 0),
            'gallery'            => $gallery,
            'thumbnail_id'       => (int) get_post_thumbnail_id($vehicle_id),
        ));
    }

    /**
     * Critical fields that trigger re-review when changed.
     */
    private const EDIT_CRITICAL_FIELDS = array(
        'price_per_day',
        'service_type',
        'city',
        'year',
        'brand',
        'model',
    );

    /**
     * AJAX handler: update an existing vendor vehicle.
     */
    public static function handle_update(): void
    {
        check_ajax_referer('mhm_vehicle_submit', 'mhm_vehicle_submit_nonce');

        if (! is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'mhm-rentiva')));
        }

        $user = wp_get_current_user();
        if (! in_array('rentiva_vendor', (array) $user->roles, true)) {
            wp_send_json_error(array('message' => __('Only approved vendors can edit vehicles.', 'mhm-rentiva')));
        }

        $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
        $post       = get_post($vehicle_id);
        if (! $post || $post->post_type !== 'vehicle' || (int) $post->post_author !== get_current_user_id()) {
            wp_send_json_error(array('message' => __('Invalid vehicle or permission denied.', 'mhm-rentiva')));
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $fields = array(
            'brand'         => sanitize_text_field(wp_unslash($_POST['brand'] ?? '')),
            'model'         => sanitize_text_field(wp_unslash($_POST['model'] ?? '')),
            'year'          => (int) ($_POST['year'] ?? 0),
            'color'         => sanitize_text_field(wp_unslash($_POST['color'] ?? '')),
            'price_per_day' => (float) ($_POST['price_per_day'] ?? 0.0),
            'mileage'       => sanitize_text_field(wp_unslash($_POST['mileage'] ?? '')),
            'license_plate' => strtoupper(sanitize_text_field(wp_unslash($_POST['license_plate'] ?? ''))),
            'seats'         => max(1, (int) ($_POST['seats'] ?? 5)),
            'doors'         => max(2, (int) ($_POST['doors'] ?? 4)),
            'transmission'  => sanitize_key(wp_unslash($_POST['transmission'] ?? '')),
            'fuel_type'     => sanitize_key(wp_unslash($_POST['fuel_type'] ?? '')),
            'engine_size'   => (float) ($_POST['engine_size'] ?? 0.0),
            'service_type'  => sanitize_key(wp_unslash($_POST['service_type'] ?? 'rental')),
            'city'          => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
        );
        // phpcs:enable

        // Detect critical field changes.
        $has_critical_change = false;
        foreach (self::EDIT_CRITICAL_FIELDS as $cf) {
            if ($cf === 'service_type') {
                $old_key = '_rentiva_vehicle_service_type';
            } elseif ($cf === 'city') {
                $old_key = '_mhm_rentiva_vehicle_city';
            } else {
                $old_key = '_mhm_rentiva_' . $cf;
            }
            $old_val = (string) get_post_meta($vehicle_id, $old_key, true);
            $new_val = (string) ($fields[$cf] ?? '');
            if ($old_val !== $new_val) {
                $has_critical_change = true;
                break;
            }
        }

        // Update post title and description.
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $title = trim(implode(' ', array_filter(array($fields['brand'], $fields['model'], $fields['year'] > 0 ? (string) $fields['year'] : ''))));
        wp_update_post(array('ID' => $vehicle_id, 'post_title' => sanitize_text_field($title), 'post_content' => $description));

        // Update meta fields.
        foreach ($fields as $key => $value) {
            $meta_key = $key === 'service_type' ? '_rentiva_vehicle_service_type' : '_mhm_rentiva_' . $key;
            if ($key === 'city') {
                $meta_key = '_mhm_rentiva_vehicle_city';
            }
            update_post_meta($vehicle_id, $meta_key, $value);
        }

        // Pickup location.
        $location_id = absint($_POST['location_id'] ?? 0);
        if ($location_id > 0) {
            update_post_meta($vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID, $location_id);
        } else {
            delete_post_meta($vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID);
        }

        // Features and equipment.
        $allowed_features = array('air_conditioning', 'power_steering', 'abs_brakes', 'airbags', 'central_locking', 'electric_windows', 'power_mirrors', 'fog_lights', 'cruise_control', 'bluetooth', 'usb_port', 'navigation', 'sunroof', 'leather_seats', 'heated_seats');
        $allowed_equipment = array('spare_tire', 'jack', 'first_aid_kit', 'fire_extinguisher', 'warning_triangle', 'jumper_cables', 'ice_scraper', 'car_cover', 'child_seat', 'gps_tracker', 'dashcam', 'phone_holder', 'charger', 'cleaning_kit', 'emergency_kit');

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_features  = isset($_POST['features'])  ? (array) wp_unslash($_POST['features'])  : array();
        $raw_equipment = isset($_POST['equipment']) ? (array) wp_unslash($_POST['equipment']) : array();
        // phpcs:enable
        update_post_meta($vehicle_id, '_mhm_rentiva_features', array_values(array_intersect(array_map('sanitize_key', $raw_features), $allowed_features)));
        update_post_meta($vehicle_id, '_mhm_rentiva_equipment', array_values(array_intersect(array_map('sanitize_key', $raw_equipment), $allowed_equipment)));

        // Category.
        $category_id = (int) ($_POST['vehicle_category'] ?? 0);
        if ($category_id > 0) {
            wp_set_object_terms($vehicle_id, $category_id, 'vehicle_category');
        }

        // Transfer data.
        $service_type = $fields['service_type'];
        if (in_array($service_type, array('transfer', 'both'), true)) {
            $raw_locs   = isset($_POST['transfer_locations']) ? (array) wp_unslash($_POST['transfer_locations']) : array();
            $raw_routes = isset($_POST['transfer_routes']) ? (array) wp_unslash($_POST['transfer_routes']) : array();
            $transfer_route_ids = array_map('absint', $raw_routes);
            update_post_meta($vehicle_id, '_mhm_rentiva_transfer_locations', array_map('absint', $raw_locs));
            update_post_meta($vehicle_id, '_mhm_rentiva_transfer_routes', $transfer_route_ids);
        }

        // Transfer capacity fields.
        $svc = sanitize_text_field($_POST['service_type'] ?? 'rental');
        if (in_array($svc, ['transfer', 'both'], true)) {
            update_post_meta($vehicle_id, '_rentiva_transfer_max_pax', absint($_POST['transfer_max_pax'] ?? 0));
            update_post_meta($vehicle_id, '_rentiva_transfer_max_luggage_score', floatval($_POST['transfer_luggage_score'] ?? 0));
            update_post_meta($vehicle_id, '_rentiva_vehicle_max_big_luggage', absint($_POST['transfer_max_big_luggage'] ?? 0));
            update_post_meta($vehicle_id, '_rentiva_vehicle_max_small_luggage', absint($_POST['transfer_max_small_luggage'] ?? 0));

            // Vendor route prices — validate against selected routes and admin min/max.
            $raw_prices  = isset($_POST['route_prices']) ? (array) wp_unslash($_POST['route_prices']) : array();
            $route_prices = array();
            if (! empty($raw_prices) && ! empty($transfer_route_ids)) {
                $allowed_route_ids = array_flip($transfer_route_ids);
                // Fetch route min/max limits.
                global $wpdb;
                $rt = $wpdb->prefix . 'rentiva_transfer_routes';
                $placeholders_r = implode(',', array_fill(0, count($transfer_route_ids), '%d'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                $route_limits = $wpdb->get_results(
                    $wpdb->prepare("SELECT id, min_price, max_price FROM {$rt} WHERE id IN ({$placeholders_r})", $transfer_route_ids),
                    OBJECT_K
                );
                foreach ($raw_prices as $rid => $price) {
                    $r = absint($rid);
                    $p = floatval($price);
                    if ($r > 0 && $p > 0 && isset($allowed_route_ids[$r])) {
                        // Clamp to admin-defined range.
                        $limit = $route_limits[$r] ?? null;
                        if ($limit) {
                            $min = (float) $limit->min_price;
                            $max = (float) $limit->max_price;
                            if ($min > 0 && $p < $min) {
                                $p = $min;
                            }
                            if ($max > 0 && $p > $max) {
                                $p = $max;
                            }
                        }
                        $route_prices[$r] = $p;
                    }
                }
            }
            update_post_meta($vehicle_id, '_mhm_rentiva_transfer_route_prices', wp_json_encode($route_prices));
        }

        // Handle gallery reorder / featured image / deletions from JS payload first,
        // so new uploads are appended after the user's current ordering/deletions.
        if (! empty($_POST['gallery_order'])) {
            $gallery_order = json_decode(wp_unslash($_POST['gallery_order']), true); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if (is_array($gallery_order)) {
                $reordered = array();
                foreach ($gallery_order as $img) {
                    $reordered[] = array(
                        'id'    => (int) ($img['id'] ?? 0),
                        'url'   => esc_url_raw($img['url'] ?? ''),
                        'alt'   => sanitize_text_field($img['alt'] ?? ''),
                        'title' => sanitize_text_field($img['title'] ?? ''),
                    );
                }
                update_post_meta($vehicle_id, '_mhm_rentiva_gallery_images', wp_json_encode($reordered));
                // First image = thumbnail.
                if (! empty($reordered[0]['id'])) {
                    set_post_thumbnail($vehicle_id, $reordered[0]['id']);
                }
            }
        }

        // Handle new photo uploads (append to gallery after reorder/deletions are applied).
        if (! empty($_FILES['photos']['name'][0])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $existing_gallery = json_decode((string) get_post_meta($vehicle_id, '_mhm_rentiva_gallery_images', true), true);
            if (! is_array($existing_gallery)) {
                $existing_gallery = array();
            }

            $max_photos_limit = (int) get_option('mhm_vehicle_max_photos', 8);
            $remaining_slots  = max(0, $max_photos_limit - count($existing_gallery));
            $file_count       = min($remaining_slots, count(array_filter($_FILES['photos']['name'])));

            for ($i = 0; $i < $file_count; $i++) {
                $_FILES['photo_single'] = array(
                    'name'     => $_FILES['photos']['name'][$i],
                    'type'     => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                    'size'     => $_FILES['photos']['size'][$i],
                );
                $attachment_id = media_handle_upload('photo_single', $vehicle_id);
                if (! is_wp_error($attachment_id)) {
                    $existing_gallery[] = array(
                        'id'    => $attachment_id,
                        'url'   => wp_get_attachment_url($attachment_id),
                        'alt'   => '',
                        'title' => '',
                    );
                }
            }

            update_post_meta($vehicle_id, '_mhm_rentiva_gallery_images', wp_json_encode($existing_gallery));
        }

        // If critical field changed on published vehicle → re-review.
        if ($has_critical_change && $post->post_status === 'publish') {
            update_post_meta($vehicle_id, '_vehicle_review_status', 'pending_review');
            do_action('mhm_rentiva_vehicle_needs_rereview', $vehicle_id);
            wp_send_json_success(array(
                'vehicle_id' => $vehicle_id,
                'rereview'   => true,
                'message'    => __('Vehicle updated. Critical fields were changed — your listing will be reviewed again before publishing.', 'mhm-rentiva'),
            ));
        }

        wp_send_json_success(array(
            'vehicle_id' => $vehicle_id,
            'rereview'   => false,
            'message'    => __('Vehicle updated successfully.', 'mhm-rentiva'),
        ));
    }
}
