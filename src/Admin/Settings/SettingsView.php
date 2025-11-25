<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings View Class
 * 
 * Handles the rendering of the settings page.
 */
final class SettingsView
{
    /**
     * Render the main settings page
     */
    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Pro feature notices and Developer Mode banner
        if (class_exists('\MHMRentiva\Admin\Core\ProFeatureNotice')) {
            \MHMRentiva\Admin\Core\ProFeatureNotice::displayPageProNotice('settings');
        }

        // ✅ WordPress Settings API standard messages
        if (isset($_GET['settings-updated']) && Settings::sanitize_text_field_safe(wp_unslash($_GET['settings-updated'] ?? '')) === 'true') {
            echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>' .
                 esc_html__('✅ Settings saved successfully!', 'mhm-rentiva') .
                 '</strong></p></div>';
        }

        // ✅ Special handling for email templates (separate form)
        if (isset($_POST['email_templates_action']) && Settings::sanitize_text_field_safe(wp_unslash($_POST['email_templates_action'] ?? '')) === 'save' && wp_verify_nonce($_POST['_wpnonce'], Settings::GROUP . '-options')) {
            \MHMRentiva\Admin\Emails\Core\EmailTemplates::handle_save_templates();
            echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>' .
                 esc_html__('✅ Email templates saved successfully!', 'mhm-rentiva') .
                 '</strong></p></div>';
        }
        
