<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Settings
 * 
 * Comprehensive security settings including rate limiting, IP control, and security rules
 * 
 * @since 4.0.0
 */
final class SecuritySettings
{
    /**
     * Get default settings for security
     *
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
            // IP Control
            'mhm_rentiva_ip_whitelist_enabled'              => '0',
            'mhm_rentiva_ip_whitelist'                      => '',
            'mhm_rentiva_ip_blacklist_enabled'              => '1',
            'mhm_rentiva_ip_blacklist'                      => '',
            'mhm_rentiva_country_restriction_enabled'       => '0',
            'mhm_rentiva_allowed_countries'                 => '',

            // Security Rules
            'mhm_rentiva_brute_force_protection'            => '1',
            'mhm_rentiva_max_login_attempts'                => 5,
            'mhm_rentiva_login_lockout_duration'            => 30,
            'mhm_rentiva_sql_injection_protection'          => '1',
            'mhm_rentiva_xss_protection'                    => '1',
            'mhm_rentiva_csrf_protection'                   => '1',

            // Authentication
            'mhm_rentiva_strong_passwords'                  => '1',
            'mhm_rentiva_password_expiry_days'              => 0,
            'mhm_rentiva_two_factor_auth'                   => '0',
            'mhm_rentiva_session_security'                  => '1',
        ];
    }

    /**
     * Register settings
     */
    public static function register(): void
    {
        self::register_settings();
    }

