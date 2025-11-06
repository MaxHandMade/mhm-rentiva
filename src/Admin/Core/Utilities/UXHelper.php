<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\I18nHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ KULLANICI DENEYİMİ İYİLEŞTİRMESİ - UX Helper
 * 
 * Kullanıcı deneyimi ve hata işleme için merkezi sınıf
 */
final class UXHelper
{
    /**
     * Error message types
     */
    public const ERROR_TYPE_BOOKING = 'booking';
    public const ERROR_TYPE_PAYMENT = 'payment';
    public const ERROR_TYPE_VEHICLE = 'vehicle';
    public const ERROR_TYPE_CUSTOMER = 'customer';
    public const ERROR_TYPE_SYSTEM = 'system';
    public const ERROR_TYPE_VALIDATION = 'validation';
    public const ERROR_TYPE_PERMISSION = 'permission';
    public const ERROR_TYPE_NETWORK = 'network';

    /**
     * Error severity levels
     */
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * User-friendly error messages
     */
    public static function get_user_friendly_error(string $error_type, string $error_code, array $context = []): string
    {
        $messages = self::get_error_messages();
        
        if (!isset($messages[$error_type][$error_code])) {
            return I18nHelper::__('An unexpected error occurred. Please try again later.');
        }

        $message = $messages[$error_type][$error_code];
        
        // Context değişkenlerini değiştir
        if (!empty($context)) {
            $message = self::replace_context_variables($message, $context);
        }

        return $message;
    }

    /**
     * Error messages with context
     */
    private static function get_error_messages(): array
    {
        return [
            self::ERROR_TYPE_BOOKING => [
                'vehicle_not_available' => I18nHelper::__('The selected vehicle is not available on %date%. Please select a different date.'),
                'booking_failed' => I18nHelper::__('An error occurred while creating reservation. Please check your information and try again.'),
                'invalid_dates' => I18nHelper::__('Invalid date range. Start date must be before end date.'),
                'past_date' => I18nHelper::__('You cannot select a past date. Please select a date after today.'),
                'too_far_future' => I18nHelper::__('Reservation date is too far in the future. You can make reservations up to %days% days in advance.'),
                'minimum_duration' => I18nHelper::__('Minimum reservation duration is %hours% hours.'),
                'maximum_duration' => I18nHelper::__('Maximum reservation duration is %days% days.'),
                'booking_limit_reached' => I18nHelper::__('You can make a maximum of %limit% reservations at once.'),
                'customer_not_found' => I18nHelper::__('Customer information not found. Please log in or register.'),
                'payment_required' => I18nHelper::__('Payment is required for reservation. Please select your payment method.'),
            ],
            self::ERROR_TYPE_PAYMENT => [
                'payment_failed' => I18nHelper::__('Payment transaction failed. Please check your card information and try again.'),
                'insufficient_funds' => I18nHelper::__('Insufficient balance. Please try a different payment method.'),
                'card_expired' => I18nHelper::__('Your card has expired. Please use a current card.'),
                'invalid_card' => I18nHelper::__('Invalid card information. Please check your card number and security code.'),
                'payment_timeout' => I18nHelper::__('Payment transaction timed out. Please try again.'),
                'refund_failed' => I18nHelper::__('Refund process failed. Please contact customer service.'),
                'partial_refund' => I18nHelper::__('Partial refund completed. %amount% has been refunded to your account.'),
                'payment_method_not_supported' => I18nHelper::__('Selected payment method is not supported. Please try a different method.'),
            ],
            self::ERROR_TYPE_VEHICLE => [
                'vehicle_not_found' => I18nHelper::__('Vehicle not found. Please refresh the page and try again.'),
                'vehicle_unavailable' => I18nHelper::__('This vehicle is currently in use. Please select a different vehicle.'),
                'vehicle_maintenance' => I18nHelper::__('Vehicle is under maintenance. Estimated duration: %duration%.'),
                'vehicle_damaged' => I18nHelper::__('Vehicle is damaged. Booking not available.'),
                'vehicle_location_changed' => I18nHelper::__('Vehicle location changed. New location: %location%.'),
                'vehicle_price_changed' => I18nHelper::__('Vehicle price updated. New price: %price%.'),
            ],
            self::ERROR_TYPE_CUSTOMER => [
                'customer_not_found' => I18nHelper::__('Customer information not found. Please log in.'),
                'customer_blocked' => I18nHelper::__('Your account has been temporarily restricted. Please contact customer service.'),
                'customer_verification_required' => I18nHelper::__('You need to verify your account. Please check your email address.'),
                'customer_profile_incomplete' => I18nHelper::__('Your profile information is incomplete. Please complete your information from your profile page.'),
                'customer_license_expired' => I18nHelper::__('Your license has expired. Please upload a current license.'),
                'customer_age_restriction' => I18nHelper::__('You cannot make a reservation due to age restriction. Minimum age: %age%.'),
            ],
            self::ERROR_TYPE_SYSTEM => [
                'database_error' => I18nHelper::__('System is temporarily unavailable. Please try again in a few minutes.'),
                'server_error' => I18nHelper::__('Server error occurred. Please try again later.'),
                'maintenance_mode' => I18nHelper::__('System is under maintenance. Estimated duration: %duration%.'),
                'feature_disabled' => I18nHelper::__('This feature is temporarily disabled.'),
                'rate_limit_exceeded' => I18nHelper::__('Too many requests. Please wait %seconds% seconds.'),
                'session_expired' => I18nHelper::__('Your session has expired. Please log in again.'),
            ],
            self::ERROR_TYPE_VALIDATION => [
                'required_field' => I18nHelper::__('This field is required.'),
                'invalid_email' => I18nHelper::__('Please enter a valid email address.'),
                'invalid_phone' => I18nHelper::__('Please enter a valid phone number.'),
                'invalid_date' => I18nHelper::__('Please enter a valid date.'),
                'invalid_time' => I18nHelper::__('Please enter a valid time.'),
                'password_too_short' => I18nHelper::__('Password must be at least %length% characters.'),
                'password_mismatch' => I18nHelper::__('Passwords do not match.'),
                'file_too_large' => I18nHelper::__('File size is too large. Maximum size: %size%.'),
                'invalid_file_type' => I18nHelper::__('Invalid file type. Allowed types: %types%.'),
            ],
            self::ERROR_TYPE_PERMISSION => [
                'access_denied' => I18nHelper::__('You do not have access to this page.'),
                'action_not_allowed' => I18nHelper::__('You do not have permission to perform this action.'),
                'admin_required' => I18nHelper::__('Administrator privileges required for this action.'),
                'login_required' => I18nHelper::__('You need to log in for this action.'),
                'verification_required' => I18nHelper::__('You need to verify your account for this action.'),
            ],
            self::ERROR_TYPE_NETWORK => [
                'connection_failed' => I18nHelper::__('Connection failed. Please check your internet connection.'),
                'timeout' => I18nHelper::__('Connection timed out. Please try again.'),
                'dns_error' => I18nHelper::__('DNS error occurred. Please try again later.'),
                'ssl_error' => I18nHelper::__('Secure connection error. Please try again later.'),
            ],
        ];
    }

