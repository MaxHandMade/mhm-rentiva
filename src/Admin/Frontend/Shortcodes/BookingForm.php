<?php

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * Booking Form Shortcode
 * 
 * Advanced booking form - vehicle selection, add-ons, deposit system
 * 
 * Usage: [rentiva_booking_form vehicle_id="123" show_addons="1" enable_deposit="1"]
 */
final class BookingForm extends AbstractShortcode
{
    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    public const SHORTCODE = 'rentiva_booking_form';

    public static function register(): void
    {
        parent::register();

        add_action('wp_ajax_mhm_rentiva_booking_form', [self::class, 'ajax_booking_form']);
        add_action('wp_ajax_nopriv_mhm_rentiva_booking_form', [self::class, 'ajax_booking_form']);
        add_action('wp_ajax_mhm_rentiva_calculate_price', [self::class, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_mhm_rentiva_calculate_price', [self::class, 'ajax_calculate_price']);

        // Availability check AJAX handlers
        add_action('wp_ajax_mhm_rentiva_check_availability', [self::class, 'ajax_check_availability']);
        add_action('wp_ajax_nopriv_mhm_rentiva_check_availability', [self::class, 'ajax_check_availability']);

        // Payment processing AJAX handlers

    }

    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_booking_form';
    }

    protected static function get_template_path(): string
    {
        return 'shortcodes/booking-form';
    }

    protected static function get_default_attributes(): array
    {
        return [
            'vehicle_id'            => '',        // Specific vehicle ID
            'start_date'            => '',        // Start date
            'end_date'              => '',        // End date
            'show_vehicle_selector' => '1',       // Show vehicle selector
            'default_days'          => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_default_rental_days', 1), // Default number of days from settings
            'min_days'              => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1), // Minimum number of days from settings
            'max_days'              => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30), // Maximum number of days from settings
            'show_payment_options'  => '1',       // Show payment options
            'show_addons'           => '1',       // Show add-ons
            'class'                 => '',        // Custom CSS class
            'redirect_url'          => '',        // Redirect after success
            'enable_deposit'        => '1',       // Deposit system active
            'default_payment'       => 'deposit', // Default payment type
            'form_title'            => '',        // Form title
            'show_vehicle_info'     => '1',       // Show vehicle information
        ];
    }

    protected static function get_css_filename(): string
    {
        return 'booking-form.css';
    }

    protected static function get_js_filename(): string
    {
        return 'booking-form.js';
    }

    protected static function get_css_dependencies(): array
    {
        return ['mhm-css-variables']; // Ensure CSS variables are loaded
    }

    protected static function get_js_dependencies(): array
    {
        return ['jquery', 'jquery-ui-datepicker']; // jQuery UI DatePicker dependency added
    }

    protected static function get_localized_data(): array
    {
        return [
            'ajax_url' => admin_url('admin-ajax.php'), // ⭐ Changed from ajaxUrl to ajax_url for consistency
            'ajaxUrl' => admin_url('admin-ajax.php'), // Keep both for backward compatibility
            'restUrl' => rest_url('mhm-rentiva/v1/'),
            'nonce' => wp_create_nonce('mhm_rentiva_booking_form_nonce'), // Correct nonce
            'strings' => static::get_localized_strings(),
            'config' => static::get_js_config(),
            'datepicker_options' => static::get_datepicker_options(),
        ];
    }

    /**
     * Override enqueue_scripts to use file-based versioning
     */
    protected static function enqueue_scripts(): void
    {
        $handle = static::get_asset_handle();
        $js_files = static::get_js_files();

        foreach ($js_files as $js_file) {
            if (static::asset_exists($js_file)) {
                // Use file-based versioning for better cache management
                $version = static::get_file_version($js_file);

                wp_enqueue_script(
                    $handle,
                    plugins_url($js_file, dirname(__DIR__, 4) . '/mhm-rentiva.php'),
                    static::get_js_dependencies(),
                    $version,
                    true
                );

                // ⭐ Localize script immediately after enqueue
                // wp_localize_script must be called after wp_enqueue_script but before script output
                static::localize_script($handle);
                break;
            }
        }
    }

    /**
     * Override enqueue_styles to use file-based versioning
     */
    protected static function enqueue_styles(): void
    {
        $handle = static::get_asset_handle();
        $css_files = static::get_css_files();

        foreach ($css_files as $css_file) {
            if (static::asset_exists($css_file)) {
                // Use file-based versioning for better cache management
                $version = static::get_file_version($css_file);

                wp_enqueue_style(
                    $handle,
                    plugins_url($css_file, dirname(__DIR__, 4) . '/mhm-rentiva.php'),
                    static::get_css_dependencies(),
                    $version
                );
                break;
            }
        }
    }

    /**
     * Expose asset loading for other components
     */
    public static function enqueue_assets(): void
    {
        parent::enqueue_assets();
    }

    /**
     * Get file version based on file modification time
     * Falls back to plugin version if file doesn't exist
     * 
     * @param string $file_path Relative path to file (e.g., 'assets/js/frontend/booking-form.js')
     * @return string Version string
     */
    private static function get_file_version(string $file_path): string
    {
        $full_path = trailingslashit(dirname(__DIR__, 4)) . $file_path;
        if (file_exists($full_path)) {
            return (string) filemtime($full_path);
        }
        return defined('MHM_RENTIVA_VERSION') ? constant('MHM_RENTIVA_VERSION') : '4.6.1'; // Fallback to plugin version
    }

    protected static function get_script_object_name(): string
    {
        return 'mhmRentivaBookingForm';
    }

    protected static function get_localized_strings(): array
    {
        return [
            // Availability check messages
            'checking_availability' => __('Checking availability...', 'mhm-rentiva'),
            'vehicle_available' => __('Vehicle is available', 'mhm-rentiva'),
            'vehicle_not_available' => __('Vehicle is not available for the selected dates. Please choose different dates.', 'mhm-rentiva'),
            'vehicle_unavailable_with_alternatives' => __('Selected vehicle is not available, but we can suggest similar vehicles:', 'mhm-rentiva'),
            'availability_check_failed' => __('Availability check failed', 'mhm-rentiva'),
            'select_this_vehicle' => __('Select This Vehicle', 'mhm-rentiva'),
            'total' => __('Total', 'mhm-rentiva'),

            // Payment messages
            'redirecting_to_payment' => __('Redirecting to payment page...', 'mhm-rentiva'),
            'payment_completed' => __('Payment completed successfully!', 'mhm-rentiva'),
            'payment_cancelled' => __('Payment cancelled. Your booking is in pending status.', 'mhm-rentiva'),
            'payment_status_unknown' => __('Payment status is unknown. Please check.', 'mhm-rentiva'),
            'popup_blocked_redirecting' => __('Popup blocked. Redirecting to payment page...', 'mhm-rentiva'),
            'select_payment_gateway' => __('Please select a payment gateway.', 'mhm-rentiva'),
            'selectPaymentType' => __('Please select a payment type.', 'mhm-rentiva'),
            'selectPaymentMethod' => __('Please select a payment method.', 'mhm-rentiva'),
            'login_required' => __('Please log in to manage favorites.', 'mhm-rentiva'),
            'added_to_favorites' => __('Added to favorites', 'mhm-rentiva'),
            'removed_from_favorites' => __('Removed from favorites', 'mhm-rentiva'),
            'add_to_favorites' => __('Add to favorites', 'mhm-rentiva'),
            'remove_from_favorites' => __('Remove from favorites', 'mhm-rentiva'),
            'terms_error' => __('You must accept the terms and conditions to complete your booking.', 'mhm-rentiva'),

            // Booking messages
            'booking_created' => __('Your booking has been successfully created!', 'mhm-rentiva'),
            'booking_created_with_id' => __('Your booking has been successfully created! Booking No:', 'mhm-rentiva'),
            'invalid_dates' => __('Return date must be after pickup date.', 'mhm-rentiva'),

            // Form validation
            'please_select_vehicle' => __('Please select a vehicle', 'mhm-rentiva'),
            'please_enter_dates' => __('Please enter pickup and dropoff dates', 'mhm-rentiva'),
            'dropoff_after_pickup' => __('Dropoff date must be after pickup date', 'mhm-rentiva'),
            'please_enter_name' => __('Please enter your full name', 'mhm-rentiva'),
            'please_enter_email' => __('Please enter a valid email address', 'mhm-rentiva'),
            'please_enter_phone' => __('Please enter your phone number', 'mhm-rentiva'),
            'selectPickupTime' => __('Please select pickup time.', 'mhm-rentiva'),
            'alternative_vehicles' => __('Alternative Vehicles', 'mhm-rentiva'),

            // Price display
            'per_day' => __('/day', 'mhm-rentiva'),
            'daily_price' => __('Daily Price', 'mhm-rentiva'),
            'days_count' => __('Days', 'mhm-rentiva'),
            'vehicle_total' => __('Vehicle Total', 'mhm-rentiva'),
            'addons_total' => __('Add-ons Total', 'mhm-rentiva'),
            'total_amount' => __('Total Amount', 'mhm-rentiva'),
            'deposit_amount' => __('Deposit Amount', 'mhm-rentiva'),
            'remaining_amount' => __('Remaining Amount', 'mhm-rentiva'),
            'tax' => __('Tax', 'mhm-rentiva'),
            'tax_included' => __('Tax (included)', 'mhm-rentiva'),

            // Loading and errors
            'loading' => __('Loading...', 'mhm-rentiva'),
            'calculating' => __('Calculating...', 'mhm-rentiva'),
            'submitting' => __('Submitting...', 'mhm-rentiva'),
            'error_occurred' => __('An error occurred', 'mhm-rentiva'),
            'try_again' => __('Please try again', 'mhm-rentiva'),
            'connection_error' => __('Connection error', 'mhm-rentiva'),
            'min_days_error' => __('Minimum rental period is %d days.', 'mhm-rentiva'),
            'max_days_error' => __('Maximum rental period is %d days.', 'mhm-rentiva'),
        ];
    }

    protected static function get_js_config(): array
    {
        return [
            'currency_symbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
            'locale' => \MHMRentiva\Admin\Core\LanguageHelper::get_current_js_locale(),
            'enable_deposit' => get_option('mhm_rentiva_enable_deposit', '1') === '1',
            'default_payment' => get_option('mhm_rentiva_default_payment', 'deposit'),
            'min_days' => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1),
            'max_days' => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30),
            'advance_booking_days' => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_advance_booking_days', 365),
        ];
    }

    protected static function prepare_template_data(array $atts): array
    {
        // Check URL parameters and override shortcode parameters
        if (isset($_GET['vehicle_id']) && !empty($_GET['vehicle_id'])) {
            $atts['vehicle_id'] = self::sanitize_text_field_safe($_GET['vehicle_id']);
        }

        if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
            $atts['start_date'] = self::sanitize_text_field_safe($_GET['start_date']);
        }

        if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
            $atts['end_date'] = self::sanitize_text_field_safe($_GET['end_date']);
        }

        // ⭐ Moved logic from template to controller
        $user_data = self::prepare_user_data();
        $validation_result = self::validate_user_access();

        $data = [
            'atts' => $atts,
            'vehicles' => [],
            'selected_vehicle' => null,
            'time_options' => self::get_time_options(),
            'addons' => self::get_available_addons(),
            'enable_deposit' => ($atts['enable_deposit'] ?? '1') === '1',
            'default_payment' => $atts['default_payment'] ?? 'deposit',
            'show_vehicle_selector' => ($atts['show_vehicle_selector'] ?? '1') === '1',
            'show_addons' => ($atts['show_addons'] ?? '1') === '1',
            'show_payment_options' => ($atts['show_payment_options'] ?? '1') === '1',
            'show_vehicle_info' => ($atts['show_vehicle_info'] ?? '1') === '1',
            'default_days' => (int) $atts['default_days'],
            'min_days' => (int) $atts['min_days'],
            'max_days' => (int) $atts['max_days'],
            // ⭐ User data and validation (moved from template)
            'user_data' => $user_data,
            'validation_error' => $validation_result['error'] ?? null,
            'customer_settings' => self::get_customer_settings(),
        ];

        // Vehicle list
        if ($data['show_vehicle_selector']) {
            $data['vehicles'] = self::get_available_vehicles();
        }

        // If specific vehicle is selected
        if (!empty($atts['vehicle_id'])) {
            $vehicle_id = intval($atts['vehicle_id']);
            $vehicle = self::get_vehicle_data($vehicle_id);
            if ($vehicle) {
                $data['selected_vehicle'] = $vehicle;
                // ⭐ Hide vehicle selector when vehicle is selected
                $data['show_vehicle_selector'] = false;
            }
        }

        return $data;
    }

    /**
     * Prepare user data for template (moved from template)
     * 
     * @return array<string, mixed> User data array
     */
    private static function prepare_user_data(): array
    {
        $current_user = wp_get_current_user();
        $is_logged_in = is_user_logged_in();

        return [
            'is_logged_in' => $is_logged_in,
            'user_id' => $is_logged_in ? $current_user->ID : 0,
            'user_name' => $is_logged_in ? $current_user->display_name : '',
            'user_email' => $is_logged_in ? $current_user->user_email : '',
            'user_phone' => $is_logged_in ? get_user_meta($current_user->ID, 'mhm_rentiva_phone', true) : '',
            'first_name' => $is_logged_in ? $current_user->first_name : '',
            'last_name' => $is_logged_in ? $current_user->last_name : '',
        ];
    }

    /**
     * Validate user access and data consent (moved from template)
     * 
     * @return array<string, mixed> Validation result with 'error' key if validation fails
     */
    private static function validate_user_access(): array
    {
        $data_consent_required = SettingsCore::get('mhm_rentiva_customer_data_consent', '0');

        // Check data consent requirement for logged-in users
        if (is_user_logged_in() && $data_consent_required === '1') {
            $user_id = get_current_user_id();
            $consent_given = get_user_meta($user_id, 'mhm_data_consent_given', true);

            if ($consent_given !== '1') {
                return [
                    'error' => __('You must provide consent for data processing before making a booking. Please update your account settings.', 'mhm-rentiva'),
                ];
            }
        }

        return [];
    }

    /**
     * Get customer management settings (moved from template)
     * 
     * @return array<string, mixed> Customer settings
     */
    private static function get_customer_settings(): array
    {
        $terms_text = SettingsCore::get('mhm_rentiva_customer_terms_text', __('I accept the terms of use and privacy policy.', 'mhm-rentiva'));

        // ⭐ If the saved text is the default English text, use the translated version
        if ($terms_text === 'I accept the terms of use and privacy policy.') {
            $terms_text = __('I accept the terms of use and privacy policy.', 'mhm-rentiva');
        }

        return [
            'registration_required' => SettingsCore::get('mhm_rentiva_customer_registration_required', '0'),
            'phone_required' => SettingsCore::get('mhm_rentiva_customer_phone_required', '0'),
            'terms_required' => SettingsCore::get('mhm_rentiva_customer_terms_required', '0'),
            'terms_text' => $terms_text,
            'data_consent_required' => SettingsCore::get('mhm_rentiva_customer_data_consent', '0'),
        ];
    }

    private static function get_vehicle_data(int $vehicle_id): ?array
    {
        $vehicle = get_post($vehicle_id);
        if (!$vehicle || $vehicle->post_type !== 'vehicle') {
            return null;
        }

        // Try different meta keys
        $price_per_day = floatval(get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true) ?: 0);
        if ($price_per_day == 0) {
            $price_per_day = floatval(get_post_meta($vehicle_id, '_price_per_day', true) ?: 0);
        }
        if ($price_per_day == 0) {
            $price_per_day = floatval(get_post_meta($vehicle_id, 'price_per_day', true) ?: 0);
        }
        if ($price_per_day == 0) {
            $price_per_day = floatval(get_post_meta($vehicle_id, '_mhm_price_per_day', true) ?: 0);
        }

        $currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

        // Vehicle image
        $image_id = get_post_thumbnail_id($vehicle_id);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

        // Deposit calculation
        $deposit_amount = 0;
        if (class_exists('\MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator')) {
            $deposit_result = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::calculate_deposit($vehicle_id, 1);
            if (isset($deposit_result['success']) && $deposit_result['success']) {
                $deposit_amount = $deposit_result['deposit_amount'] ?? 0;
            }
        }

        // Rating and Favorite information (from VehiclesList)
        $rating = VehiclesList::get_vehicle_rating($vehicle_id);

        // Favorite check (from user meta)
        $is_favorited = false;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true) ?: [];
            $is_favorited = in_array($vehicle_id, $favorites);
        }

        // Meta information
        $year = get_post_meta($vehicle_id, '_mhm_rentiva_year', true) ?: get_post_meta($vehicle_id, '_year', true) ?: get_post_meta($vehicle_id, 'year', true);
        $mileage = get_post_meta($vehicle_id, '_mhm_rentiva_mileage', true) ?: get_post_meta($vehicle_id, '_mileage', true) ?: get_post_meta($vehicle_id, 'mileage', true);
        $seats = get_post_meta($vehicle_id, '_mhm_rentiva_seats', true) ?: get_post_meta($vehicle_id, '_seats', true) ?: get_post_meta($vehicle_id, 'seats', true);

        return [
            'id' => $vehicle_id,
            'title' => $vehicle->post_title,
            'excerpt' => $vehicle->post_excerpt,
            'price_per_day' => $price_per_day,
            'currency_symbol' => $currency_symbol,
            'rating' => $rating,
            'favorite' => $is_favorited,
            'image_url' => $image_url,
            'features' => VehicleFeatureHelper::collect_items($vehicle_id),
            'permalink' => get_permalink($vehicle_id) ?: '',
            'deposit_amount' => $deposit_amount,
            // Meta information
            'year' => $year,
            'mileage' => $mileage,
            'seats' => $seats,
        ];
    }

    private static function get_available_vehicles(): array
    {
        $vehicles = get_posts([
            'post_type' => PT_Vehicle::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $result = [];
        foreach ($vehicles as $vehicle) {
            // Check vehicle status - only show active vehicles
            $status = get_post_meta($vehicle->ID, '_mhm_vehicle_status', true);

            // Fallback for legacy data
            if (empty($status)) {
                $old_status = get_post_meta($vehicle->ID, '_mhm_vehicle_availability', true);
                if ($old_status === '0' || $old_status === 'passive' || $old_status === 'inactive') {
                    $status = 'inactive';
                }
            }

            if ($status && $status !== 'active') {
                continue;
            }

            $result[] = [
                'id' => $vehicle->ID,
                'title' => $vehicle->post_title,
                'price_per_day' => get_post_meta($vehicle->ID, '_mhm_rentiva_price_per_day', true) ?: '0',
                'featured_image' => get_the_post_thumbnail_url($vehicle->ID, 'thumbnail'),
            ];
        }

        return $result;
    }

    private static function get_time_options(): array
    {
        $options = [];
        // Generate time options from 00:00 to 23:00 (full day)
        for ($hour = 0; $hour <= 23; $hour++) {
            $time = sprintf('%02d:00', $hour);
            $options[] = [
                'value' => $time,
                'label' => $time
            ];
        }
        return $options;
    }

    private static function get_available_addons(): array
    {
        $addons = get_posts([
            'post_type' => 'vehicle_addon',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);

        $result = [];
        foreach ($addons as $addon) {
            $result[] = [
                'id' => $addon->ID,
                'title' => $addon->post_title,
                'price' => get_post_meta($addon->ID, 'addon_price', true) ?: '0',
                'description' => $addon->post_excerpt,
            ];
        }

        return $result;
    }

    /**
     * AJAX booking form
     */
    public static function ajax_booking_form(): void
    {
        try {
            // Security checks
            \MHMRentiva\Admin\Core\SecurityHelper::verify_ajax_request_or_die(
                'mhm_rentiva_booking_form_nonce',
                'read',
                __('Security check failed.', 'mhm-rentiva')
            );

            // Rate limiting check
            \MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit_or_die(
                'booking_form_submission',
                5, // 5 requests
                300, // 5 minutes
                __('Too many booking requests. Please wait 5 minutes.', 'mhm-rentiva')
            );

            // Input validation
            $vehicle_id = \MHMRentiva\Admin\Core\SecurityHelper::validate_vehicle_id($_POST['vehicle_id'] ?? 0);

            // Check vehicle status
            $vehicle_status = get_post_meta($vehicle_id, '_mhm_vehicle_status', true);

            // Fallback for legacy data
            if (empty($vehicle_status)) {
                $old_status = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);
                if ($old_status === '0' || $old_status === 'passive' || $old_status === 'inactive') {
                    $vehicle_status = 'inactive';
                }
            }

            if ($vehicle_status && $vehicle_status !== 'active') {
                wp_send_json_error(['message' => __('Selected vehicle is not available (Status: ' . $vehicle_status . ').', 'mhm-rentiva')]);
                return;
            }

            $pickup_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['pickup_date'] ?? '');
            $dropoff_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['dropoff_date'] ?? '');
            $pickup_time = self::sanitize_text_field_safe($_POST['pickup_time'] ?? '');
            $dropoff_time = self::sanitize_text_field_safe($_POST['dropoff_time'] ?? '');

            // Validate pickup time (required)
            if (empty($pickup_time)) {
                wp_send_json_error(['message' => __('Pickup time is required.', 'mhm-rentiva')]);
                return;
            }

            // Ensure dropoff time matches pickup time (security measure)
            if (empty($dropoff_time)) {
                $dropoff_time = $pickup_time;
            }

            $guests = max(1, intval($_POST['guests'] ?? 1));

            // ⭐ WooCommerce Integration: Customer information is optional
            // WooCommerce checkout will collect customer information
            // We only use customer info if user is logged in (for cart data)
            $customer_first_name = '';
            $customer_last_name = '';
            $customer_name = '';
            $customer_email = '';
            $customer_phone = '';
            $user_id = 0;

            // If user is logged in, get their information (optional, for cart data)
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $user_id = $current_user->ID;
                $customer_first_name = $current_user->first_name ?: $current_user->display_name;
                $customer_last_name = $current_user->last_name ?: '';
                $customer_name = trim($customer_first_name . ' ' . $customer_last_name);
                $customer_email = $current_user->user_email;
                $customer_phone = get_user_meta($current_user->ID, 'billing_phone', true) ?: get_user_meta($current_user->ID, 'mhm_rentiva_phone', true) ?: '';
            }

            // If form fields are provided (for logged-in users or manual entry), use them
            if (!empty($_POST['customer_first_name'])) {
                $customer_first_name = self::sanitize_text_field_safe($_POST['customer_first_name']);
            }
            if (!empty($_POST['customer_last_name'])) {
                $customer_last_name = self::sanitize_text_field_safe($_POST['customer_last_name']);
            }
            if (!empty($_POST['customer_email'])) {
                $customer_email_raw = self::sanitize_text_field_safe($_POST['customer_email']);
                // Only validate if email is provided and not empty
                if (!empty($customer_email_raw) && is_email($customer_email_raw)) {
                    $customer_email = sanitize_email($customer_email_raw);
                }
            }
            if (!empty($_POST['customer_phone'])) {
                $customer_phone = self::sanitize_text_field_safe($_POST['customer_phone']);
            }

            // Update customer name if we have first/last name
            if (!empty($customer_first_name) || !empty($customer_last_name)) {
                $customer_name = trim($customer_first_name . ' ' . $customer_last_name);
            }
            $payment_type = self::sanitize_text_field_safe($_POST['payment_type'] ?? 'deposit');

            // ⭐ SAFETY CHECK: Force Full Payment if Deposit field is removed/empty
            // This ensures we fallback to full payment instead of 0-deposit (free) booking
            $deposit_check_value = get_post_meta($vehicle_id, '_mhm_rentiva_deposit', true);
            if (empty($deposit_check_value)) {
                $payment_type = 'full';
            }

            // ⭐ WooCommerce only - payment_method and payment_gateway removed
            $payment_method = 'woocommerce';
            $payment_gateway = 'woocommerce';

            // ⭐ Terms & Conditions validation removed - WooCommerce handles this on checkout page
            // If WooCommerce is not active, terms validation would be handled here
            // But since we're using WooCommerce, validation happens on checkout

            // ✅ JavaScript AJAX: 'addons' (array), Normal form submit: 'selected_addons' (array)
            $selected_addons = [];

            // Check 'addons' parameter sent via AJAX
            if (isset($_POST['addons'])) {
                $addons_raw = $_POST['addons'];

                // Convert string to array (single value case)
                if (is_string($addons_raw)) {
                    $addons_raw = [$addons_raw];
                }

                if (is_array($addons_raw)) {
                    $selected_addons = \MHMRentiva\Admin\Core\SecurityHelper::validate_numeric_array($addons_raw);
                }
            }
            // Check 'selected_addons' parameter sent via normal form submit
            elseif (isset($_POST['selected_addons'])) {
                $addons_raw = $_POST['selected_addons'];

                // Convert string to array (single value case)
                if (is_string($addons_raw)) {
                    $addons_raw = [$addons_raw];
                }

                if (is_array($addons_raw)) {
                    $selected_addons = \MHMRentiva\Admin\Core\SecurityHelper::validate_numeric_array($addons_raw);
                }
            }


            // Validation
            if ($vehicle_id <= 0) {
                wp_send_json_error(['message' => __('Invalid vehicle ID.', 'mhm-rentiva')]);
            }

            if (empty($pickup_date) || empty($dropoff_date)) {
                wp_send_json_error(['message' => __('Please select dates.', 'mhm-rentiva')]);
            }

            // ⭐ WooCommerce Integration: Customer information validation removed
            // WooCommerce checkout will handle customer information collection and validation
            // We only validate if WooCommerce is NOT active (fallback for non-WooCommerce installations)
            $is_admin = current_user_can('manage_options');

            // Only validate customer info if WooCommerce is NOT active (legacy support)
            if (!class_exists('WooCommerce') && !$is_admin) {
                if (empty($customer_first_name) || empty($customer_last_name) || empty($customer_email) || empty($customer_phone)) {
                    wp_send_json_error(['message' => __('Please fill in contact information.', 'mhm-rentiva')]);
                    return;
                }
            }

            // If admin, use current user information
            if ($is_admin && (empty($customer_first_name) || empty($customer_last_name) || empty($customer_email))) {
                $current_user = wp_get_current_user();
                $customer_first_name = $customer_first_name ?: $current_user->first_name ?: $current_user->display_name;
                $customer_last_name = $customer_last_name ?: $current_user->last_name ?: '';
                $customer_email = $customer_email ?: $current_user->user_email;
                $customer_phone = $customer_phone ?: get_user_meta($current_user->ID, 'billing_phone', true) ?: get_user_meta($current_user->ID, 'mhm_rentiva_phone', true);
                $customer_name = trim($customer_first_name . ' ' . $customer_last_name);
            }

            // Date validation
            $start_date = new \DateTime($pickup_date);
            $end_date = new \DateTime($dropoff_date);

            if ($end_date <= $start_date) {
                wp_send_json_error(['message' => __('Return date must be after pickup date.', 'mhm-rentiva')]);
            }

            // ⭐ AVAILABILITY CHECK - CONFLICT CHECK (with cache)
            // This provides user feedback and pricing info
            $availability_result = \MHMRentiva\Admin\Booking\Helpers\Util::check_availability(
                $vehicle_id,
                $pickup_date,
                $pickup_time,
                $dropoff_date,
                $dropoff_time
            );

            if (!$availability_result['ok']) {
                wp_send_json_error([
                    'message' => $availability_result['message'],
                    'code' => $availability_result['code']
                ]);
            }

            // Calculate number of days
            $days = $start_date->diff($end_date)->days;
            $days = max(1, $days); // Ensure at least 1 day

            // ⭐ RENTAL LIMITS VALIDATION
            $min_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1);
            $max_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30);

            if ($days < $min_days) {
                wp_send_json_error([
                    'message' => sprintf(__('Minimum rental period is %d days.', 'mhm-rentiva'), $min_days),
                    'code' => 'min_days_error'
                ]);
            }

            if ($max_days > 0 && $days > $max_days) {
                wp_send_json_error([
                    'message' => sprintf(__('Maximum rental period is %d days.', 'mhm-rentiva'), $max_days),
                    'code' => 'max_days_error'
                ]);
            }

            // ⭐ ATOMIC OVERLAP CHECK - Final check before creating booking to prevent race conditions
            // This is the authoritative check - no cache, real-time database query
            // Parse dates to timestamps for atomic check
            $start_ts = strtotime($pickup_date . ' ' . $pickup_time);
            $end_ts = strtotime($dropoff_date . ' ' . $dropoff_time);

            // Clear cache before atomic check to ensure fresh data
            if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
                \MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
            }

            // Use locked overlap check to prevent concurrent bookings
            if (\MHMRentiva\Admin\Booking\Helpers\Util::has_overlap_locked($vehicle_id, $start_ts, $end_ts)) {
                wp_send_json_error([
                    'message' => __('This vehicle is already booked for the selected dates. Please choose different dates or select another vehicle.', 'mhm-rentiva'),
                    'code' => 'unavailable'
                ]);
            }

            // ⭐ WooCommerce Integration: User account creation removed
            // WooCommerce checkout will handle user account creation
            // We only get user ID if user is already logged in
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
            } else {
                $user_id = 0; // Will be set when order is created in WooCommerce checkout
            }

            // Calculate deposit
            $deposit_result = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::calculate_booking_deposit(
                $vehicle_id,
                $days,
                $payment_type,
                $selected_addons,
                $start_ts // ⭐ Pass start timestamp for weekend multiplier
            );

            if (!$deposit_result['success']) {
                wp_send_json_error(['message' => __('Price could not be calculated.', 'mhm-rentiva')]);
            }

            // ⭐ PAYMENT GATEWAY INTEGRATION: Use interface-based payment gateway
            // This allows different payment systems (WooCommerce, custom gateways, etc.)

            // Get active payment gateway
            $payment_gateway = self::get_active_payment_gateway();

            if ($payment_gateway && $payment_gateway->is_available()) {
                // ✅ Fix: Use raw numeric values instead of formatted string
                $amount_to_pay = 0.0;

                if ($payment_type === 'deposit') {
                    $amount_to_pay = floatval($deposit_result['deposit_amount']);
                } else {
                    $amount_to_pay = floatval($deposit_result['total_amount']);
                }

                // Prepare booking data to store in payment system
                // ⭐ Customer information is optional - Payment gateway checkout will collect it
                $booking_data_for_payment = [
                    'vehicle_id' => $vehicle_id,
                    'pickup_date' => $pickup_date,
                    'dropoff_date' => $dropoff_date,
                    'pickup_time' => $pickup_time,
                    'dropoff_time' => $dropoff_time,
                    'guests' => $guests,
                    'customer_user_id' => $user_id, // 0 if not logged in, will be set by payment gateway
                    'customer_name' => $customer_name ?: '', // Optional - Payment gateway will collect
                    'customer_first_name' => $customer_first_name ?: '', // Optional - Payment gateway will collect
                    'customer_last_name' => $customer_last_name ?: '', // Optional - Payment gateway will collect
                    'customer_email' => $customer_email ?: '', // Optional - Payment gateway will collect
                    'customer_phone' => $customer_phone ?: '', // Optional - Payment gateway will collect
                    'payment_type' => $payment_type,
                    'payment_method' => $payment_gateway->get_gateway_name(),
                    'payment_gateway' => $payment_gateway->get_gateway_name(),
                    'deposit_amount' => $deposit_result['deposit_amount'],
                    'remaining_amount' => $deposit_result['remaining_amount'],
                    'deposit_type' => $deposit_result['deposit_type'],
                    'payment_display' => $deposit_result['payment_display'],
                    'total_price' => $deposit_result['total_amount'],
                    'rental_days' => $days,
                    'selected_addons' => $selected_addons,
                    'cancellation_policy' => '24_hours',
                    'cancellation_deadline' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                    'payment_deadline' => self::get_payment_deadline($payment_gateway->get_gateway_name()),
                ];

                try {
                    // Add booking data to payment system (without creating booking yet)
                    // Booking will be created when payment is processed
                    if ($payment_gateway->add_booking_to_payment($booking_data_for_payment, $amount_to_pay)) {
                        wp_send_json_success([
                            'message' => __('Redirecting to payment page...', 'mhm-rentiva'),
                            'payment_required' => true,
                            'payment_url' => $payment_gateway->get_checkout_url(),
                            'redirect_url' => $payment_gateway->get_checkout_url(),
                            'payment_method' => $payment_gateway->get_gateway_name(),
                            'booking_data' => [
                                'vehicle_id' => $vehicle_id,
                                'pickup_date' => $pickup_date,
                                'dropoff_date' => $dropoff_date,
                                'days' => $days,
                                'total_price' => $deposit_result['total_amount'],
                                'deposit_amount' => $deposit_result['deposit_amount'],
                                'remaining_amount' => $deposit_result['remaining_amount'],
                                'payment_type' => $payment_type,
                                'payment_method' => $payment_gateway->get_gateway_name(),
                                'addons' => $selected_addons,
                            ]
                        ]);
                        return;
                    }
                } catch (\Exception $e) {
                    // Log error but continue to fallback (or show error)
                    error_log('MHM Rentiva Payment Gateway Error: ' . $e->getMessage());
                    wp_send_json_error(['message' => sprintf(__('Payment gateway error: %s', 'mhm-rentiva'), $e->getMessage())]);
                    return;
                }
            }

            // ⭐ No payment gateway available
            // This code should not be reached if a payment gateway is active (should be handled above)
            wp_send_json_error(['message' => __('No payment gateway is available. Please contact the site administrator.', 'mhm-rentiva')]);
            return;
        } catch (\InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $debug_mode = defined('WP_DEBUG') && WP_DEBUG;
            $message = \MHMRentiva\Admin\Core\SecurityHelper::get_safe_error_message(
                $e->getMessage(),
                $debug_mode
            );
            wp_send_json_error(['message' => $message]);
        }
    }

    /**
     * AJAX price calculation handler
     */
    public static function ajax_calculate_price(): void
    {
        try {
            // Security checks
            \MHMRentiva\Admin\Core\SecurityHelper::verify_ajax_request_or_die(
                'mhm_rentiva_booking_form_nonce',
                'read',
                __('Security check failed.', 'mhm-rentiva')
            );

            // Rate limiting check (increased limits)
            \MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit_or_die(
                'price_calculation',
                100, // 100 requests (increased)
                60, // 1 minute (reduced)
                __('Too many price calculation requests. Please wait.', 'mhm-rentiva')
            );

            // Input validation
            $vehicle_id = \MHMRentiva\Admin\Core\SecurityHelper::validate_vehicle_id($_POST['vehicle_id'] ?? 0);
            $pickup_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['pickup_date'] ?? '');
            $dropoff_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['dropoff_date'] ?? '');
            $addons = \MHMRentiva\Admin\Core\SecurityHelper::validate_numeric_array($_POST['addons'] ?? []);

            // Filter empty addon placeholder (JavaScript doesn't send empty array, sends [0])
            $addons = array_filter($addons, function ($addon_id) {
                return $addon_id > 0;
            });

            if ($vehicle_id <= 0 || empty($pickup_date) || empty($dropoff_date)) {
                wp_send_json_error(['message' => __('Invalid parameters.', 'mhm-rentiva')]);
            }

            // ⭐ DATE VALIDATION: Same-day booking check
            $pickup_datetime = new \DateTime($pickup_date);
            $today = new \DateTime('today');
            $same_day_booking_enabled = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_same_day_booking', '1') === '1';

            if (!$same_day_booking_enabled && $pickup_datetime->format('Y-m-d') === $today->format('Y-m-d')) {
                wp_send_json_error([
                    'message' => __('Same-day booking is not allowed. Please select a future date.', 'mhm-rentiva')
                ]);
            }

            // ⭐ DATE VALIDATION: Advance booking check
            $advance_booking_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_advance_booking', 365);
            $max_booking_date = clone $today;
            $max_booking_date->modify("+{$advance_booking_days} days");

            if ($pickup_datetime > $max_booking_date) {
                wp_send_json_error([
                    'message' => sprintf(
                        /* translators: %d placeholder. */
                        __('Booking too far in advance. Maximum advance booking is %d days.', 'mhm-rentiva'),
                        $advance_booking_days
                    )
                ]);
            }

            // ⭐ DATE VALIDATION: Min/Max rental days
            $min_rental_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1);
            $max_rental_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30);

            // Get vehicle price
            $vehicle_price = floatval(get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true) ?: 0);

            // Calculate number of days (for vehicle rental)
            $start_date = new \DateTime($pickup_date);
            $end_date = new \DateTime($dropoff_date);
            $days = $start_date->diff($end_date)->days;

            // For vehicle rental: both pickup and return days included
            // 10.10 - 17.10 = 7 days (pickup day included, return day included)
            // diff()->days = 7 returns, this is correct

            // ⭐ DATE VALIDATION: Check min/max rental days
            if ($days < $min_rental_days) {
                wp_send_json_error([
                    'message' => sprintf(
                        /* translators: %d placeholder. */
                        __('Minimum rental period is %d days.', 'mhm-rentiva'),
                        $min_rental_days
                    )
                ]);
            }

            if ($days > $max_rental_days) {
                wp_send_json_error([
                    'message' => sprintf(
                        /* translators: %d placeholder. */
                        __('Maximum rental period is %d days.', 'mhm-rentiva'),
                        $max_rental_days
                    )
                ]);
            }

            // ⭐ Apply Vehicle Management Settings
            $base_price_multiplier = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_base_price', 1.0);
            $weekend_multiplier = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_weekend_multiplier', 1.0);

            // ⭐ Tax settings - check WooCommerce first if available
            $tax_enabled = false;
            $tax_inclusive = false;
            $tax_rate = 0;

            if (class_exists('WooCommerce')) {
                // WooCommerce is active - use WooCommerce tax settings
                $tax_enabled = \wc_tax_enabled();
                if ($tax_enabled) {
                    $tax_inclusive = \wc_prices_include_tax();
                    // Get default tax rate from WooCommerce
                    // ⭐ Use global namespace for WC_Tax class
                    $tax_rates = \WC_Tax::get_rates();
                    if (!empty($tax_rates)) {
                        // Get first tax rate (usually the default)
                        $first_rate = reset($tax_rates);
                        $tax_rate = (float) ($first_rate['rate'] ?? 0);
                    }
                }
            } else {
                // WooCommerce not active - use plugin settings
                $tax_inclusive = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_tax_inclusive', '0') === '1';
                $tax_rate = (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_tax_rate', 0);
                $tax_enabled = $tax_rate > 0;
            }

            // Calculate pricing per day (weekend and seasonal multipliers included)
            $seasonal_enabled = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_seasonal_pricing', '0') === '1';
            $vehicle_total = 0.0;

            $iter_date = clone $start_date;
            for ($i = 0; $i < max(0, (int) $days); $i++) {
                $day_price = $vehicle_price * $base_price_multiplier;
                // Weekend multiplier (Saturday/Sunday)
                $dow = (int) $iter_date->format('N'); // 1=Monday, 7=Sunday
                if ($dow >= 6) {
                    $day_price = $day_price * $weekend_multiplier;
                }
                // Seasonal multiplier
                if ($seasonal_enabled) {
                    $season_multiplier = \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings::get_seasonal_multiplier_for_date($iter_date->format('Y-m-d'));
                    $day_price = $day_price * (float) $season_multiplier;
                }
                $vehicle_total += $day_price;
                $iter_date->modify('+1 day');
            }

            // Tax calculation - only if tax is enabled
            $tax_amount = 0;
            $vehicle_subtotal = $vehicle_total;

            if ($tax_enabled && $tax_rate > 0) {
                if ($tax_inclusive) {
                    // ⭐ Tax inclusive pricing:
                    // vehicle_price from meta is ALREADY tax-inclusive
                    // vehicle_total (calculated above) is ALREADY tax-inclusive
                    // We need to EXTRACT tax from the total to show breakdown
                    // Formula: tax = total * (rate / (100 + rate))
                    // Example: If total is 1,200 ₺ with 18% tax included:
                    //   tax = 1,200 * (18 / 118) = 183.05 ₺
                    //   subtotal = 1,200 - 183.05 = 1,016.95 ₺
                    $tax_amount = ($vehicle_total * $tax_rate) / (100 + $tax_rate);
                    $vehicle_subtotal = $vehicle_total - $tax_amount;
                } else {
                    // ⭐ Tax exclusive pricing:
                    // vehicle_price from meta is tax-exclusive
                    // vehicle_total (calculated above) is tax-exclusive
                    // We need to ADD tax to the total
                    // Formula: tax = total * (rate / 100)
                    // Example: If total is 1,000 ₺ with 18% tax:
                    //   tax = 1,000 * 0.18 = 180 ₺
                    //   vehicle_total = 1,000 + 180 = 1,180 ₺
                    $vehicle_subtotal = $vehicle_total; // Subtotal is same as vehicle_total before tax
                    $tax_amount = ($vehicle_total * $tax_rate) / 100;
                    $vehicle_total = $vehicle_total + $tax_amount;
                }
            }

            // Addon total price (daily calculation)
            $addon_total = 0;
            foreach ($addons as $addon_id) {
                $addon_price = floatval(get_post_meta($addon_id, 'addon_price', true) ?: 0);
                $addon_total += $addon_price * $days; // Daily calculation
            }

            // Total price (after tax) - this is what customer will pay
            $total_price = $vehicle_total + $addon_total;

            // Payment type (deposit or full)
            $payment_type = self::sanitize_text_field_safe($_POST['payment_type'] ?? 'deposit');

            // ⭐ SAFETY CHECK: Force Full Payment if Deposit field is removed/empty
            // This ensures calculations reflect "No Deposit" as "Full Payment Required"
            $deposit_check_value = get_post_meta($vehicle_id, '_mhm_rentiva_deposit', true);
            if (empty($deposit_check_value)) {
                $payment_type = 'full';
            }

            // Payment type validation
            if (!in_array($payment_type, ['deposit', 'full'])) {
                throw new \InvalidArgumentException(__('Invalid payment type.', 'mhm-rentiva'));
            }

            // ⭐ Calculate deposit based on FINAL total_price (with tax included)
            // This ensures deposit and remaining amounts are calculated correctly
            $deposit_value = get_post_meta($vehicle_id, '_mhm_rentiva_deposit', true);
            $deposit_type = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::get_deposit_type($deposit_value);
            $deposit_amount = 0;
            $remaining_amount = $total_price;

            if ($payment_type === 'deposit' && !empty($deposit_value)) {
                if ($deposit_type === 'percentage') {
                    $percentage = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::extract_percentage($deposit_value);
                    $deposit_amount = ($total_price * $percentage) / 100;
                } elseif ($deposit_type === 'fixed') {
                    $deposit_amount = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::extract_fixed_amount($deposit_value);
                }

                $remaining_amount = max(0, $total_price - $deposit_amount);
            } elseif ($payment_type === 'full') {
                $deposit_amount = $total_price;
                $remaining_amount = 0;
            }

            // Format deposit result to match expected structure
            $deposit_result = [
                'success' => true,
                'payment_type' => $payment_type,
                'deposit_amount' => round($deposit_amount, 2),
                'total_amount' => round($total_price, 2),
                'remaining_amount' => round($remaining_amount, 2),
                'deposit_type' => $deposit_type,
            ];

            // Currency
            $currency = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD');
            $currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

            // ✅ Check if pickup date is weekend (Saturday=6, Sunday=7)
            $pickup_datetime = new \DateTime($pickup_date);
            $day_of_week = (int) $pickup_datetime->format('N'); // 1 (Monday) to 7 (Sunday)

            $response_data = [
                'vehicle_price' => $vehicle_price,
                'vehicle_subtotal' => $vehicle_subtotal, // Subtotal before tax
                'vehicle_total' => $vehicle_total, // Total with tax
                'addon_total' => $addon_total,
                'total_price' => $total_price,
                'days' => $days,
                'currency' => $currency,
                'currency_symbol' => $currency_symbol,
                'deposit_amount' => $deposit_result['deposit_amount'] ?? 0,
                'remaining_amount' => $deposit_result['remaining_amount'] ?? 0,
                // ⭐ Vehicle Management Settings information
                'base_price_multiplier' => $base_price_multiplier,
                'weekend_multiplier' => $weekend_multiplier,
                'tax_enabled' => $tax_enabled,
                'tax_inclusive' => $tax_inclusive,
                'tax_rate' => $tax_rate,
                'tax_amount' => $tax_amount ?? 0,
                'is_weekend' => $day_of_week >= 6,
            ];

            wp_send_json_success($response_data);
        } catch (\InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $debug_mode = defined('WP_DEBUG') && WP_DEBUG;
            $message = \MHMRentiva\Admin\Core\SecurityHelper::get_safe_error_message(
                $e->getMessage(),
                $debug_mode
            );
            wp_send_json_error(['message' => $message]);
        }
    }

    /**
     * Get active payment gateway
     * 
     * Uses filter hook to allow external plugins to register custom payment gateways
     * 
     * @return PaymentGatewayInterface|null Active payment gateway instance
     */
    private static function get_active_payment_gateway(): ?\MHMRentiva\Admin\Payment\Core\PaymentGatewayInterface
    {
        /**
         * Filter: Allow external plugins to register custom payment gateways
         * 
         * @param PaymentGatewayInterface|null $gateway Payment gateway instance
         * @return PaymentGatewayInterface|null Modified payment gateway instance
         * 
         * @example
         * add_filter('mhm_rentiva_payment_gateway', function($gateway) {
         *     if (class_exists('MyCustomPaymentGateway')) {
         *         return new MyCustomPaymentGateway();
         *     }
         *     return $gateway;
         * });
         */
        $gateway = apply_filters('mhm_rentiva_payment_gateway', null);

        if ($gateway instanceof \MHMRentiva\Admin\Payment\Core\PaymentGatewayInterface) {
            return $gateway;
        }

        // Default: Try WooCommerce if available
        if (class_exists('\MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge')) {
            return new \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge();
        }

        return null;
    }





    /**
     * Get payment deadline based on payment method and settings
     * 
     * @param string $payment_method Payment method (woocommerce only)
     * @return string Payment deadline in 'Y-m-d H:i:s' format
     */
    private static function get_payment_deadline(string $payment_method): string
    {
        // Get payment deadline minutes from settings (default: 30 minutes)
        $deadline_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
            'mhm_rentiva_booking_payment_deadline_minutes',
            30
        );

        // Minimum 5 minutes
        if ($deadline_minutes < 5) {
            $deadline_minutes = 5;
        }

        // ⭐ Use current_time() instead of date() to match WordPress timezone
        // This ensures consistency with AutoCancel which uses current_time('mysql')
        $current_timestamp = current_time('timestamp');
        $deadline_timestamp = $current_timestamp + ($deadline_minutes * 60);
        return date('Y-m-d H:i:s', $deadline_timestamp);
    }

    /**
     * Availability Check AJAX Handler
     */
    public static function ajax_check_availability(): void
    {
        try {
            // Security checks - Correct nonce usage for BookingForm
            \MHMRentiva\Admin\Core\SecurityHelper::verify_ajax_request_or_die(
                'mhm_rentiva_booking_form_nonce',
                'read',
                __('Security check failed.', 'mhm-rentiva')
            );

            // Rate limiting check
            \MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit_or_die(
                'availability_check',
                100, // 100 requests (increased for testing)
                300, // 5 minutes
                __('Too many availability checks. Please wait.', 'mhm-rentiva')
            );

            // Input validation
            $vehicle_id = \MHMRentiva\Admin\Core\SecurityHelper::validate_vehicle_id($_POST['vehicle_id'] ?? 0);
            $pickup_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['pickup_date'] ?? '');
            $pickup_time = self::sanitize_text_field_safe($_POST['pickup_time'] ?? '');
            // Check field names from JavaScript (dropoff_date or return_date)
            $dropoff_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['dropoff_date'] ?? $_POST['return_date'] ?? '');
            $dropoff_time = self::sanitize_text_field_safe($_POST['dropoff_time'] ?? $_POST['return_time'] ?? '');

            if (!$vehicle_id || !$pickup_date || !$dropoff_date) {
                wp_send_json_error(['message' => __('Invalid data.', 'mhm-rentiva')]);
            }

            // ⭐ Clear cache before checking to ensure fresh data
            // This prevents showing stale availability data when a booking was just created
            if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
                \MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
            }

            // ⭐ ADVANCED AVAILABILITY CHECK - With alternative suggestions
            $result = \MHMRentiva\Admin\Booking\Helpers\Util::check_availability_with_alternatives(
                $vehicle_id,
                $pickup_date,
                $pickup_time,
                $dropoff_date,
                $dropoff_time
            );

            if ($result['ok']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $debug_mode = defined('WP_DEBUG') && WP_DEBUG;
            $message = \MHMRentiva\Admin\Core\SecurityHelper::get_safe_error_message(
                $e->getMessage(),
                $debug_mode
            );
            wp_send_json_error(['message' => $message]);
        }
    }

    /**
     * Gets locale for JavaScript
     * 
     * @deprecated Use LanguageHelper::get_current_js_locale() instead
     */
    private static function get_js_locale(): string
    {
        return \MHMRentiva\Admin\Core\LanguageHelper::get_current_js_locale();
    }

    /**
     * jQuery UI DatePicker options for global localization
     */
    private static function get_datepicker_options(): array
    {
        // Apply booking constraints from Vehicle Management Settings
        $allow_same_day = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_allow_same_day', '1') === '1';
        $advance_days   = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_advance_booking_days', 365);

        $min_date = $allow_same_day ? 0 : 1; // 0=today, 1=tomorrow
        $max_date = max(1, $advance_days);

        return [
            'dateFormat' => 'yy-mm-dd',
            'minDate' => $min_date, // Today or Tomorrow
            'maxDate' => $max_date, // Advance booking window
            'showButtonPanel' => true,
            'closeText' => __('Close', 'mhm-rentiva'),
            'currentText' => __('Today', 'mhm-rentiva'),
            'clearText' => __('Clear', 'mhm-rentiva'),
            'monthNames' => [
                __('January', 'mhm-rentiva'),
                __('February', 'mhm-rentiva'),
                __('March', 'mhm-rentiva'),
                __('April', 'mhm-rentiva'),
                __('May', 'mhm-rentiva'),
                __('June', 'mhm-rentiva'),
                __('July', 'mhm-rentiva'),
                __('August', 'mhm-rentiva'),
                __('September', 'mhm-rentiva'),
                __('October', 'mhm-rentiva'),
                __('November', 'mhm-rentiva'),
                __('December', 'mhm-rentiva')
            ],
            'monthNamesShort' => [
                __('Jan', 'mhm-rentiva'),
                __('Feb', 'mhm-rentiva'),
                __('Mar', 'mhm-rentiva'),
                __('Apr', 'mhm-rentiva'),
                __('May', 'mhm-rentiva'),
                __('Jun', 'mhm-rentiva'),
                __('Jul', 'mhm-rentiva'),
                __('Aug', 'mhm-rentiva'),
                __('Sep', 'mhm-rentiva'),
                __('Oct', 'mhm-rentiva'),
                __('Nov', 'mhm-rentiva'),
                __('Dec', 'mhm-rentiva')
            ],
            'dayNames' => [
                __('Sunday', 'mhm-rentiva'),
                __('Monday', 'mhm-rentiva'),
                __('Tuesday', 'mhm-rentiva'),
                __('Wednesday', 'mhm-rentiva'),
                __('Thursday', 'mhm-rentiva'),
                __('Friday', 'mhm-rentiva'),
                __('Saturday', 'mhm-rentiva')
            ],
            'dayNamesShort' => [
                __('Sun', 'mhm-rentiva'),
                __('Mon', 'mhm-rentiva'),
                __('Tue', 'mhm-rentiva'),
                __('Wed', 'mhm-rentiva'),
                __('Thu', 'mhm-rentiva'),
                __('Fri', 'mhm-rentiva'),
                __('Sat', 'mhm-rentiva')
            ],
            'dayNamesMin' => [
                __('Su', 'mhm-rentiva'),
                __('Mo', 'mhm-rentiva'),
                __('Tu', 'mhm-rentiva'),
                __('We', 'mhm-rentiva'),
                __('Th', 'mhm-rentiva'),
                __('Fr', 'mhm-rentiva'),
                __('Sa', 'mhm-rentiva')
            ],
            'weekHeader' => __('Wk', 'mhm-rentiva'),
            'firstDay' => 1, // Monday
            'isRTL' => is_rtl(),
            'showMonthAfterYear' => false,
            'yearSuffix' => '',
            // Critical DatePicker Configuration
            'dateFormat' => 'yy-mm-dd', // ← ISO format for consistency
            'minDate' => 0, // ← Today or later only
            'maxDate' => '+2y', // ← Maximum 2 years ahead
            'changeMonth' => true, // ← Month dropdown
            'changeYear' => true, // ← Year dropdown
            'showButtonPanel' => true, // ← Today/Done buttons
            'closeText' => __('Close', 'mhm-rentiva'),
            'currentText' => __('Today', 'mhm-rentiva'),
            'showOtherMonths' => true,
            'selectOtherMonths' => true,
        ];
    }

    /**
     * Format currency price with symbol and position
     */
    public static function format_currency_price(float $price): string
    {
        $symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();
        $position = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency_position', 'right_space');
        $formatted_amount = number_format($price, 0, ',', '.');

        switch ($position) {
            case 'left':
                return $symbol . $formatted_amount;
            case 'left_space':
                return $symbol . ' ' . $formatted_amount;
            case 'right':
                return $formatted_amount . $symbol;
            case 'right_space':
            default:
                return $formatted_amount . ' ' . $symbol;
        }
    }
}