    /**
     * Register all security settings
     */
    public static function register_settings(): void
    {
        // IP Control Section
        add_settings_section(
            'mhm_rentiva_ip_control_section',
            __('IP Control & Access', 'mhm-rentiva'),
            [self::class, 'render_ip_control_section_description'],
            'mhm_rentiva_settings'
        );

        // Security Rules Section
        add_settings_section(
            'mhm_rentiva_security_rules_section',
            __('Security Rules', 'mhm-rentiva'),
            [self::class, 'render_security_rules_section_description'],
            'mhm_rentiva_settings'
        );

        // Authentication Section
        add_settings_section(
            'mhm_rentiva_authentication_section',
            __('Authentication Security', 'mhm-rentiva'),
            [self::class, 'render_authentication_section_description'],
            'mhm_rentiva_settings'
        );

        // Register setting
        // Note: Main mhm_rentiva_settings is registered in SettingsCore::init() with proper sanitize_callback

        // IP Control Fields
        add_settings_field(
            'mhm_rentiva_ip_whitelist_enabled',
            __('Enable IP Whitelist', 'mhm-rentiva'),
            [self::class, 'render_ip_whitelist_enabled_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_ip_control_section'
        );

        add_settings_field(
            'mhm_rentiva_ip_whitelist',
            __('Whitelisted IPs', 'mhm-rentiva'),
            [self::class, 'render_ip_whitelist_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_ip_control_section'
        );

        add_settings_field(
            'mhm_rentiva_ip_blacklist_enabled',
            __('Enable IP Blacklist', 'mhm-rentiva'),
            [self::class, 'render_ip_blacklist_enabled_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_ip_control_section'
        );

        add_settings_field(
            'mhm_rentiva_ip_blacklist',
            __('Blacklisted IPs', 'mhm-rentiva'),
            [self::class, 'render_ip_blacklist_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_ip_control_section'
        );

        add_settings_field(
            'mhm_rentiva_country_restriction_enabled',
            __('Enable Country Restriction', 'mhm-rentiva'),
            [self::class, 'render_country_restriction_enabled_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_ip_control_section'
        );

        add_settings_field(
            'mhm_rentiva_allowed_countries',
            __('Allowed Countries', 'mhm-rentiva'),
            [self::class, 'render_allowed_countries_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_ip_control_section'
        );

        // Security Rules Fields
        add_settings_field(
            'mhm_rentiva_brute_force_protection',
            __('Brute Force Protection', 'mhm-rentiva'),
            [self::class, 'render_brute_force_protection_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_security_rules_section'
        );

        add_settings_field(
            'mhm_rentiva_max_login_attempts',
            __('Max Login Attempts', 'mhm-rentiva'),
            [self::class, 'render_max_login_attempts_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_security_rules_section'
        );

        add_settings_field(
            'mhm_rentiva_login_lockout_duration',
            __('Login Lockout Duration (minutes)', 'mhm-rentiva'),
            [self::class, 'render_login_lockout_duration_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_security_rules_section'
        );

        add_settings_field(
            'mhm_rentiva_sql_injection_protection',
            __('SQL Injection Protection', 'mhm-rentiva'),
            [self::class, 'render_sql_injection_protection_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_security_rules_section'
        );

        add_settings_field(
            'mhm_rentiva_xss_protection',
            __('XSS Protection', 'mhm-rentiva'),
            [self::class, 'render_xss_protection_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_security_rules_section'
        );

        add_settings_field(
            'mhm_rentiva_csrf_protection',
            __('CSRF Protection', 'mhm-rentiva'),
            [self::class, 'render_csrf_protection_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_security_rules_section'
        );

        // Authentication Fields
        add_settings_field(
            'mhm_rentiva_strong_passwords',
            __('Require Strong Passwords', 'mhm-rentiva'),
            [self::class, 'render_strong_passwords_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_authentication_section'
        );

        add_settings_field(
            'mhm_rentiva_password_expiry_days',
            __('Password Expiry (days)', 'mhm-rentiva'),
            [self::class, 'render_password_expiry_days_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_authentication_section'
        );

        add_settings_field(
            'mhm_rentiva_two_factor_auth',
            __('Two-Factor Authentication', 'mhm-rentiva'),
            [self::class, 'render_two_factor_auth_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_authentication_section'
        );

        add_settings_field(
            'mhm_rentiva_session_security',
            __('Enhanced Session Security', 'mhm-rentiva'),
            [self::class, 'render_session_security_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_authentication_section'
        );
    }

    /**
     * IP control section description
     */
    public static function render_ip_control_section_description(): void
    {
        echo '<p>' . esc_html__('Configure IP whitelist, blacklist, and country restrictions.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Security rules section description
     */
    public static function render_security_rules_section_description(): void
    {
        echo '<p>' . esc_html__('Configure security rules and protection mechanisms.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Authentication section description
     */
    public static function render_authentication_section_description(): void
    {
        echo '<p>' . esc_html__('Configure authentication security and password policies.', 'mhm-rentiva') . '</p>';
    }

    /**
     * IP whitelist enabled field
     */
    public static function render_ip_whitelist_enabled_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_ip_whitelist_enabled', '0');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_ip_whitelist_enabled]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Only allow access from whitelisted IPs', 'mhm-rentiva') . '</p>';
    }

    /**
     * IP whitelist field
     */
    public static function render_ip_whitelist_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_ip_whitelist', '');
        echo '<textarea name="mhm_rentiva_settings[mhm_rentiva_ip_whitelist]" rows="5" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('One IP address per line. Supports CIDR notation (e.g., 192.168.1.0/24)', 'mhm-rentiva') . '</p>';
    }

    /**
     * IP blacklist enabled field
     */
    public static function render_ip_blacklist_enabled_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_ip_blacklist_enabled', '1');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_ip_blacklist_enabled]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Block access from blacklisted IPs', 'mhm-rentiva') . '</p>';
    }

    /**
     * IP blacklist field
     */
    public static function render_ip_blacklist_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_ip_blacklist', '');
        echo '<textarea name="mhm_rentiva_settings[mhm_rentiva_ip_blacklist]" rows="5" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('One IP address per line. Supports CIDR notation', 'mhm-rentiva') . '</p>';
    }

    /**
     * Country restriction enabled field
     */
    public static function render_country_restriction_enabled_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_country_restriction_enabled', '0');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_country_restriction_enabled]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Restrict access by country', 'mhm-rentiva') . '</p>';
    }

    /**
     * Allowed countries field
     */
    public static function render_allowed_countries_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_allowed_countries', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_allowed_countries]" value="' . esc_attr($value) . '" class="large-text" placeholder="US,CA,GB,DE,FR" />';
        echo '<p class="description">' . esc_html__('Comma-separated list of allowed country codes (e.g., US,CA,GB)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Brute force protection field
     */
    public static function render_brute_force_protection_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_brute_force_protection', '1');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_brute_force_protection]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Protect against brute force login attempts', 'mhm-rentiva') . '</p>';
    }

    /**
     * Max login attempts field
     */
    public static function render_max_login_attempts_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_max_login_attempts', 5);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_max_login_attempts]" value="' . esc_attr($value) . '" min="3" max="20" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Maximum login attempts before lockout', 'mhm-rentiva') . '</p>';
    }

    /**
     * Login lockout duration field
     */
    public static function render_login_lockout_duration_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_login_lockout_duration', 30);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_login_lockout_duration]" value="' . esc_attr($value) . '" min="5" max="1440" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Lockout duration after max attempts (minutes)', 'mhm-rentiva') . '</p>';
    }

    /**
     * SQL injection protection field
     */
    public static function render_sql_injection_protection_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_sql_injection_protection', '1');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_sql_injection_protection]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Protect against SQL injection attacks', 'mhm-rentiva') . '</p>';
    }

    /**
     * XSS protection field
     */
    public static function render_xss_protection_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_xss_protection', '1');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_xss_protection]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Protect against XSS attacks', 'mhm-rentiva') . '</p>';
    }

    /**
     * CSRF protection field
     */
    public static function render_csrf_protection_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_csrf_protection', '1');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_csrf_protection]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Protect against CSRF attacks', 'mhm-rentiva') . '</p>';
    }

    /**
     * Strong passwords field
     */
    public static function render_strong_passwords_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_strong_passwords', '1');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_strong_passwords]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Require strong passwords for all accounts', 'mhm-rentiva') . '</p>';
    }

    /**
     * Password expiry days field
     */
    public static function render_password_expiry_days_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_password_expiry_days', 0);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_password_expiry_days]" value="' . esc_attr($value) . '" min="0" max="365" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Password expiry in days (0 = never expire)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Two-factor auth field
     */
    public static function render_two_factor_auth_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_two_factor_auth', '0');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_two_factor_auth]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Enable two-factor authentication for admin accounts', 'mhm-rentiva') . '</p>';
    }

    /**
     * Session security field
     */
    public static function render_session_security_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_session_security', '1');
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_session_security]" value="1" ' . checked($value, '1', false) . ' />';
        echo '<p class="description">' . esc_html__('Enhanced session security and validation', 'mhm-rentiva') . '</p>';
    }
}