    /**
     * Context variables'ları değiştir
     */
    private static function replace_context_variables(string $message, array $context): string
    {
        foreach ($context as $key => $value) {
            $message = str_replace('%' . $key . '%', $value, $message);
        }
        return $message;
    }

    /**
     * Success messages
     */
    public static function get_success_message(string $action_type, array $context = []): string
    {
        $messages = [
            'booking_created' => I18nHelper::__('Your reservation has been successfully created. Your reservation number: %booking_id%'),
            'booking_updated' => I18nHelper::__('Your reservation has been successfully updated.'),
            'booking_cancelled' => I18nHelper::__('Your reservation has been successfully cancelled.'),
            'payment_completed' => I18nHelper::__('Your payment has been successfully completed.'),
            'profile_updated' => I18nHelper::__('Your profile information has been successfully updated.'),
            'password_changed' => I18nHelper::__('Your password has been successfully changed.'),
            'email_sent' => I18nHelper::__('Email sent successfully.'),
            'file_uploaded' => I18nHelper::__('File uploaded successfully.'),
            'settings_saved' => I18nHelper::__('Settings saved successfully.'),
        ];

        if (!isset($messages[$action_type])) {
            return I18nHelper::__('Operation completed successfully.');
        }

        $message = $messages[$action_type];
        
        if (!empty($context)) {
            $message = self::replace_context_variables($message, $context);
        }

        return $message;
    }

