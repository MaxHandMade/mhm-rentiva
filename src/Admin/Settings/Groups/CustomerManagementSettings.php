<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customer Management Settings
 * 
 * Only includes actively used customer settings
 * 
 * @since 4.0.0
 */
final class CustomerManagementSettings
{
    /**
     * Register settings
     */
    public static function register(): void
    {
        self::register_settings();
    }

    /**
     * Register all customer management settings
     */
    public static function register_settings(): void
    {
        // Customer Registration Section
        add_settings_section(
            'mhm_rentiva_customer_registration_section',
            __('Customer Registration Settings', 'mhm-rentiva'),
            [self::class, 'render_registration_section_description'],
            'mhm_rentiva_settings'
        );

        // Customer Account Section
        add_settings_section(
            'mhm_rentiva_customer_account_section',
            __('Customer Account Settings', 'mhm-rentiva'),
            [self::class, 'render_account_section_description'],
            'mhm_rentiva_settings'
        );

        // Customer Communication Section
        add_settings_section(
            'mhm_rentiva_customer_communication_section',
            __('Customer Communication Settings', 'mhm-rentiva'),
            [self::class, 'render_communication_section_description'],
            'mhm_rentiva_settings'
        );

        // Customer Security Section
        add_settings_section(
            'mhm_rentiva_customer_security_section',
            __('Customer Security Settings', 'mhm-rentiva'),
            [self::class, 'render_security_section_description'],
            'mhm_rentiva_settings'
        );

        // Customer Privacy Section
        add_settings_section(
            'mhm_rentiva_customer_privacy_section',
            __('Customer Privacy Settings', 'mhm-rentiva'),
            [self::class, 'render_privacy_section_description'],
            'mhm_rentiva_settings'
        );

        // Customer Experience Section
        add_settings_section(
            'mhm_rentiva_customer_experience_section',
            __('Customer Experience Settings', 'mhm-rentiva'),
            [self::class, 'render_experience_section_description'],
            'mhm_rentiva_settings'
        );

        // Registration Fields
        add_settings_field(
            'mhm_rentiva_customer_registration_enabled',
            __('Enable Customer Registration', 'mhm-rentiva'),
            [self::class, 'render_registration_enabled_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_registration_section'
        );

        add_settings_field(
            'mhm_rentiva_customer_email_verification',
            __('Require Email Verification', 'mhm-rentiva'),
            [self::class, 'render_email_verification_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_registration_section'
        );

        add_settings_field(
            'mhm_rentiva_customer_phone_required',
            __('Phone Number Required', 'mhm-rentiva'),
            [self::class, 'render_phone_required_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_registration_section'
        );

        add_settings_field(
            'mhm_rentiva_customer_terms_required',
            __('Terms & Conditions Required', 'mhm-rentiva'),
            [self::class, 'render_terms_required_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_registration_section'
        );

        add_settings_field(
            'mhm_rentiva_customer_terms_text',
            __('Terms & Conditions Text', 'mhm-rentiva'),
            [self::class, 'render_terms_text_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_registration_section'
        );

        // Account Fields
        add_settings_field(
            'mhm_rentiva_customer_auto_login',
            __('Auto Login After Registration', 'mhm-rentiva'),
            [self::class, 'render_auto_login_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_account_section'
        );

        // Communication Fields
        add_settings_field(
            'mhm_rentiva_customer_welcome_email',
            __('Send Welcome Email', 'mhm-rentiva'),
            [self::class, 'render_welcome_email_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_communication_section'
        );

        add_settings_field(
            'mhm_rentiva_customer_booking_notifications',
            __('Send Booking Notifications', 'mhm-rentiva'),
            [self::class, 'render_booking_notifications_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_communication_section'
        );

        // REMOVED: customer_marketing_emails (no marketing system)

        // Security Fields
        add_settings_field(
            'mhm_rentiva_customer_password_min_length',
            __('Minimum Password Length', 'mhm-rentiva'),
            [self::class, 'render_password_min_length_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_security_section'
        );

        add_settings_field(
            'mhm_rentiva_customer_password_require_special',
            __('Require Special Characters', 'mhm-rentiva'),
            [self::class, 'render_password_require_special_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_security_section'
        );

        // Privacy Fields
        add_settings_field(
            'mhm_rentiva_customer_gdpr_compliance',
            __('Enable GDPR Compliance', 'mhm-rentiva'),
            [self::class, 'render_gdpr_compliance_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_privacy_section'
        );

        // REMOVED: customer_data_retention_days (no GDPR cleanup job)
        // REMOVED: customer_consent_required (duplicate of terms_required)

        // Experience Fields
        add_settings_field(
            'mhm_rentiva_customer_default_role',
            __('Default Customer Role', 'mhm-rentiva'),
            [self::class, 'render_default_role_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_experience_section'
        );

        add_settings_field(
            'mhm_rentiva_customer_notification_frequency',
            __('Notification Frequency', 'mhm-rentiva'),
            [self::class, 'render_notification_frequency_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_customer_experience_section'
        );
    }

    /**
     * Section Descriptions
     */
    public static function render_registration_section_description(): void
    {
        echo '<p>' . esc_html__('Configure customer registration requirements and validation rules.', 'mhm-rentiva') . '</p>';
    }

    public static function render_account_section_description(): void
    {
        echo '<p>' . esc_html__('Configure customer account management settings.', 'mhm-rentiva') . '</p>';
    }

    public static function render_communication_section_description(): void
    {
        echo '<p>' . esc_html__('Configure customer email notification settings.', 'mhm-rentiva') . '</p>';
    }

    public static function render_security_section_description(): void
    {
        echo '<p>' . esc_html__('Configure password and account security requirements.', 'mhm-rentiva') . '</p>';
    }

    public static function render_privacy_section_description(): void
    {
        echo '<p>' . esc_html__('Configure GDPR compliance and data retention settings.', 'mhm-rentiva') . '</p>';
    }

    public static function render_experience_section_description(): void
    {
        echo '<p>' . esc_html__('Configure customer experience and notification preferences.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Registration Fields
     */
    public static function render_registration_enabled_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_registration_enabled');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_customer_registration_enabled]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Allow new customers to register accounts', 'mhm-rentiva') . '</p>';
    }

    public static function render_email_verification_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_email_verification');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_customer_email_verification]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Require email verification for new accounts', 'mhm-rentiva') . '</p>';
    }

    public static function render_phone_required_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_phone_required');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_customer_phone_required]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Require phone number during registration', 'mhm-rentiva') . '</p>';
    }

    public static function render_terms_required_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_terms_required');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_customer_terms_required]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Require acceptance of terms and conditions', 'mhm-rentiva') . '</p>';
    }

    public static function render_terms_text_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_terms_text');
        echo '<textarea name="mhm_rentiva_settings[mhm_rentiva_customer_terms_text]" rows="3" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Custom text for terms and conditions checkbox. Use {privacy_policy} for privacy policy link.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Account Fields
     */
    public static function render_auto_login_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_auto_login');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_customer_auto_login]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Automatically log in customers after registration', 'mhm-rentiva') . '</p>';
    }

    /**
     * Communication Fields
     */
    public static function render_welcome_email_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_welcome_email');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_customer_welcome_email]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Send welcome email to new customers', 'mhm-rentiva') . '</p>';
    }

    public static function render_booking_notifications_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_booking_notifications');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_customer_booking_notifications]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Send email notifications for booking updates', 'mhm-rentiva') . '</p>';
    }

    /**
     * Security Fields
     */
    public static function render_password_min_length_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_password_min_length');
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_customer_password_min_length]" value="' . esc_attr($value) . '" min="6" max="32" step="1" class="small-text" />';
        echo '<p class="description">' . esc_html__('Minimum password length for customer accounts', 'mhm-rentiva') . '</p>';
    }

    public static function render_password_require_special_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_password_require_special');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_customer_password_require_special]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Require special characters in customer passwords', 'mhm-rentiva') . '</p>';
    }

    /**
     * Privacy Fields
     */
    public static function render_gdpr_compliance_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_gdpr_compliance');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_customer_gdpr_compliance]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Enable GDPR compliance features for customer data', 'mhm-rentiva') . '</p>';
    }

    /**
     * Experience Fields
     */
    public static function render_default_role_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_default_role');
        $roles = [
            'customer' => __('Customer', 'mhm-rentiva'),
            'subscriber' => __('Subscriber', 'mhm-rentiva'),
            'contributor' => __('Contributor', 'mhm-rentiva'),
        ];
        
        echo '<select name="mhm_rentiva_settings[mhm_rentiva_customer_default_role]">';
        foreach ($roles as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Default role assigned to new customers', 'mhm-rentiva') . '</p>';
    }

    public static function render_notification_frequency_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_notification_frequency');
        $frequencies = [
            'immediate' => __('Immediate', 'mhm-rentiva'),
            'daily' => __('Daily Digest', 'mhm-rentiva'),
            'weekly' => __('Weekly Digest', 'mhm-rentiva'),
            'disabled' => __('Disabled', 'mhm-rentiva'),
        ];
        
        echo '<select name="mhm_rentiva_settings[mhm_rentiva_customer_notification_frequency]">';
        foreach ($frequencies as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Default notification frequency for customers', 'mhm-rentiva') . '</p>';
    }
}
