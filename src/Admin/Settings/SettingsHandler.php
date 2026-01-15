<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\REST\Settings\RESTSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Handler Class
 * 
 * Handles settings form submissions and action processing.
 * Separates logic from the view.
 * 
 * @since 4.0.0
 */
final class SettingsHandler
{
    /**
     * Handle settings page actions
     * 
     * This method should be called before rendering the settings page.
     */
    public static function handle(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        self::handle_email_templates();
        self::handle_rest_settings();
    }

    /**
     * Handle Email Templates Save Action
     */
    private static function handle_email_templates(): void
    {
        if (
            isset($_POST['email_templates_action']) &&
            sanitize_key($_POST['email_templates_action']) === 'save' &&
            isset($_POST['_wpnonce']) &&
            wp_verify_nonce($_POST['_wpnonce'], Settings::GROUP . '-options')
        ) {
            EmailTemplates::handle_save_templates();
            add_settings_error(
                'mhm_rentiva_messages',
                'email_templates_saved',
                __('Email templates saved successfully!', 'mhm-rentiva'),
                'success'
            );
        }
    }

    /**
     * Handle REST Settings Save Action
     */
    private static function handle_rest_settings(): void
    {
        if (
            isset($_POST['option_page']) &&
            $_POST['option_page'] === 'mhm_rentiva_rest_settings' &&
            isset($_POST['action']) &&
            $_POST['action'] === 'update' &&
            isset($_POST['_wpnonce']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'mhm_rentiva_rest_settings-options')
        ) {
            if (isset($_POST['mhm_rentiva_rest_settings']) && is_array($_POST['mhm_rentiva_rest_settings'])) {
                $rest_settings = RESTSettings::sanitize_settings($_POST['mhm_rentiva_rest_settings']);
                update_option('mhm_rentiva_rest_settings', $rest_settings);

                add_settings_error(
                    'mhm_rentiva_messages',
                    'rest_settings_saved',
                    __('REST API Settings saved successfully!', 'mhm-rentiva'),
                    'success'
                );
            }
        }
    }
}
