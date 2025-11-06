<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;

if (!defined('ABSPATH')) {
    exit;
}

final class LicenseAdmin
{
    /**
     * Safe sanitize text field that handles null values
     * 
     * @param mixed $value Value to sanitize
     * @return string Sanitized value
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_menu'], 14); // Priority 14 to come after ShortcodeSettings
        add_action('admin_post_mhm_rentiva_activate_license', [self::class, 'handle_activation']);
        add_action('admin_post_mhm_rentiva_deactivate_license', [self::class, 'handle_deactivation']);
        add_action('admin_notices', [self::class, 'admin_notices']);
    }

    public static function add_menu(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __('License Management', 'mhm-rentiva'),
            __('License', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-license',
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'mhm-rentiva'));
        }

        $license = LicenseManager::instance();
        $license_data = $license->get();
        $is_active = $license->isActive();
        $is_dev_mode = $license->isDevelopmentEnvironment();

        echo '<div class="wrap mhm-rentiva-wrap">';
        echo '<h1>' . esc_html__('License Management', 'mhm-rentiva') . '</h1>';
        echo '<p class="description">' . esc_html__('Enter your license key to enable Pro features (online payments, unlimited vehicles, export, advanced reports).', 'mhm-rentiva') . '</p>';


        // Developer mode warning
        if ($is_dev_mode) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>' . esc_html__('🚀 Developer Mode Active', 'mhm-rentiva') . '</strong><br>';
            echo esc_html__('Automatic developer mode active (development environment detected). All Pro features enabled.', 'mhm-rentiva');
            echo '</p></div>';
        }

        // License status
        echo '<h2>' . esc_html__('License Status', 'mhm-rentiva') . '</h2>';

        if ($is_active) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>' . esc_html__('✅ Pro License Active', 'mhm-rentiva') . '</strong></p>';
            echo '</div>';

            if ($license_data && isset($license_data['expires'])) {
                $expires_date = date_i18n(get_option('date_format'), $license_data['expires']);
                echo '<p><strong>' . esc_html__('Expiry Date:', 'mhm-rentiva') . '</strong> ' . esc_html($expires_date) . '</p>';
            }
            
            echo '<p>' . esc_html__('All Pro features active: Online payments, unlimited vehicles, export, advanced reports.', 'mhm-rentiva') . '</p>';
        } else {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>' . esc_html__('⚠️ Lite Version', 'mhm-rentiva') . '</strong></p>';
            echo '</div>';
            echo '<p>' . esc_html__('You are currently using the Lite version. A license key is required for Pro features.', 'mhm-rentiva') . '</p>';
            echo '<p><strong>' . esc_html__('Lite Version Limits:', 'mhm-rentiva') . '</strong></p>';
            echo '<ul>';
            echo '<li>' . esc_html__('Maximum 3 vehicles can be added', 'mhm-rentiva') . '</li>';
            echo '<li>' . esc_html__('Maximum 50 bookings can be made', 'mhm-rentiva') . '</li>';
            echo '<li>' . esc_html__('Maximum 3 customers can be added', 'mhm-rentiva') . '</li>';
            echo '<li>' . esc_html__('Offline payment + PayPal available', 'mhm-rentiva') . '</li>';
            echo '<li>' . esc_html__('Export feature not available', 'mhm-rentiva') . '</li>';
            echo '<li>' . esc_html__('Report range limited to 30 days', 'mhm-rentiva') . '</li>';
            echo '</ul>';
        }

        // License activation form - always show
        echo '<h2>' . esc_html__('License Activation', 'mhm-rentiva') . '</h2>';
        
        if ($is_active && !$is_dev_mode) {
            echo '<div class="notice notice-info inline">';
            echo '<p>' . esc_html__('Your license is active. To change to a new license key, first deactivate the current license.', 'mhm-rentiva') . '</p>';
            echo '</div>';
        }
        
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('mhm_rentiva_license_action', 'mhm_rentiva_license_nonce');
        echo '<input type="hidden" name="action" value="mhm_rentiva_activate_license">';
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="license_key">' . esc_html__('License Key', 'mhm-rentiva') . '</label></th>';
        echo '<td>';

        // Show current license key
        if ($is_active && !empty($license_data['key'])) {
            echo '<p><strong>' . esc_html__('Current License:', 'mhm-rentiva') . '</strong> ' . esc_html($license_data['key']) . '</p>';
            echo '<input type="text" id="license_key" name="license_key" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX">';
            echo '<p class="description">' . esc_html__('Enter new license key (to change current license).', 'mhm-rentiva') . '</p>';
        } else {
            echo '<input type="text" id="license_key" name="license_key" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" required>';
            echo '<p class="description">' . esc_html__('Paste your Pro license key and click the Activate button.', 'mhm-rentiva') . '</p>';
        }
        
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        submit_button(__('Activate License', 'mhm-rentiva'), 'primary', 'submit', false);
        echo '</form>';

        // License deactivation - always show
        echo '<h2>' . esc_html__('License Management', 'mhm-rentiva') . '</h2>';
        
        if ($is_dev_mode) {
            echo '<div class="notice notice-info inline">';
            echo '<p>' . esc_html__('You are running in developer mode. You can deactivate to test real license.', 'mhm-rentiva') . '</p>';
            echo '</div>';
        }
        
        echo '<p>' . esc_html__('If you want to deactivate your license, click the button below. This will disable Pro features.', 'mhm-rentiva') . '</p>';
        
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Are you sure you want to deactivate the license?', 'mhm-rentiva')) . '\')">';
        wp_nonce_field('mhm_rentiva_license_action', 'mhm_rentiva_license_nonce');
        echo '<input type="hidden" name="action" value="mhm_rentiva_deactivate_license">';
        
        submit_button(__('Deactivate License', 'mhm-rentiva'), 'secondary', 'submit', false);
        echo '</form>';

        // Lite vs Pro comparison
        self::render_feature_comparison();

        echo '</div>';
    }

    public static function handle_activation(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission for this operation.', 'mhm-rentiva'));
        }

        if (!isset($_POST['mhm_rentiva_license_nonce']) || 
            !wp_verify_nonce($_POST['mhm_rentiva_license_nonce'], 'mhm_rentiva_license_action')) {
            wp_die(__('Security check failed.', 'mhm-rentiva'));
        }

        $license_key = self::sanitize_text_field_safe($_POST['license_key'] ?? '');
        if (empty($license_key)) {
            wp_redirect(add_query_arg(['license' => 'error', 'message' => 'empty_key'], wp_get_referer()));
            exit;
        }

        // License validation (example - real API integration required)
        $result = self::validate_license($license_key);
        
        if ($result['success']) {
            $license = LicenseManager::instance();
            $license->setLicenseData([
                'key' => $license_key,
                'status' => 'active',
                'expires' => $result['expires'] ?? (time() + YEAR_IN_SECONDS),
                'activated_at' => time(),
            ]);
            
            wp_redirect(add_query_arg(['license' => 'activated'], wp_get_referer()));
        } else {
            wp_redirect(add_query_arg(['license' => 'error', 'message' => $result['message'] ?? 'invalid'], wp_get_referer()));
        }
        
        exit;
    }

    public static function handle_deactivation(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission for this operation.', 'mhm-rentiva'));
        }

        if (!isset($_POST['mhm_rentiva_license_nonce']) || 
            !wp_verify_nonce($_POST['mhm_rentiva_license_nonce'], 'mhm_rentiva_license_action')) {
            wp_die(__('Security check failed.', 'mhm-rentiva'));
        }

        $license = LicenseManager::instance();
        $license->clearLicense();
        
        wp_redirect(add_query_arg(['license' => 'deactivated'], wp_get_referer()));
        exit;
    }

    public static function admin_notices(): void
    {
        if (!isset($_GET['license'])) {
            return;
        }

        $message = $_GET['license'];
        $error_message = $_GET['message'] ?? '';

        switch ($message) {
            case 'activated':
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . esc_html__('✅ License successfully activated!', 'mhm-rentiva') . '</p>';
                echo '</div>';
                break;
                
            case 'deactivated':
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p>' . esc_html__('ℹ️ License deactivated.', 'mhm-rentiva') . '</p>';
                echo '</div>';
                break;
                
            case 'error':
                $error_text = match($error_message) {
                    'empty_key' => __('License key cannot be empty.', 'mhm-rentiva'),
                    'invalid' => __('Invalid license key.', 'mhm-rentiva'),
                    'expired' => __('License has expired.', 'mhm-rentiva'),
                    default => __('License activation failed.', 'mhm-rentiva'),
                };
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . esc_html($error_text) . '</p>';
                echo '</div>';
                break;
        }
    }

    private static function validate_license(string $license_key): array
    {
        // Example license validation - real API integration required
        if (strlen($license_key) < 10) {
            return ['success' => false, 'message' => 'invalid'];
        }

        // Test license keys
        $test_keys = [
            'TEST-LICENSE-KEY-12345',
            'DEMO-LICENSE-KEY-67890',
            'PRO-LICENSE-KEY-ABCDE'
        ];

        if (in_array($license_key, $test_keys, true)) {
            return [
                'success' => true,
                'expires' => time() + YEAR_IN_SECONDS,
            ];
        }

        // Real API call will be here
        // For now, simple validation
        if (strlen($license_key) >= 15 && str_contains($license_key, '-')) {
            return [
                'success' => true,
                'expires' => time() + YEAR_IN_SECONDS,
            ];
        }

        return ['success' => false, 'message' => 'invalid'];
    }

    private static function render_feature_comparison(): void
    {
        echo '<h2>' . esc_html__('Lite vs Pro Comparison', 'mhm-rentiva') . '</h2>';

        $features = [
            __('Maximum Vehicle Count', 'mhm-rentiva') => ['Lite: 3', 'Pro: Unlimited'],
            __('Maximum Bookings', 'mhm-rentiva') => ['Lite: 50', 'Pro: Unlimited'],
            __('Maximum Customers', 'mhm-rentiva') => ['Lite: 3', 'Pro: Unlimited'],
            __('Payment Gateways', 'mhm-rentiva') => ['Lite: Offline + PayPal', 'Pro: PayTR + Stripe + PayPal + Offline'],
            __('Report Range', 'mhm-rentiva') => ['Lite: 30 days', 'Pro: Unlimited'],
            __('Export Feature', 'mhm-rentiva') => ['Lite: ❌', 'Pro: ✅'],
            __('Messaging System', 'mhm-rentiva') => ['Lite: ❌', 'Pro: ✅'],
        ];

        echo '<table class="widefat">';
        echo '<thead><tr><th>' . esc_html__('Feature', 'mhm-rentiva') . '</th><th>' . esc_html__('Lite', 'mhm-rentiva') . '</th><th>' . esc_html__('Pro', 'mhm-rentiva') . '</th></tr></thead>';
        echo '<tbody>';

        foreach ($features as $feature => $values) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($feature) . '</strong></td>';
            echo '<td>' . esc_html($values[0]) . '</td>';
            echo '<td>' . esc_html($values[1]) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }
}
