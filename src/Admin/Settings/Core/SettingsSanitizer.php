<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsSanitizer
{
    /**
     * Static variable to store timeout values for hook access
     */
    private static $timeout_values_static = [];
    
    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        // ✅ STRICT null check - must be first
        if ($value === null) {
            return '';
        }
        // ✅ Empty string check
        if ($value === '') {
            return '';
        }
        // ✅ Convert to string if not already - this prevents strlen() errors
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        // ✅ Now safe to call sanitize_text_field
        return sanitize_text_field((string) $value);
    }

    /**
     * ✅ Recursively clean null values from array - more robust version
     */
    private static function clean_null_values_recursive(&$array): void
    {
        if (!is_array($array)) {
            return;
        }
        
        foreach ($array as $key => &$value) {
            if ($value === null) {
                $value = '';
            } elseif (is_array($value)) {
                self::clean_null_values_recursive($value);
            }
        }
    }
    
    /**
     * ✅ PUBLIC: Clean $_POST and $_REQUEST arrays - called from hook
     */
    public static function clean_post_recursive(&$array): void
    {
        if (!is_array($array)) {
            return;
        }
        
        foreach ($array as $key => &$value) {
            if ($value === null) {
                $value = '';
            } elseif (is_array($value)) {
                self::clean_post_recursive($value);
            }
        }
    }

    public static function sanitize($input): array
    {
        $defaults = SettingsCore::defaults();
        $current_values = get_option('mhm_rentiva_settings', []);
        if (!is_array($current_values)) {
            $current_values = [];
        }
        
        // ✅ CRITICAL: START with current values to preserve settings from other tabs
        // This ensures we don't lose data when submitting a single tab
        $out = $current_values;
        
        // ✅ CRITICAL: Clean null values from database BEFORE processing
        // This prevents null values from being passed to WordPress core functions
        self::clean_null_values_recursive($out);
        
        // ✅ REMOVED: update_option call here causes infinite loop
        // WordPress will save the returned value automatically
        
        // ✅ CRITICAL: Null check FIRST - before any processing to prevent errors
        if ($input === null || !is_array($input)) {
            return $out;
        }
        
        // ✅ CRITICAL: Clean null values IMMEDIATELY - before WordPress core touches them
        // Convert all null values in input to empty strings to prevent strlen() errors
        self::clean_null_values_recursive($input);
        
        // ✅ CRITICAL: Also clean $_POST array if it exists (prevent strlen() errors from WordPress core)
        // This MUST happen before WordPress Settings API reads $_POST
        if (isset($_POST['mhm_rentiva_settings']) && is_array($_POST['mhm_rentiva_settings'])) {
            self::clean_null_values_recursive($_POST['mhm_rentiva_settings']);
        }
        
        // ✅ CRITICAL: Clean entire $_POST array to prevent any null values from reaching WordPress core
        if (isset($_POST) && is_array($_POST)) {
            self::clean_null_values_recursive($_POST);
        }
        
        // ✅ CRITICAL: Clean entire $_REQUEST array
        if (isset($_REQUEST) && is_array($_REQUEST)) {
            self::clean_null_values_recursive($_REQUEST);
        }
        
        // If input contains mhm_rentiva_settings array, extract it
        if (isset($input['mhm_rentiva_settings']) && is_array($input['mhm_rentiva_settings'])) {
            // Merge mhm_rentiva_settings array into main input array
            // This ensures all form fields are accessible at the top level
            foreach ($input['mhm_rentiva_settings'] as $key => $value) {
                // Convert null to empty string before merging to prevent strlen() errors
                $input[$key] = $value === null ? '' : $value;
            }
            unset($input['mhm_rentiva_settings']);
        }
        
        // ✅ CRITICAL: Final null check after extraction
        self::clean_null_values_recursive($input);
        
        // ✅ REMOVED: Global checkbox normalization
        // Checkboxes are now handled individually in their respective tab-specific sanitize functions
        // This prevents conflicts when saving one tab (e.g., System) from affecting other tabs (e.g., Email)

        // IMPORTANT: Handle offline checkbox only when Payment tab is submitted
        $is_payment_tab = isset($input['mhm_rentiva_stripe_enabled']) || isset($input['mhm_rentiva_paypal_enabled']) || isset($input['mhm_rentiva_paytr_enabled']) || isset($input['mhm_rentiva_offline_enabled']) || isset($input['mhm_rentiva_booking_default_payment_method']);
        if ($is_payment_tab) {
            if (!isset($input['mhm_rentiva_offline_enabled']) || $input['mhm_rentiva_offline_enabled'] === null) {
                $input['mhm_rentiva_offline_enabled'] = '0';
            }
        }

        // ✅ Ensure timeout values are captured early (before sanitization methods)
        // Capture timeout values from input to ensure they are saved even if value is 0
        // Check both original input array and extracted array
        $timeout_values = [];
        $timeout_keys = [
            'mhm_rentiva_booking_payment_gateway_timeout_minutes',
            'mhm_rentiva_booking_payment_deadline_minutes',
            'mhm_rentiva_paypal_timeout',
            'mhm_rentiva_paytr_timeout_limit'
        ];
        
        foreach ($timeout_keys as $key) {
            // Check in extracted input array first
            if (array_key_exists($key, $input)) {
                $value = $input[$key];
                // Convert to string first to handle empty strings properly
                $value_str = (string) $value;
                if ($key === 'mhm_rentiva_booking_payment_gateway_timeout_minutes') {
                    $timeout_values[$key] = $value_str === '' ? 0 : max(0, min(60, intval($value_str)));
                } elseif ($key === 'mhm_rentiva_booking_payment_deadline_minutes') {
                    $timeout_values[$key] = $value_str === '' ? 0 : max(0, min(1440, intval($value_str)));
                } elseif ($key === 'mhm_rentiva_paypal_timeout' || $key === 'mhm_rentiva_paytr_timeout_limit') {
                    $timeout_values[$key] = $value_str === '' ? 0 : max(0, min(120, absint($value_str)));
                }
            }
            // Also check in original mhm_rentiva_settings array if it exists
            elseif (isset($_POST['mhm_rentiva_settings'][$key])) {
                $value = $_POST['mhm_rentiva_settings'][$key];
                // Convert to string first to handle empty strings properly
                $value_str = (string) $value;
                if ($key === 'mhm_rentiva_booking_payment_gateway_timeout_minutes') {
                    $timeout_values[$key] = $value_str === '' ? 0 : max(0, min(60, intval($value_str)));
                } elseif ($key === 'mhm_rentiva_booking_payment_deadline_minutes') {
                    $timeout_values[$key] = $value_str === '' ? 0 : max(0, min(1440, intval($value_str)));
                } elseif ($key === 'mhm_rentiva_paypal_timeout' || $key === 'mhm_rentiva_paytr_timeout_limit') {
                    $timeout_values[$key] = $value_str === '' ? 0 : max(0, min(120, absint($value_str)));
                }
            }
        }
        
        // ✅ Store timeout values in static variable for hook access
        self::$timeout_values_static = $timeout_values;

        $option_input_keys = array_filter(
            array_keys($input),
            static function ($key): bool {
                return is_string($key) && strpos($key, 'mhm_rentiva_') === 0;
            }
        );

        if (isset($input['comparison_fields']) && is_array($input['comparison_fields']) && empty($option_input_keys)) {
            $comparison_settings = self::sanitize_comparison_settings($input, $current_values);
            if (!empty($comparison_settings)) {
                return array_merge($current_values, $comparison_settings);
            }

            return $current_values;
        }

        // currency
        if (isset($input['mhm_rentiva_currency'])) {
            $cur = strtoupper(substr(self::sanitize_text_field_safe($input['mhm_rentiva_currency']), 0, 4));
            $out['mhm_rentiva_currency'] = $cur !== '' ? $cur : $defaults['mhm_rentiva_currency'];
        } else {
            $out['mhm_rentiva_currency'] = $defaults['mhm_rentiva_currency'];
        }

        // currency_position
        $allowedPos = array_keys(self::currency_positions());
        if (isset($input['mhm_rentiva_currency_position']) && in_array($input['mhm_rentiva_currency_position'], $allowedPos, true)) {
            $out['mhm_rentiva_currency_position'] = $input['mhm_rentiva_currency_position'];
        } else {
            $out['mhm_rentiva_currency_position'] = $defaults['mhm_rentiva_currency_position'];
        }

        // date_format (safe fallback to WP date_format if default key missing)
        $allowedDate = array_keys(self::date_formats());
        if (isset($input['mhm_rentiva_date_format']) && in_array($input['mhm_rentiva_date_format'], $allowedDate, true)) {
            $out['mhm_rentiva_date_format'] = $input['mhm_rentiva_date_format'];
        } else {
            $out['mhm_rentiva_date_format'] = $defaults['mhm_rentiva_date_format'] ?? (string) get_option('date_format', 'Y-m-d');
        }

        // time_format (safe fallback to WP time_format if default key missing)
        $allowedTime = array_keys(self::time_formats());
        if (isset($input['mhm_rentiva_time_format']) && in_array($input['mhm_rentiva_time_format'], $allowedTime, true)) {
            $out['mhm_rentiva_time_format'] = $input['mhm_rentiva_time_format'];
        } else {
            $out['mhm_rentiva_time_format'] = $defaults['mhm_rentiva_time_format'] ?? (string) get_option('time_format', 'H:i');
        }

        // default_rental_days
        $days = isset($input['mhm_rentiva_default_rental_days']) ? absint($input['mhm_rentiva_default_rental_days']) : $defaults['mhm_rentiva_default_rental_days'];
        $out['mhm_rentiva_default_rental_days'] = max(1, $days);

        // Site Information Settings
        $out = array_merge($out, self::sanitize_site_info_settings($input, $defaults));

        // Date & Time Settings
        $out = array_merge($out, self::sanitize_datetime_settings($input, $defaults));

        // ✅ CRITICAL FIX: Only sanitize sections that were actually submitted
        // This prevents resetting settings from other tabs when saving one tab
        
        // Detect which tab was submitted by checking for tab-specific fields
        $is_vehicle_tab = isset($input['mhm_rentiva_vehicle_base_price']) || isset($input['mhm_rentiva_vehicle_show_images']) || isset($input['mhm_rentiva_vehicle_min_rental_days']);
        $is_booking_tab = isset($input['mhm_rentiva_booking_cancellation_deadline_hours']) || isset($input['mhm_rentiva_booking_payment_deadline_minutes']) || isset($input['mhm_rentiva_booking_auto_cancel_enabled']);
        $is_customer_tab = isset($input['mhm_rentiva_customer_registration_enabled']) || isset($input['mhm_rentiva_customer_email_verification']);
        $is_email_tab = isset($input['mhm_rentiva_email_from_name']) || isset($input['mhm_rentiva_email_from_address']) || isset($input['mhm_rentiva_email_test_mode']) || isset($input['mhm_rentiva_email_send_enabled']) || isset($input['mhm_rentiva_email_auto_send']) || isset($input['mhm_rentiva_email_log_enabled']);
        $is_payment_tab = isset($input['mhm_rentiva_stripe_enabled']) || isset($input['mhm_rentiva_paypal_enabled']) || isset($input['mhm_rentiva_paytr_enabled']) || isset($input['mhm_rentiva_offline_enabled']) || isset($input['mhm_rentiva_booking_default_payment_method']);
        $is_frontend_tab = isset($input['mhm_rentiva_booking_url']) || isset($input['mhm_rentiva_login_url']) || isset($input['mhm_rentiva_register_url']) || isset($input['mhm_rentiva_my_account_url']) || isset($input['mhm_rentiva_text_book_now']) || isset($input['mhm_rentiva_text_view_details']) || isset($input['mhm_rentiva_text_added_to_favorites']) || isset($input['mhm_rentiva_text_make_booking']) || isset($input['mhm_rentiva_text_login_here']);
        $is_maintenance_tab = isset($input['mhm_rentiva_auto_cancel_enabled']) || isset($input['mhm_rentiva_log_cleanup_enabled']);
        $is_reconcile_tab = isset($input['mhm_rentiva_reconcile_enabled']) || isset($input['mhm_rentiva_reconcile_frequency']) || isset($input['mhm_rentiva_reconcile_timeout']);
        $is_logs_tab = isset($input['mhm_rentiva_log_level']) || isset($input['mhm_rentiva_log_retention_days']) || isset($input['mhm_rentiva_debug_mode']);
        $is_system_tab = isset($input['mhm_rentiva_rate_limit_enabled']) || isset($input['mhm_rentiva_cache_enabled']) || isset($input['mhm_rentiva_ip_whitelist_enabled']) || isset($input['mhm_rentiva_brute_force_protection']);
        
        // Vehicle Management Settings - only if vehicle tab submitted
        if ($is_vehicle_tab) {
            $out = array_merge($out, self::sanitize_vehicle_management_settings($input, $defaults));
        }

        // Booking Settings - only if booking tab submitted
        if ($is_booking_tab) {
            $out = array_merge($out, self::sanitize_booking_settings($input, $defaults));
        }

        // Customer Management Settings - only if customer tab submitted
        if ($is_customer_tab) {
            $out = array_merge($out, self::sanitize_customer_management_settings($input, $defaults));
        }

        // Email and Brand Settings - only if email tab submitted
        if ($is_email_tab) {
            $out = array_merge($out, self::sanitize_email_brand_settings($input, $defaults));
            $out = array_merge($out, self::sanitize_email_sending_settings($input, $defaults));
        }

        // Payment settings - only if payment tab submitted
        if ($is_payment_tab) {
            // Stripe settings
            $out = array_merge($out, self::sanitize_stripe_settings($input, $defaults));

            // PayTR settings
            $out = array_merge($out, self::sanitize_paytr_settings($input, $defaults));

            // Offline Payment settings
            $out = array_merge($out, self::sanitize_offline_settings($input, $defaults));

            // PayPal settings
            $out = array_merge($out, self::sanitize_paypal_settings($input, $defaults));
        }

        // Maintenance settings - only if maintenance tab submitted
        if ($is_maintenance_tab) {
            $out = array_merge($out, self::sanitize_maintenance_settings($input, $defaults));
        }

        // Reconciliation settings - only if reconcile tab submitted
        if ($is_reconcile_tab) {
            $out = array_merge($out, self::sanitize_reconcile_settings($input, $defaults));
        }

        // Logs settings - only if logs tab submitted
        if ($is_logs_tab) {
            $out = array_merge($out, self::sanitize_logs_settings($input, $defaults));
        }

        // System & Performance settings - only if system tab submitted
        if ($is_system_tab) {
            $out = array_merge($out, self::sanitize_system_settings($input, $defaults));
        }

        // Frontend URL & Text settings - only if frontend tab submitted
        if ($is_frontend_tab) {
            $out = array_merge($out, self::sanitize_frontend_settings($input, $defaults));
        }

        // Vehicle Pricing settings (including deposit)
        $vehicle_pricing_result = self::sanitize_vehicle_pricing_settings($input, $defaults);
        if (!empty($vehicle_pricing_result)) {
            $out = array_merge($out, $vehicle_pricing_result);
        }

        $comparison_settings = self::sanitize_comparison_settings($input, $current_values);
        if (!empty($comparison_settings)) {
            $out = array_merge($out, $comparison_settings);
        }

        // Comments settings
        $comments_settings = self::sanitize_comments_settings($input, $current_values);
        if (!empty($comments_settings)) {
            $out = array_merge($out, $comments_settings);
        }

        // ✅ REMOVED: Duplicate merge of current_values (already done at line 83)
        // if (!empty($current_values)) { $out = array_merge($current_values, $out); }

        // ✅ Ensure timeout values are properly saved (even if they are 0)
        // Apply captured timeout values to output (overrides any previous values)
        if (!empty($timeout_values)) {
            $out = array_merge($out, $timeout_values);
        }
        
        // Process timeout values from input if not already captured
        $timeout_keys = [
            'mhm_rentiva_booking_payment_gateway_timeout_minutes',
            'mhm_rentiva_booking_payment_deadline_minutes',
            'mhm_rentiva_paypal_timeout',
            'mhm_rentiva_paytr_timeout_limit'
        ];
        
        foreach ($timeout_keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = $input[$key];
                $value_str = (string) $value;
                if ($key === 'mhm_rentiva_booking_payment_gateway_timeout_minutes') {
                    $out[$key] = $value_str === '' ? 0 : max(0, min(60, intval($value_str)));
                } elseif ($key === 'mhm_rentiva_booking_payment_deadline_minutes') {
                    $out[$key] = $value_str === '' ? 0 : max(0, min(1440, intval($value_str)));
                } elseif ($key === 'mhm_rentiva_paypal_timeout' || $key === 'mhm_rentiva_paytr_timeout_limit') {
                    $out[$key] = $value_str === '' ? 0 : max(0, min(120, absint($value_str)));
                }
            }
        }
        
        // Ensure timeout values are integers
        foreach ($timeout_keys as $key) {
            if (isset($out[$key])) {
                $out[$key] = (int) $out[$key];
            }
        }

        // ✅ SESSION ABUSE FIXED - WordPress transient usage
        // Set transient for success message (valid for 5 minutes)
        set_transient('mhm_settings_saved_' . get_current_user_id(), true, 300);

        // ✅ CACHE OPTIMIZATION - Clear cache when settings change
        if (class_exists('\MHMRentiva\Admin\Core\Utilities\CacheManager')) {
            \MHMRentiva\Admin\Core\Utilities\CacheManager::clear_settings_cache();
        }

        return $out;
    }
    
    /**
     * Get timeout values from static variable
     */
    public static function get_timeout_values(): array
    {
        return self::$timeout_values_static;
    }

    private static function sanitize_comments_settings($input, $current_values): array
    {
        $out = [];
        
        // Check comments settings
        if (isset($input['mhm_rentiva_comments_settings']) && is_array($input['mhm_rentiva_comments_settings'])) {
            $comments_input = $input['mhm_rentiva_comments_settings'];
            
            // Approval settings
            if (isset($comments_input['approval'])) {
                $out['comments_approval'] = [
                    'auto_approve' => isset($comments_input['approval']['auto_approve']) ? (bool) $comments_input['approval']['auto_approve'] : false,
                    'require_login' => isset($comments_input['approval']['require_login']) ? (bool) $comments_input['approval']['require_login'] : false, // Unchecked = false
                    'allow_guest_comments' => isset($comments_input['approval']['allow_guest_comments']) ? (bool) $comments_input['approval']['allow_guest_comments'] : false,
                    'moderation_required' => isset($comments_input['approval']['moderation_required']) ? (bool) $comments_input['approval']['moderation_required'] : false, // Unchecked = false
                    'admin_notification' => isset($comments_input['approval']['admin_notification']) ? (bool) $comments_input['approval']['admin_notification'] : false // Unchecked = false
                ];
            }
            
            // Limits settings
            if (isset($comments_input['limits'])) {
                $out['comments_limits'] = [
                    'comments_per_page' => isset($comments_input['limits']['comments_per_page']) ? max(1, min(100, (int) $comments_input['limits']['comments_per_page'])) : 10,
                    'comment_length_min' => isset($comments_input['limits']['comment_length_min']) ? max(1, min(1000, (int) $comments_input['limits']['comment_length_min'])) : 5,
                    'comment_length_max' => isset($comments_input['limits']['comment_length_max']) ? max(10, min(5000, (int) $comments_input['limits']['comment_length_max'])) : 1000,
                    'rating_required' => isset($comments_input['limits']['rating_required']) ? (bool) $comments_input['limits']['rating_required'] : false // Unchecked = false
                ];
            }
            
            // Spam protection settings
            if (isset($comments_input['spam_protection'])) {
                $spam_input = $comments_input['spam_protection'];
                $out['comments_spam_protection'] = [
                    'enabled' => isset($spam_input['enabled']) ? (bool) $spam_input['enabled'] : false, // Unchecked = false
                    'rate_limiting_time' => isset($spam_input['rate_limiting_time']) ? max(1, min(60, (int) $spam_input['rate_limiting_time'])) : 1,
                    'duplicate_detection_time' => isset($spam_input['duplicate_detection_time']) ? max(1, min(60, (int) $spam_input['duplicate_detection_time'])) : 10,
                    'spam_words' => isset($spam_input['spam_words']) ? 
                        (is_array($spam_input['spam_words']) ? 
                            array_map([self::class, 'sanitize_text_field_safe'], $spam_input['spam_words']) : 
                            array_map([self::class, 'sanitize_text_field_safe'], explode(',', (string) $spam_input['spam_words']))) : 
                        ['spam', 'viagra', 'casino', 'loan', 'free money', 'click here'],
                    'ip_blacklist_threshold' => isset($spam_input['ip_blacklist_threshold']) ? max(1, min(20, (int) $spam_input['ip_blacklist_threshold'])) : 5
                ];
            }
            
            // Display settings
            if (isset($comments_input['display'])) {
                $out['comments_display'] = [
                    'show_ratings' => isset($comments_input['display']['show_ratings']) ? (bool) $comments_input['display']['show_ratings'] : false, // Unchecked = false
                    'show_avatars' => isset($comments_input['display']['show_avatars']) ? (bool) $comments_input['display']['show_avatars'] : false, // Unchecked = false
                    'allow_editing' => isset($comments_input['display']['allow_editing']) ? (bool) $comments_input['display']['allow_editing'] : false, // Unchecked = false
                    'allow_deletion' => isset($comments_input['display']['allow_deletion']) ? (bool) $comments_input['display']['allow_deletion'] : false, // Unchecked = false
                    'edit_time_limit' => isset($comments_input['display']['edit_time_limit']) ? max(0, min(168, (int) $comments_input['display']['edit_time_limit'])) : 24
                ];
            }
            
            // Cache settings
            if (isset($comments_input['cache'])) {
                $out['comments_cache'] = [
                    'enabled' => isset($comments_input['cache']['enabled']) ? (bool) $comments_input['cache']['enabled'] : false, // Unchecked = false
                    'duration' => isset($comments_input['cache']['duration']) ? max(1, min(1440, (int) $comments_input['cache']['duration'])) : 15,
                    'clear_on_comment' => isset($comments_input['cache']['clear_on_comment']) ? (bool) $comments_input['cache']['clear_on_comment'] : false // Unchecked = false
                ];
            }
        }
        
        return $out;
    }

    private static function sanitize_email_brand_settings($input, $defaults): array
    {
        $out = [];

        // email_from_name
        if (isset($input['mhm_rentiva_email_from_name'])) {
            $name = self::sanitize_text_field_safe($input['mhm_rentiva_email_from_name']);
            $out['mhm_rentiva_email_from_name'] = $name !== '' ? $name : ($defaults['mhm_rentiva_email_from_name'] ?? get_bloginfo('name'));
        } else {
            $out['mhm_rentiva_email_from_name'] = $defaults['mhm_rentiva_email_from_name'] ?? get_bloginfo('name');
        }

        // email_from_address
        if (isset($input['mhm_rentiva_email_from_address'])) {
            $email_val = $input['mhm_rentiva_email_from_address'];
            $email = \MHMRentiva\Admin\Settings\Core\SettingsHelper::sanitize_email_safe($email_val);
            $out['mhm_rentiva_email_from_address'] = is_email($email) ? $email : ($defaults['mhm_rentiva_email_from_address'] ?? get_option('admin_email'));
        } else {
            $out['mhm_rentiva_email_from_address'] = $defaults['mhm_rentiva_email_from_address'] ?? get_option('admin_email');
        }

        // brand_name
        if (isset($input['mhm_rentiva_brand_name'])) {
            $brand = self::sanitize_text_field_safe($input['mhm_rentiva_brand_name']);
            $out['mhm_rentiva_brand_name'] = $brand !== '' ? $brand : $defaults['mhm_rentiva_brand_name'];
        } else {
            $out['mhm_rentiva_brand_name'] = $defaults['mhm_rentiva_brand_name'];
        }

        // brand_logo_url
        if (isset($input['mhm_rentiva_brand_logo_url'])) {
            $logo_val = $input['mhm_rentiva_brand_logo_url'];
            $logo = ($logo_val !== null && $logo_val !== '') ? esc_url_raw((string) $logo_val) : '';
            $out['mhm_rentiva_brand_logo_url'] = $logo;
        } else {
            $out['mhm_rentiva_brand_logo_url'] = $defaults['mhm_rentiva_brand_logo_url'];
        }

        // email_primary_color
        if (isset($input['mhm_rentiva_email_primary_color'])) {
            $color_val = $input['mhm_rentiva_email_primary_color'];
            $color = ($color_val !== null && $color_val !== '') ? sanitize_hex_color((string) $color_val) : '';
            $out['mhm_rentiva_email_primary_color'] = $color !== '' ? $color : $defaults['mhm_rentiva_email_primary_color'];
        } else {
            $out['mhm_rentiva_email_primary_color'] = $defaults['mhm_rentiva_email_primary_color'];
        }

        // email_footer_text
        if (isset($input['mhm_rentiva_email_footer_text'])) {
            $footer_val = $input['mhm_rentiva_email_footer_text'];
            // Ensure value is not null and is a valid string before passing to sanitize function
            if ($footer_val !== null && $footer_val !== '' && (is_string($footer_val) || is_numeric($footer_val))) {
                $footer = \MHMRentiva\Admin\Settings\Core\SettingsHelper::sanitize_textarea_field_safe($footer_val);
                $out['mhm_rentiva_email_footer_text'] = $footer !== '' ? $footer : $defaults['mhm_rentiva_email_footer_text'];
            } else {
                $out['mhm_rentiva_email_footer_text'] = $defaults['mhm_rentiva_email_footer_text'];
            }
        } else {
            $out['mhm_rentiva_email_footer_text'] = $defaults['mhm_rentiva_email_footer_text'];
        }

        return $out;
    }

    private static function sanitize_email_sending_settings($input, $defaults): array
    {
        $out = [];

        // Reply-To
        if (isset($input['mhm_rentiva_email_reply_to'])) {
            $email_val = $input['mhm_rentiva_email_reply_to'];
            $email = \MHMRentiva\Admin\Settings\Core\SettingsHelper::sanitize_email_safe($email_val);
            $out['mhm_rentiva_email_reply_to'] = is_email($email) ? $email : ($defaults['mhm_rentiva_email_reply_to'] ?? get_option('admin_email'));
        } else {
            $out['mhm_rentiva_email_reply_to'] = $defaults['mhm_rentiva_email_reply_to'] ?? get_option('admin_email');
        }

        // Send enabled
        $out['mhm_rentiva_email_send_enabled'] = (isset($input['mhm_rentiva_email_send_enabled']) && $input['mhm_rentiva_email_send_enabled'] === '1') ? '1' : '0';

        // Test mode + address
        $out['mhm_rentiva_email_test_mode'] = (isset($input['mhm_rentiva_email_test_mode']) && $input['mhm_rentiva_email_test_mode'] === '1') ? '1' : '0';
        if (isset($input['mhm_rentiva_email_test_address'])) {
            $test_val = $input['mhm_rentiva_email_test_address'];
            $test_email = \MHMRentiva\Admin\Settings\Core\SettingsHelper::sanitize_email_safe($test_val);
            $out['mhm_rentiva_email_test_address'] = is_email($test_email) ? $test_email : ($defaults['mhm_rentiva_email_test_address'] ?? get_option('admin_email'));
        } else {
            $out['mhm_rentiva_email_test_address'] = $defaults['mhm_rentiva_email_test_address'] ?? get_option('admin_email');
        }

        // Template path
        if (isset($input['mhm_rentiva_email_template_path'])) {
            $out['mhm_rentiva_email_template_path'] = self::sanitize_text_field_safe($input['mhm_rentiva_email_template_path']);
        } else {
            $out['mhm_rentiva_email_template_path'] = $defaults['mhm_rentiva_email_template_path'] ?? 'mhm-rentiva/emails/';
        }

        // Auto send + logging + retention
        $out['mhm_rentiva_email_auto_send'] = (isset($input['mhm_rentiva_email_auto_send']) && $input['mhm_rentiva_email_auto_send'] === '1') ? '1' : '0';
        $out['mhm_rentiva_email_log_enabled'] = (isset($input['mhm_rentiva_email_log_enabled']) && $input['mhm_rentiva_email_log_enabled'] === '1') ? '1' : '0';
        if (isset($input['mhm_rentiva_email_log_retention_days'])) {
            $days = absint($input['mhm_rentiva_email_log_retention_days']);
            $out['mhm_rentiva_email_log_retention_days'] = $days >= 1 && $days <= 365 ? $days : 30;
        } else {
            $out['mhm_rentiva_email_log_retention_days'] = $defaults['mhm_rentiva_email_log_retention_days'] ?? 30;
        }

        return $out;
    }

    private static function sanitize_stripe_settings($input, $defaults): array
    {
        $out = [];

        if (isset($input['mhm_rentiva_stripe_enabled'])) {
            $out['mhm_rentiva_stripe_enabled'] = $input['mhm_rentiva_stripe_enabled'] === '1' ? '1' : '0';
        } else {
            // Keep current value, don't use default value
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_stripe_enabled'] = $current_settings['mhm_rentiva_stripe_enabled'] ?? $defaults['mhm_rentiva_stripe_enabled'];
        }

        if (isset($input['mhm_rentiva_stripe_mode'])) {
            $mode = self::sanitize_text_field_safe( $input['mhm_rentiva_stripe_mode']);
            $out['mhm_rentiva_stripe_mode'] = in_array($mode, ['test', 'live'], true) ? $mode : 'test';
        } else {
            $out['mhm_rentiva_stripe_mode'] = $defaults['mhm_rentiva_stripe_mode'];
        }

        // Test mode settings
        if (isset($input['mhm_rentiva_stripe_test_mode'])) {
            $out['mhm_rentiva_stripe_test_mode'] = $input['mhm_rentiva_stripe_test_mode'] === '1' ? '1' : '0';
        } else {
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_stripe_test_mode'] = $current_settings['mhm_rentiva_stripe_test_mode'] ?? $defaults['mhm_rentiva_stripe_test_mode'];
        }

        if (isset($input['mhm_rentiva_stripe_publishable_key'])) {
            $out['mhm_rentiva_stripe_publishable_key'] = self::sanitize_text_field_safe( $input['mhm_rentiva_stripe_publishable_key']);
        } else {
            $out['mhm_rentiva_stripe_publishable_key'] = $defaults['mhm_rentiva_stripe_publishable_key'];
        }

        if (isset($input['mhm_rentiva_stripe_secret_key'])) {
            $out['mhm_rentiva_stripe_secret_key'] = self::sanitize_text_field_safe( $input['mhm_rentiva_stripe_secret_key']);
        } else {
            $out['mhm_rentiva_stripe_secret_key'] = $defaults['mhm_rentiva_stripe_secret_key'];
        }

        // Stripe test keys
        if (isset($input['mhm_rentiva_stripe_pk_test'])) {
            $out['mhm_rentiva_stripe_pk_test'] = self::sanitize_text_field_safe( $input['mhm_rentiva_stripe_pk_test']);
        } else {
            $out['mhm_rentiva_stripe_pk_test'] = $defaults['mhm_rentiva_stripe_pk_test'];
        }

        if (isset($input['mhm_rentiva_stripe_sk_test'])) {
            $out['mhm_rentiva_stripe_sk_test'] = self::sanitize_text_field_safe( $input['mhm_rentiva_stripe_sk_test']);
        } else {
            $out['mhm_rentiva_stripe_sk_test'] = $defaults['mhm_rentiva_stripe_sk_test'];
        }

        if (isset($input['mhm_rentiva_stripe_webhook_secret_test'])) {
            $out['mhm_rentiva_stripe_webhook_secret_test'] = self::sanitize_text_field_safe( $input['mhm_rentiva_stripe_webhook_secret_test']);
        } else {
            $out['mhm_rentiva_stripe_webhook_secret_test'] = $defaults['mhm_rentiva_stripe_webhook_secret_test'];
        }

        // Stripe live keys
        if (isset($input['mhm_rentiva_stripe_pk_live'])) {
            $out['mhm_rentiva_stripe_pk_live'] = self::sanitize_text_field_safe( $input['mhm_rentiva_stripe_pk_live']);
        } else {
            $out['mhm_rentiva_stripe_pk_live'] = $defaults['mhm_rentiva_stripe_pk_live'];
        }

        if (isset($input['mhm_rentiva_stripe_sk_live'])) {
            $out['mhm_rentiva_stripe_sk_live'] = self::sanitize_text_field_safe( $input['mhm_rentiva_stripe_sk_live']);
        } else {
            $out['mhm_rentiva_stripe_sk_live'] = $defaults['mhm_rentiva_stripe_sk_live'];
        }

        if (isset($input['mhm_rentiva_stripe_webhook_secret_live'])) {
            $out['mhm_rentiva_stripe_webhook_secret_live'] = self::sanitize_text_field_safe( $input['mhm_rentiva_stripe_webhook_secret_live']);
        } else {
            $out['mhm_rentiva_stripe_webhook_secret_live'] = $defaults['mhm_rentiva_stripe_webhook_secret_live'];
        }

        return $out;
    }

    private static function sanitize_paytr_settings($input, $defaults): array
    {
        $out = [];

        if (isset($input['mhm_rentiva_paytr_enabled'])) {
            $out['mhm_rentiva_paytr_enabled'] = $input['mhm_rentiva_paytr_enabled'] === '1' ? '1' : '0';
        } else {
            // Keep current value, don't use default value
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_paytr_enabled'] = $current_settings['mhm_rentiva_paytr_enabled'] ?? $defaults['mhm_rentiva_paytr_enabled'];
        }

        if (isset($input['mhm_rentiva_paytr_merchant_id'])) {
            $out['mhm_rentiva_paytr_merchant_id'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paytr_merchant_id']);
        } else {
            $out['mhm_rentiva_paytr_merchant_id'] = $defaults['mhm_rentiva_paytr_merchant_id'];
        }

        if (isset($input['mhm_rentiva_paytr_merchant_key'])) {
            $out['mhm_rentiva_paytr_merchant_key'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paytr_merchant_key']);
        } else {
            $out['mhm_rentiva_paytr_merchant_key'] = $defaults['mhm_rentiva_paytr_merchant_key'];
        }

        if (isset($input['mhm_rentiva_paytr_merchant_salt'])) {
            $out['mhm_rentiva_paytr_merchant_salt'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paytr_merchant_salt']);
        } else {
            $out['mhm_rentiva_paytr_merchant_salt'] = $defaults['mhm_rentiva_paytr_merchant_salt'];
        }

        // Test mode settings
        if (isset($input['mhm_rentiva_paytr_test_mode'])) {
            $out['mhm_rentiva_paytr_test_mode'] = $input['mhm_rentiva_paytr_test_mode'] === '1' ? '1' : '0';
        } else {
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_paytr_test_mode'] = $current_settings['mhm_rentiva_paytr_test_mode'] ?? $defaults['mhm_rentiva_paytr_test_mode'];
        }

        // PayTR additional settings
        if (isset($input['mhm_rentiva_paytr_no_installment'])) {
            $out['mhm_rentiva_paytr_no_installment'] = $input['mhm_rentiva_paytr_no_installment'] === '1' ? '1' : '0';
        } else {
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_paytr_no_installment'] = $current_settings['mhm_rentiva_paytr_no_installment'] ?? $defaults['mhm_rentiva_paytr_no_installment'];
        }

        if (isset($input['mhm_rentiva_paytr_max_installment'])) {
            $out['mhm_rentiva_paytr_max_installment'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paytr_max_installment']);
        } else {
            $out['mhm_rentiva_paytr_max_installment'] = $defaults['mhm_rentiva_paytr_max_installment'];
        }

        if (isset($input['mhm_rentiva_paytr_non_3d'])) {
            $out['mhm_rentiva_paytr_non_3d'] = $input['mhm_rentiva_paytr_non_3d'] === '1' ? '1' : '0';
        } else {
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_paytr_non_3d'] = $current_settings['mhm_rentiva_paytr_non_3d'] ?? $defaults['mhm_rentiva_paytr_non_3d'];
        }

        // PayTR timeout is handled in main sanitize() function via $timeout_values
        // Skipping here to avoid conflicts - will be applied at the end

        if (isset($input['mhm_rentiva_paytr_debug_on'])) {
            $out['mhm_rentiva_paytr_debug_on'] = $input['mhm_rentiva_paytr_debug_on'] === '1' ? '1' : '0';
        } else {
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_paytr_debug_on'] = $current_settings['mhm_rentiva_paytr_debug_on'] ?? $defaults['mhm_rentiva_paytr_debug_on'];
        }

        return $out;
    }

    private static function sanitize_maintenance_settings($input, $defaults): array
    {
        $out = [];

        // Maintenance checkboxes
        $out['mhm_rentiva_auto_cancel_enabled'] = isset($input['mhm_rentiva_auto_cancel_enabled']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_auto_cancel_minutes'])) {
            $minutes = absint($input['mhm_rentiva_auto_cancel_minutes']);
            $out['mhm_rentiva_auto_cancel_minutes'] = max(5, min(1440, $minutes));
        } else {
            $out['mhm_rentiva_auto_cancel_minutes'] = $defaults['mhm_rentiva_auto_cancel_minutes'] ?? 30;
        }

        return $out;
    }

    private static function sanitize_logs_settings($input, $defaults): array
    {
        $out = [];

        // Log checkboxes
        $out['mhm_rentiva_log_cleanup_enabled'] = isset($input['mhm_rentiva_log_cleanup_enabled']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_log_retention_days'])) {
            $days = absint($input['mhm_rentiva_log_retention_days']);
            $out['mhm_rentiva_log_retention_days'] = max(1, min(365, $days));
        } else {
            $out['mhm_rentiva_log_retention_days'] = $defaults['mhm_rentiva_log_retention_days'] ?? 30;
        }

        if (isset($input['mhm_rentiva_log_level'])) {
            $levels = ['error', 'warning', 'info', 'debug'];
            $out['mhm_rentiva_log_level'] = in_array($input['mhm_rentiva_log_level'], $levels, true) ? $input['mhm_rentiva_log_level'] : 'info';
        } else {
            $out['mhm_rentiva_log_level'] = $defaults['mhm_rentiva_log_level'] ?? 'info';
        }

        $out['mhm_rentiva_debug_mode'] = isset($input['mhm_rentiva_debug_mode']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_log_max_size'])) {
            $size = absint($input['mhm_rentiva_log_max_size']);
            $out['mhm_rentiva_log_max_size'] = max(1, min(100, $size));
        } else {
            $out['mhm_rentiva_log_max_size'] = $defaults['mhm_rentiva_log_max_size'] ?? 10;
        }

        return $out;
    }

    /**
     * Sanitize Reconciliation Settings
     */
    private static function sanitize_reconcile_settings($input, $defaults): array
    {
        $out = [];

        // Reconciliation checkboxes
        $out['mhm_rentiva_reconcile_enabled'] = isset($input['mhm_rentiva_reconcile_enabled']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_reconcile_frequency'])) {
            $frequencies = ['hourly', 'daily', 'weekly'];
            $out['mhm_rentiva_reconcile_frequency'] = in_array($input['mhm_rentiva_reconcile_frequency'], $frequencies, true) ? $input['mhm_rentiva_reconcile_frequency'] : 'daily';
        } else {
            $out['mhm_rentiva_reconcile_frequency'] = $defaults['mhm_rentiva_reconcile_frequency'] ?? 'daily';
        }

        if (isset($input['mhm_rentiva_reconcile_timeout'])) {
            $timeout = absint($input['mhm_rentiva_reconcile_timeout']);
            $out['mhm_rentiva_reconcile_timeout'] = max(5, min(60, $timeout));
        } else {
            $out['mhm_rentiva_reconcile_timeout'] = $defaults['mhm_rentiva_reconcile_timeout'] ?? 30;
        }

        $out['mhm_rentiva_reconcile_notify_errors'] = isset($input['mhm_rentiva_reconcile_notify_errors']) ? '1' : '0';

        return $out;
    }

    /**
     * Sanitize System & Performance Settings
     */
    private static function sanitize_system_settings($input, $defaults): array
    {
        $out = [];

        // Core/Rate Limiting Settings
        $out['mhm_rentiva_rate_limit_enabled'] = isset($input['mhm_rentiva_rate_limit_enabled']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_rate_limit_general_minute'])) {
            $value = absint($input['mhm_rentiva_rate_limit_general_minute']);
            $out['mhm_rentiva_rate_limit_general_minute'] = max(10, min(1000, $value));
        } else {
            $out['mhm_rentiva_rate_limit_general_minute'] = $defaults['mhm_rentiva_rate_limit_general_minute'] ?? 60;
        }

        if (isset($input['mhm_rentiva_rate_limit_booking_minute'])) {
            $value = absint($input['mhm_rentiva_rate_limit_booking_minute']);
            $out['mhm_rentiva_rate_limit_booking_minute'] = max(1, min(100, $value));
        } else {
            $out['mhm_rentiva_rate_limit_booking_minute'] = $defaults['mhm_rentiva_rate_limit_booking_minute'] ?? 5;
        }

        if (isset($input['mhm_rentiva_rate_limit_payment_minute'])) {
            $value = absint($input['mhm_rentiva_rate_limit_payment_minute']);
            $out['mhm_rentiva_rate_limit_payment_minute'] = max(1, min(50, $value));
        } else {
            $out['mhm_rentiva_rate_limit_payment_minute'] = $defaults['mhm_rentiva_rate_limit_payment_minute'] ?? 3;
        }

        // Cache Settings
        $out['mhm_rentiva_cache_enabled'] = isset($input['mhm_rentiva_cache_enabled']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_cache_default_ttl'])) {
            $value = floatval($input['mhm_rentiva_cache_default_ttl']);
            $out['mhm_rentiva_cache_default_ttl'] = max(0.5, min(24, $value));
        } else {
            $out['mhm_rentiva_cache_default_ttl'] = $defaults['mhm_rentiva_cache_default_ttl'] ?? 1;
        }

        if (isset($input['mhm_rentiva_cache_lists_ttl'])) {
            $value = absint($input['mhm_rentiva_cache_lists_ttl']);
            $out['mhm_rentiva_cache_lists_ttl'] = max(1, min(60, $value));
        } else {
            $out['mhm_rentiva_cache_lists_ttl'] = $defaults['mhm_rentiva_cache_lists_ttl'] ?? 5;
        }

        if (isset($input['mhm_rentiva_cache_reports_ttl'])) {
            $value = absint($input['mhm_rentiva_cache_reports_ttl']);
            $out['mhm_rentiva_cache_reports_ttl'] = max(1, min(1440, $value));
        } else {
            $out['mhm_rentiva_cache_reports_ttl'] = $defaults['mhm_rentiva_cache_reports_ttl'] ?? 15;
        }

        if (isset($input['mhm_rentiva_cache_charts_ttl'])) {
            $value = absint($input['mhm_rentiva_cache_charts_ttl']);
            $out['mhm_rentiva_cache_charts_ttl'] = max(1, min(1440, $value));
        } else {
            $out['mhm_rentiva_cache_charts_ttl'] = $defaults['mhm_rentiva_cache_charts_ttl'] ?? 10;
        }

        // Database Settings
        $out['mhm_rentiva_db_auto_optimize'] = isset($input['mhm_rentiva_db_auto_optimize']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_db_performance_threshold'])) {
            $value = absint($input['mhm_rentiva_db_performance_threshold']);
            $out['mhm_rentiva_db_performance_threshold'] = max(50, min(1000, $value));
        } else {
            $out['mhm_rentiva_db_performance_threshold'] = $defaults['mhm_rentiva_db_performance_threshold'] ?? 100;
        }

        // WordPress Optimization Settings
        $out['mhm_rentiva_wp_optimization_enabled'] = isset($input['mhm_rentiva_wp_optimization_enabled']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_wp_memory_limit'])) {
            $value = absint($input['mhm_rentiva_wp_memory_limit']);
            $out['mhm_rentiva_wp_memory_limit'] = max(128, min(1024, $value));
        } else {
            $out['mhm_rentiva_wp_memory_limit'] = $defaults['mhm_rentiva_wp_memory_limit'] ?? 256;
        }

        if (isset($input['mhm_rentiva_wp_meta_query_limit'])) {
            $value = absint($input['mhm_rentiva_wp_meta_query_limit']);
            $out['mhm_rentiva_wp_meta_query_limit'] = max(1, min(20, $value));
        } else {
            $out['mhm_rentiva_wp_meta_query_limit'] = $defaults['mhm_rentiva_wp_meta_query_limit'] ?? 5;
        }

        // Security Settings
        $out['mhm_rentiva_ip_whitelist_enabled'] = isset($input['mhm_rentiva_ip_whitelist_enabled']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_ip_whitelist'])) {
            $out['mhm_rentiva_ip_whitelist'] = sanitize_textarea_field($input['mhm_rentiva_ip_whitelist']);
        } else {
            $out['mhm_rentiva_ip_whitelist'] = $defaults['mhm_rentiva_ip_whitelist'] ?? '';
        }

        $out['mhm_rentiva_ip_blacklist_enabled'] = isset($input['mhm_rentiva_ip_blacklist_enabled']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_ip_blacklist'])) {
            $out['mhm_rentiva_ip_blacklist'] = sanitize_textarea_field($input['mhm_rentiva_ip_blacklist']);
        } else {
            $out['mhm_rentiva_ip_blacklist'] = $defaults['mhm_rentiva_ip_blacklist'] ?? '';
        }

        $out['mhm_rentiva_country_restriction_enabled'] = isset($input['mhm_rentiva_country_restriction_enabled']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_allowed_countries'])) {
            $out['mhm_rentiva_allowed_countries'] = sanitize_text_field($input['mhm_rentiva_allowed_countries']);
        } else {
            $out['mhm_rentiva_allowed_countries'] = $defaults['mhm_rentiva_allowed_countries'] ?? '';
        }

        $out['mhm_rentiva_brute_force_protection'] = isset($input['mhm_rentiva_brute_force_protection']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_max_login_attempts'])) {
            $value = absint($input['mhm_rentiva_max_login_attempts']);
            $out['mhm_rentiva_max_login_attempts'] = max(3, min(20, $value));
        } else {
            $out['mhm_rentiva_max_login_attempts'] = $defaults['mhm_rentiva_max_login_attempts'] ?? 5;
        }

        if (isset($input['mhm_rentiva_login_lockout_duration'])) {
            $value = absint($input['mhm_rentiva_login_lockout_duration']);
            $out['mhm_rentiva_login_lockout_duration'] = max(5, min(1440, $value));
        } else {
            $out['mhm_rentiva_login_lockout_duration'] = $defaults['mhm_rentiva_login_lockout_duration'] ?? 30;
        }

        $out['mhm_rentiva_sql_injection_protection'] = isset($input['mhm_rentiva_sql_injection_protection']) ? '1' : '0';
        $out['mhm_rentiva_xss_protection'] = isset($input['mhm_rentiva_xss_protection']) ? '1' : '0';
        $out['mhm_rentiva_csrf_protection'] = isset($input['mhm_rentiva_csrf_protection']) ? '1' : '0';
        $out['mhm_rentiva_strong_passwords'] = isset($input['mhm_rentiva_strong_passwords']) ? '1' : '0';
        
        if (isset($input['mhm_rentiva_password_expiry_days'])) {
            $value = absint($input['mhm_rentiva_password_expiry_days']);
            $out['mhm_rentiva_password_expiry_days'] = max(0, min(365, $value));
        } else {
            $out['mhm_rentiva_password_expiry_days'] = $defaults['mhm_rentiva_password_expiry_days'] ?? 0;
        }

        $out['mhm_rentiva_two_factor_auth'] = isset($input['mhm_rentiva_two_factor_auth']) ? '1' : '0';
        $out['mhm_rentiva_session_security'] = isset($input['mhm_rentiva_session_security']) ? '1' : '0';

        return $out;
    }

    private static function sanitize_offline_settings($input, $defaults): array
    {
        $out = [];

        // Checkbox handling: If form is submitted but checkbox is not in POST, it means unchecked
        // Check if this is a form submission - if $input is not empty and contains other settings, it's a form submission
        $is_form_submission = !empty($input) && (
            isset($input['mhm_rentiva_stripe_enabled']) || 
            isset($input['mhm_rentiva_paytr_enabled']) || 
            isset($input['mhm_rentiva_paypal_enabled']) ||
            isset($input['mhm_rentiva_currency']) ||
            isset($_POST['mhm_rentiva_settings'])
        );
        
        if (isset($input['mhm_rentiva_offline_enabled'])) {
            $out['mhm_rentiva_offline_enabled'] = $input['mhm_rentiva_offline_enabled'] === '1' ? '1' : '0';
        } elseif ($is_form_submission) {
            // Form was submitted but checkbox not in POST, means unchecked
            $out['mhm_rentiva_offline_enabled'] = '0';
        } else {
            // Keep current value, don't use default value
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_offline_enabled'] = $current_settings['mhm_rentiva_offline_enabled'] ?? $defaults['mhm_rentiva_offline_enabled'];
        }

        if (isset($input['mhm_rentiva_offline_instructions'])) {
            $instructions_val = $input['mhm_rentiva_offline_instructions'];
            // Skip sanitization for empty values to avoid strlen() errors
            if ($instructions_val === null || $instructions_val === '' || !is_string($instructions_val)) {
                $out['mhm_rentiva_offline_instructions'] = '';
            } else {
                $out['mhm_rentiva_offline_instructions'] = \MHMRentiva\Admin\Settings\Core\SettingsHelper::sanitize_textarea_field_safe($instructions_val);
            }
        } else {
            $out['mhm_rentiva_offline_instructions'] = $defaults['mhm_rentiva_offline_instructions'];
        }

        if (isset($input['mhm_rentiva_offline_accounts'])) {
            // Use wp_kses to preserve Turkish characters
            $accounts_val = $input['mhm_rentiva_offline_accounts'];
            // Skip sanitization for empty values to avoid strlen() errors
            if ($accounts_val === null || $accounts_val === '' || !is_string($accounts_val)) {
                $out['mhm_rentiva_offline_accounts'] = '';
            } else {
                $out['mhm_rentiva_offline_accounts'] = wp_kses_post((string) $accounts_val);
            }
        } else {
            $out['mhm_rentiva_offline_accounts'] = $defaults['mhm_rentiva_offline_accounts'];
        }

        return $out;
    }

    private static function sanitize_paypal_settings($input, $defaults): array
    {
        $out = [];

        if (isset($input['mhm_rentiva_paypal_enabled'])) {
            $out['mhm_rentiva_paypal_enabled'] = $input['mhm_rentiva_paypal_enabled'] === '1' ? '1' : '0';
        } else {
            // Keep current value, don't use default value
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_paypal_enabled'] = $current_settings['mhm_rentiva_paypal_enabled'] ?? $defaults['mhm_rentiva_paypal_enabled'];
        }

        if (isset($input['mhm_rentiva_paypal_mode'])) {
            $mode = self::sanitize_text_field_safe( $input['mhm_rentiva_paypal_mode']);
            $out['mhm_rentiva_paypal_mode'] = in_array($mode, ['sandbox', 'live'], true) ? $mode : 'sandbox';
        } else {
            $out['mhm_rentiva_paypal_mode'] = $defaults['mhm_rentiva_paypal_mode'];
        }

        if (isset($input['mhm_rentiva_paypal_client_id'])) {
            $out['mhm_rentiva_paypal_client_id'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paypal_client_id']);
        } else {
            $out['mhm_rentiva_paypal_client_id'] = $defaults['mhm_rentiva_paypal_client_id'];
        }

        if (isset($input['mhm_rentiva_paypal_client_secret'])) {
            $out['mhm_rentiva_paypal_client_secret'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paypal_client_secret']);
        } else {
            $out['mhm_rentiva_paypal_client_secret'] = $defaults['mhm_rentiva_paypal_client_secret'];
        }

        // Test mode settings
        if (isset($input['mhm_rentiva_paypal_test_mode'])) {
            $out['mhm_rentiva_paypal_test_mode'] = $input['mhm_rentiva_paypal_test_mode'] === '1' ? '1' : '0';
        } else {
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_paypal_test_mode'] = $current_settings['mhm_rentiva_paypal_test_mode'] ?? $defaults['mhm_rentiva_paypal_test_mode'];
        }

        // PayPal test keys
        if (isset($input['mhm_rentiva_paypal_client_id_test'])) {
            $out['mhm_rentiva_paypal_client_id_test'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paypal_client_id_test']);
        } else {
            $out['mhm_rentiva_paypal_client_id_test'] = $defaults['mhm_rentiva_paypal_client_id_test'];
        }

        if (isset($input['mhm_rentiva_paypal_client_secret_test'])) {
            $out['mhm_rentiva_paypal_client_secret_test'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paypal_client_secret_test']);
        } else {
            $out['mhm_rentiva_paypal_client_secret_test'] = $defaults['mhm_rentiva_paypal_client_secret_test'];
        }

        // PayPal live keys
        if (isset($input['mhm_rentiva_paypal_client_id_live'])) {
            $out['mhm_rentiva_paypal_client_id_live'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paypal_client_id_live']);
        } else {
            $out['mhm_rentiva_paypal_client_id_live'] = $defaults['mhm_rentiva_paypal_client_id_live'];
        }

        if (isset($input['mhm_rentiva_paypal_client_secret_live'])) {
            $out['mhm_rentiva_paypal_client_secret_live'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paypal_client_secret_live']);
        } else {
            $out['mhm_rentiva_paypal_client_secret_live'] = $defaults['mhm_rentiva_paypal_client_secret_live'];
        }

        // PayPal additional settings
        if (isset($input['mhm_rentiva_paypal_currency'])) {
            $out['mhm_rentiva_paypal_currency'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paypal_currency']);
        } else {
            $out['mhm_rentiva_paypal_currency'] = $defaults['mhm_rentiva_paypal_currency'];
        }

        if (isset($input['mhm_rentiva_paypal_webhook_id'])) {
            $out['mhm_rentiva_paypal_webhook_id'] = self::sanitize_text_field_safe( $input['mhm_rentiva_paypal_webhook_id']);
        } else {
            $out['mhm_rentiva_paypal_webhook_id'] = $defaults['mhm_rentiva_paypal_webhook_id'];
        }

        if (isset($input['mhm_rentiva_paypal_debug_mode'])) {
            $out['mhm_rentiva_paypal_debug_mode'] = $input['mhm_rentiva_paypal_debug_mode'] === '1' ? '1' : '0';
        } else {
            $current_settings = get_option('mhm_rentiva_settings', []);
            $out['mhm_rentiva_paypal_debug_mode'] = $current_settings['mhm_rentiva_paypal_debug_mode'] ?? $defaults['mhm_rentiva_paypal_debug_mode'];
        }

        // PayPal timeout is handled in main sanitize() function via $timeout_values
        // Skipping here to avoid conflicts - will be applied at the end

        return $out;
    }

    /**
     * Sanitize Frontend URL & Text Settings
     */
    private static function sanitize_frontend_settings($input, $defaults): array
    {
        $out = [];

        // Frontend URLs
        $url_fields = [
            'mhm_rentiva_booking_url',
            'mhm_rentiva_login_url',
            'mhm_rentiva_register_url',
            'mhm_rentiva_my_account_url',
            'mhm_rentiva_my_bookings_url',
            'mhm_rentiva_my_favorites_url',
            'mhm_rentiva_vehicles_list_url',
            'mhm_rentiva_search_url',
            'mhm_rentiva_contact_url',
        ];

        foreach ($url_fields as $field) {
            if (isset($input[$field])) {
                $out[$field] = esc_url_raw($input[$field]);
            } else {
                $out[$field] = $defaults[$field] ?? '';
            }
        }

        // Frontend Texts
        $text_fields = [
            'mhm_rentiva_text_book_now',
            'mhm_rentiva_text_view_details',
            'mhm_rentiva_text_added_to_favorites',
            'mhm_rentiva_text_removed_from_favorites',
            'mhm_rentiva_text_make_booking',
            'mhm_rentiva_text_processing',
            'mhm_rentiva_text_back_to_bookings',
            'mhm_rentiva_text_cancel_booking',
            'mhm_rentiva_text_view_dashboard',
            'mhm_rentiva_text_login_here',
            'mhm_rentiva_text_already_have_account',
            'mhm_rentiva_text_first_name',
            'mhm_rentiva_text_last_name',
            'mhm_rentiva_text_email',
            'mhm_rentiva_text_phone',
            'mhm_rentiva_text_loading',
            'mhm_rentiva_text_error',
            'mhm_rentiva_text_booking_success',
            'mhm_rentiva_text_select_vehicle',
            'mhm_rentiva_text_select_dates',
            'mhm_rentiva_text_invalid_dates',
            'mhm_rentiva_text_select_payment_type',
            'mhm_rentiva_text_select_payment_method',
            'mhm_rentiva_text_calculating',
            'mhm_rentiva_text_payment_redirect',
            'mhm_rentiva_text_payment_success',
            'mhm_rentiva_text_payment_cancelled',
            'mhm_rentiva_text_popup_blocked',
        ];

        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $out[$field] = self::sanitize_text_field_safe($input[$field]);
            } else {
                $out[$field] = $defaults[$field] ?? '';
            }
        }

        // Login required is a textarea field
        if (isset($input['mhm_rentiva_text_login_required'])) {
            $out['mhm_rentiva_text_login_required'] = sanitize_textarea_field($input['mhm_rentiva_text_login_required']);
        } else {
            $out['mhm_rentiva_text_login_required'] = $defaults['mhm_rentiva_text_login_required'] ?? '';
        }

        return $out;
    }

    private static function sanitize_vehicle_pricing_settings($input, $defaults): array
    {
        $out = [];
        
        // Get current vehicle_pricing settings
        $current_settings = get_option('mhm_rentiva_settings', []);
        $current_vehicle_pricing = $current_settings['vehicle_pricing'] ?? $defaults['vehicle_pricing'];
        
        // If vehicle_pricing input exists, process it
        if (isset($input['vehicle_pricing']) && is_array($input['vehicle_pricing'])) {
            $vehicle_pricing_input = $input['vehicle_pricing'];
            
            // Deposit settings
            if (isset($vehicle_pricing_input['deposit_settings']) && is_array($vehicle_pricing_input['deposit_settings'])) {
                $deposit_input = $vehicle_pricing_input['deposit_settings'];
                
                $deposit_settings = [
                    'enable_deposit' => isset($deposit_input['enable_deposit']) ? (bool) $deposit_input['enable_deposit'] : ($current_vehicle_pricing['deposit_settings']['enable_deposit'] ?? false),
                    'deposit_type' => self::sanitize_text_field_safe($deposit_input['deposit_type'] ?? ($current_vehicle_pricing['deposit_settings']['deposit_type'] ?? 'both')),
                    'allow_no_deposit' => isset($deposit_input['allow_no_deposit']) ? (bool) $deposit_input['allow_no_deposit'] : ($current_vehicle_pricing['deposit_settings']['allow_no_deposit'] ?? true),
                    'required_for_booking' => isset($deposit_input['required_for_booking']) ? (bool) $deposit_input['required_for_booking'] : ($current_vehicle_pricing['deposit_settings']['required_for_booking'] ?? false),
                    'show_deposit_in_listing' => isset($deposit_input['show_deposit_in_listing']) ? (bool) $deposit_input['show_deposit_in_listing'] : ($current_vehicle_pricing['deposit_settings']['show_deposit_in_listing'] ?? true),
                    'show_deposit_in_detail' => isset($deposit_input['show_deposit_in_detail']) ? (bool) $deposit_input['show_deposit_in_detail'] : ($current_vehicle_pricing['deposit_settings']['show_deposit_in_detail'] ?? true),
                    'deposit_refund_policy' => (($deposit_policy_val = ($deposit_input['deposit_refund_policy'] ?? ($current_vehicle_pricing['deposit_settings']['deposit_refund_policy'] ?? ''))) !== null && $deposit_policy_val !== '' && (is_string($deposit_policy_val) || is_numeric($deposit_policy_val))) 
                        ? \MHMRentiva\Admin\Settings\Core\SettingsHelper::sanitize_textarea_field_safe($deposit_policy_val) 
                        : '',
                    'deposit_payment_methods' => isset($deposit_input['deposit_payment_methods']) && is_array($deposit_input['deposit_payment_methods']) 
                        ? array_map([self::class, 'sanitize_text_field_safe'], $deposit_input['deposit_payment_methods'])
                        : ($current_vehicle_pricing['deposit_settings']['deposit_payment_methods'] ?? ['credit_card', 'cash', 'bank_transfer'])
                ];
                
                $current_vehicle_pricing['deposit_settings'] = $deposit_settings;
            }
            
            // Seasonal multipliers
            if (isset($vehicle_pricing_input['seasonal_multipliers']) && is_array($vehicle_pricing_input['seasonal_multipliers'])) {
                foreach ($vehicle_pricing_input['seasonal_multipliers'] as $key => $season) {
                    if (isset($season['multiplier'])) {
                        $current_vehicle_pricing['seasonal_multipliers'][$key]['multiplier'] = floatval($season['multiplier']);
                    }
                }
            }
            
            // Discount options
            if (isset($vehicle_pricing_input['discount_options']) && is_array($vehicle_pricing_input['discount_options'])) {
                foreach ($vehicle_pricing_input['discount_options'] as $key => $discount) {
                    if (isset($discount['enabled'])) {
                        $current_vehicle_pricing['discount_options'][$key]['enabled'] = (bool) $discount['enabled'];
                    }
                    if (isset($discount['min_days'])) {
                        $current_vehicle_pricing['discount_options'][$key]['min_days'] = absint($discount['min_days']);
                    }
                    if (isset($discount['advance_days'])) {
                        $current_vehicle_pricing['discount_options'][$key]['advance_days'] = absint($discount['advance_days']);
                    }
                    if (isset($discount['discount_percent'])) {
                        $current_vehicle_pricing['discount_options'][$key]['discount_percent'] = absint($discount['discount_percent']);
                    }
                }
            }
            
            // Currency settings
            if (isset($vehicle_pricing_input['currency_settings']) && is_array($vehicle_pricing_input['currency_settings'])) {
                $currency_input = $vehicle_pricing_input['currency_settings'];
                $current_vehicle_pricing['currency_settings']['default_currency'] = self::sanitize_text_field_safe($currency_input['default_currency'] ?? $current_vehicle_pricing['currency_settings']['default_currency']);
                $current_vehicle_pricing['currency_settings']['auto_update_rates'] = isset($currency_input['auto_update_rates']) ? (bool) $currency_input['auto_update_rates'] : ($current_vehicle_pricing['currency_settings']['auto_update_rates'] ?? false);
            }
            
            // General settings
            if (isset($vehicle_pricing_input['general_settings']) && is_array($vehicle_pricing_input['general_settings'])) {
                $general_input = $vehicle_pricing_input['general_settings'];
                $current_vehicle_pricing['general_settings']['min_rental_days'] = absint($general_input['min_rental_days'] ?? $current_vehicle_pricing['general_settings']['min_rental_days']);
                $current_vehicle_pricing['general_settings']['max_rental_days'] = absint($general_input['max_rental_days'] ?? $current_vehicle_pricing['general_settings']['max_rental_days']);
                $current_vehicle_pricing['general_settings']['decimal_places'] = absint($general_input['decimal_places'] ?? $current_vehicle_pricing['general_settings']['decimal_places']);
            }
        }
        
        $out['vehicle_pricing'] = $current_vehicle_pricing;
        
        return $out;
    }

    private static function sanitize_comparison_settings(array $input, array $current_values): array
    {
        $out = [];

        if (isset($input['comparison_fields']) && is_array($input['comparison_fields'])) {
            $out['comparison_fields'] = self::sanitize_comparison_fields($input['comparison_fields']);
        }

        return $out;
    }

    private static function sanitize_comparison_fields(array $fields): array
    {
        $sanitized = [];

        foreach ($fields as $category => $field_list) {
            if (!is_array($field_list)) {
                continue;
            }

            $category_key = sanitize_key((string) $category);
            if ($category_key === '') {
                continue;
            }

            $clean_fields = [];
            foreach ($field_list as $field) {
                if (!is_scalar($field)) {
                    continue;
                }

                $field_key = sanitize_key((string) $field);
                if ($field_key !== '') {
                    $clean_fields[$field_key] = true;
                }
            }

            $sanitized[$category_key] = array_keys($clean_fields);
        }

        return $sanitized;
    }

    public static function currency_positions(): array
    {
        return [
            'left'        => __('Left ($100)', 'mhm-rentiva'),
            'right'       => __('Right (100$)', 'mhm-rentiva'),
            'left_space'  => __('Left + Space ($ 100)', 'mhm-rentiva'),
            'right_space' => __('Right + Space (100 $)', 'mhm-rentiva'),
        ];
    }

    public static function date_formats(): array
    {
        return [
            'Y-m-d' => '2024-01-15',
            'd-m-Y' => '15-01-2024',
            'm/d/Y' => '01/15/2024',
            'd/m/Y' => '15/01/2024',
        ];
    }

    public static function time_formats(): array
    {
        return [
            'H:i'   => '23:59',
            'H:i:s' => '23:59:59',
        ];
    }

    /**
     * Sanitize site information settings
     */
    private static function sanitize_site_info_settings($input, $defaults): array
    {
        $out = [];

        // site_url (read-only, no sanitization needed)
        if (isset($input['mhm_rentiva_site_url'])) {
            $out['mhm_rentiva_site_url'] = esc_url_raw($input['mhm_rentiva_site_url'] !== null ? (string) $input['mhm_rentiva_site_url'] : '');
        } else {
            $out['mhm_rentiva_site_url'] = $defaults['mhm_rentiva_site_url'] ?? get_option('siteurl', '');
        }

        // home_url (read-only, no sanitization needed)
        if (isset($input['mhm_rentiva_home_url'])) {
            $out['mhm_rentiva_home_url'] = esc_url_raw($input['mhm_rentiva_home_url'] !== null ? (string) $input['mhm_rentiva_home_url'] : '');
        } else {
            $out['mhm_rentiva_home_url'] = $defaults['mhm_rentiva_home_url'] ?? get_option('home', '');
        }

        // admin_email (read-only, no sanitization needed)
        if (isset($input['mhm_rentiva_admin_email'])) {
            $admin_email_val = $input['mhm_rentiva_admin_email'];
            $admin_email = \MHMRentiva\Admin\Settings\Core\SettingsHelper::sanitize_email_safe($admin_email_val);
            $out['mhm_rentiva_admin_email'] = $admin_email !== '' ? $admin_email : ($defaults['mhm_rentiva_admin_email'] ?? get_option('admin_email', ''));
        } else {
            $out['mhm_rentiva_admin_email'] = $defaults['mhm_rentiva_admin_email'] ?? get_option('admin_email', '');
        }

        // site_language (read-only, no sanitization needed)
        if (isset($input['mhm_rentiva_site_language'])) {
            $out['mhm_rentiva_site_language'] = self::sanitize_text_field_safe($input['mhm_rentiva_site_language']);
        } else {
            $out['mhm_rentiva_site_language'] = $defaults['mhm_rentiva_site_language'] ?? get_locale();
        }

        // timezone (read-only, no sanitization needed)
        if (isset($input['mhm_rentiva_timezone'])) {
            $out['mhm_rentiva_timezone'] = self::sanitize_text_field_safe($input['mhm_rentiva_timezone']);
        } else {
            $out['mhm_rentiva_timezone'] = $defaults['mhm_rentiva_timezone'] ?? wp_timezone_string();
        }

        return $out;
    }

    /**
     * Sanitize date & time settings
     */
    private static function sanitize_datetime_settings($input, $defaults): array
    {
        $out = [];

        // time_format (read-only, no sanitization needed)
        if (isset($input['mhm_rentiva_time_format'])) {
            $out['mhm_rentiva_time_format'] = self::sanitize_text_field_safe($input['mhm_rentiva_time_format']);
        } else {
            $out['mhm_rentiva_time_format'] = $defaults['mhm_rentiva_time_format'] ?? get_option('time_format', 'H:i');
        }

        // start_of_week (read-only, no sanitization needed)
        if (isset($input['mhm_rentiva_start_of_week'])) {
            $week_start = absint($input['mhm_rentiva_start_of_week']);
            $out['mhm_rentiva_start_of_week'] = ($week_start >= 0 && $week_start <= 6) ? $week_start : 1;
        } else {
            $out['mhm_rentiva_start_of_week'] = $defaults['mhm_rentiva_start_of_week'] ?? get_option('start_of_week', 1);
        }

        return $out;
    }

    /**
     * Sanitize vehicle management settings
     */
    private static function sanitize_vehicle_management_settings($input, $defaults): array
    {
        $out = [];

        // Pricing Settings
        $out['mhm_rentiva_vehicle_base_price'] = isset($input['mhm_rentiva_vehicle_base_price']) 
            ? max(0.1, floatval($input['mhm_rentiva_vehicle_base_price'])) 
            : $defaults['mhm_rentiva_vehicle_base_price'] ?? 1.0;

        $out['mhm_rentiva_vehicle_weekend_multiplier'] = isset($input['mhm_rentiva_vehicle_weekend_multiplier']) 
            ? max(0.1, floatval($input['mhm_rentiva_vehicle_weekend_multiplier'])) 
            : $defaults['mhm_rentiva_vehicle_weekend_multiplier'] ?? 1.0;

        $out['mhm_rentiva_vehicle_tax_inclusive'] = isset($input['mhm_rentiva_vehicle_tax_inclusive']) ? '1' : '0';

        $out['mhm_rentiva_vehicle_tax_rate'] = isset($input['mhm_rentiva_vehicle_tax_rate']) 
            ? max(0, min(100, floatval($input['mhm_rentiva_vehicle_tax_rate']))) 
            : $defaults['mhm_rentiva_vehicle_tax_rate'] ?? 0;

        // Display Settings
        $out['mhm_rentiva_vehicle_cards_per_page'] = isset($input['mhm_rentiva_vehicle_cards_per_page']) 
            ? max(1, min(100, intval($input['mhm_rentiva_vehicle_cards_per_page']))) 
            : $defaults['mhm_rentiva_vehicle_cards_per_page'] ?? 12;

        $out['mhm_rentiva_vehicle_default_sort'] = isset($input['mhm_rentiva_vehicle_default_sort']) 
            ? self::sanitize_text_field_safe($input['mhm_rentiva_vehicle_default_sort'])  
            : $defaults['mhm_rentiva_vehicle_default_sort'] ?? 'price_asc';

        // Checkbox fields - ALWAYS set value, either from input or from current settings
        // If checkbox is checked, it will be in input as '1'
        // If checkbox is unchecked, it won't be in input, so we set it to '0'
        $out['mhm_rentiva_vehicle_show_images'] = isset($input['mhm_rentiva_vehicle_show_images']) && $input['mhm_rentiva_vehicle_show_images'] === '1' ? '1' : '0';
        $out['mhm_rentiva_vehicle_show_features'] = isset($input['mhm_rentiva_vehicle_show_features']) && $input['mhm_rentiva_vehicle_show_features'] === '1' ? '1' : '0';
        $out['mhm_rentiva_vehicle_show_availability'] = isset($input['mhm_rentiva_vehicle_show_availability']) && $input['mhm_rentiva_vehicle_show_availability'] === '1' ? '1' : '0';
        
        // Comparison fields - handle array input
        if (isset($input['comparison_fields']) && is_array($input['comparison_fields'])) {
            $out['comparison_fields'] = self::sanitize_comparison_fields($input['comparison_fields']);
        } else {
            $out['comparison_fields'] = $defaults['comparison_fields'] ?? [];
        }

        // Availability Settings
        $out['mhm_rentiva_vehicle_min_rental_days'] = isset($input['mhm_rentiva_vehicle_min_rental_days']) 
            ? max(1, intval($input['mhm_rentiva_vehicle_min_rental_days'])) 
            : $defaults['mhm_rentiva_vehicle_min_rental_days'] ?? 1;

        $out['mhm_rentiva_vehicle_max_rental_days'] = isset($input['mhm_rentiva_vehicle_max_rental_days']) 
            ? max(1, min(365, intval($input['mhm_rentiva_vehicle_max_rental_days']))) 
            : $defaults['mhm_rentiva_vehicle_max_rental_days'] ?? 30;

        $out['mhm_rentiva_vehicle_advance_booking_days'] = isset($input['mhm_rentiva_vehicle_advance_booking_days']) 
            ? max(1, min(365, intval($input['mhm_rentiva_vehicle_advance_booking_days']))) 
            : $defaults['mhm_rentiva_vehicle_advance_booking_days'] ?? 365;

        $out['mhm_rentiva_vehicle_allow_same_day'] = isset($input['mhm_rentiva_vehicle_allow_same_day']) ? '1' : '0';

        return $out;
    }

    /**
     * Sanitize booking settings - Clean and simple
     */
    private static function sanitize_booking_settings($input, $defaults): array
    {
        $out = [];

        // REMOVED: booking_date_format (duplicate), booking_history_retention_days (unused)

        // Time Management Settings
        $out['mhm_rentiva_booking_cancellation_deadline_hours'] = isset($input['mhm_rentiva_booking_cancellation_deadline_hours']) 
            ? max(1, min(168, intval($input['mhm_rentiva_booking_cancellation_deadline_hours']))) 
            : ($defaults['mhm_rentiva_booking_cancellation_deadline_hours'] ?? 24);

        $out['mhm_rentiva_booking_payment_deadline_minutes'] = isset($input['mhm_rentiva_booking_payment_deadline_minutes']) 
            ? max(0, min(1440, intval($input['mhm_rentiva_booking_payment_deadline_minutes']))) 
            : ($defaults['mhm_rentiva_booking_payment_deadline_minutes'] ?? 30);

        $out['mhm_rentiva_booking_auto_cancel_enabled'] = isset($input['mhm_rentiva_booking_auto_cancel_enabled']) ? '1' : '0';
        
        // REMOVED: booking_confirmation_timeout_hours (no system), booking_reminder_hours_before (no reminder)

        // Notification Settings
        $out['mhm_rentiva_booking_send_confirmation_emails'] = isset($input['mhm_rentiva_booking_send_confirmation_emails']) ? '1' : '0';
        $out['mhm_rentiva_booking_send_reminder_emails'] = isset($input['mhm_rentiva_booking_send_reminder_emails']) ? '1' : '0';
        $out['mhm_rentiva_booking_admin_notifications'] = isset($input['mhm_rentiva_booking_admin_notifications']) ? '1' : '0';

        return $out;
    }

    /**
     * Sanitize Customer Management Settings
     */
    private static function sanitize_customer_management_settings($input, $defaults): array
    {
        $out = [];

        // Registration Settings
        $out['mhm_rentiva_customer_registration_enabled'] = isset($input['mhm_rentiva_customer_registration_enabled']) ? '1' : '0';
        $out['mhm_rentiva_customer_email_verification'] = isset($input['mhm_rentiva_customer_email_verification']) ? '1' : '0';
        $out['mhm_rentiva_customer_phone_required'] = isset($input['mhm_rentiva_customer_phone_required']) ? '1' : '0';
        $out['mhm_rentiva_customer_terms_required'] = isset($input['mhm_rentiva_customer_terms_required']) ? '1' : '0';
        if (isset($input['mhm_rentiva_customer_terms_text'])) {
            $terms_val = $input['mhm_rentiva_customer_terms_text'];
            if ($terms_val !== null && $terms_val !== '' && (is_string($terms_val) || is_numeric($terms_val))) {
                $out['mhm_rentiva_customer_terms_text'] = \MHMRentiva\Admin\Settings\Core\SettingsHelper::sanitize_textarea_field_safe($terms_val);
            } else {
                $out['mhm_rentiva_customer_terms_text'] = $defaults['mhm_rentiva_customer_terms_text'] ?? 'I accept the terms of use and privacy policy.';
            }
        } else {
            $out['mhm_rentiva_customer_terms_text'] = $defaults['mhm_rentiva_customer_terms_text'] ?? 'I accept the terms of use and privacy policy.';
        }

        // Account Settings
        $out['mhm_rentiva_customer_auto_login'] = isset($input['mhm_rentiva_customer_auto_login']) ? '1' : '0';

        // Communication Settings
        $out['mhm_rentiva_customer_welcome_email'] = isset($input['mhm_rentiva_customer_welcome_email']) ? '1' : '0';
        $out['mhm_rentiva_customer_booking_notifications'] = isset($input['mhm_rentiva_customer_booking_notifications']) ? '1' : '0';
        // REMOVED: customer_marketing_emails (no marketing system)

        // Security Settings
        $out['mhm_rentiva_customer_password_min_length'] = isset($input['mhm_rentiva_customer_password_min_length']) 
            ? max(6, min(32, intval($input['mhm_rentiva_customer_password_min_length']))) 
            : $defaults['mhm_rentiva_customer_password_min_length'] ?? 8;
        $out['mhm_rentiva_customer_password_require_special'] = isset($input['mhm_rentiva_customer_password_require_special']) ? '1' : '0';

        // Privacy Settings
        $out['mhm_rentiva_customer_gdpr_compliance'] = isset($input['mhm_rentiva_customer_gdpr_compliance']) ? '1' : '0';
        // REMOVED: customer_data_retention_days (no cleanup job), customer_consent_required (duplicate)

        // Experience Settings
        $out['mhm_rentiva_customer_default_role'] = isset($input['mhm_rentiva_customer_default_role']) 
            ? self::sanitize_text_field_safe($input['mhm_rentiva_customer_default_role'])  
            : $defaults['mhm_rentiva_customer_default_role'] ?? 'customer';
        $out['mhm_rentiva_customer_notification_frequency'] = isset($input['mhm_rentiva_customer_notification_frequency']) 
            ? self::sanitize_text_field_safe($input['mhm_rentiva_customer_notification_frequency'])  
            : $defaults['mhm_rentiva_customer_notification_frequency'] ?? 'immediate';

        return $out;
    }
}

// ✅ CRITICAL FIX: Prevent WordPress core from passing null to strlen()
// Override WordPress sanitize_text_field function at the earliest possible moment

// ✅ CRITICAL: Run IMMEDIATELY when file is loaded (before any WordPress hooks)
// This ensures we catch null values BEFORE WordPress core processes them
if (!function_exists('mhm_rentiva_sanitize_text_field_safe')) {
    /**
     * Safe wrapper for WordPress sanitize_text_field that handles null values
     */
    function mhm_rentiva_sanitize_text_field_safe($str) {
        // ✅ CRITICAL: Null check FIRST - before any processing
        if ($str === null) {
            return '';
        }
        // ✅ Empty string check
        if ($str === '') {
            return '';
        }
        // ✅ Convert to string if not already
        if (!is_string($str) && !is_numeric($str)) {
            return '';
        }
        // ✅ Now safe to call WordPress core function
        return sanitize_text_field((string) $str);
    }
}

// ✅ REMOVED: plugins_loaded hook with update_option - causes infinite loop
// Null cleaning is handled in sanitize() callback and immediate POST cleaning

// ✅ CRITICAL: Clean $_POST and $_REQUEST arrays BEFORE WordPress Settings API processes them
// Use 'plugins_loaded' hook to run VERY EARLY, before WordPress processes anything
add_action('plugins_loaded', function() {
    // ✅ Only run on admin pages and POST requests
    if (!is_admin() || !isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    
    // ✅ Only run on settings pages
    if (!isset($_GET['page']) || strpos($_GET['page'], 'mhm_rentiva') === false) {
        return;
    }
    
    // ✅ Clean $_POST array recursively - MUST happen before WordPress Settings API reads it
    if (isset($_POST) && is_array($_POST)) {
        \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::clean_post_recursive($_POST);
    }
    
    // ✅ Clean $_REQUEST array recursively
    if (isset($_REQUEST) && is_array($_REQUEST)) {
        \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::clean_post_recursive($_REQUEST);
    }
}, 2);

// ✅ Hook into sanitize_option to catch null values BEFORE WordPress core processes them
add_filter('sanitize_option_mhm_rentiva_settings', function($value) {
    if (is_array($value)) {
        \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::clean_post_recursive($value);
    }
    return $value;
}, PHP_INT_MIN, 1);

// ✅ REMOVED: pre_update_option hook - causes infinite loop with update_option action below
// Null cleaning is handled in sanitize() callback and hooks above

// ✅ CRITICAL: Clean $_POST and $_REQUEST arrays BEFORE WordPress Settings API processes them
// Hook into 'admin_init' with early priority
add_action('admin_init', function() {
    // ✅ Only run on POST requests (form submissions)
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    
    // ✅ Only run on settings pages
    if (!isset($_GET['page']) || strpos($_GET['page'], 'mhm_rentiva') === false) {
        return;
    }
    
    // ✅ Clean $_POST array recursively - MUST happen before WordPress Settings API reads it
    if (isset($_POST) && is_array($_POST)) {
        \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::clean_post_recursive($_POST);
    }
    
    // ✅ Clean $_REQUEST array recursively
    if (isset($_REQUEST) && is_array($_REQUEST)) {
        \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::clean_post_recursive($_REQUEST);
    }
}, 1); // Priority 1 to run early in admin_init hook

// ✅ REMOVED: update_option hook - causes infinite loop
// Timeout values are now handled directly in sanitize() callback
