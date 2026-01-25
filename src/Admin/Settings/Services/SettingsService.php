<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Services;

use MHMRentiva\Admin\REST\Settings\RESTSettings;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ SETTINGS SERVICE - Settings Business Logic Layer
 * 
 * Handles settings logic such as resetting and saving complex groups.
 * Separation of Concerns: Handler -> Service -> Repository/WP-API
 * 
 * @since 4.6.0
 */
final class SettingsService
{
    /**
     * Resets setting defaults for a specific scope/tab.
     * 
     * @param string $target_tab The settings tab/scope to reset.
     * @return bool True if anything changed.
     */
    public static function reset_defaults(string $target_tab): bool
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        if (empty($target_tab)) {
            return false;
        }

        // 1. Resolve Provider Class based on Tab Slug
        $provider_class = match ($target_tab) {
            'general'  => \MHMRentiva\Admin\Settings\Groups\GeneralSettings::class,
            'vehicle'  => \MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings::class,
            'booking'  => \MHMRentiva\Admin\Settings\Groups\BookingSettings::class,
            'customer' => \MHMRentiva\Admin\Settings\Groups\CustomerManagementSettings::class,
            'email'    => \MHMRentiva\Admin\Settings\Groups\EmailSettings::class,
            'messages' => \MHMRentiva\Admin\Messages\Settings\MessagesSettings::class,
            'frontend' => \MHMRentiva\Admin\Settings\Groups\FrontendSettings::class,
            'integration' => \MHMRentiva\Admin\REST\Settings\RESTSettings::class,
            'transfer' => \MHMRentiva\Admin\Settings\Groups\TransferSettings::class,
            'addons'   => \MHMRentiva\Admin\Settings\Groups\AddonSettings::class,
            default    => null,
        };

        $defaults = [];

        // Special handling for 'system' tab (Multiple providers: Core + Security)
        if ($target_tab === 'system') {
            if (class_exists(\MHMRentiva\Admin\Settings\Groups\CoreSettings::class)) {
                $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_default_settings());
            }
            if (class_exists(\MHMRentiva\Admin\Settings\Groups\SecuritySettings::class)) {
                $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\SecuritySettings::get_default_settings());
            }
        }
        // Standard single provider logic
        elseif ($provider_class && class_exists($provider_class) && method_exists($provider_class, 'get_default_settings')) {
            $defaults = $provider_class::get_default_settings();
        }

        // Fallback or specific logic for email templates if needed
        if (empty($defaults) && empty($provider_class) && in_array($target_tab, ['email-templates'])) {
            if (class_exists(EmailSettings::class)) {
                $defaults = EmailSettings::get_default_settings();
            }
        }

        if (empty($defaults)) {
            return false;
        }

        // 2. Handle Separate Option Storage (e.g. Messages)
        $target_option_name = 'mhm_rentiva_settings';
        if ($provider_class && defined("$provider_class::OPTION_NAME")) {
            $target_option_name = constant("$provider_class::OPTION_NAME");
        }

        if ($target_option_name !== 'mhm_rentiva_settings') {
            update_option($target_option_name, $defaults);
            return true;
        }

        $master_option = (array) get_option('mhm_rentiva_settings', []);
        $changed = false;

        // 3. DEFINE SCOPES for Email specific logic
        $is_template_scope = in_array($target_tab, ['email-templates', 'notification-templates', 'notification_templates'], true);
        $is_general_email_scope = in_array($target_tab, ['email', 'email_configuration', 'email-configuration', 'email-settings'], true);

        // 3. Iterate and Overwrite
        foreach ($defaults as $key => $default_value) {
            $is_content_key = (
                strpos($key, '_body') !== false ||
                strpos($key, '_subject') !== false ||
                strpos($key, '_content') !== false
            );

            if ($is_template_scope) {
                if ($is_content_key) {
                    update_option($key, $default_value);
                    $changed = true;
                }
            } else {
                // For regular tabs, update master option
                if (!isset($master_option[$key]) || $master_option[$key] !== $default_value) {
                    $master_option[$key] = $default_value;
                    $changed = true;
                }
            }
        }

        // 4. Commit changes to master application settings
        if ($changed && !$is_template_scope) {
            update_option('mhm_rentiva_settings', $master_option);
        }

        // 5. Cleanup legacy standalone options for Email
        if ($is_general_email_scope) {
            $legacy_keys = [
                'mhm_rentiva_sender_name',
                'mhm_rentiva_sender_email',
                'mhm_rentiva_base_color',
                'mhm_rentiva_header_image',
                'mhm_rentiva_footer_text',
                'mhm_rentiva_test_mode',
                'mhm_rentiva_test_email_address'
            ];
            foreach ($legacy_keys as $key) {
                delete_option($key);
            }
        }

        return $changed;
    }

    /**
     * Saves sanitized REST settings.
     * 
     * @param array $settings_data Raw input data from $_POST.
     * @return bool Success status.
     */
    public static function save_rest_settings(array $settings_data): bool
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $sanitized_settings = RESTSettings::sanitize_settings($settings_data);
        return update_option(RESTSettings::OPTION_NAME, $sanitized_settings);
    }
}