        // ✅ Special handling for REST Settings (separate option, separate form)
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'mhm_rentiva_rest_settings' && isset($_POST['action']) && $_POST['action'] === 'update' && wp_verify_nonce($_POST['_wpnonce'], 'mhm_rentiva_rest_settings-options')) {
            // REST Settings are handled by WordPress Settings API automatically
            // The sanitize_callback in RESTSettings::init() will handle the sanitization
            if (isset($_POST['mhm_rentiva_rest_settings']) && is_array($_POST['mhm_rentiva_rest_settings'])) {
                $rest_settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::sanitize_settings($_POST['mhm_rentiva_rest_settings']);
                update_option('mhm_rentiva_rest_settings', $rest_settings);
            }
            echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>' .
                 esc_html__('✅ REST API Settings saved successfully!', 'mhm-rentiva') .
                 '</strong></p></div>';
        }
        
        // Tab control
        $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        
        // Reorganized tab list
        $settings_tabs = [
            'general' => __('General Settings', 'mhm-rentiva'),
            'vehicle' => __('Vehicle Management', 'mhm-rentiva'),
            'booking' => __('Booking Management', 'mhm-rentiva'),
            'customer' => __('Customer Management', 'mhm-rentiva'),
            'payment' => __('Payment Settings', 'mhm-rentiva'),
            'email' => __('Email & Notifications', 'mhm-rentiva'),
            'messages' => __('Messages Settings', 'mhm-rentiva'),
            'system' => __('System & Performance', 'mhm-rentiva'),
            'frontend' => __('Frontend & Display', 'mhm-rentiva'),
            'integration' => __('Integration Settings', 'mhm-rentiva'),
            'database-cleanup' => __('Database Cleanup', 'mhm-rentiva'),
            'cron-monitor' => __('Cron Job Monitor', 'mhm-rentiva'),
            'testing' => __('Settings Testing', 'mhm-rentiva'),
        ];

        ?>
        
        <div class="wrap mhm-settings-page">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="mhm-settings-layout">
                <!-- Left Menu -->
                <div class="mhm-settings-sidebar">
                    <nav class="mhm-settings-nav">
                        <?php foreach ($settings_tabs as $tab_key => $tab_label): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=' . $tab_key)); ?>" 
                               class="mhm-settings-nav-item <?php echo $current_tab === $tab_key ? 'active' : ''; ?>">
                                <?php echo esc_html($tab_label); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Main Content -->
                <div class="mhm-settings-content">
                <?php if ($current_tab === 'email-templates'): ?>
                    <div class="mhm-settings-form-container">
                        <form method="post" action="" class="mhm-settings-form" id="mhm-email-templates-form">
                            <?php
                            // Special form handling for email templates
                            wp_nonce_field(Settings::GROUP . '-options');
                            echo '<input type="hidden" name="action" value="update" />';
                            echo '<input type="hidden" name="option_page" value="' . esc_attr(Settings::GROUP) . '" />';
                            echo '<input type="hidden" name="current_tab" value="' . esc_attr(sanitize_key($_GET['type'] ?? 'booking_notifications')) . '" />';
                            echo '<input type="hidden" name="email_templates_action" value="save" />';
                            
                            // Render email templates content
                            self::render_email_templates_tab();
                            ?>
                            
                            <div class="submit-section">
                                <?php submit_button(__('Save Changes', 'mhm-rentiva'), 'primary', 'submit', false); ?>
                            </div>
                        </form>
                    </div>
                <?php elseif ($current_tab === 'integration'): ?>
                    <!-- Integration Settings: REST API Settings uses separate form -->
                    <div class="mhm-settings-form-container">
                        <form method="post" action="" class="mhm-settings-form" id="mhm-rest-settings-form">
                            <?php
                            // WordPress Settings API for REST Settings
                            settings_fields('mhm_rentiva_rest_settings');
                            wp_nonce_field('mhm_rentiva_rest_settings-options');
                            echo '<input type="hidden" name="action" value="update" />';
                            echo '<input type="hidden" name="option_page" value="mhm_rentiva_rest_settings" />';
                            
                            // Render Integration Settings (includes REST Settings)
                            self::render_integration_settings();
                            ?>
                            
                            <div class="submit-section" style="margin-top: 20px;">
                                <?php submit_button(__('Save REST API Settings', 'mhm-rentiva'), 'primary', 'submit', false); ?>
                            </div>
                        </form>
                    </div>
                <?php elseif ($current_tab === 'messages'): ?>
                    <!-- Messages Settings: Uses separate form with its own option group -->
                    <div class="mhm-settings-form-container">
                        <?php self::render_messages_settings(); ?>
                    </div>
                <?php elseif (in_array($current_tab, ['testing', 'database-cleanup', 'cron-monitor'], true)): ?>
                    <!-- Testing, Database Cleanup, and Cron Monitor tabs: No form needed, just controls -->
                    <div class="mhm-settings-utility-container">
                        <?php
                        switch ($current_tab) {
                            case 'testing':
                                self::render_testing_page();
                                break;
                            case 'database-cleanup':
                                self::render_database_cleanup_page();
                                break;
                            case 'cron-monitor':
                                self::render_cron_monitor_page();
                                break;
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <div class="mhm-settings-form-container">
                        <!-- Single form for all settings -->
                            <form method="post" action="options.php" class="mhm-settings-form" id="mhm-settings-main-form">
                                <?php
                                // Capture entire form content with output buffering to clean nested forms
                                ob_start();
                                
                                // WordPress Settings API handles nonce and hidden fields
                                settings_fields('mhm_rentiva_settings');
                                
                                // Render the relevant section according to tab
                                switch ($current_tab) {
                                    case 'general':
                                        self::render_general_settings();
                                        break;
                                    case 'vehicle':
                                        self::render_vehicle_management_settings();
                                        break;
                                    case 'booking':
                                        self::render_booking_settings();
                                        break;
                                    case 'customer':
                                        self::render_customer_management_settings();
                                        break;
                                    case 'email':
                                        self::render_tab_reset_button('email');
                                        self::render_section_clean('mhm_rentiva_email_section');
                                        break;
                                    case 'payment':
                                        self::render_payment_settings();
                                        break;
                                    case 'system':
                                        self::render_tab_reset_button('system');
                                        self::render_section_clean('mhm_rentiva_core_section');
                                        self::render_section_clean('mhm_rentiva_ip_control_section');
                                        self::render_section_clean('mhm_rentiva_security_rules_section');
                                        self::render_section_clean('mhm_rentiva_authentication_section');
                                        self::render_section_clean('mhm_rentiva_maintenance_section');
                                        self::render_section_clean('mhm_rentiva_logs_section');
                                        self::render_section_clean('mhm_rentiva_reconcile_section');
                                        break;
                                    case 'frontend':
                                        self::render_tab_reset_button('frontend');
                                        self::render_section_clean('mhm_rentiva_frontend_section');
                                        self::render_section_clean('mhm_rentiva_button_texts_section');
                                        self::render_section_clean('mhm_rentiva_action_texts_section');
                                        self::render_section_clean('mhm_rentiva_form_labels_section');
                                        self::render_section_clean('mhm_rentiva_message_texts_section');
                                        self::render_section_clean('mhm_rentiva_comments_section');
                                        break;
                                    case 'database-cleanup':
                                        self::render_database_cleanup_page();
                                        break;
                                    case 'testing':
                                        self::render_testing_page();
                                        break;
                                    default:
                                        // Default case for unknown tabs
                                        echo '<p>' . esc_html__('Settings for this section are not yet implemented.', 'mhm-rentiva') . '</p>';
                                        break;
                                }
                                
                                submit_button();
                                
                                $form_content = ob_get_clean();
                                
                                // Aggressively remove any nested form elements
                                // Remove complete form tags with content (non-greedy to avoid removing outer form)
                                $form_content = preg_replace('/<form[^>]*>.*?<\/form>/is', '', $form_content);
                                // Remove standalone opening form tags
                                $form_content = preg_replace('/<form[^>]*>/i', '', $form_content);
                                // Remove standalone closing form tags
                                $form_content = preg_replace('/<\/form>/i', '', $form_content);
                                // Remove form attributes from other elements that might cause DOM warnings
                                $form_content = preg_replace('/\s+form\s*=\s*["\'][^"\']*["\']/i', '', $form_content);
                                
                                echo $form_content;
                                ?>
                            </form>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Clean section rendering - prevent nested forms
     */
    public static function render_section_clean(string $section_name): void
    {
        global $wp_settings_sections, $wp_settings_fields;
        
        if (!isset($wp_settings_sections[Settings::PAGE][$section_name])) {
            return;
        }
        
        $section = $wp_settings_sections[Settings::PAGE][$section_name];
        
        if ($section['title']) {
            echo '<h2>' . esc_html($section['title']) . '</h2>' . "\n";
        }
        
        if ($section['callback']) {
            // Capture section callback output and clean any form elements
            ob_start();
            call_user_func($section['callback'], $section);
            $section_output = ob_get_clean();
            
            // Aggressively remove any form elements from section callback
            $section_output = preg_replace('/<form[^>]*>.*?<\/form>/is', '', $section_output);
            $section_output = preg_replace('/<form[^>]*>/i', '', $section_output);
            $section_output = preg_replace('/<\/form>/i', '', $section_output);
            $section_output = preg_replace('/\s+form\s*=\s*["\'][^"\']*["\']/i', '', $section_output);
            // Remove any form-like attributes
            $section_output = preg_replace('/\s+role\s*=\s*["\']form["\']/i', '', $section_output);
            
            echo $section_output;
        }
        
        if (!isset($wp_settings_fields[Settings::PAGE][$section_name])) {
            return;
        }
        
        echo '<div class="mhm-settings-fields-container">';
        echo '<table class="form-table" role="presentation">';
        
        foreach ($wp_settings_fields[Settings::PAGE][$section_name] as $field) {
            echo '<tr>';
            if (!empty($field['args']['label_for'])) {
                echo '<th scope="row"><label for="' . esc_attr($field['args']['label_for']) . '">' . esc_html($field['title']) . '</label></th>';
            } else {
                echo '<th scope="row">' . esc_html($field['title']) . '</th>';
            }
            echo '<td>';
            
            // Call field callback but prevent nested forms
            ob_start();
            call_user_func($field['callback'], $field['args']);
            $field_output = ob_get_clean();
            
            // Aggressively remove any form elements
            // Remove complete form tags with content
            $field_output = preg_replace('/<form[^>]*>.*?<\/form>/is', '', $field_output);
            // Remove opening form tags
            $field_output = preg_replace('/<form[^>]*>/i', '', $field_output);
            // Remove closing form tags
            $field_output = preg_replace('/<\/form>/i', '', $field_output);
            // Remove any nested form elements (fieldset, button type="submit" outside form context)
            $field_output = preg_replace('/<fieldset[^>]*>/i', '<div>', $field_output);
            $field_output = preg_replace('/<\/fieldset>/i', '</div>', $field_output);
            
            echo $field_output;
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }

    /**
     * Render Vehicle Pricing Settings
     */
    public static function render_vehicle_pricing_settings(): void
    {
        if (class_exists('\MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings')) {
            echo '<h2>' . esc_html__('Vehicle Pricing Settings', 'mhm-rentiva') . '</h2>';
            echo '<p>' . esc_html__('Configure settings for vehicle pricing, seasonal multipliers, discounts, and additional services.', 'mhm-rentiva') . '</p>';
            
            echo '<table class="form-table">';
            echo '<tr><th scope="row">' . esc_html__('Seasonal Pricing', 'mhm-rentiva') . '</th><td>';
            
            $seasonal_multipliers = \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings::get_seasonal_multipliers();
            
            foreach ($seasonal_multipliers as $key => $season) {
                echo '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
                echo '<h4>' . esc_html($season['name']) . '</h4>';
                echo '<label for="season_' . esc_attr($key) . '_multiplier">' . esc_html__('Multiplier', 'mhm-rentiva') . '</label><br>';
                echo '<input type="number" id="season_' . esc_attr($key) . '_multiplier" name="mhm_rentiva_settings[vehicle_pricing][seasonal_multipliers][' . esc_attr($key) . '][multiplier]" value="' . esc_attr($season['multiplier']) . '" min="0.1" max="5.0" step="0.1" style="width: 100px;">';
                echo '<p class="description">' . esc_html($season['description']) . '</p>';
                echo '</div>';
            }
            
            echo '</td></tr>';
            
            echo '<tr><th scope="row">' . esc_html__('Discount Options', 'mhm-rentiva') . '</th><td>';
            
            $discount_options = \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings::get_discount_options();
            
            foreach ($discount_options as $key => $discount) {
                echo '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
                echo '<h4>' . esc_html($discount['name']) . '</h4>';
                echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][discount_options][' . esc_attr($key) . '][enabled]" value="1"' . checked($discount['enabled'], true, false) . '> ' . esc_html__('Active', 'mhm-rentiva') . '</label><br><br>';
                
                if (isset($discount['min_days'])) {
                    echo '<label for="discount_' . esc_attr($key) . '_min_days">' . esc_html__('Minimum Days', 'mhm-rentiva') . '</label><br>';
                    echo '<input type="number" id="discount_' . esc_attr($key) . '_min_days" name="mhm_rentiva_settings[vehicle_pricing][discount_options][' . esc_attr($key) . '][min_days]" value="' . esc_attr($discount['min_days']) . '" min="1" style="width: 100px;"><br><br>';
                }
                
                if (isset($discount['advance_days'])) {
                    echo '<label for="discount_' . esc_attr($key) . '_advance_days">' . esc_html__('Advance Booking (Days)', 'mhm-rentiva') . '</label><br>';
                    echo '<input type="number" id="discount_' . esc_attr($key) . '_advance_days" name="mhm_rentiva_settings[vehicle_pricing][discount_options][' . esc_attr($key) . '][advance_days]" value="' . esc_attr($discount['advance_days']) . '" min="1" style="width: 100px;"><br><br>';
                }
                
                echo '<label for="discount_' . esc_attr($key) . '_percent">' . esc_html__('Discount Percentage', 'mhm-rentiva') . '</label><br>';
                echo '<input type="number" id="discount_' . esc_attr($key) . '_percent" name="mhm_rentiva_settings[vehicle_pricing][discount_options][' . esc_attr($key) . '][discount_percent]" value="' . esc_attr($discount['discount_percent']) . '" min="1" max="100" style="width: 100px;">%';
                echo '<p class="description">' . esc_html($discount['description']) . '</p>';
                echo '</div>';
            }
            
            echo '</td></tr>';
            
               echo '<tr><th scope="row">' . esc_html__('Additional Services', 'mhm-rentiva') . '</th><td>';
               echo '<p class="description">' . esc_html__('Additional services are managed from the "Additional Services" menu. No default addon settings here.', 'mhm-rentiva') . '</p>';
               echo '<a href="' . esc_url(admin_url('edit.php?post_type=vehicle_addon')) . '" class="button">' . esc_html__('Manage Additional Services', 'mhm-rentiva') . '</a>';
               echo '</td></tr>';
            
            echo '<tr><th scope="row">' . esc_html__('Currency Settings', 'mhm-rentiva') . '</th><td>';
            
            $current_currency = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD');
            
            echo '<label for="default_currency">' . esc_html__('Default Currency', 'mhm-rentiva') . '</label><br>';
            echo '<select id="default_currency" name="mhm_rentiva_settings[mhm_rentiva_currency]" style="width: 150px;">';
            // Use centralized currency list from CurrencyHelper
            $currencies = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_list_for_dropdown();
            foreach ($currencies as $code => $name) {
                echo '<option value="' . esc_attr($code) . '"' . selected($current_currency, $code, false) . '>' . esc_html($name) . '</option>';
            }
            echo '</select><br><br>';
            
            echo '</td></tr>';
            
            // General Settings
            echo '<tr><th scope="row">' . esc_html__('General Settings', 'mhm-rentiva') . '</th><td>';
            
            $general_settings = \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings::get_general_settings();
            
            echo '<label for="min_rental_days">' . esc_html__('Minimum Rental Period (Days)', 'mhm-rentiva') . '</label><br>';
            echo '<input type="number" id="min_rental_days" name="mhm_rentiva_settings[vehicle_pricing][general_settings][min_rental_days]" value="' . esc_attr($general_settings['min_rental_days']) . '" min="1" style="width: 100px;"><br><br>';
            
            echo '<label for="max_rental_days">' . esc_html__('Maximum Rental Period (Days)', 'mhm-rentiva') . '</label><br>';
            echo '<input type="number" id="max_rental_days" name="mhm_rentiva_settings[vehicle_pricing][general_settings][max_rental_days]" value="' . esc_attr($general_settings['max_rental_days']) . '" min="1" max="365" style="width: 100px;"><br><br>';
            
            echo '<label for="decimal_places">' . esc_html__('Decimal Places', 'mhm-rentiva') . '</label><br>';
            echo '<input type="number" id="decimal_places" name="mhm_rentiva_settings[vehicle_pricing][general_settings][decimal_places]" value="' . esc_attr($general_settings['decimal_places']) . '" min="0" max="4" style="width: 100px;">';
            
            echo '</td></tr>';
            
            echo '<tr><th scope="row">' . esc_html__('Deposit Settings', 'mhm-rentiva') . '</th><td>';
            
            $deposit_settings = \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings::get_deposit_settings();
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][enable_deposit]" value="0">';
            echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][enable_deposit]" value="1"' . checked($deposit_settings['enable_deposit'], true, false) . '> ' . esc_html__('Enable Deposit System', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Enable deposit collection feature for vehicle rentals.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<label for="deposit_type">' . esc_html__('Deposit Type', 'mhm-rentiva') . '</label><br>';
            echo '<select id="deposit_type" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_type]" style="width: 200px;">';
            echo '<option value="fixed"' . selected($deposit_settings['deposit_type'], 'fixed', false) . '>' . esc_html__('Fixed Amount Only', 'mhm-rentiva') . '</option>';
            echo '<option value="percentage"' . selected($deposit_settings['deposit_type'], 'percentage', false) . '>' . esc_html__('Percentage Only', 'mhm-rentiva') . '</option>';
            echo '<option value="both"' . selected($deposit_settings['deposit_type'], 'both', false) . '>' . esc_html__('Both', 'mhm-rentiva') . '</option>';
            echo '</select>';
            echo '<p class="description">' . esc_html__('Determine which deposit types will be used.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][allow_no_deposit]" value="0">';
            echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][allow_no_deposit]" value="1"' . checked($deposit_settings['allow_no_deposit'], true, false) . '> ' . esc_html__('Allow Rental Without Deposit', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Allow vehicle rental without deposit.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][required_for_booking]" value="0">';
            echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][required_for_booking]" value="1"' . checked($deposit_settings['required_for_booking'], true, false) . '> ' . esc_html__('Required for Booking', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Make deposit payment mandatory to complete booking.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][show_deposit_in_listing]" value="0">';
            echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][show_deposit_in_listing]" value="1"' . checked($deposit_settings['show_deposit_in_listing'], true, false) . '> ' . esc_html__('Show in Listing Page', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Display deposit information in vehicle list.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][show_deposit_in_detail]" value="0">';
            echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][show_deposit_in_detail]" value="1"' . checked($deposit_settings['show_deposit_in_detail'], true, false) . '> ' . esc_html__('Show in Detail Page', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Display deposit information on vehicle detail page.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<label for="deposit_refund_policy">' . esc_html__('Deposit Refund Policy', 'mhm-rentiva') . '</label><br>';
            echo '<textarea id="deposit_refund_policy" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_refund_policy]" rows="3" style="width: 100%;">' . esc_textarea($deposit_settings['deposit_refund_policy']) . '</textarea>';
            echo '<p class="description">' . esc_html__('Explain your deposit refund policy.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<label for="deposit_payment_methods">' . esc_html__('Accepted Payment Methods', 'mhm-rentiva') . '</label><br>';
            echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_payment_methods][]" value="credit_card"' . checked(in_array('credit_card', $deposit_settings['deposit_payment_methods']), true, false) . '> ' . esc_html__('Credit Card', 'mhm-rentiva') . '</label><br>';
            echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_payment_methods][]" value="cash"' . checked(in_array('cash', $deposit_settings['deposit_payment_methods']), true, false) . '> ' . esc_html__('Cash', 'mhm-rentiva') . '</label><br>';
            echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_payment_methods][]" value="bank_transfer"' . checked(in_array('bank_transfer', $deposit_settings['deposit_payment_methods']), true, false) . '> ' . esc_html__('Bank Transfer', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Select accepted payment methods for deposit payment.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '</td></tr>';
            
            echo '</table>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('VehiclePricingSettings class not found.', 'mhm-rentiva') . '</p></div>';
        }
    }

    /**
     * Render REST API settings
     */
    public static function render_rest_settings(): void
    {
        if (class_exists('\MHMRentiva\Admin\REST\Settings\RESTSettings')) {
            
            echo '<div class="mhm-rest-settings-header">';
            echo '<div>';
            echo '<h2>' . esc_html__('REST API Settings', 'mhm-rentiva') . '</h2>';
            echo '<p>' . esc_html__('Configure REST API security, performance and behavior settings.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            echo '<button type="button" id="mhm-reset-rest-settings-btn" class="button button-secondary">';
            echo '<span class="dashicons dashicons-update"></span> ';
            echo esc_html__('Reset to Defaults', 'mhm-rentiva');
            echo '</button>';
            echo '</div>';
            
            // Rate Limiting
            echo '<table class="form-table">';
            echo '<tr><th scope="row">' . esc_html__('Rate Limiting', 'mhm-rentiva') . '</th><td>';
            
            $rate_settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::get_rate_limit_settings();
            
            echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[rate_limiting][enabled]" value="1"' . checked($rate_settings['enabled'], true, false) . '> ' . esc_html__('Rate limiting enabled', 'mhm-rentiva') . '</label><br><br>';
            
            echo '<label for="rest_default_limit">' . esc_html__('Default Limit (Requests/Minute)', 'mhm-rentiva') . '</label><br>';
            echo '<input type="number" id="rest_default_limit" name="mhm_rentiva_rest_settings[rate_limiting][default_limit]" value="' . esc_attr($rate_settings['default_limit']) . '" min="1" max="1000" style="width: 150px;">';
            echo '<p class="description">' . esc_html__('Maximum number of requests per minute for normal users.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label for="rest_strict_limit">' . esc_html__('Strict Limit (Requests/Minute)', 'mhm-rentiva') . '</label><br>';
            echo '<input type="number" id="rest_strict_limit" name="mhm_rentiva_rest_settings[rate_limiting][strict_limit]" value="' . esc_attr($rate_settings['strict_limit']) . '" min="1" max="100" style="width: 150px;">';
            echo '<p class="description">' . esc_html__('Strict limit for non-admin users.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label for="rest_burst_limit">' . esc_html__('Burst Limit (Requests/5 Minutes)', 'mhm-rentiva') . '</label><br>';
            echo '<input type="number" id="rest_burst_limit" name="mhm_rentiva_rest_settings[rate_limiting][burst_limit]" value="' . esc_attr($rate_settings['burst_limit']) . '" min="1" max="1000" style="width: 150px;">';
            echo '<p class="description">' . esc_html__('Burst limit for short-term intensive usage.', 'mhm-rentiva') . '</p>';
            
            echo '</td></tr>';
            
            echo '<tr><th scope="row">' . esc_html__('Token Settings', 'mhm-rentiva') . '</th><td>';
            
            $token_settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::get_token_settings();
            
            echo '<label for="rest_token_expiry">' . esc_html__('Default Token Duration (Hours)', 'mhm-rentiva') . '</label><br>';
            echo '<input type="number" id="rest_token_expiry" name="mhm_rentiva_rest_settings[tokens][default_expiry_hours]" value="' . esc_attr($token_settings['default_expiry_hours']) . '" min="1" max="168" style="width: 150px;">';
            echo '<p class="description">' . esc_html__('Default token validity period.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[tokens][refresh_enabled]" value="1"' . checked($token_settings['refresh_enabled'], true, false) . '> ' . esc_html__('Token refresh enabled', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Allow users to refresh their tokens.', 'mhm-rentiva') . '</p>';
            
            echo '</td></tr>';
            
            echo '<tr><th scope="row">' . esc_html__('Security Settings', 'mhm-rentiva') . '</th><td>';
            
            $security_settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::get_security_settings();
            
            echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[security][require_https]" value="1"' . checked($security_settings['require_https'], true, false) . '> ' . esc_html__('HTTPS required', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Require HTTPS for all REST API requests.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[security][user_agent_validation]" value="1"' . checked($security_settings['user_agent_validation'], true, false) . '> ' . esc_html__('User Agent Validation', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Block suspicious user agents (bots, curl, etc.).', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label for="rest_ip_whitelist">' . esc_html__('IP Whitelist (Comma separated)', 'mhm-rentiva') . '</label><br>';
            echo '<textarea id="rest_ip_whitelist" name="mhm_rentiva_rest_settings[security][ip_whitelist]" rows="3" cols="50" placeholder="192.168.1.1, 10.0.0.1">' . esc_textarea(implode(', ', $security_settings['ip_whitelist'])) . '</textarea>';
            echo '<p class="description">' . esc_html__('Only allow requests from these IPs. If left blank, all IPs are allowed.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[security][ip_blacklist_enabled]" value="1"' . checked($security_settings['ip_blacklist_enabled'], true, false) . '> ' . esc_html__('IP Blacklist enabled', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Block requests from specified IPs.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label for="rest_ip_blacklist">' . esc_html__('IP Blacklist (Comma separated)', 'mhm-rentiva') . '</label><br>';
            echo '<textarea id="rest_ip_blacklist" name="mhm_rentiva_rest_settings[security][ip_blacklist]" rows="3" cols="50" placeholder="192.168.1.100, 10.0.0.50, 172.16.1.200">' . esc_textarea(implode(', ', $security_settings['ip_blacklist'])) . '</textarea>';
            echo '<p class="description">' . esc_html__('Block all requests from these IPs. Example: 192.168.1.100, 10.0.0.50', 'mhm-rentiva') . '</p>';
            
            echo '</td></tr>';
            
            echo '<tr><th scope="row">' . esc_html__('Cache Settings', 'mhm-rentiva') . '</th><td>';
            
            $cache_settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::get_cache_settings();
            
            echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[cache][enabled]" value="1"' . checked($cache_settings['enabled'], true, false) . '> ' . esc_html__('API Cache enabled', 'mhm-rentiva') . '</label><br><br>';
            
            echo '<label for="rest_cache_duration">' . esc_html__('Cache Duration (Seconds)', 'mhm-rentiva') . '</label><br>';
            echo '<input type="number" id="rest_cache_duration" name="mhm_rentiva_rest_settings[cache][duration_seconds]" value="' . esc_attr($cache_settings['duration_seconds']) . '" min="60" max="3600" style="width: 150px;">';
            echo '<p class="description">' . esc_html__('Duration for which API responses are cached.', 'mhm-rentiva') . '</p>';
            
            echo '</td></tr>';
            
            echo '<tr><th scope="row">' . esc_html__('Developer Mode', 'mhm-rentiva') . '</th><td>';
            
            $dev_settings = \MHMRentiva\Admin\REST\Settings\RESTSettings::get_development_settings();
            $wp_debug_status = \MHMRentiva\Admin\REST\Settings\RESTSettings::is_wp_debug_enabled() ? 
                '<span style="color: #46b450;">✓ Active</span>' : 
                '<span style="color: #dc3232;">✗ Inactive</span>';
            
            echo '<p><strong>WordPress Debug Status:</strong> ' . $wp_debug_status . '</p>';
            
            echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[development][debug_mode]" value="1"' . checked($dev_settings['debug_mode'], true, false) . '> ' . esc_html__('Debug mode enabled', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Enable REST API debug information.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][auto_enable_on_debug]" value="1"' . checked($dev_settings['auto_enable_on_debug'], true, false) . '> ' . esc_html__('Auto enable if WP_DEBUG is active', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Automatically enable developer features if WordPress debug mode is active.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][rate_limit_bypass]" value="1"' . checked($dev_settings['rate_limit_bypass'], true, false) . '> ' . esc_html__('Rate limit bypass', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Bypass rate limiting in developer mode.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][security_bypass]" value="1"' . checked($dev_settings['security_bypass'], true, false) . '> ' . esc_html__('Security bypass', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Bypass security checks in developer mode (WARNING: Use only in development environment!).', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][cors_all_origins]" value="1"' . checked($dev_settings['cors_all_origins'], true, false) . '> ' . esc_html__('Allow CORS from all origins', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Allow CORS requests from all origins in developer mode.', 'mhm-rentiva') . '</p>';
            
            echo '<br><br><label><input type="checkbox" name="mhm_rentiva_rest_settings[development][verbose_logging]" value="1"' . checked($dev_settings['verbose_logging'], true, false) . '> ' . esc_html__('Verbose logging', 'mhm-rentiva') . '</label>';
            echo '<p class="description">' . esc_html__('Detailed logging for all REST API requests and responses.', 'mhm-rentiva') . '</p>';
            
            echo '</td></tr>';
            
            echo '</table>';
            
            // ✅ API Keys Management Section
            self::render_api_keys_section();
            
            // ✅ Available Endpoints Section
            self::render_endpoints_section();
            
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('REST Settings class not found.', 'mhm-rentiva') . '</p></div>';
        }
    }
    
    /**
     * Render API Keys Management Section
     */
    public static function render_api_keys_section(): void
    {
        if (!class_exists('\MHMRentiva\Admin\REST\APIKeyManager')) {
            return;
        }
        
        wp_enqueue_style('mhm-rest-api-keys', MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/rest-api-keys.css', [], MHM_RENTIVA_VERSION);
        wp_enqueue_script('mhm-rest-api-keys', MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/rest-api-keys.js', ['jquery'], MHM_RENTIVA_VERSION, true);
        wp_localize_script('mhm-rest-api-keys', 'mhmRestApiKeys', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_rest_settings'),
            'strings' => [
                'create_key' => __('Create API Key', 'mhm-rentiva'),
                'key_name' => __('Key Name', 'mhm-rentiva'),
                'key_name_required' => __('Key name is required.', 'mhm-rentiva'),
                'permissions' => __('Permissions', 'mhm-rentiva'),
                'permissions_required' => __('Please select at least one permission.', 'mhm-rentiva'),
                'read' => __('Read', 'mhm-rentiva'),
                'write' => __('Write', 'mhm-rentiva'),
                'admin' => __('Admin', 'mhm-rentiva'),
                'created' => __('Created', 'mhm-rentiva'),
                'last_used' => __('Last Used', 'mhm-rentiva'),
                'status' => __('Status', 'mhm-rentiva'),
                'actions' => __('Actions', 'mhm-rentiva'),
                'copy' => __('Copy', 'mhm-rentiva'),
                'revoke' => __('Revoke', 'mhm-rentiva'),
                'delete' => __('Delete', 'mhm-rentiva'),
                'no_keys' => __('No API keys found.', 'mhm-rentiva'),
                'key_copied' => __('API key copied to clipboard!', 'mhm-rentiva'),
                'confirm_revoke' => __('Are you sure you want to revoke this API key?', 'mhm-rentiva'),
                'confirm_delete' => __('Are you sure you want to delete this API key? This cannot be undone.', 'mhm-rentiva'),
                'close' => __('Close', 'mhm-rentiva'),
                'failed_create' => __('Failed to create API key.', 'mhm-rentiva'),
                'failed_revoke' => __('Failed to revoke API key.', 'mhm-rentiva'),
                'failed_delete' => __('Failed to delete API key.', 'mhm-rentiva'),
                'error_occurred' => __('An error occurred. Please try again.', 'mhm-rentiva'),
                'reset_to_defaults' => __('Reset to Defaults', 'mhm-rentiva'),
                'resetting' => __('Resetting...', 'mhm-rentiva'),
                'confirm_reset' => __('Are you sure you want to reset all REST API settings to default values? This action cannot be undone.', 'mhm-rentiva'),
                'reset_success' => __('Settings reset to defaults successfully. Page will reload...', 'mhm-rentiva'),
                'reset_failed' => __('Failed to reset settings to defaults.', 'mhm-rentiva'),
            ]
        ]);
        
        echo '<hr class="mhm-rest-api-section-separator">';
        echo '<div class="mhm-api-keys-section">';
        echo '<h2>' . esc_html__('API Keys Management', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('Create and manage API keys for REST API access.', 'mhm-rentiva') . '</p>';
        
        // Create New Key Form
        echo '<div class="mhm-api-keys-create-form">';
        echo '<h3>' . esc_html__('Create New API Key', 'mhm-rentiva') . '</h3>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="new_key_name">' . esc_html__('Key Name', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="text" id="new_key_name" class="regular-text" placeholder="' . esc_attr__('e.g., Production API Key', 'mhm-rentiva') . '"></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label>' . esc_html__('Permissions', 'mhm-rentiva') . '</label></th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="new_key_permissions[]" value="read" checked> ' . esc_html__('Read', 'mhm-rentiva') . '</label><br>';
        echo '<label><input type="checkbox" name="new_key_permissions[]" value="write"> ' . esc_html__('Write', 'mhm-rentiva') . '</label><br>';
        echo '<label><input type="checkbox" name="new_key_permissions[]" value="admin"> ' . esc_html__('Admin', 'mhm-rentiva') . '</label>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        echo '<p class="submit"><button type="button" id="mhm-create-api-key-btn" class="button button-primary">' . esc_html__('Generate API Key', 'mhm-rentiva') . '</button></p>';
        echo '</div>';
        
        // Keys List
        echo '<div id="mhm-api-keys-list-container" class="mhm-api-keys-list-container">';
        echo '<h3>' . esc_html__('Active API Keys', 'mhm-rentiva') . '</h3>';
        echo '<button type="button" id="mhm-refresh-keys-btn" class="button mhm-api-keys-refresh-btn">' . esc_html__('Refresh List', 'mhm-rentiva') . '</button>';
        echo '<div id="mhm-api-keys-list"></div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render Available Endpoints Section
     */
    public static function render_endpoints_section(): void
    {
        if (!class_exists('\MHMRentiva\Admin\REST\EndpointListHelper')) {
            return;
        }
        
        wp_enqueue_script('mhm-rest-api-keys'); // Use same script
        
        echo '<hr class="mhm-rest-api-section-separator">';
        echo '<div class="mhm-endpoints-section">';
        echo '<h2>' . esc_html__('Available REST API Endpoints', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('List of all registered REST API endpoints for the plugin.', 'mhm-rentiva') . '</p>';
        
        echo '<button type="button" id="mhm-refresh-endpoints-btn" class="button mhm-endpoints-refresh-btn">' . esc_html__('Refresh Endpoints', 'mhm-rentiva') . '</button>';
        echo '<div id="mhm-endpoints-list"></div>';
        echo '</div>';
    }

    /**
     * Render email templates tab
     */
    public static function render_email_templates_tab(): void
    {
        if (class_exists('\MHMRentiva\Admin\Emails\Core\EmailTemplates')) {
            \MHMRentiva\Admin\Emails\Core\EmailTemplates::render_content_only();
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Email templates module not found.', 'mhm-rentiva') . '</p></div>';
        }
    }

    /**
     * Render Vehicle Management Settings
     */
    public static function render_vehicle_management_settings(): void
    {
        echo '<div class="mhm-settings-tab-header">';
        echo '<div>';
        echo '<h2>' . esc_html__('Vehicle Management Settings', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('Configure vehicle pricing, display options, and availability settings.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        echo '<button type="button" class="button button-secondary mhm-reset-tab-btn" data-tab="vehicle">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Reset to Defaults', 'mhm-rentiva');
        echo '</button>';
        echo '</div>';
        
        // Vehicle pricing settings
        self::render_section_clean('mhm_rentiva_vehicle_pricing_section');
        
        // Vehicle display settings
        self::render_section_clean('mhm_rentiva_vehicle_display_section');
        
        // Vehicle availability settings
        self::render_section_clean('mhm_rentiva_vehicle_availability_section');
        
        // Vehicle comparison settings
        self::render_section_clean('mhm_rentiva_vehicle_comparison_section');
    }

    /**
     * Render Customer Management Settings
     */
    public static function render_customer_management_settings(): void
    {
        echo '<div class="mhm-settings-tab-header">';
        echo '<div>';
        echo '<h2>' . esc_html__('Customer Management Settings', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('Configure customer registration, account management, and communication settings.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        echo '<button type="button" class="button button-secondary mhm-reset-tab-btn" data-tab="customer">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Reset to Defaults', 'mhm-rentiva');
        echo '</button>';
        echo '</div>';
        
        // Customer registration settings
        self::render_section_clean('mhm_rentiva_customer_registration_section');
        
        // Customer account settings
        self::render_section_clean('mhm_rentiva_customer_account_section');
        
        // Customer communication settings
        self::render_section_clean('mhm_rentiva_customer_communication_section');
        
        // Customer security settings
        self::render_section_clean('mhm_rentiva_customer_security_section');
        
        // Customer privacy settings
        self::render_section_clean('mhm_rentiva_customer_privacy_section');
        
        // Customer experience settings
        self::render_section_clean('mhm_rentiva_customer_experience_section');
    }

    /**
     * Render Payment Settings
     */
    public static function render_payment_settings(): void
    {
        // Use output buffering to clean any nested form elements
        ob_start();
        
        echo '<div class="mhm-settings-tab-header">';
        echo '<div>';
        echo '<h2>' . esc_html__('Payment Settings', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('Configure payment gateways and payment processing settings.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        echo '<button type="button" class="button button-secondary mhm-reset-tab-btn" data-tab="payment">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Reset to Defaults', 'mhm-rentiva');
        echo '</button>';
        echo '</div>';
        
        // General Payment Settings
        self::render_section_clean('mhm_rentiva_general_payment_section');
        
        // Payment Gateway Status
        self::render_section_clean('mhm_rentiva_payment_gateway_status_section');
        

        
        // ⭐ Offline payment removed - WooCommerce handles all payments
        
        $payment_content = ob_get_clean();
        
        // Aggressively remove any nested form elements
        $payment_content = preg_replace('/<form[^>]*>.*?<\/form>/is', '', $payment_content);
        $payment_content = preg_replace('/<form[^>]*>/i', '', $payment_content);
        $payment_content = preg_replace('/<\/form>/i', '', $payment_content);
        $payment_content = preg_replace('/\s+form\s*=\s*["\'][^"\']*["\']/i', '', $payment_content);
        
        echo $payment_content;
    }

    /**
     * Render Integration Settings
     */
    public static function render_integration_settings(): void
    {
        echo '<h2>' . esc_html__('Integration Settings', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('Configure third-party integrations and REST API settings.', 'mhm-rentiva') . '</p>';
        
        // Render REST API Settings
        self::render_rest_settings();
    }

    /**
     * Render Messages Settings
     */
    public static function render_messages_settings(): void
    {
        if (!class_exists('\MHMRentiva\Admin\Messages\Settings\MessagesSettings')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('MessagesSettings class not found.', 'mhm-rentiva') . '</p></div>';
            return;
        }

        // Ensure MessagesSettings is initialized
        \MHMRentiva\Admin\Messages\Settings\MessagesSettings::init();

        // Get settings
        $settings = \MHMRentiva\Admin\Messages\Settings\MessagesSettings::get_settings();
        $active_subtab = isset($_GET['subtab']) ? sanitize_key(wp_unslash($_GET['subtab'])) : 'email';
        
        // Enqueue messages settings assets
        wp_enqueue_style(
            'mhm-messages-settings',
            esc_url(MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/messages-settings.css'),
            [],
            MHM_RENTIVA_VERSION
        );

        wp_enqueue_script(
            'mhm-messages-settings',
            esc_url(MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/messages-settings.js'),
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );

        ?>
        <div class="mhm-messages-settings-container">
            <div class="mhm-settings-tab-header">
                <div>
                    <h2><?php echo esc_html__('Messages Settings', 'mhm-rentiva'); ?></h2>
                    <p><?php echo esc_html__('Configure message system settings, email notifications, categories, and statuses.', 'mhm-rentiva'); ?></p>
                </div>
                <button type="button" class="button button-secondary mhm-reset-messages-btn">
                    <span class="dashicons dashicons-update"></span>
                    <?php echo esc_html__('Reset to Defaults', 'mhm-rentiva'); ?>
                </button>
            </div>

            <!-- Sub-tabs Navigation -->
            <nav class="mhm-messages-subtabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=messages&subtab=email')); ?>" 
                   class="mhm-subtab <?php echo $active_subtab === 'email' ? 'active' : ''; ?>">
                    <?php echo esc_html__('Email', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=messages&subtab=general')); ?>" 
                   class="mhm-subtab <?php echo $active_subtab === 'general' ? 'active' : ''; ?>">
                    <?php echo esc_html__('General', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=messages&subtab=categories')); ?>" 
                   class="mhm-subtab <?php echo $active_subtab === 'categories' ? 'active' : ''; ?>">
                    <?php echo esc_html__('Categories', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=messages&subtab=statuses')); ?>" 
                   class="mhm-subtab <?php echo $active_subtab === 'statuses' ? 'active' : ''; ?>">
                    <?php echo esc_html__('Statuses', 'mhm-rentiva'); ?>
                </a>
            </nav>

            <!-- Settings Form -->
            <form method="post" action="options.php" class="mhm-settings-form" id="mhm-messages-settings-form">
                <?php 
                settings_fields(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_GROUP);
                ?>

                <!-- Email Tab -->
                <div id="messages-email" class="mhm-subtab-content <?php echo $active_subtab === 'email' ? 'active' : ''; ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="admin_email"><?php echo esc_html__('Admin Email', 'mhm-rentiva'); ?></label></th>
                            <td>
                                <input type="email" id="admin_email" 
                                       name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[admin_email]" 
                                       value="<?php echo esc_attr($settings['admin_email'] ?? ''); ?>" 
                                       class="regular-text">
                                <p class="description"><?php echo esc_html__('Email address for message notifications', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="from_name"><?php echo esc_html__('Sender Name', 'mhm-rentiva'); ?></label></th>
                            <td>
                                <input type="text" id="from_name" 
                                       name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[from_name]" 
                                       value="<?php echo esc_attr($settings['from_name'] ?? ''); ?>" 
                                       class="regular-text">
                                <p class="description"><?php echo esc_html__('Sender name to display in emails', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="from_email"><?php echo esc_html__('Sender Email', 'mhm-rentiva'); ?></label></th>
                            <td>
                                <input type="email" id="from_email" 
                                       name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[from_email]" 
                                       value="<?php echo esc_attr($settings['from_email'] ?? ''); ?>" 
                                       class="regular-text">
                                <p class="description"><?php echo esc_html__('Email address to send emails from', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Admin Notifications', 'mhm-rentiva'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[email_admin_notifications]" 
                                           value="1" <?php checked($settings['email_admin_notifications'] ?? false, true); ?>>
                                    <?php echo esc_html__('Send notification to admin when new message arrives', 'mhm-rentiva'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Customer Notifications', 'mhm-rentiva'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[email_customer_notifications]" 
                                           value="1" <?php checked($settings['email_customer_notifications'] ?? false, true); ?>>
                                    <?php echo esc_html__('Send notification to customer when reply arrives', 'mhm-rentiva'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- General Tab -->
                <div id="messages-general" class="mhm-subtab-content <?php echo $active_subtab === 'general' ? 'active' : ''; ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Dashboard Widget', 'mhm-rentiva'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[dashboard_widget_enabled]" 
                                           value="1" <?php checked($settings['dashboard_widget_enabled'] ?? false, true); ?>>
                                    <?php echo esc_html__('Show message widget in dashboard', 'mhm-rentiva'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="dashboard_widget_max_messages"><?php echo esc_html__('Widget Max Messages', 'mhm-rentiva'); ?></label></th>
                            <td>
                                <input type="number" id="dashboard_widget_max_messages" 
                                       name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[dashboard_widget_max_messages]" 
                                       value="<?php echo esc_attr($settings['dashboard_widget_max_messages'] ?? 5); ?>" 
                                       min="1" max="20" class="small-text">
                                <p class="description"><?php echo esc_html__('Maximum number of messages to show in dashboard widget', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Auto Reply', 'mhm-rentiva'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[auto_reply_enabled]" 
                                           value="1" <?php checked($settings['auto_reply_enabled'] ?? false, true); ?>>
                                    <?php echo esc_html__('Send automatic reply to new messages', 'mhm-rentiva'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="auto_reply_message"><?php echo esc_html__('Auto Reply Message', 'mhm-rentiva'); ?></label></th>
                            <td>
                                <textarea id="auto_reply_message" 
                                          name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[auto_reply_message]" 
                                          rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['auto_reply_message'] ?? ''); ?></textarea>
                                <p class="description"><?php echo esc_html__('Automatic reply message to send to customers', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Categories Tab -->
                <div id="messages-categories" class="mhm-subtab-content <?php echo $active_subtab === 'categories' ? 'active' : ''; ?>">
                    <div id="category-list">
                        <?php 
                        $categories = $settings['categories'] ?? [];
                        foreach ($categories as $key => $name): 
                        ?>
                            <div class="mhm-category-item">
                                <input type="text" 
                                       name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[categories][<?php echo esc_attr($key); ?>]" 
                                       value="<?php echo esc_attr($name); ?>" 
                                       class="category-name regular-text" 
                                       required>
                                <button type="button" class="button remove-category-btn"><?php echo esc_html__('Delete', 'mhm-rentiva'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mhm-add-item">
                        <input type="text" id="new-category-name" class="regular-text" placeholder="<?php echo esc_attr__('New category name', 'mhm-rentiva'); ?>">
                        <button type="button" id="add-category-btn" class="button"><?php echo esc_html__('Add Category', 'mhm-rentiva'); ?></button>
                    </div>
                </div>

                <!-- Statuses Tab -->
                <div id="messages-statuses" class="mhm-subtab-content <?php echo $active_subtab === 'statuses' ? 'active' : ''; ?>">
                    <div id="status-list">
                        <?php 
                        $statuses = $settings['statuses'] ?? [];
                        foreach ($statuses as $key => $name): 
                        ?>
                            <div class="mhm-status-item">
                                <input type="text" 
                                       name="<?php echo esc_attr(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::OPTION_NAME); ?>[statuses][<?php echo esc_attr($key); ?>]" 
                                       value="<?php echo esc_attr($name); ?>" 
                                       class="status-name regular-text" 
                                       required>
                                <button type="button" class="button remove-status-btn"><?php echo esc_html__('Delete', 'mhm-rentiva'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mhm-add-item">
                        <input type="text" id="new-status-name" class="regular-text" placeholder="<?php echo esc_attr__('New status name', 'mhm-rentiva'); ?>">
                        <button type="button" id="add-status-btn" class="button"><?php echo esc_html__('Add Status', 'mhm-rentiva'); ?></button>
                    </div>
                </div>

                <?php submit_button(__('Save Settings', 'mhm-rentiva')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render testing page
     */
    public static function render_testing_page(): void
    {
        if (!class_exists('\MHMRentiva\Admin\Settings\Testing\SettingsTester')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Settings tester class not found.', 'mhm-rentiva') . '</p></div>';
            return;
        }

        echo '<div class="mhm-settings-testing">';
        echo '<h2>' . esc_html__('Settings Testing', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('This page allows you to test all plugin settings to ensure they are working correctly.', 'mhm-rentiva') . '</p>';
        
        echo '<div class="test-controls">';
        echo '<button type="button" id="mhm-run-tests" class="button button-primary">' . esc_html__('Run All Tests', 'mhm-rentiva') . '</button>';
        echo '<button type="button" id="mhm-clear-tests" class="button button-secondary">' . esc_html__('Clear Results', 'mhm-rentiva') . '</button>';
        echo '</div>';
        
        echo '<div id="mhm-test-results" class="test-results" style="display: none;">';
        echo '</div>';
        
        echo '</div>';
        
        // Add JavaScript for testing
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#mhm-run-tests').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php esc_html_e('Running Tests...', 'mhm-rentiva'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhm_run_settings_tests',
                        nonce: '<?php echo wp_create_nonce('mhm_settings_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#mhm-test-results').html(response.data).show();
                        } else {
                            $('#mhm-test-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>').show();
                        }
                    },
                    error: function() {
                        $('#mhm-test-results').html('<div class="notice notice-error"><p><?php esc_html_e('Failed to run tests.', 'mhm-rentiva'); ?></p></div>').show();
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php esc_html_e('Run All Tests', 'mhm-rentiva'); ?>');
                    }
                });
            });
            
            $('#mhm-clear-tests').on('click', function() {
                $('#mhm-test-results').hide().empty();
            });
        });
        </script>
        <?php
    }

    /**
     * Render Database Cleanup page
     */
    public static function render_database_cleanup_page(): void
    {
        if (!class_exists('\MHMRentiva\Admin\Utilities\Database\DatabaseCleanupPage')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Database cleanup class not found.', 'mhm-rentiva') . '</p></div>';
            return;
        }

        // Enqueue assets for database cleanup
        wp_enqueue_style(
            'mhm-database-cleanup',
            esc_url(MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/database-cleanup.css'),
            [],
            MHM_RENTIVA_VERSION
        );

        wp_enqueue_script(
            'mhm-database-cleanup',
            esc_url(MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/database-cleanup.js'),
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );

        wp_localize_script('mhm-database-cleanup', 'mhm_db_cleanup_vars', [
            'nonce' => wp_create_nonce('mhm_db_cleanup'),
            'analyzing_text' => esc_html__('Analyzing...', 'mhm-rentiva'),
            'cleaning_text' => esc_html__('Cleaning...', 'mhm-rentiva'),
            'optimizing_text' => esc_html__('Optimizing...', 'mhm-rentiva'),
            'error_text' => esc_html__('Error:', 'mhm-rentiva'),
            'success_text' => esc_html__('Success:', 'mhm-rentiva'),
            'analyze_text' => esc_html__('Analyze Database', 'mhm-rentiva'),
            'clean_orphaned_text' => esc_html__('Clean Orphaned Meta', 'mhm-rentiva'),
            'clean_transients_text' => esc_html__('Clean Expired Transients', 'mhm-rentiva'),
            'optimize_autoload_text' => esc_html__('Optimize Autoload', 'mhm-rentiva'),
            'optimize_tables_text' => esc_html__('Optimize Tables', 'mhm-rentiva'),
            'clean_invalid_meta_text' => esc_html__('Clean', 'mhm-rentiva'),
            'confirm_orphaned_text' => esc_html__('This will delete orphaned meta data. A backup will be created. Continue?', 'mhm-rentiva'),
            'confirm_tables_text' => esc_html__('Table optimization may take several minutes. Continue?', 'mhm-rentiva'),
            'confirm_invalid_meta_text' => esc_html__('This will delete invalid meta keys. A backup will be created. Continue?', 'mhm-rentiva'),
            'confirm_restore_text' => esc_html__('This will restore the backup data. Continue?', 'mhm-rentiva'),
            'confirm_delete_backup_text' => esc_html__('This will permanently delete the backup. This action cannot be undone. Continue?', 'mhm-rentiva'),
            'loading_text' => esc_html__('Loading...', 'mhm-rentiva'),
            'refresh_text' => esc_html__('Refresh Backup List', 'mhm-rentiva'),
            'restoring_text' => esc_html__('Restoring...', 'mhm-rentiva'),
            'deleting_text' => esc_html__('Deleting...', 'mhm-rentiva'),
            'backup_table_text' => esc_html__('Table Name', 'mhm-rentiva'),
            'backup_type_text' => esc_html__('Type', 'mhm-rentiva'),
            'backup_date_text' => esc_html__('Date', 'mhm-rentiva'),
            'backup_rows_text' => esc_html__('Rows', 'mhm-rentiva'),
            'backup_size_text' => esc_html__('Size', 'mhm-rentiva'),
            'actions_text' => esc_html__('Actions', 'mhm-rentiva'),
            'download_text' => esc_html__('Download', 'mhm-rentiva'),
            'restore_text' => esc_html__('Restore', 'mhm-rentiva'),
            'delete_text' => esc_html__('Delete', 'mhm-rentiva'),
            'no_backups_text' => esc_html__('No backups found.', 'mhm-rentiva'),
            'type_invalid_meta_text' => esc_html__('Invalid Meta', 'mhm-rentiva'),
            'type_orphaned_meta_text' => esc_html__('Orphaned Meta', 'mhm-rentiva'),
            'type_custom_text' => esc_html__('Custom', 'mhm-rentiva'),
            'create_full_backup_text' => esc_html__('Create Full Backup', 'mhm-rentiva'),
            'confirm_create_full_backup_text' => esc_html__('This will create a full backup of all plugin-related tables. This may take a few minutes. Continue?', 'mhm-rentiva'),
            'creating_backup_text' => esc_html__('Creating Backup...', 'mhm-rentiva'),
            'confirm_restore_full_backup_text' => esc_html__('WARNING: This will restore the backup and may overwrite existing data. This operation is irreversible. Continue?', 'mhm-rentiva'),
            'backup_name_text' => esc_html__('Backup Name', 'mhm-rentiva'),
            'backup_tables_text' => esc_html__('Tables', 'mhm-rentiva'),
            'backup_status_text' => esc_html__('Status', 'mhm-rentiva'),
            'file_exists_text' => esc_html__('Available', 'mhm-rentiva'),
            'file_missing_text' => esc_html__('Missing', 'mhm-rentiva'),
        ]);

        echo '<div class="mhm-database-cleanup-page">';
        echo '<h2>' . esc_html__('Database Cleanup & Optimization', 'mhm-rentiva') . '</h2>';

        echo '<div class="notice notice-warning">';
        echo '<p>';
        echo '<strong>' . esc_html__('Warning:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('Database cleanup operations are irreversible. Automatic backup is created before each operation.', 'mhm-rentiva');
        echo '</p>';
        echo '</div>';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::render_cleanup_buttons();

        echo '<div id="mhm-cleanup-results" style="margin-top: 20px;"></div>';
        
        // Full Database Backup Section
        echo '<div id="mhm-full-backup-section" style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">';
        echo '<h2>' . esc_html__('Full Database Backup', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Create a complete backup of all plugin-related database tables (posts, postmeta, options, and custom tables).', 'mhm-rentiva') . '</p>';
        
        // Security notice
        echo '<div class="notice notice-info" style="margin-top: 10px;">';
        echo '<p>';
        echo '<span class="dashicons dashicons-lock" style="color: #2271b1; vertical-align: middle;"></span> ';
        echo '<strong>' . esc_html__('Security:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('Backup files are stored securely in wp-content/mhm-rentiva-backups/ and are protected from direct web access using .htaccess and index.php files (WordPress standards compliant).', 'mhm-rentiva');
        echo '</p>';
        echo '</div>';
        echo '<button type="button" class="button button-primary" id="mhm-create-full-backup-btn" style="margin-bottom: 15px;">';
        echo '<span class="dashicons dashicons-database-add"></span> ';
        echo esc_html__('Create Full Backup', 'mhm-rentiva');
        echo '</button>';
        echo '<button type="button" class="button" id="mhm-refresh-full-backups-btn" style="margin-bottom: 15px; margin-left: 10px;">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Refresh List', 'mhm-rentiva');
        echo '</button>';
        echo '<div id="mhm-full-backup-list" style="margin-top: 10px;"></div>';
        echo '</div>';
        
        // Cleanup Backup Management Section
        echo '<div id="mhm-backup-management" style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">';
        echo '<h2>' . esc_html__('Cleanup Backups', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Manage and restore backups created during cleanup operations (orphaned meta, invalid meta, etc.).', 'mhm-rentiva') . '</p>';
        echo '<button type="button" class="button" id="mhm-refresh-backups-btn" style="margin-bottom: 15px;">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Refresh Backup List', 'mhm-rentiva');
        echo '</button>';
        echo '<div id="mhm-backup-list" style="margin-top: 10px;"></div>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render Cron Job Monitor page
     */
    public static function render_cron_monitor_page(): void
    {
        // Enqueue scripts
        wp_enqueue_script(
            'mhm-cron-monitor',
            plugins_url('assets/js/admin/cron-monitor.js', MHM_RENTIVA_PLUGIN_FILE),
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );

        wp_localize_script('mhm-cron-monitor', 'mhm_cron_vars', [
            'nonce' => wp_create_nonce('mhm_cron_monitor'),
            'run_text' => esc_html__('Run Now', 'mhm-rentiva'),
            'running_text' => esc_html__('Running...', 'mhm-rentiva'),
            'refresh_text' => esc_html__('Refresh List', 'mhm-rentiva'),
            'loading_text' => esc_html__('Loading...', 'mhm-rentiva'),
            'success_text' => esc_html__('Success:', 'mhm-rentiva'),
            'error_text' => esc_html__('Error:', 'mhm-rentiva'),
            'confirm_run_text' => esc_html__('This will execute the cron job immediately. Continue?', 'mhm-rentiva'),
            'hook_text' => esc_html__('Hook', 'mhm-rentiva'),
            'name_text' => esc_html__('Name', 'mhm-rentiva'),
            'description_text' => esc_html__('Description', 'mhm-rentiva'),
            'schedule_text' => esc_html__('Schedule', 'mhm-rentiva'),
            'next_run_text' => esc_html__('Next Run', 'mhm-rentiva'),
            'status_text' => esc_html__('Status', 'mhm-rentiva'),
            'actions_text' => esc_html__('Actions', 'mhm-rentiva'),
            'scheduled_text' => esc_html__('Scheduled', 'mhm-rentiva'),
            'not_scheduled_text' => esc_html__('Not Scheduled', 'mhm-rentiva'),
            'testing_text' => esc_html__('Testing...', 'mhm-rentiva'),
            'test_results_text' => esc_html__('Test Results', 'mhm-rentiva'),
            'active_text' => esc_html__('Active', 'mhm-rentiva'),
            'registered_not_scheduled_text' => esc_html__('Registered but not scheduled', 'mhm-rentiva'),
            'not_registered_text' => esc_html__('Not registered', 'mhm-rentiva'),
            'hook_not_registered_text' => esc_html__('Hook is not registered - cannot run', 'mhm-rentiva'),
        ]);

        echo '<div class="mhm-cron-monitor-page">';
        echo '<h2>' . esc_html__('Cron Job Monitor', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Monitor and manage all plugin-related cron jobs. You can manually run any cron job from here.', 'mhm-rentiva') . '</p>';
        
        echo '<button type="button" class="button" id="mhm-refresh-cron-list-btn" style="margin-bottom: 15px;">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Refresh List', 'mhm-rentiva');
        echo '</button>';
        
        echo '<button type="button" class="button button-secondary" id="mhm-test-cron-jobs-btn" style="margin-bottom: 15px; margin-left: 10px;">';
        echo '<span class="dashicons dashicons-admin-tools"></span> ';
        echo esc_html__('Test All Cron Jobs', 'mhm-rentiva');
        echo '</button>';
        
        echo '<div id="mhm-cron-test-results" style="margin-top: 15px; margin-bottom: 15px;"></div>';
        echo '<div id="mhm-cron-list" style="margin-top: 10px;"></div>';
        echo '</div>';
    }

    /**
     * Render General Settings
     */
    public static function render_general_settings(): void
    {
        echo '<div class="mhm-settings-tab-header">';
        echo '<div></div>'; // Empty div for spacing
        echo '<button type="button" class="button button-secondary mhm-reset-tab-btn" data-tab="general">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Reset to Defaults', 'mhm-rentiva');
        echo '</button>';
        echo '</div>';
        
        // Currency & Pricing Section (includes Company & Support fields)
        self::render_section_clean('mhm_rentiva_general_section');
        
        // Site Information Section
        self::render_section_clean('mhm_rentiva_site_info_section');
        
        // Date & Time Settings Section
        self::render_section_clean('mhm_rentiva_datetime_section');
        
        // System Information Section
        self::render_section_clean('mhm_rentiva_system_info_section');
    }

    /**
     * Render Booking Management Settings
     */
    public static function render_booking_settings(): void
    {
        echo '<div class="mhm-settings-tab-header">';
        echo '<div>';
        echo '<h2>' . esc_html__('Booking Management Settings', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('Configure booking times, notifications, and payment settings.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        echo '<button type="button" class="button button-secondary mhm-reset-tab-btn" data-tab="booking">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Reset to Defaults', 'mhm-rentiva');
        echo '</button>';
        echo '</div>';
        
        // Basic booking settings
        self::render_section_clean('mhm_rentiva_booking_basic_section');
        
        // Time management settings
        self::render_section_clean('mhm_rentiva_booking_time_section');
        
        // Notification settings
        self::render_section_clean('mhm_rentiva_booking_notification_section');
        
        // Additional Services settings
        self::render_section_clean('mhm_rentiva_addons_section');
    }
    
    /**
     * Render reset button for a settings tab
     * 
     * @param string $tab Tab name
     */
    public static function render_tab_reset_button(string $tab): void
    {
        echo '<div class="mhm-settings-tab-header">';
        echo '<div></div>'; // Empty div for spacing
        echo '<button type="button" class="button button-secondary mhm-reset-tab-btn" data-tab="' . esc_attr($tab) . '">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Reset to Defaults', 'mhm-rentiva');
        echo '</button>';
        echo '</div>';
    }
}