    /**
     * Warning messages
     */
    public static function get_warning_message(string $warning_type, array $context = []): string
    {
        $messages = [
            'booking_ending_soon' => I18nHelper::__('Your reservation will end in %time%.'),
            'payment_due' => I18nHelper::__('Your payment must be made on %date%.'),
            'vehicle_return_due' => I18nHelper::__('You must return the vehicle on %date%.'),
            'license_expiring' => I18nHelper::__('Your license will expire in %days% days.'),
            'maintenance_scheduled' => I18nHelper::__('Vehicle maintenance is scheduled for %date%.'),
            'price_increase' => I18nHelper::__('Vehicle price will increase from %date%.'),
        ];

        if (!isset($messages[$warning_type])) {
            return I18nHelper::__('Warning: %message%');
        }

        $message = $messages[$warning_type];
        
        if (!empty($context)) {
            $message = self::replace_context_variables($message, $context);
        }

        return $message;
    }

    /**
     * Info messages
     */
    public static function get_info_message(string $info_type, array $context = []): string
    {
        $messages = [
            'booking_confirmed' => I18nHelper::__('Your reservation has been confirmed. Details have been sent via email.'),
            'payment_processing' => I18nHelper::__('Your payment is being processed. Please wait...'),
            'vehicle_ready' => I18nHelper::__('Your vehicle is ready. You can pick it up at the reservation time.'),
            'maintenance_completed' => I18nHelper::__('Vehicle maintenance completed. You can make a reservation.'),
            'new_feature' => I18nHelper::__('New feature: %feature%. Click for details.'),
            'system_update' => I18nHelper::__('System updated. New features available.'),
        ];

        if (!isset($messages[$info_type])) {
            return I18nHelper::__('Info: %message%');
        }

        $message = $messages[$info_type];
        
        if (!empty($context)) {
            $message = self::replace_context_variables($message, $context);
        }

        return $message;
    }

    /**
     * Error recovery suggestions
     */
    public static function get_recovery_suggestions(string $error_type, string $error_code): array
    {
        $suggestions = [
            self::ERROR_TYPE_BOOKING => [
                'vehicle_not_available' => [
                    I18nHelper::__('Select a different date'),
                    I18nHelper::__('Select a different vehicle'),
                    I18nHelper::__('Contact customer service'),
                ],
                'booking_failed' => [
                    I18nHelper::__('Check your information'),
                    I18nHelper::__('Refresh the page'),
                    I18nHelper::__('Try a different browser'),
                ],
            ],
            self::ERROR_TYPE_PAYMENT => [
                'payment_failed' => [
                    I18nHelper::__('Check your card information'),
                    I18nHelper::__('Try a different card'),
                    I18nHelper::__('Contact your bank'),
                ],
                'insufficient_funds' => [
                    I18nHelper::__('Check your account balance'),
                    I18nHelper::__('Try a different payment method'),
                    I18nHelper::__('Try partial payment option'),
                ],
            ],
            self::ERROR_TYPE_SYSTEM => [
                'database_error' => [
                    I18nHelper::__('Wait a few minutes'),
                    I18nHelper::__('Refresh the page'),
                    I18nHelper::__('Contact customer service'),
                ],
                'server_error' => [
                    I18nHelper::__('Try again later'),
                    I18nHelper::__('Try a different device'),
                    I18nHelper::__('Check your internet connection'),
                ],
            ],
        ];

        return $suggestions[$error_type][$error_code] ?? [
            I18nHelper::__('Refresh the page'),
            I18nHelper::__('Try again later'),
            I18nHelper::__('Contact customer service'),
        ];
    }

    /**
     * Error severity level
     */
    public static function get_error_severity(string $error_type, string $error_code): string
    {
        $severity_map = [
            self::ERROR_TYPE_BOOKING => [
                'vehicle_not_available' => self::SEVERITY_MEDIUM,
                'booking_failed' => self::SEVERITY_HIGH,
                'invalid_dates' => self::SEVERITY_LOW,
            ],
            self::ERROR_TYPE_PAYMENT => [
                'payment_failed' => self::SEVERITY_HIGH,
                'insufficient_funds' => self::SEVERITY_MEDIUM,
                'card_expired' => self::SEVERITY_MEDIUM,
            ],
            self::ERROR_TYPE_SYSTEM => [
                'database_error' => self::SEVERITY_CRITICAL,
                'server_error' => self::SEVERITY_CRITICAL,
                'maintenance_mode' => self::SEVERITY_MEDIUM,
            ],
        ];

        return $severity_map[$error_type][$error_code] ?? self::SEVERITY_MEDIUM;
    }

