<?php

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

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
        add_action('wp_ajax_mhm_rentiva_stripe_checkout', [self::class, 'ajax_stripe_checkout']);
        add_action('wp_ajax_nopriv_mhm_rentiva_stripe_checkout', [self::class, 'ajax_stripe_checkout']);
        add_action('wp_ajax_mhm_rentiva_paypal_checkout', [self::class, 'ajax_paypal_checkout']);
        add_action('wp_ajax_nopriv_mhm_rentiva_paypal_checkout', [self::class, 'ajax_paypal_checkout']);
        add_action('wp_ajax_mhm_rentiva_paytr_checkout', [self::class, 'ajax_paytr_checkout']);
        add_action('wp_ajax_nopriv_mhm_rentiva_paytr_checkout', [self::class, 'ajax_paytr_checkout']);
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
        return []; // No jQuery UI theme - use custom datepicker styling
    }

    protected static function get_js_dependencies(): array
    {
        return ['jquery', 'jquery-ui-datepicker']; // jQuery UI DatePicker dependency added
    }

    protected static function get_localized_data(): array
    {
        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('mhm-rentiva/v1/'),
            'nonce' => wp_create_nonce('mhm_rentiva_booking_form_nonce'), // Correct nonce
            'strings' => static::get_localized_strings(),
            'config' => static::get_js_config(),
            'datepicker_options' => static::get_datepicker_options(),
        ];
    }

    /**
     * Load asset files
     */
    protected static function enqueue_assets(): void
    {
        // CSS
        wp_enqueue_style(
            'mhm-rentiva-booking-form',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/booking-form.css',
            static::get_css_dependencies(),
            MHM_RENTIVA_VERSION . '.' . time() . '.' . rand(1000, 9999) // Cache busting
        );
        
        // JavaScript
        wp_enqueue_script(
            'mhm-rentiva-booking-form',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/booking-form.js',
            static::get_js_dependencies(),
            MHM_RENTIVA_VERSION . '.' . time() . '.' . rand(1000, 9999), // Cache busting
            true
        );

        // Localize script with correct data structure
        wp_localize_script('mhm-rentiva-booking-form', 'mhmRentivaBookingForm', [
            'ajax_url' => admin_url('admin-ajax.php'), // ← snake_case key for JS compatibility
            'ajaxUrl' => admin_url('admin-ajax.php'),  // ← backup camelCase
            'nonce' => wp_create_nonce('mhm_rentiva_booking_form_nonce'),
            'currency' => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD'),
            'currencySymbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
            'currencyPosition' => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency_position', 'right_space'),
            'defaultDays' => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_default_rental_days', 1),
            'minDays' => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1),
            'maxDays' => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30),
            'datepicker_options' => self::get_datepicker_options(), // ← DatePicker options eklendi!
            'strings' => [
                'loading' => __('⏳ Checking availability...', 'mhm-rentiva'),
                'error' => __('❌ An error occurred. Please try again.', 'mhm-rentiva'),
                'success' => __('✅ Success!', 'mhm-rentiva'),
                'selectVehicle' => __('🚗 Please select a vehicle first', 'mhm-rentiva'),
                'selectDates' => __('📅 Please select pickup and return dates', 'mhm-rentiva'),
                'invalidDates' => __('❌ Invalid date selection. Please check your dates.', 'mhm-rentiva'),
                'priceCalculated' => __('💰 Price calculated successfully', 'mhm-rentiva'),
                'vehicleNotAvailable' => __('❌ Vehicle is not available for selected dates', 'mhm-rentiva'),
                'checkingAvailability' => __('🔍 Checking vehicle availability...', 'mhm-rentiva'),
                'findingAlternatives' => __('🔍 Finding alternative vehicles...', 'mhm-rentiva'),
                'noAlternatives' => __('❌ No alternative vehicles found', 'mhm-rentiva'),
                'vehicle_unavailable_with_alternatives' => __('❌ Selected vehicle is not available, but we found similar vehicles for you:', 'mhm-rentiva'),
                'select_this_vehicle' => __('Select This Vehicle', 'mhm-rentiva'),
                'vehicle_available' => __('✅ Great! This vehicle is available for your selected dates.', 'mhm-rentiva'),
                'check_availability' => __('🔍 Checking availability...', 'mhm-rentiva'),
                'daily_price' => __('Daily Price', 'mhm-rentiva'),
                'total' => __('Total', 'mhm-rentiva'),
            ],
            'favorites' => [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_rentiva_toggle_favorite'),
                'strings' => [
                    'added' => __('Added to favorites', 'mhm-rentiva'),
                    'removed' => __('Removed from favorites', 'mhm-rentiva'),
                    'login_required' => __('Please log in to manage favorites.', 'mhm-rentiva'),
                    'error' => __('An error occurred while updating favorites.', 'mhm-rentiva'),
                    'add_label' => __('Add to favorites', 'mhm-rentiva'),
                    'remove_label' => __('Remove from favorites', 'mhm-rentiva'),
                ],
            ],
        ]);
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
            
            // Price display
            'per_day' => __('/day', 'mhm-rentiva'),
            'daily_price' => __('Daily Price', 'mhm-rentiva'),
            'days_count' => __('Days', 'mhm-rentiva'),
            'vehicle_total' => __('Vehicle Total', 'mhm-rentiva'),
            'addons_total' => __('Add-ons Total', 'mhm-rentiva'),
            'total_amount' => __('Total Amount', 'mhm-rentiva'),
            'deposit_amount' => __('Deposit Amount', 'mhm-rentiva'),
            'remaining_amount' => __('Remaining Amount', 'mhm-rentiva'),
            
            // Loading and errors
            'loading' => __('Loading...', 'mhm-rentiva'),
            'calculating' => __('Calculating...', 'mhm-rentiva'),
            'submitting' => __('Submitting...', 'mhm-rentiva'),
            'error_occurred' => __('An error occurred', 'mhm-rentiva'),
            'try_again' => __('Please try again', 'mhm-rentiva'),
            'connection_error' => __('Connection error', 'mhm-rentiva'),
        ];
    }

    protected static function get_js_config(): array
    {
        return [
            'currency_symbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
            'locale' => self::get_js_locale(),
            'enable_deposit' => get_option('mhm_rentiva_enable_deposit', '1') === '1',
            'default_payment' => get_option('mhm_rentiva_default_payment', 'deposit'),
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
        for ($hour = 8; $hour <= 20; $hour++) {
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
                5, // 5 request
                300, // 5 minutes
                __('Too many booking requests. Please wait 5 minutes.', 'mhm-rentiva')
            );

            // Input validation
            $vehicle_id = \MHMRentiva\Admin\Core\SecurityHelper::validate_vehicle_id($_POST['vehicle_id'] ?? 0);
            $pickup_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['pickup_date'] ?? '');
            $dropoff_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['dropoff_date'] ?? '');
            $pickup_time = self::sanitize_text_field_safe($_POST['pickup_time'] ?? '');
            $dropoff_time = self::sanitize_text_field_safe($_POST['dropoff_time'] ?? '');
            $guests = max(1, intval($_POST['guests'] ?? 1));
            // Get first/last name fields
            $customer_first_name = self::sanitize_text_field_safe($_POST['customer_first_name'] ?? '');
            $customer_last_name = self::sanitize_text_field_safe($_POST['customer_last_name'] ?? '');
            $customer_name = trim($customer_first_name . ' ' . $customer_last_name);
            $customer_email = \MHMRentiva\Admin\Core\SecurityHelper::validate_email($_POST['customer_email'] ?? '');
            $customer_phone = \MHMRentiva\Admin\Core\SecurityHelper::validate_phone($_POST['customer_phone'] ?? '');
            $payment_type = self::sanitize_text_field_safe($_POST['payment_type'] ?? 'deposit');
            $payment_method = self::sanitize_text_field_safe($_POST['payment_method'] ?? 'online');
            $payment_gateway = self::sanitize_text_field_safe($_POST['payment_gateway'] ?? '');
            
            // ⭐ Terms & Conditions validation (if required)
            $terms_required = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_terms_required', '0') === '1';
            if ($terms_required) {
                // Accept 'on', '1', 'true', or any truthy value
                $terms_value = $_POST['terms_accepted'] ?? '';
                $terms_accepted = !empty($terms_value) && ($terms_value === 'on' || $terms_value === '1' || $terms_value === 'true');
                
                if (!$terms_accepted) {
                    wp_send_json_error([
                        'message' => __('You must accept the terms and conditions to complete your booking.', 'mhm-rentiva'),
                        'debug' => 'Terms not accepted. Received: ' . var_export($terms_value, true)
                    ]);
                }
            }
            
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
            

            // Validasyon
            if ($vehicle_id <= 0) {
                wp_send_json_error(['message' => __('Invalid vehicle ID.', 'mhm-rentiva')]);
            }

            if (empty($pickup_date) || empty($dropoff_date)) {
                wp_send_json_error(['message' => __('Please select dates.', 'mhm-rentiva')]);
            }

            // Skip validation for admin users
            $is_admin = current_user_can('manage_options');
            
            if (!$is_admin && (empty($customer_first_name) || empty($customer_last_name) || empty($customer_email) || empty($customer_phone))) {
                wp_send_json_error(['message' => __('Please fill in contact information.', 'mhm-rentiva')]);
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

            // ⭐ AVAILABILITY CHECK - CONFLICT CHECK
            $availability_result = \MHMRentiva\Admin\Booking\Helpers\Util::check_availability(
                $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time
            );
            
            if (!$availability_result['ok']) {
                wp_send_json_error([
                    'message' => $availability_result['message'],
                    'code' => $availability_result['code']
                ]);
            }

            // Calculate number of days
            $days = $start_date->diff($end_date)->days;
            
            // ⭐ Check or create customer account
            $user = get_user_by('email', $customer_email);
            $is_new_user = false;
            $user_id = 0;

            if (!$user) {
                // Generate username from first name + last name
                $base_username = trim(strtolower($customer_first_name . '.' . $customer_last_name));
                $base_username = sanitize_user($base_username, true);
                
                // If username is empty or invalid, use email prefix as fallback
                if (empty($base_username) || !validate_username($base_username)) {
                    $email_parts = explode('@', $customer_email);
                    $base_username = sanitize_user($email_parts[0], true);
                }
                
                // Ensure username is unique
                $username = $base_username;
                $counter = 1;
                while (username_exists($username)) {
                    $username = $base_username . $counter;
                    $counter++;
                }
                
                // Create automatic WordPress account
                $user_id = wp_create_user(
                    $username,
                    wp_generate_password(12, true, true),
                    $customer_email
                );
                
                if (!is_wp_error($user_id)) {
                    // Determine safe default role
                    $default_role = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_default_role', 'customer');
                    if (!get_role($default_role)) {
                        $default_role = 'customer';
                    }
                    // Update user information
                    wp_update_user([
                        'ID' => $user_id,
                        'display_name' => $customer_name,
                        'first_name' => $customer_first_name,
                        'last_name' => $customer_last_name,
                        'role' => $default_role,
                    ]);
                    // Ensure role is set even if wp_update_user ignores role
                    $wp_user_obj = new \WP_User($user_id);
                    if (!in_array($default_role, (array) $wp_user_obj->roles, true)) {
                        $wp_user_obj->set_role($default_role);
                    }
                    
                    // Save meta data
                    update_user_meta($user_id, 'mhm_rentiva_phone', $customer_phone);
                    update_user_meta($user_id, 'mhm_rentiva_customer', true);
                    
                    $is_new_user = true;
                    
                    // Send password reset email (WordPress automatic)
                    wp_new_user_notification($user_id, null, 'user');
                } else {
                    // User could not be created but continue (booking can be made)
                }
            } else {
                $user_id = $user->ID;
            }
            
            // Calculate deposit
            $deposit_result = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::calculate_booking_deposit(
                $vehicle_id,
                $days,
                $payment_type,
                $selected_addons
            );

            if (!$deposit_result['success']) {
                wp_send_json_error(['message' => __('Price could not be calculated.', 'mhm-rentiva')]);
            }

            // Create booking
            $booking_data = [
                'post_type' => 'vehicle_booking',
                'post_status' => 'publish',
                'post_title' => sprintf(__('Booking - %s', 'mhm-rentiva'), get_the_title($vehicle_id)),
                'meta_input' => [
                    '_mhm_vehicle_id' => $vehicle_id,
                    // ✅ Fixed: Compatible with admin panel meta keys
                    '_mhm_start_date' => $pickup_date,
                    '_mhm_end_date' => $dropoff_date,
                    '_mhm_pickup_date' => $pickup_date,  // Compatible with Handler.php
                    '_mhm_dropoff_date' => $dropoff_date, // Compatible with Handler.php
                    '_mhm_start_ts' => strtotime($pickup_date . ' ' . $pickup_time), // For admin panel
                    '_mhm_end_ts' => strtotime($dropoff_date . ' ' . $dropoff_time), // For admin panel
                    '_mhm_start_time' => $pickup_time,
                    '_mhm_end_time' => $dropoff_time,
                    '_mhm_guests' => $guests,
                    '_mhm_customer_user_id' => $user_id, // ✅ Link User ID
                    '_mhm_customer_name' => $customer_name,
                    '_mhm_customer_first_name' => $customer_first_name,
                    '_mhm_customer_last_name' => $customer_last_name,
                    '_mhm_customer_email' => $customer_email,
                    '_mhm_customer_phone' => $customer_phone,
                    '_mhm_status' => 'pending',
                    '_mhm_booking_type' => 'booking_form',
                    '_mhm_created_via' => 'booking_form_shortcode',
                    '_mhm_payment_type' => $payment_type,
                    '_mhm_payment_method' => $payment_method,
                    '_mhm_payment_gateway' => ($payment_method === 'online') ? $payment_gateway : 'offline',
                    '_mhm_payment_status' => 'pending', // Pending for offline payments
                    '_mhm_deposit_amount' => $deposit_result['deposit_amount'],
                    '_mhm_remaining_amount' => $deposit_result['remaining_amount'],
                    '_mhm_deposit_type' => $deposit_result['deposit_type'],
                    '_mhm_payment_display' => $deposit_result['payment_display'],
                    '_mhm_total_price' => $deposit_result['total_amount'],
                    '_mhm_rental_days' => $days,
                    '_mhm_selected_addons' => $selected_addons,
                    '_mhm_cancellation_policy' => '24_hours',
                    '_mhm_cancellation_deadline' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                    '_mhm_payment_deadline' => $payment_method === 'offline' ? 
                        date('Y-m-d H:i:s', strtotime('+30 minutes')) : null,
                ]
            ];

            $booking_id = wp_insert_post($booking_data);

            if (is_wp_error($booking_id)) {
                wp_send_json_error(['message' => __('Booking could not be created.', 'mhm-rentiva')]);
            }

            // Booking History - "Booking created" note
            \MHMRentiva\Admin\Booking\Meta\BookingMeta::add_history_note(
                $booking_id,
                __('Booking created', 'mhm-rentiva'),
                'system'
            );
            update_post_meta($booking_id, '_mhm_booking_created', '1');

        // ✅ Trigger email notifications
        do_action('mhm_rentiva_booking_created', $booking_id, $booking_data);
            
            // Debug: Log email sending

            // Payment processing check
            if ($payment_method === 'online' && $payment_gateway) {
                // Create payment URL for online payment
                $payment_url = self::create_payment_url($booking_id, $deposit_result, $payment_gateway);
                
                wp_send_json_success([
                    'booking_id' => $booking_id,
                    'message' => __('Booking created. Redirecting to payment page...', 'mhm-rentiva'),
                    'payment_required' => true,
                    'payment_url' => $payment_url,
                    'redirect_url' => self::get_redirect_url($booking_id, $_POST['redirect_url'] ?? ''),
                    'confirmation_url' => \MHMRentiva\Admin\Frontend\Shortcodes\BookingConfirmation::get_confirmation_url($booking_id),
                    'booking_data' => [
                        'vehicle_id' => $vehicle_id,
                        'pickup_date' => $pickup_date,
                        'dropoff_date' => $dropoff_date,
                        'days' => $days,
                        'total_price' => $deposit_result['total_amount'],
                        'deposit_amount' => $deposit_result['deposit_amount'],
                        'remaining_amount' => $deposit_result['remaining_amount'],
                        'payment_type' => $payment_type,
                        'payment_method' => $payment_method,
                        'payment_gateway' => $payment_gateway,
                        'addons' => $selected_addons,
                    ]
                ]);
            } else {
                // Direct success for offline payment
                wp_send_json_success([
                    'booking_id' => $booking_id,
                    'message' => __('Booking created successfully. Payment details will be sent via email.', 'mhm-rentiva'),
                    'payment_required' => false,
                    'redirect_url' => self::get_redirect_url($booking_id, $_POST['redirect_url'] ?? ''),
                    'confirmation_url' => \MHMRentiva\Admin\Frontend\Shortcodes\BookingConfirmation::get_confirmation_url($booking_id),
                    'booking_data' => [
                        'vehicle_id' => $vehicle_id,
                        'pickup_date' => $pickup_date,
                        'dropoff_date' => $dropoff_date,
                        'days' => $days,
                        'total_price' => $deposit_result['total_amount'],
                        'deposit_amount' => $deposit_result['deposit_amount'],
                        'remaining_amount' => $deposit_result['remaining_amount'],
                        'payment_type' => $payment_type,
                        'payment_method' => $payment_method,
                        'addons' => $selected_addons,
                    ]
                ]);
            }

        } catch (InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (Exception $e) {
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
            $addons = array_filter($addons, function($addon_id) {
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
                        __('Minimum rental period is %d days.', 'mhm-rentiva'),
                        $min_rental_days
                    )
                ]);
            }

            if ($days > $max_rental_days) {
                wp_send_json_error([
                    'message' => sprintf(
                        __('Maximum rental period is %d days.', 'mhm-rentiva'),
                        $max_rental_days
                    )
                ]);
            }
            
            // ⭐ Vehicle Management Settings uygula
            $base_price_multiplier = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_base_price', 1.0);
            $weekend_multiplier = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_weekend_multiplier', 1.0);
            $tax_inclusive = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_tax_inclusive', '0') === '1';
            $tax_rate = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_tax_rate', 0);
            
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
            
            // Tax calculation
            if ($tax_inclusive) {
                // Tax inclusive price - already calculated
                $tax_amount = 0;
            } else {
                // Tax exclusive price - add tax
                $tax_amount = ($vehicle_total * $tax_rate) / 100;
                $vehicle_total = $vehicle_total + $tax_amount;
            }
            
            // Addon total price (daily calculation)
            $addon_total = 0;
            foreach ($addons as $addon_id) {
                $addon_price = floatval(get_post_meta($addon_id, 'addon_price', true) ?: 0);
                $addon_total += $addon_price * $days; // Daily calculation
            }
            
            // Total price
            $total_price = $vehicle_total + $addon_total;
            
            // Payment type (deposit or full)
            $payment_type = self::sanitize_text_field_safe($_POST['payment_type'] ?? 'deposit');
            
            // Payment type validation
            if (!in_array($payment_type, ['deposit', 'full'])) {
                throw new InvalidArgumentException(__('Invalid payment type.', 'mhm-rentiva'));
            }
            
            // Calculate deposit
            $deposit_result = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::calculate_booking_deposit(
                $vehicle_id,
                $days,
                $payment_type,
                $addons
            );
            
            // Currency
            $currency = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD');
            $currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

            // ✅ Check if pickup date is weekend (Saturday=6, Sunday=7)
            $pickup_datetime = new \DateTime($pickup_date);
            $day_of_week = (int) $pickup_datetime->format('N'); // 1 (Monday) to 7 (Sunday)

            $response_data = [
                'vehicle_price' => $vehicle_price,
                'vehicle_total' => $vehicle_total,
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
     * Create payment URL
     */
    private static function create_payment_url(int $booking_id, array $deposit_result, string $payment_gateway): string
    {
        $payment_data = [
            'booking_id' => $booking_id,
            'amount' => $deposit_result['deposit_amount'],
            'total_amount' => $deposit_result['total_amount'],
            'currency' => 'USD',
            'payment_type' => 'deposit',
            'gateway' => $payment_gateway,
            'return_url' => self::get_redirect_url($booking_id),
            'cancel_url' => wp_get_referer() ?: home_url(),
        ];

        // Create payment URL based on gateway
        switch ($payment_gateway) {
            case 'stripe':
                return self::create_stripe_payment_url($payment_data);
            case 'paypal':
                return self::create_paypal_payment_url($payment_data);
            case 'paytr':
                return self::create_paytr_payment_url($payment_data);
            default:
                return self::get_redirect_url($booking_id);
        }
    }

    /**
     * Create Stripe payment URL
     */
    private static function create_stripe_payment_url(array $payment_data): string
    {
        // Create Stripe Checkout Session
        $session_data = [
            'booking_id' => $payment_data['booking_id'],
            'amount' => $payment_data['amount'],
            'currency' => $payment_data['currency'],
            'return_url' => $payment_data['return_url'],
            'cancel_url' => $payment_data['cancel_url'],
        ];

        // Stripe session creation process will be done here
        // For now, returning placeholder URL
        return add_query_arg([
            'action' => 'mhm_rentiva_stripe_checkout',
            'booking_id' => $payment_data['booking_id'],
            'amount' => $payment_data['amount'],
            'nonce' => wp_create_nonce('mhm_rentiva_stripe_checkout')
        ], admin_url('admin-ajax.php'));
    }

    /**
     * Create PayPal payment URL
     */
    private static function create_paypal_payment_url(array $payment_data): string
    {
        // Create PayPal Order
        return add_query_arg([
            'action' => 'mhm_rentiva_paypal_checkout',
            'booking_id' => $payment_data['booking_id'],
            'amount' => $payment_data['amount'],
            'nonce' => wp_create_nonce('mhm_rentiva_paypal_checkout')
        ], admin_url('admin-ajax.php'));
    }

    /**
     * Create PayTR payment URL
     */
    private static function create_paytr_payment_url(array $payment_data): string
    {
        // Create PayTR Token
        return add_query_arg([
            'action' => 'mhm_rentiva_paytr_checkout',
            'booking_id' => $payment_data['booking_id'],
            'amount' => $payment_data['amount'],
            'nonce' => wp_create_nonce('mhm_rentiva_paytr_checkout')
        ], admin_url('admin-ajax.php'));
    }

    private static function get_redirect_url(int $booking_id, string $custom_redirect = ''): string
    {
        if (!empty($custom_redirect)) {
            return $custom_redirect;
        }

        return '';
    }

    /**
     * Stripe Checkout AJAX Handler
     */
    public static function ajax_stripe_checkout(): void
    {
        try {
            // Nonce check
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_stripe_checkout')) {
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            $booking_id = (int) ($_POST['booking_id'] ?? 0);
            $amount = (float) ($_POST['amount'] ?? 0);

            if (!$booking_id || !$amount) {
                wp_send_json_error(['message' => __('Invalid booking or amount.', 'mhm-rentiva')]);
            }

            // Get booking information
            $booking = get_post($booking_id);
            if (!$booking || $booking->post_type !== 'vehicle_booking') {
                wp_send_json_error(['message' => __('Booking not found.', 'mhm-rentiva')]);
            }

            // Create Stripe Checkout Session
            $checkout_url = self::create_stripe_checkout_session($booking_id, $amount);
            
            wp_send_json_success([
                'checkout_url' => $checkout_url,
                'message' => __('Stripe payment page ready.', 'mhm-rentiva')
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Could not create payment page.', 'mhm-rentiva')]);
        }
    }

    /**
     * PayPal Checkout AJAX Handler
     */
    public static function ajax_paypal_checkout(): void
    {
        try {
            // Nonce check
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_paypal_checkout')) {
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            $booking_id = (int) ($_POST['booking_id'] ?? 0);
            $amount = (float) ($_POST['amount'] ?? 0);

            if (!$booking_id || !$amount) {
                wp_send_json_error(['message' => __('Invalid booking or amount.', 'mhm-rentiva')]);
            }

            // Create PayPal Order
            $checkout_url = self::create_paypal_order($booking_id, $amount);
            
            wp_send_json_success([
                'checkout_url' => $checkout_url,
                'message' => __('PayPal payment page ready.', 'mhm-rentiva')
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Could not create payment page.', 'mhm-rentiva')]);
        }
    }

    /**
     * PayTR Checkout AJAX Handler
     */
    public static function ajax_paytr_checkout(): void
    {
        try {
            // Nonce check
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_paytr_checkout')) {
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            $booking_id = (int) ($_POST['booking_id'] ?? 0);
            $amount = (float) ($_POST['amount'] ?? 0);

            if (!$booking_id || !$amount) {
                wp_send_json_error(['message' => __('Invalid booking or amount.', 'mhm-rentiva')]);
            }

            // Create PayTR Token
            $checkout_url = self::create_paytr_token($booking_id, $amount);
            
            wp_send_json_success([
                'checkout_url' => $checkout_url,
                'message' => __('PayTR payment page ready.', 'mhm-rentiva')
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Could not create payment page.', 'mhm-rentiva')]);
        }
    }

    /**
     * Create Stripe Checkout Session
     */
    private static function create_stripe_checkout_session(int $booking_id, float $amount): string
    {
        // Real Stripe API integration will be done here
        // For now, returning placeholder URL
        
        $session_data = [
            'booking_id' => $booking_id,
            'amount' => $amount,
            'currency' => 'try',
            'success_url' => self::get_payment_success_url($booking_id),
            'cancel_url' => self::get_payment_cancel_url($booking_id),
        ];

        // Real implementation will use Stripe SDK
        // return $stripe->checkout->sessions->create($session_data)->url;
        
        return add_query_arg([
            'booking_id' => $booking_id,
            'amount' => $amount,
            'gateway' => 'stripe'
        ], home_url('/stripe-checkout/'));
    }

    /**
     * Creates PayPal Order
     */
    private static function create_paypal_order(int $booking_id, float $amount): string
    {
        // Real PayPal API integration will be implemented here
        // For now, returning placeholder URL
        
        $order_data = [
            'booking_id' => $booking_id,
            'amount' => $amount,
            'currency' => 'TRY',
            'return_url' => self::get_payment_success_url($booking_id),
            'cancel_url' => self::get_payment_cancel_url($booking_id),
        ];

        // Real implementation will use PayPal SDK
        // return $paypal->orders->create($order_data)->links[0]->href;
        
        return add_query_arg([
            'booking_id' => $booking_id,
            'amount' => $amount,
            'gateway' => 'paypal'
        ], home_url('/paypal-checkout/'));
    }

    /**
     * Creates PayTR Token
     */
    private static function create_paytr_token(int $booking_id, float $amount): string
    {
        // Real PayTR API integration will be implemented here
        // For now, returning placeholder URL
        
        $token_data = [
            'booking_id' => $booking_id,
            'amount' => $amount,
            'currency' => 'TRY',
            'success_url' => self::get_payment_success_url($booking_id),
            'fail_url' => self::get_payment_cancel_url($booking_id),
        ];

        // Real implementation will use PayTR API
        // return $paytr->createToken($token_data);
        
        return add_query_arg([
            'booking_id' => $booking_id,
            'amount' => $amount,
            'gateway' => 'paytr'
        ], home_url('/paytr-checkout/'));
    }

    /**
     * Payment success URL
     */
    private static function get_payment_success_url(int $booking_id): string
    {
        return add_query_arg([
            'booking_id' => $booking_id,
            'payment_status' => 'success'
        ], self::get_redirect_url($booking_id));
    }

    /**
     * Payment cancel URL
     */
    private static function get_payment_cancel_url(int $booking_id): string
    {
        return add_query_arg([
            'booking_id' => $booking_id,
            'payment_status' => 'cancelled'
        ], wp_get_referer() ?: home_url());
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

            // ⭐ ADVANCED AVAILABILITY CHECK - With alternative suggestions
            $result = \MHMRentiva\Admin\Booking\Helpers\Util::check_availability_with_alternatives(
                $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time
            );

            if ($result['ok']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }

        } catch (InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (Exception $e) {
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
                __('January', 'mhm-rentiva'), __('February', 'mhm-rentiva'), 
                __('March', 'mhm-rentiva'), __('April', 'mhm-rentiva'), 
                __('May', 'mhm-rentiva'), __('June', 'mhm-rentiva'), 
                __('July', 'mhm-rentiva'), __('August', 'mhm-rentiva'), 
                __('September', 'mhm-rentiva'), __('October', 'mhm-rentiva'), 
                __('November', 'mhm-rentiva'), __('December', 'mhm-rentiva')
            ],
            'monthNamesShort' => [
                __('Jan', 'mhm-rentiva'), __('Feb', 'mhm-rentiva'), 
                __('Mar', 'mhm-rentiva'), __('Apr', 'mhm-rentiva'), 
                __('May', 'mhm-rentiva'), __('Jun', 'mhm-rentiva'), 
                __('Jul', 'mhm-rentiva'), __('Aug', 'mhm-rentiva'), 
                __('Sep', 'mhm-rentiva'), __('Oct', 'mhm-rentiva'), 
                __('Nov', 'mhm-rentiva'), __('Dec', 'mhm-rentiva')
            ],
            'dayNames' => [
                __('Sunday', 'mhm-rentiva'), __('Monday', 'mhm-rentiva'), 
                __('Tuesday', 'mhm-rentiva'), __('Wednesday', 'mhm-rentiva'), 
                __('Thursday', 'mhm-rentiva'), __('Friday', 'mhm-rentiva'), 
                __('Saturday', 'mhm-rentiva')
            ],
            'dayNamesShort' => [
                __('Sun', 'mhm-rentiva'), __('Mon', 'mhm-rentiva'), 
                __('Tue', 'mhm-rentiva'), __('Wed', 'mhm-rentiva'), 
                __('Thu', 'mhm-rentiva'), __('Fri', 'mhm-rentiva'), 
                __('Sat', 'mhm-rentiva')
            ],
            'dayNamesMin' => [
                __('Su', 'mhm-rentiva'), __('Mo', 'mhm-rentiva'), 
                __('Tu', 'mhm-rentiva'), __('We', 'mhm-rentiva'), 
                __('Th', 'mhm-rentiva'), __('Fr', 'mhm-rentiva'), 
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
