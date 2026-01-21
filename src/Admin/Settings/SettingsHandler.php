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
        self::handle_reset_defaults();
    }

    /**
     * Handle Reset Defaults Action
     */
    private static function handle_reset_defaults(): void
    {
        // 1. Check for Reset Action and Permissions
        if (
            !isset($_GET['reset_defaults']) ||
            $_GET['reset_defaults'] !== 'true' ||
            !isset($_GET['_wpnonce']) ||
            !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'mhm_rentiva_reset_defaults')
        ) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;

        $target_tab = sanitize_key($_GET['tab'] ?? '');

        // 1. DEFINE SCOPES
        $is_template_scope = in_array($target_tab, ['email-templates', 'notification-templates', 'notification_templates'], true);
        $is_general_scope = in_array($target_tab, ['email', 'email_configuration', 'email-configuration', 'email-settings'], true);

        // 2. Fetch Defaults to Overwrite
        $defaults = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_default_settings();
        $master_option = get_option('mhm_rentiva_settings', []);
        if (!is_array($master_option)) {
            $master_option = [];
        }

        $changed = false;

        // 3. Iterate and Overwrite
        foreach ($defaults as $key => $default_value) {
            // Determine if this key belongs to "Templates" or "General"
            $is_content_key = (
                strpos($key, '_body') !== false ||
                strpos($key, '_subject') !== false ||
                strpos($key, '_content') !== false
            );

            $should_reset = false;

            if ($is_template_scope) {
                // --- SCOPE A: TEMPLATES (OVERWRITE STRATEGY) ---
                // We fetch the "Gold Standard" defaults and FORCE them into the database.
                // This overrides any hardcoded legacy defaults in the renderer files.
                if ($is_content_key) {
                    update_option($key, $default_value);
                    $changed = true;
                }
            } elseif ($is_general_scope && !$is_content_key) {
                // --- SCOPE B: GENERAL SETTINGS ---
                $should_reset = true;
                // Clean up loose legacy options for general settings
                delete_option($key);
            }

            if ($should_reset) {
                // FORCE UPDATE: Set the value in the master array to the default
                $master_option[$key] = $default_value;
                $changed = true;
            }
        }

        // 2. Safety Net for "Booking Reminder" (In case it's missing from the main array)
        if ($is_template_scope && method_exists(\MHMRentiva\Admin\Settings\Groups\EmailSettings::class, 'get_default_booking_reminder_body')) {
            update_option(
                'mhm_rentiva_booking_reminder_body',
                \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_default_booking_reminder_body()
            );
        }

        // 4. Save Changes to Master Array
        if ($changed) {
            update_option('mhm_rentiva_settings', $master_option);
        }

        // 5. Additional SQL Cleanup (For safety / legacy / ghosts)
        // We do NOT delete template options anymore, we overwrite them.
        if ($is_general_scope) {
            // Clean specific legacy keys not in defaults
            $legacy_keys = [
                'mhm_rentiva_sender_name',
                'mhm_rentiva_sender_email',
                'mhm_rentiva_base_color',
                'mhm_rentiva_header_image',
                'mhm_rentiva_footer_text',
                'mhm_rentiva_test_mode',
                'mhm_rentiva_test_email_address'
            ];
            foreach ($legacy_keys as $key) delete_option($key);
        }

        // 6. FLUSH CACHE
        wp_cache_flush();

        // 7. SMART REDIRECT
        $redirect_url = admin_url('admin.php?page=mhm-rentiva-settings');
        if (!empty($target_tab)) $redirect_url = add_query_arg('tab', $target_tab, $redirect_url);
        if (isset($_GET['view'])) $redirect_url = add_query_arg('view', sanitize_text_field($_GET['view']), $redirect_url);

        $redirect_url = add_query_arg(['settings-updated' => 'true', 'reset' => 'success'], $redirect_url);

        wp_safe_redirect($redirect_url);
        exit;
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
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), Settings::GROUP . '-options')
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
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'mhm_rentiva_rest_settings-options')
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
