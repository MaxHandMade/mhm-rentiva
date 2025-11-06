<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonManager;

if (!defined('ABSPATH')) {
    exit;
}

final class AddonSettings
{
    public const PAGE = 'mhm_rentiva_addon_settings';

    public static function register(): void
    {
        // WordPress Settings API is not used - manual form processing
        add_action('wp_ajax_mhm_create_default_addons', [self::class, 'ajax_create_default_addons']);
    }

    public static function defaults(): array
    {
        return [
            'system_enabled' => '1',
            'show_prices' => '1',
            'allow_multiple' => '1',
            'display_order' => 'price_asc',
        ];
    }

    public static function get(string $key, $default = null)
    {
        $settings = get_option('mhm_rentiva_addon_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings = array_merge(self::defaults(), $settings);
        $value = array_key_exists($key, $settings) ? $settings[$key] : $default;
        // Convert null values to string
        return $value !== null ? (string) $value : '';
    }

    public static function sanitize(array $input): array
    {
        $output = [];
        $defaults = self::defaults();

        // Sanitize input
        if (!is_array($input)) {
            $input = [];
        }

        // ✅ CODE DUPLICATION RESOLVED - Common checkbox handling
        $checkbox_fields = ['system_enabled', 'show_prices', 'allow_multiple'];
        foreach ($checkbox_fields as $field) {
            $output[$field] = isset($input[$field]) ? '1' : '0';
        }

        // display_order - select field sanitization
        $display_order = $input['display_order'] ?? null;
        $allowed_orders = ['price_asc', 'price_desc', 'name_asc', 'name_desc', 'menu_order'];
        $output['display_order'] = ($display_order !== null && in_array($display_order, $allowed_orders, true))
            ? $display_order
            : $defaults['display_order'];

        return $output;
    }

    public static function admin_notices(): void
    {
        // Show success message after settings are saved
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            add_settings_error(
                'mhm_rentiva_addon_settings',
                'settings_updated',
                __('Settings saved successfully.', 'mhm-rentiva'),
                'updated'
            );
        }
    }

