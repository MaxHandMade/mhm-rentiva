<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manual Booking Meta Box
 * 
 * Admin can add booking manually
 */
final class ManualBookingMetaBox extends AbstractMetaBox
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

    protected static function get_post_type(): string
    {
        return 'vehicle_booking';
    }

    protected static function get_meta_box_id(): string
    {
        return 'mhm_rentiva_manual_booking';
    }

    protected static function get_title(): string
    {
        return __('Create Manual Booking', 'mhm-rentiva');
    }

    protected static function get_context(): string
    {
        return 'normal';
    }

    protected static function get_priority(): string
    {
        return 'high';
    }

    protected static function get_fields(): array
    {
        return [
            'mhm_manual_booking_fields' => [
                'title' => __('Create Manual Booking', 'mhm-rentiva'),
                'context' => 'normal',
                'priority' => 'high',
                'template' => 'render',
            ],
        ];
    }

    public static function register(): void
    {
        // Show meta box only when creating new booking
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);

        // Hide WordPress default post fields
        add_action('add_meta_boxes', [self::class, 'remove_default_meta_boxes'], 999);

        // Scripts ve styles
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);

        // AJAX handlers
        add_action('wp_ajax_mhm_rentiva_calculate_manual_booking', [self::class, 'ajax_calculate_price']);
        add_action('wp_ajax_mhm_rentiva_create_manual_booking', [self::class, 'ajax_create_booking']);
    }

    /**
     * Remove WordPress default meta boxes
     */
    public static function remove_default_meta_boxes(): void
    {
        global $post, $pagenow;

        // Only on new vehicle_booking page
        if ($pagenow !== 'post-new.php' || !$post || $post->post_type !== 'vehicle_booking' || $post->post_status !== 'auto-draft') {
            return;
        }

        // Remove default meta boxes
        remove_meta_box('submitdiv', 'vehicle_booking', 'side');
        remove_meta_box('slugdiv', 'vehicle_booking', 'normal');
        remove_meta_box('authordiv', 'vehicle_booking', 'normal');
        remove_meta_box('postcustom', 'vehicle_booking', 'normal');
        remove_meta_box('postexcerpt', 'vehicle_booking', 'normal');
        remove_meta_box('trackbacksdiv', 'vehicle_booking', 'normal');
        remove_meta_box('commentstatusdiv', 'vehicle_booking', 'normal');
        remove_meta_box('commentsdiv', 'vehicle_booking', 'normal');
        remove_meta_box('revisionsdiv', 'vehicle_booking', 'normal');
        remove_meta_box('pageparentdiv', 'vehicle_booking', 'side');
        remove_meta_box('postimagediv', 'vehicle_booking', 'side');

        // Hide title field
        add_filter('enter_title_here', function ($title) {
            return '';
        });
    }

    /**
     * Add meta box - only when creating new booking
     */
    public static function add_meta_boxes(): void
    {
        global $post, $pagenow;

        // Only on new booking creation page
        if ($pagenow !== 'post-new.php') {
            return;
        }

        // Post type check
        if (!$post || $post->post_type !== 'vehicle_booking') {
            return;
        }

        // Show only when creating new booking (in auto-draft status)
        if ($post->post_status !== 'auto-draft') {
            return;
        }

        add_meta_box(
            self::get_meta_box_id(),
            self::get_title(),
            [self::class, 'render'],
            self::get_post_type(),
            self::get_context(),
            self::get_priority()
        );
    }

    public static function enqueue_scripts(string $hook): void
    {
        global $post_type;

        // Load only on new booking creation page
        if ($hook === 'post-new.php' && $post_type === 'vehicle_booking') {
            wp_enqueue_style(
                'mhm-manual-booking-meta',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/manual-booking-meta.css',
                [],
                MHM_RENTIVA_VERSION
            );

            wp_enqueue_script(
                'mhm-manual-booking-meta',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/manual-booking-meta.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );

            // Localize for AJAX
            wp_localize_script('mhm-manual-booking-meta', 'mhmManualBooking', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_manual_booking_nonce'),
                'text' => [
                    'calculating' => __('Calculating...', 'mhm-rentiva'),
                    'error' => __('An error occurred', 'mhm-rentiva'),
                    'success' => __('Booking created', 'mhm-rentiva'),
                ]
            ]);
        }
    }

    public static function render(\WP_Post $post, array $args = []): void
    {
        wp_nonce_field('mhm_manual_booking_action', 'mhm_manual_booking_meta_nonce');

        // Get available vehicles - first without meta query
        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        // Manually filter available ones
        $available_vehicles = [];
        foreach ($vehicles as $vehicle) {
            $available = get_post_meta($vehicle->ID, '_mhm_vehicle_availability', true);
            if ($available === 'active') {
                $available_vehicles[] = $vehicle;
            }
        }
        $vehicles = $available_vehicles;

        // Get users
        $users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);

        echo '<div class="mhm-manual-booking-form">';


        echo '<div class="mhm-booking-fields">';

        // Vehicle Selection
        echo '<div class="mhm-field-group">';
        echo '<label for="mhm_manual_vehicle_id" class="mhm-field-label">' . __('Select Vehicle', 'mhm-rentiva') . ' <span class="required">*</span></label>';


        echo '<select id="mhm_manual_vehicle_id" name="mhm_manual_vehicle_id" class="mhm-field-select" required>';
        echo '<option value="">' . __('Select a vehicle...', 'mhm-rentiva') . '</option>';

        foreach ($vehicles as $vehicle) {
            $price = get_post_meta($vehicle->ID, '_mhm_rentiva_price_per_day', true);
            $price_float = floatval($price);
            $price_text = $price_float > 0 ? ' (' . self::format_addon_price($price_float) . '/' . __('day', 'mhm-rentiva') . ')' : '';
            echo '<option value="' . esc_attr($vehicle->ID) . '" data-price="' . esc_attr($price_float) . '">';
            echo esc_html($vehicle->post_title . $price_text);
            echo '</option>';
        }

        echo '</select>';
        echo '</div>';

        // Customer Selection
        echo '<div class="mhm-field-group">';
        echo '<label for="mhm_manual_customer_id" class="mhm-field-label">' . __('Customer', 'mhm-rentiva') . ' <span class="required">*</span></label>';
        echo '<select id="mhm_manual_customer_id" name="mhm_manual_customer_id" class="mhm-field-select" required>';
        echo '<option value="">' . __('Select a customer...', 'mhm-rentiva') . '</option>';
        echo '<option value="new_customer">' . __('+ Create New Customer', 'mhm-rentiva') . '</option>';

        foreach ($users as $user) {
            echo '<option value="' . esc_attr($user->ID) . '">';
            echo esc_html($user->display_name . ' (' . $user->user_email . ')');
            echo '</option>';
        }

        echo '</select>';
        echo '</div>';

        // New Customer Information (hidden)
        echo '<div id="mhm_new_customer_fields" class="mhm-field-group" style="display: none;">';
        echo '<h4>' . __('New Customer Information', 'mhm-rentiva') . '</h4>';

        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_new_customer_first_name" class="mhm-field-label">' . __('First Name', 'mhm-rentiva') . ' <span class="required">*</span></label>';
        echo '<input type="text" id="mhm_new_customer_first_name" name="mhm_new_customer_first_name" class="mhm-field-input">';
        echo '</div>';

        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_new_customer_last_name" class="mhm-field-label">' . __('Last Name', 'mhm-rentiva') . ' <span class="required">*</span></label>';
        echo '<input type="text" id="mhm_new_customer_last_name" name="mhm_new_customer_last_name" class="mhm-field-input">';
        echo '</div>';

        echo '<div class="mhm-field-group">';
        echo '<label for="mhm_new_customer_email" class="mhm-field-label">' . __('Email', 'mhm-rentiva') . ' <span class="required">*</span></label>';
        echo '<input type="email" id="mhm_new_customer_email" name="mhm_new_customer_email" class="mhm-field-input">';
        echo '</div>';

        echo '<div class="mhm-field-group">';
        echo '<label for="mhm_new_customer_phone" class="mhm-field-label">' . __('Phone', 'mhm-rentiva') . ' <span class="required">*</span></label>';
        echo '<input type="tel" id="mhm_new_customer_phone" name="mhm_new_customer_phone" class="mhm-field-input">';
        echo '</div>';

        echo '</div>';

        // Date and Time Fields
        echo '<div class="mhm-datetime-fields">';

        // Pickup Date
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_manual_pickup_date" class="mhm-field-label">' . __('Pickup Date', 'mhm-rentiva') . ' <span class="required">*</span></label>';
        echo '<input type="date" id="mhm_manual_pickup_date" name="mhm_manual_pickup_date" class="mhm-field-input" required>';
        echo '</div>';

        // Pickup Time
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_manual_pickup_time" class="mhm-field-label">' . __('Pickup Time', 'mhm-rentiva') . ' <span class="required">*</span></label>';
        $default_pickup_time = apply_filters('mhm_rentiva_default_pickup_time', '10:00');
        echo '<input type="time" id="mhm_manual_pickup_time" name="mhm_manual_pickup_time" class="mhm-field-input" value="' . esc_attr($default_pickup_time) . '" required>';
        echo '</div>';

        // Dropoff Date
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_manual_dropoff_date" class="mhm-field-label">' . __('Return Date', 'mhm-rentiva') . ' <span class="required">*</span></label>';
        echo '<input type="date" id="mhm_manual_dropoff_date" name="mhm_manual_dropoff_date" class="mhm-field-input" required>';
        echo '</div>';

        // Dropoff Time
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_manual_dropoff_time" class="mhm-field-label">' . __('Return Time', 'mhm-rentiva') . ' <span class="required">*</span></label>';
        $default_dropoff_time = apply_filters('mhm_rentiva_default_dropoff_time', '10:00');
        echo '<input type="time" id="mhm_manual_dropoff_time" name="mhm_manual_dropoff_time" class="mhm-field-input" value="' . esc_attr($default_dropoff_time) . '" required>';
        echo '</div>';

        echo '</div>';

        // Guest Count
        echo '<div class="mhm-field-group">';
        echo '<label for="mhm_manual_guests" class="mhm-field-label">' . __('Number of Guests', 'mhm-rentiva') . '</label>';
        echo '<input type="number" id="mhm_manual_guests" name="mhm_manual_guests" class="mhm-field-input" value="1" min="1" max="10">';
        echo '</div>';

        // Additional Services Selection
        echo '<div class="mhm-field-group">';
        echo '<label class="mhm-field-label">' . __('Additional Services', 'mhm-rentiva') . '</label>';

        // Get existing additional services (same as frontend form)
        $addons = get_posts([
            'post_type' => 'vehicle_addon',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);

        $available_addons = [];
        foreach ($addons as $addon) {
            $available_addons[] = [
                'id' => $addon->ID,
                'title' => $addon->post_title,
                'price' => get_post_meta($addon->ID, 'addon_price', true) ?: '0',
                'description' => $addon->post_excerpt,
                'required' => (bool) get_post_meta($addon->ID, 'addon_required', true)
            ];
        }

        if (!empty($available_addons)) {
            echo '<div class="mhm-addon-selection">';
            echo '<p class="description">' . __('Select the additional services needed for this booking.', 'mhm-rentiva') . '</p>';

            foreach ($available_addons as $addon) {
                $checked = $addon['required'] ? 'checked disabled' : '';
                $required_text = $addon['required'] ? ' <span class="required">*</span>' : '';

                echo '<label class="mhm-addon-item">';
                echo '<input type="checkbox" name="selected_addons[]" value="' . esc_attr($addon['id']) . '" class="mhm-addon-checkbox" data-price="' . esc_attr($addon['price']) . '" ' . $checked . '>';
                echo '<span class="mhm-addon-info">';
                echo '<span class="mhm-addon-title">' . esc_html($addon['title']) . $required_text . '</span>';
                echo '<span class="mhm-addon-price">+ ' . esc_html(self::format_addon_price((float)$addon['price'])) . '</span>';
                echo '</span>';
                if (!empty($addon['description'])) {
                    echo '<span class="mhm-addon-description">' . esc_html($addon['description']) . '</span>';
                }
                echo '</label>';
            }

            echo '<div class="mhm-addon-total" style="display: none;">';
            echo '<strong>' . __('Additional Services Total:', 'mhm-rentiva') . ' <span class="mhm-addon-total-amount">' . esc_html(self::format_addon_price(0)) . '</span></strong>';
            echo '</div>';

            echo '</div>';
        } else {
            echo '<p class="description">' . __('No additional services available.', 'mhm-rentiva') . '</p>';
        }
        echo '</div>';

        // Payment Type
        echo '<div class="mhm-field-group">';
        echo '<label for="mhm_manual_payment_type" class="mhm-field-label">' . __('Payment Type', 'mhm-rentiva') . '</label>';
        echo '<select id="mhm_manual_payment_type" name="mhm_manual_payment_type" class="mhm-field-select">';
        echo '<option value="full">' . __('Full Payment', 'mhm-rentiva') . '</option>';
        echo '<option value="deposit" selected>' . __('Deposit', 'mhm-rentiva') . '</option>';
        echo '</select>';
        echo '</div>';

        // Payment Method
        echo '<div class="mhm-field-group">';
        echo '<label for="mhm_manual_payment_method" class="mhm-field-label">' . __('Payment Method', 'mhm-rentiva') . '</label>';
        echo '<select id="mhm_manual_payment_method" name="mhm_manual_payment_method" class="mhm-field-select">';
        echo '<option value="offline" selected>' . __('Offline', 'mhm-rentiva') . '</option>';
        echo '<option value="online">' . __('Online', 'mhm-rentiva') . '</option>';
        echo '</select>';
        echo '</div>';

        // Status
        echo '<div class="mhm-field-group">';
        echo '<label for="mhm_manual_booking_status" class="mhm-field-label">' . __('Status', 'mhm-rentiva') . '</label>';
        echo '<select id="mhm_manual_booking_status" name="mhm_manual_status" class="mhm-field-select">';
        echo '<option value="pending">' . __('Pending', 'mhm-rentiva') . '</option>';
        echo '<option value="confirmed" selected>' . __('Confirmed', 'mhm-rentiva') . '</option>';
        echo '<option value="in_progress">' . __('In Progress', 'mhm-rentiva') . '</option>';
        echo '<option value="completed">' . __('Completed', 'mhm-rentiva') . '</option>';
        echo '</select>';
        echo '</div>';

        // Notes
        echo '<div class="mhm-field-group">';
        echo '<label for="mhm_manual_notes" class="mhm-field-label">' . __('Notes', 'mhm-rentiva') . '</label>';
        echo '<textarea id="mhm_manual_notes" name="mhm_manual_notes" class="mhm-field-textarea" rows="3" placeholder="' . __('Special notes about the booking...', 'mhm-rentiva') . '"></textarea>';
        echo '</div>';

        echo '</div>';

        // Price Calculation
        echo '<div class="mhm-price-calculation" style="display: none;">';
        echo '<h4>' . __('Fiyat Hesaplama', 'mhm-rentiva') . '</h4>';
        echo '<div class="mhm-price-details"></div>';
        echo '</div>';

        // Buttons
        echo '<div class="mhm-booking-actions">';
        echo '<button type="button" id="mhm-calculate-price" class="button button-secondary">' . __('Calculate Price', 'mhm-rentiva') . '</button>';
        echo '<button type="button" id="mhm-create-booking" class="button button-primary" style="display: none;">' . __('Create Booking', 'mhm-rentiva') . '</button>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * AJAX: Price calculation
     */
    public static function ajax_calculate_price(): void
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_manual_booking_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
        }

        $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
        $pickup_date = self::sanitize_text_field_safe($_POST['pickup_date'] ?? '');
        $pickup_time = self::sanitize_text_field_safe($_POST['pickup_time'] ?? '');
        $dropoff_date = self::sanitize_text_field_safe($_POST['dropoff_date'] ?? '');
        $dropoff_time = self::sanitize_text_field_safe($_POST['dropoff_time'] ?? '');
        $payment_type = self::sanitize_text_field_safe($_POST['payment_type'] ?? 'deposit');

        if (!$vehicle_id || !$pickup_date || !$dropoff_date) {
            wp_send_json_error(['message' => __('Required fields are missing.', 'mhm-rentiva')]);
        }

        // Date/time parse
        $datetime_result = Util::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

        if (is_wp_error($datetime_result)) {
            wp_send_json_error(['message' => $datetime_result->get_error_message()]);
        }

        $start_ts = $datetime_result['start_ts'];
        $end_ts = $datetime_result['end_ts'];
        $days = Util::rental_days($start_ts, $end_ts);

        // Availability check
        $availability = Util::check_availability($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

        if (!$availability['ok']) {
            wp_send_json_error(['message' => $availability['message']]);
        }

        // Additional services calculation (daily)
        $selected_addons = array_map('intval', $_POST['selected_addons'] ?? []);
        $addon_total = 0;

        if (!empty($selected_addons)) {
            foreach ($selected_addons as $addon_id) {
                $addon = \MHMRentiva\Admin\Addons\AddonManager::get_addon_by_id($addon_id);
                if ($addon) {
                    $addon_total += $addon['price'] * $days; // Daily calculation
                }
            }
        }

        // Calculate deposit (including addons)
        $deposit_result = DepositCalculator::calculate_booking_deposit($vehicle_id, $days, $payment_type, $selected_addons, $start_ts);

        if (!$deposit_result['success']) {
            wp_send_json_error(['message' => __('Price could not be calculated.', 'mhm-rentiva')]);
        }

        wp_send_json_success([
            'days' => $days,
            'price_per_day' => $availability['price_per_day'],
            'vehicle_total' => $deposit_result['vehicle_total'], // Only vehicle total
            'addon_total' => $addon_total,
            'total_amount' => $deposit_result['total_amount'], // Vehicle + additional services
            'final_total' => $deposit_result['total_amount'],
            'deposit_amount' => $deposit_result['deposit_amount'],
            'remaining_amount' => $deposit_result['remaining_amount'],
            'deposit_type' => $deposit_result['deposit_type'],
            'payment_display' => $deposit_result['payment_display'],
        ]);
    }

    /**
     * AJAX: Create booking
     */
    public static function ajax_create_booking(): void
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_manual_booking_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
        }

        // Input validation
        $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
        $customer_id = self::sanitize_text_field_safe($_POST['customer_id'] ?? '');
        $pickup_date = self::sanitize_text_field_safe($_POST['pickup_date'] ?? '');
        $pickup_time = self::sanitize_text_field_safe($_POST['pickup_time'] ?? '');
        $dropoff_date = self::sanitize_text_field_safe($_POST['dropoff_date'] ?? '');
        $dropoff_time = self::sanitize_text_field_safe($_POST['dropoff_time'] ?? '');
        $guests = max(1, intval($_POST['guests'] ?? 1));
        $payment_type = self::sanitize_text_field_safe($_POST['payment_type'] ?? 'deposit');
        $payment_method = self::sanitize_text_field_safe($_POST['payment_method'] ?? 'woocommerce');
        $status = self::sanitize_text_field_safe($_POST['status'] ?? 'confirmed');
        $notes = sanitize_textarea_field((string) ($_POST['notes'] ?? ''));

        if (!$vehicle_id || !$customer_id || !$pickup_date || !$dropoff_date) {
            wp_send_json_error(['message' => __('Gerekli alanlar eksik.', 'mhm-rentiva')]);
        }

        // Customer processing
        $customer = null;
        $customer_first_name = '';
        $customer_last_name = '';
        $customer_name = '';
        $customer_email = '';
        $customer_phone = '';

        if ($customer_id === 'new_customer') {
            // Create new customer
            $customer_first_name = self::sanitize_text_field_safe($_POST['new_customer_first_name'] ?? '');
            $customer_last_name = self::sanitize_text_field_safe($_POST['new_customer_last_name'] ?? '');
            $customer_name = trim($customer_first_name . ' ' . $customer_last_name);
            $customer_email = sanitize_email((string) ($_POST['new_customer_email'] ?? ''));
            $customer_phone = self::sanitize_text_field_safe($_POST['new_customer_phone'] ?? '');

            if (!$customer_first_name || !$customer_last_name || !$customer_email || !$customer_phone) {
                wp_send_json_error(['message' => __('New customer information is missing.', 'mhm-rentiva')]);
            }

            // Email check
            if (email_exists($customer_email)) {
                wp_send_json_error(['message' => __('This email address is already registered.', 'mhm-rentiva')]);
            }

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

            // Create new user
            $user_id = wp_create_user($username, wp_generate_password(12, true, true), $customer_email);
            if (is_wp_error($user_id)) {
                wp_send_json_error(['message' => $user_id->get_error_message()]);
            }

            // Determine safe default role (same as normal booking form)
            $default_role = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_default_role', 'customer');
            if (!get_role($default_role)) {
                $default_role = 'customer';
            }

            // Update user information (same as normal booking form)
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

            // Save meta information (same as normal booking form)
            update_user_meta($user_id, 'mhm_rentiva_phone', $customer_phone);
            update_user_meta($user_id, 'mhm_rentiva_customer', true);

            $customer = get_userdata($user_id);
        } else {
            // Existing customer
            $customer = get_userdata((int) $customer_id);
            if (!$customer) {
                wp_send_json_error(['message' => __('Customer not found.', 'mhm-rentiva')]);
            }

            // Get existing customer information
            $customer_first_name = $customer->first_name;
            $customer_last_name = $customer->last_name;
            $customer_name = $customer->display_name;
            $customer_email = $customer->user_email;
            $customer_phone = get_user_meta($customer->ID, 'mhm_rentiva_phone', true) ?: get_user_meta($customer->ID, 'phone', true);
        }

        // Date/time parse
        $datetime_result = Util::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

        if (is_wp_error($datetime_result)) {
            wp_send_json_error(['message' => $datetime_result->get_error_message()]);
        }

        $start_ts = $datetime_result['start_ts'];
        $end_ts = $datetime_result['end_ts'];
        $days = Util::rental_days($start_ts, $end_ts);

        // Availability check
        $availability = Util::check_availability($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

        if (!$availability['ok']) {
            wp_send_json_error(['message' => $availability['message']]);
        }

        // Add-ons calculation (same as BookingForm.php)
        $selected_addons = array_map('intval', $_POST['selected_addons'] ?? []);
        $addon_total = 0;
        $addon_details = [];


        if (!empty($selected_addons)) {
            foreach ($selected_addons as $addon_id) {
                $addon_price = floatval(get_post_meta($addon_id, 'addon_price', true) ?: 0);
                $addon_total += $addon_price * $days; // Daily calculation (same as BookingForm.php)

                $addon_details[] = [
                    'id' => $addon_id,
                    'title' => get_the_title($addon_id),
                    'price' => $addon_price
                ];
            }
        }

        // Deposit calculation (same as BookingForm.php)
        $deposit_result = DepositCalculator::calculate_booking_deposit($vehicle_id, $days, $payment_type, $selected_addons, $start_ts);

        if (!$deposit_result['success']) {
            wp_send_json_error(['message' => __('Price could not be calculated.', 'mhm-rentiva')]);
        }


        // Create booking
        $booking_data = [
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            /* translators: %s placeholder. */
            'post_title' => sprintf(__('Manual Booking - %s', 'mhm-rentiva'), get_the_title($vehicle_id)),
            'post_content' => $notes,
            'meta_input' => [
                '_mhm_vehicle_id' => $vehicle_id,
                '_mhm_customer_user_id' => $customer->ID,
                '_mhm_customer_name' => $customer_name,
                '_mhm_customer_first_name' => $customer_first_name,
                '_mhm_customer_last_name' => $customer_last_name,
                '_mhm_customer_email' => $customer_email,
                '_mhm_customer_phone' => $customer_phone,
                '_mhm_start_date' => $pickup_date,
                '_mhm_end_date' => $dropoff_date,
                '_mhm_pickup_date' => $pickup_date,
                '_mhm_dropoff_date' => $dropoff_date,
                '_mhm_start_time' => $pickup_time,
                '_mhm_end_time' => $dropoff_time,
                '_mhm_start_ts' => $start_ts,
                '_mhm_end_ts' => $end_ts,
                '_mhm_guests' => $guests,
                '_mhm_status' => $status,
                '_mhm_booking_type' => 'manual',
                '_mhm_created_via' => 'admin_manual',
                '_mhm_created_by' => get_current_user_id(),
                '_mhm_payment_type' => $payment_type,
                '_mhm_payment_method' => $payment_method,
                '_mhm_payment_gateway' => '',
                '_mhm_deposit_amount' => $deposit_result['deposit_amount'],
                '_mhm_remaining_amount' => $deposit_result['remaining_amount'],
                '_mhm_deposit_type' => $deposit_result['deposit_type'],
                '_mhm_payment_display' => $deposit_result['payment_display'],
                '_mhm_total_price' => $deposit_result['total_amount'],
                '_mhm_rental_days' => $days,
                '_mhm_selected_addons' => $selected_addons,
                '_mhm_cancellation_policy' => '24_hours',
                '_mhm_cancellation_deadline' => date('Y-m-d H:i:s', strtotime('+' . apply_filters('mhm_rentiva_cancellation_deadline_hours', '24') . ' hours')),
                '_mhm_payment_status' => 'pending', // ⭐ WooCommerce handles payment status
                '_mhm_payment_deadline' => date('Y-m-d H:i:s', strtotime('+' . apply_filters('mhm_rentiva_payment_deadline_minutes', '30') . ' minutes')),
            ]
        ];

        $booking_id = wp_insert_post($booking_data);

        if (is_wp_error($booking_id)) {
            wp_send_json_error(['message' => __('Booking could not be created.', 'mhm-rentiva')]);
        }

        // Save manual booking type
        update_post_meta($booking_id, '_mhm_booking_type', 'manual');

        // Save deposit amount (if deposit payment)
        if ($payment_type === 'deposit') {
            // Use already calculated deposit amount
            if (isset($deposit_result['deposit_amount'])) {
                update_post_meta($booking_id, '_mhm_deposit_amount', $deposit_result['deposit_amount']);
            }
        }

        // Booking History - "Booking created" note
        \MHMRentiva\Admin\Booking\Meta\BookingMeta::add_history_note(
            $booking_id,
            __('Booking created', 'mhm-rentiva'),
            'system'
        );
        update_post_meta($booking_id, '_mhm_booking_created', '1');

        // Trigger email notifications
        do_action('mhm_rentiva_booking_created', $booking_id, $booking_data);

        wp_send_json_success([
            'booking_id' => $booking_id,
            'message' => __('Booking created successfully.', 'mhm-rentiva'),
            'redirect_url' => get_edit_post_link($booking_id, 'raw')
        ]);
    }

    /**
     * Format addon price with currency symbol and position
     */
    private static function format_addon_price(float $price): string
    {
        $symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();
        $position = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency_position', 'right_space');
        $formatted_amount = number_format($price, 2, ',', '.');

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
