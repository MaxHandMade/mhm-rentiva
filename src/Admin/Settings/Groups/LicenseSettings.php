<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Licensing\LicenseManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License Settings
 * Manages license activation, validation and status display.
 */
final class LicenseSettings
{
    public const SECTION_LICENSE = 'mhm_rentiva_license_section';

    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    /**
     * Register settings
     */
    public static function register(): void
    {
        $page_slug = SettingsCore::PAGE;

        // License Section
        add_settings_section(
            self::SECTION_LICENSE,
            __('License Management', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $page_slug
        );

        // License Key Field
        add_settings_field(
            'mhm_rentiva_license_key',
            __('License Key', 'mhm-rentiva'),
            [self::class, 'render_license_key_field'],
            $page_slug,
            self::SECTION_LICENSE
        );

        // Register setting for schema purposes (though handled manually)
        register_setting($page_slug, 'mhm_rentiva_license_key', [
            'sanitize_callback' => [self::class, 'sanitize_text_field_safe'],
            'type' => 'string',
            'default' => '',
            'description' => __('License key for Pro features', 'mhm-rentiva')
        ]);
    }

    /**
     * Section Description & Status
     */
    public static function render_section_description(): void
    {
        echo '<p>' . esc_html__('Enter your license key to enable Pro features (online payments, unlimited vehicles, export, advanced reports).', 'mhm-rentiva') . '</p>';

        if (class_exists('\MHMRentiva\Admin\Licensing\LicenseManager')) {
            $lm = LicenseManager::instance();
            $data = $lm->get();

            // Sanitize license data
            $data = is_array($data) ? $data : [];
            $expires_at = isset($data['expires_at']) ? absint($data['expires_at']) : 0;

            $status = $lm->isActive() ? __('Active', 'mhm-rentiva') : __('Inactive (Lite mode)', 'mhm-rentiva');
            $status_color = $lm->isActive() ? 'green' : '#666';
            $expires = $expires_at > 0 ? date_i18n(get_option('date_format'), $expires_at) : '—';

            echo '<div class="notice notice-info inline" style="margin: 10px 0 20px 0; padding: 10px;">';
            echo '<p><strong>' . esc_html__('Status:', 'mhm-rentiva') . '</strong> <span style="color:' . esc_attr($status_color) . '; font-weight:bold;">' . esc_html($status) . '</span> &nbsp; | &nbsp; ';
            echo '<strong>' . esc_html__('Expires:', 'mhm-rentiva') . '</strong> ' . esc_html($expires) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Render License Key Field
     */
    public static function render_license_key_field(): void
    {
        $key = '';
        if (class_exists('\MHMRentiva\Admin\Licensing\LicenseManager')) {
            $lm = LicenseManager::instance();
            $key = self::sanitize_text_field_safe($lm->getKey());
        }

        // Input Name is custom to avoid auto-save by options.php, forcing manual button action
        echo '<input type="text" class="regular-text" name="mhm_license_key" value="' . esc_attr($key) . '" placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="50" style="width: 350px;" />';
        echo '<p class="description">' . esc_html__('Paste your license key and click the Activate button.', 'mhm-rentiva') . '</p>';

        echo '<div style="margin-top: 15px;">';
        echo '<button class="button button-primary" name="mhm_license_action" value="activate" type="submit">' . esc_html__('Activate License', 'mhm-rentiva') . '</button> ';

        if ($key !== '') {
            echo '<button class="button" name="mhm_license_action" value="validate" type="submit">' . esc_html__('Check Status', 'mhm-rentiva') . '</button> ';
            echo '<button class="button button-link-delete" name="mhm_license_action" value="deactivate" type="submit" onclick="return confirm(\'' . esc_js(__('Are you sure you want to deactivate the license on this site?', 'mhm-rentiva')) . '\')" style="float:none; color: #a00;">' . esc_html__('Deactivate', 'mhm-rentiva') . '</button>';
        }

        wp_nonce_field('mhm_rentiva_license_action', 'mhm_license_nonce');
        echo '</div>';
    }

    /**
     * Handle license form processing
     * This method should be hooked to admin_init or admin_post
     */
    public static function handle_license_action(): void
    {
        // Check if this is a license action
        if (!isset($_POST['mhm_license_action']) || !isset($_POST['mhm_license_nonce'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce(self::sanitize_text_field_safe(wp_unslash($_POST['mhm_license_nonce'])), 'mhm_rentiva_license_action')) {
            wp_die(esc_html__('Security check failed. Please try again.', 'mhm-rentiva'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'mhm-rentiva'));
        }

        if (!class_exists('\MHMRentiva\Admin\Licensing\LicenseManager')) {
            return;
        }

        $action = self::sanitize_text_field_safe(wp_unslash($_POST['mhm_license_action']));
        $lm = LicenseManager::instance();
        $result = null;

        switch ($action) {
            case 'activate':
                $key = self::sanitize_text_field_safe(wp_unslash($_POST['mhm_license_key'] ?? ''));
                if (empty($key)) {
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a license key.', 'mhm-rentiva') . '</p></div>';
                    });
                    return;
                }
                $result = $lm->activate($key);
                break;

            case 'validate':
                $result = $lm->validate();
                break;

            case 'deactivate':
                $result = $lm->deactivate();
                break;
        }

        // Handle result
        if (is_wp_error($result)) {
            add_action('admin_notices', function () use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            });
        } elseif ($result !== null) {
            add_action('admin_notices', function () use ($action) {
                $message = '';
                switch ($action) {
                    case 'activate':
                        $message = __('License activated successfully.', 'mhm-rentiva');
                        break;
                    case 'validate':
                        $message = __('License validation completed.', 'mhm-rentiva');
                        break;
                    case 'deactivate':
                        $message = __('License deactivated successfully.', 'mhm-rentiva');
                        break;
                }
                if ($message) {
                    echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
                }
            });
        }
    }

    /**
     * Get the license key with proper sanitization
     */
    public static function get_license_key(): string
    {
        if (!class_exists('\MHMRentiva\Admin\Licensing\LicenseManager')) {
            return '';
        }
        $lm = LicenseManager::instance();
        return self::sanitize_text_field_safe($lm->getKey());
    }

    /**
     * Check if license is active
     */
    public static function is_license_active(): bool
    {
        if (!class_exists('\MHMRentiva\Admin\Licensing\LicenseManager')) {
            return false;
        }
        $lm = LicenseManager::instance();
        return $lm->isActive();
    }

    /**
     * Get license data with proper sanitization
     */
    public static function get_license_data(): array
    {
        if (!class_exists('\MHMRentiva\Admin\Licensing\LicenseManager')) {
            return [];
        }
        $lm = LicenseManager::instance();
        $data = $lm->get();

        if (!is_array($data)) {
            return [];
        }

        // Sanitize license data
        $sanitized_data = [];
        if (isset($data['expires_at'])) {
            $sanitized_data['expires_at'] = absint($data['expires_at']);
        }
        if (isset($data['status'])) {
            $sanitized_data['status'] = self::sanitize_text_field_safe($data['status']);
        }
        if (isset($data['key'])) {
            $sanitized_data['key'] = self::sanitize_text_field_safe($data['key']);
        }

        return $sanitized_data;
    }
}