    public static function render_page(bool $in_tab = false): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'mhm-rentiva'));
        }

        // Handle form submission - manual POST processing (for standalone page only)
        if (!$in_tab && isset($_POST['submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'mhm_rentiva_addon_settings_save')) {
            $settings = self::sanitize($_POST['mhm_rentiva_addon_settings'] ?? []);
            update_option('mhm_rentiva_addon_settings', $settings);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'mhm-rentiva') . '</p></div>';
        }

        // Add wrapper for standalone page only
        if (!$in_tab) {
            echo '<div class="wrap mhm-rentiva-wrap">';
            echo '<h1>' . esc_html__('Additional Service Settings', 'mhm-rentiva') . '</h1>';
        }

        // Nonce field - always required (even within a tab)
        wp_nonce_field('mhm_rentiva_addon_settings_save');

        // Form wrapper - for standalone page only
        if (!$in_tab) {
            echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=addons')) . '">';
        }

        // Form fields
        echo '<table class="form-table">';
        echo '<tbody>';

        // Additional Service System
        $system_enabled = self::get('system_enabled', '1');
        echo '<tr>';
        echo '<th scope="row"><label for="system_enabled">' . esc_html__('Additional Service System', 'mhm-rentiva') . '</label></th>';
        echo '<td>';
        echo '<label><input type="checkbox" id="system_enabled" name="mhm_rentiva_addon_settings[system_enabled]" value="1" ' . checked($system_enabled, '1', false) . '> ' . esc_html__('Enable additional service system', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('When disabled, additional services are not shown in booking form.', 'mhm-rentiva') . '</p>';
        echo '</td>';
        echo '</tr>';

        // Price Display
        $show_prices = self::get('show_prices', '1');
        echo '<tr>';
        echo '<th scope="row"><label for="show_prices">' . esc_html__('Price Display', 'mhm-rentiva') . '</label></th>';
        echo '<td>';
        echo '<label><input type="checkbox" id="show_prices" name="mhm_rentiva_addon_settings[show_prices]" value="1" ' . checked($show_prices, '1', false) . '> ' . esc_html__('Show additional service prices in booking form', 'mhm-rentiva') . '</label>';
        echo '</td>';
        echo '</tr>';

        // Multiple Selection
        $allow_multiple = self::get('allow_multiple', '1');
        echo '<tr>';
        echo '<th scope="row"><label for="allow_multiple">' . esc_html__('Multiple Selection', 'mhm-rentiva') . '</label></th>';
        echo '<td>';
        echo '<label><input type="checkbox" id="allow_multiple" name="mhm_rentiva_addon_settings[allow_multiple]" value="1" ' . checked($allow_multiple, '1', false) . '> ' . esc_html__('Allow multiple additional service selection', 'mhm-rentiva') . '</label>';
        echo '</td>';
        echo '</tr>';

        // Display Order
        $display_order = self::get('display_order', 'price_asc');
        echo '<tr>';
        echo '<th scope="row"><label for="display_order">' . esc_html__('Display Order', 'mhm-rentiva') . '</label></th>';
        echo '<td>';
        echo '<select id="display_order" name="mhm_rentiva_addon_settings[display_order]">';
        $options = [
            'price_asc' => __('Price ascending', 'mhm-rentiva'),
            'price_desc' => __('Price descending', 'mhm-rentiva'),
            'name_asc' => __('Name A-Z', 'mhm-rentiva'),
            'name_desc' => __('Name Z-A', 'mhm-rentiva'),
            'menu_order' => __('Menu order', 'mhm-rentiva'),
        ];
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($display_order, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        // Submit button - for standalone page only (main page button is used within a tab)
        if (!$in_tab) {
            echo '<p class="submit">';
            echo '<input type="submit" name="submit" class="button-primary" value="' . esc_attr__('Save Settings', 'mhm-rentiva') . '" />';
            echo '</p>';
            echo '</form>';
        }

        // Default additional services creation section (outside form)
        echo '<hr style="margin: 30px 0;">';
        echo '<h2>' . esc_html__('Default Additional Services', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Automatically create default additional services for new installations.', 'mhm-rentiva') . '</p>';

        $existing_count = wp_count_posts('vehicle_addon')->publish;
        if ($existing_count > 0) {
            echo '<p class="description">' . sprintf(esc_html__('There are already %d additional services. Click the button below to create new default services.', 'mhm-rentiva'), $existing_count) . '</p>';
            echo '<button type="button" class="button" id="create-default-addons">' . esc_html__('Create Default Additional Services', 'mhm-rentiva') . '</button>';
        } else {
            echo '<p class="description">' . esc_html__('No additional services created yet. You can create default additional services with the button below.', 'mhm-rentiva') . '</p>';
            echo '<button type="button" class="button button-primary" id="create-default-addons">' . esc_html__('Create Default Additional Services', 'mhm-rentiva') . '</button>';
        }

        // Close wrapper for standalone page only
        if (!$in_tab) {
            echo '</div>';
        }

        // AJAX script for creating default addons
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#create-default-addons').on('click', function(e) {
                e.preventDefault();

                if (!confirm('<?php echo esc_js(__('Are you sure you want to create default additional services?', 'mhm-rentiva')); ?>')) {
                    return;
                }

                $(this).prop('disabled', true).text('<?php echo esc_js(__('Creating...', 'mhm-rentiva')); ?>');

                $.post(ajaxurl, {
                    action: 'mhm_create_default_addons',
                    nonce: '<?php echo wp_create_nonce('mhm_create_default_addons'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__('An error occurred. Please try again.', 'mhm-rentiva')); ?>');
                        $('#create-default-addons').prop('disabled', false).text('<?php echo esc_js(__('Create Default Additional Services', 'mhm-rentiva')); ?>');
                    }
                })
                .fail(function() {
                    alert('<?php echo esc_js(__('An error occurred. Please try again.', 'mhm-rentiva')); ?>');
                    $('#create-default-addons').prop('disabled', false).text('<?php echo esc_js(__('Create Default Additional Services', 'mhm-rentiva')); ?>');
                });
            });
        });
        </script>
        <?php
    }

    public static function ajax_create_default_addons(): void
    {
        check_ajax_referer('mhm_create_default_addons', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'mhm-rentiva'));
        }

        AddonManager::create_default_addons();

        wp_send_json_success([
            'message' => __('Default additional services created successfully.', 'mhm-rentiva')
        ]);
    }
}