    /**
     * Error icon
     */
    public static function get_error_icon(string $error_type, string $severity): string
    {
        $icons = [
            self::SEVERITY_LOW => '⚠️',
            self::SEVERITY_MEDIUM => '⚠️',
            self::SEVERITY_HIGH => '❌',
            self::SEVERITY_CRITICAL => '🚨',
        ];

        return $icons[$severity] ?? '⚠️';
    }

    /**
     * Error color
     */
    public static function get_error_color(string $severity): string
    {
        $colors = [
            self::SEVERITY_LOW => '#f39c12',
            self::SEVERITY_MEDIUM => '#e67e22',
            self::SEVERITY_HIGH => '#e74c3c',
            self::SEVERITY_CRITICAL => '#c0392b',
        ];

        return $colors[$severity] ?? '#e67e22';
    }

    /**
     * Error action button
     */
    public static function get_error_action(string $error_type, string $error_code): array
    {
        $actions = [
            self::ERROR_TYPE_BOOKING => [
                'vehicle_not_available' => [
                    'text' => I18nHelper::__('Select Different Date'),
                    'url' => '#',
                    'class' => 'button button-primary',
                ],
                'booking_failed' => [
                    'text' => I18nHelper::__('Try Again'),
                    'url' => '#',
                    'class' => 'button button-primary',
                ],
            ],
            self::ERROR_TYPE_PAYMENT => [
                'payment_failed' => [
                    'text' => I18nHelper::__('Retry Payment'),
                    'url' => '#',
                    'class' => 'button button-primary',
                ],
            ],
        ];

        return $actions[$error_type][$error_code] ?? [
            'text' => I18nHelper::__('Try Again'),
            'url' => '#',
            'class' => 'button button-primary',
        ];
    }

    /**
     * Error notification HTML
     */
    public static function render_error_notification(string $error_type, string $error_code, array $context = []): string
    {
        $message = self::get_user_friendly_error($error_type, $error_code, $context);
        $severity = self::get_error_severity($error_type, $error_code);
        $icon = self::get_error_icon($error_type, $severity);
        $color = self::get_error_color($severity);
        $suggestions = self::get_recovery_suggestions($error_type, $error_code);
        $action = self::get_error_action($error_type, $error_code);

        $suggestions_html = '';
        if (!empty($suggestions)) {
            $suggestions_html = '<ul class="error-suggestions">';
            foreach ($suggestions as $suggestion) {
                $suggestions_html .= '<li>' . esc_html($suggestion) . '</li>';
            }
            $suggestions_html .= '</ul>';
        }

        $action_html = '';
        if (!empty($action)) {
            $action_html = sprintf(
                '<a href="%s" class="%s">%s</a>',
                esc_url($action['url']),
                esc_attr($action['class']),
                esc_html($action['text'])
            );
        }

        return sprintf(
            '<div class="mhm-error-notification" style="border-left-color: %s;">
                <div class="error-header">
                    <span class="error-icon">%s</span>
                    <span class="error-message">%s</span>
                </div>
                %s
                %s
            </div>',
            esc_attr($color),
            esc_html($icon),
            esc_html($message),
            $suggestions_html,
            $action_html
        );
    }

    /**
     * Success notification HTML
     */
    public static function render_success_notification(string $action_type, array $context = []): string
    {
        $message = self::get_success_message($action_type, $context);

        return sprintf(
            '<div class="mhm-success-notification">
                <div class="success-header">
                    <span class="success-icon">✅</span>
                    <span class="success-message">%s</span>
                </div>
            </div>',
            esc_html($message)
        );
    }

    /**
     * Warning notification HTML
     */
    public static function render_warning_notification(string $warning_type, array $context = []): string
    {
        $message = self::get_warning_message($warning_type, $context);

        return sprintf(
            '<div class="mhm-warning-notification">
                <div class="warning-header">
                    <span class="warning-icon">⚠️</span>
                    <span class="warning-message">%s</span>
                </div>
            </div>',
            esc_html($message)
        );
    }

    /**
     * Info notification HTML
     */
    public static function render_info_notification(string $info_type, array $context = []): string
    {
        $message = self::get_info_message($info_type, $context);

        return sprintf(
            '<div class="mhm-info-notification">
                <div class="info-header">
                    <span class="info-icon">ℹ️</span>
                    <span class="info-message">%s</span>
                </div>
            </div>',
            esc_html($message)
        );
    }
}
