<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

final class Mode
{
    // Gateways


    // Pro-only features
    public const FEATURE_EXPORT          = 'export';
    public const FEATURE_REPORTS_ADV     = 'reports_adv';
    public const FEATURE_MESSAGES        = 'messages';

    /**
     * Check if Pro version is active
     * 
     * @return bool True if Pro
     */
    public static function isPro(): bool
    {
        return LicenseManager::instance()->isActive();
    }

    /**
     * Check if Lite version is active
     * 
     * @return bool True if Lite
     */
    public static function isLite(): bool
    {
        return !self::isPro();
    }

    /**
     * Check if feature is enabled
     * 
     * @param string $feature Feature name
     * @return bool True if enabled
     */
    public static function featureEnabled(string $feature): bool
    {
        if (self::isPro()) {
            return true;
        }

        switch ($feature) {
            case self::FEATURE_EXPORT: // Export is now available for Lite (CSV only)
                return true;
            case self::FEATURE_REPORTS_ADV:
            case self::FEATURE_MESSAGES:
                return false;
        }

        return false;
    }
// ... (skip until get_pro_features_list)
    /**
     * Get Pro-only features list
     * 
     * @return array List of Pro features
     */
    public static function get_pro_features_list(): array
    {
        return [
            __('Advanced Reporting (FEATURE_REPORTS_ADV)', 'mhm-rentiva'),
            __('Messaging System (FEATURE_MESSAGES)', 'mhm-rentiva'),
            __('Full REST API Access', 'mhm-rentiva'),
            __('JSON Export Format', 'mhm-rentiva'),
            __('Unlimited Date Range for Reports', 'mhm-rentiva'),
            __('Unlimited Report Rows', 'mhm-rentiva'),
            __('Email Notifications', 'mhm-rentiva'),
            __('GDPR Compliance Tools', 'mhm-rentiva'),
        ];
    }

    /**
     * Get maximum vehicles allowed
     * 
     * @return int Maximum vehicles
     */
    public static function maxVehicles(): int
    {
        return self::isPro() ? PHP_INT_MAX : (int) apply_filters('mhm_rentiva_lite_max_vehicles', 3);
    }

    /**
     * Get maximum bookings allowed
     * 
     * @return int Maximum bookings
     */
    public static function maxBookings(): int
    {
        return self::isPro() ? PHP_INT_MAX : (int) apply_filters('mhm_rentiva_lite_max_bookings', 50);
    }

    /**
     * Get maximum customers allowed
     * 
     * @return int Maximum customers
     */
    public static function maxCustomers(): int
    {
        return self::isPro() ? PHP_INT_MAX : (int) apply_filters('mhm_rentiva_lite_max_customers', 3);
    }

    /**
     * Get maximum report range days
     * 
     * @return int Maximum days
     */
    public static function reportsMaxRangeDays(): int
    {
        return self::isPro() ? PHP_INT_MAX : (int) apply_filters('mhm_rentiva_lite_reports_max_days', 30);
    }

    /**
     * Get maximum report rows
     * 
     * @return int Maximum rows
     */
    public static function reportsMaxRows(): int
    {
        return self::isPro() ? PHP_INT_MAX : (int) apply_filters('mhm_rentiva_lite_reports_max_rows', 500);
    }

    /**
     * Get allowed payment gateways
     * 
     * @return array Allowed gateways
     */
    public static function allowedGateways(): array
    {
        $allowed = [];
        return apply_filters('mhm_rentiva_allowed_gateways', $allowed, self::isPro());
    }

    /**
     * Global is_pro() function - for use throughout the project
     * 
     * @return bool True if Pro
     */
    public static function is_pro(): bool
    {
        return self::isPro();
    }

    /**
     * Get centralized Lite vs Pro comparison table data
     * This is the single source of truth for feature comparison across all admin pages
     * 
     * @return array Comparison data with 'name', 'lite', 'pro' keys
     */
    public static function get_comparison_table_data(): array
    {
        return [
            [
                'name' => __('Maximum Vehicles', 'mhm-rentiva'),
                'lite' => sprintf(
                    /* translators: %d: maximum number of vehicles allowed in Lite version. */
                    __('%d vehicles', 'mhm-rentiva'),
                    (int) apply_filters('mhm_rentiva_lite_max_vehicles', 3)
                ),
                'pro' => __('Unlimited', 'mhm-rentiva'),
            ],
            [
                'name' => __('Maximum Bookings', 'mhm-rentiva'),
                'lite' => sprintf(
                    /* translators: %d: maximum number of bookings allowed in Lite version. */
                    __('%d bookings', 'mhm-rentiva'),
                    (int) apply_filters('mhm_rentiva_lite_max_bookings', 50)
                ),
                'pro' => __('Unlimited', 'mhm-rentiva'),
            ],
            [
                'name' => __('Maximum Customers', 'mhm-rentiva'),
                'lite' => sprintf(
                    /* translators: %d: maximum number of customers allowed in Lite version. */
                    __('%d customers', 'mhm-rentiva'),
                    (int) apply_filters('mhm_rentiva_lite_max_customers', 3)
                ),
                'pro' => __('Unlimited', 'mhm-rentiva'),
            ],
            [
                'name' => __('Maximum Addons', 'mhm-rentiva'),
                'lite' => sprintf(
                    /* translators: %d: maximum number of addon services allowed in Lite version. */
                    __('%d addon services', 'mhm-rentiva'),
                    4
                ),
                'pro' => __('Unlimited', 'mhm-rentiva'),
            ],
            [
                'name' => __('Report Date Range', 'mhm-rentiva'),
                'lite' => sprintf(
                    /* translators: %d: maximum number of days for report range in Lite version. */
                    __('%d days', 'mhm-rentiva'),
                    (int) apply_filters('mhm_rentiva_lite_reports_max_days', 30)
                ),
                'pro' => __('Unlimited', 'mhm-rentiva'),
            ],
            [
                'name' => __('Report Rows', 'mhm-rentiva'),
                'lite' => sprintf(
                    /* translators: %d: maximum number of rows for reports in Lite version. */
                    __('%d rows', 'mhm-rentiva'),
                    (int) apply_filters('mhm_rentiva_lite_reports_max_rows', 500)
                ),
                'pro' => __('Unlimited', 'mhm-rentiva'),
            ],
            [
                'name' => __('Payment Gateways', 'mhm-rentiva'),
                'lite' => __('WooCommerce', 'mhm-rentiva'),
                'pro' => __('WooCommerce', 'mhm-rentiva'),
            ],
            [
                'name' => __('Export Formats', 'mhm-rentiva'),
                'lite' => esc_html__('CSV', 'mhm-rentiva'),
                'pro' => esc_html__('CSV, JSON', 'mhm-rentiva'),
            ],
            [
                'name' => __('Advanced Reports', 'mhm-rentiva'),
                'lite' => __('Not available', 'mhm-rentiva'),
                'lite_icon' => '❌',
                'pro' => __('Available', 'mhm-rentiva'),
                'pro_icon' => '✅',
            ],
            [
                'name' => __('Messaging System', 'mhm-rentiva'),
                'lite' => __('Not available', 'mhm-rentiva'),
                'lite_icon' => '❌',
                'pro' => __('Available', 'mhm-rentiva'),
                'pro_icon' => '✅',
            ],
            [
                'name' => __('REST API Access', 'mhm-rentiva'),
                'lite' => __('Limited', 'mhm-rentiva'),
                'pro' => __('Full REST API', 'mhm-rentiva'),
            ],
        ];
    }



    /**
     * Helper to render comparison table HTML
     * 
     * @param bool $compact Whether to use compact mode (no icons, shorter text)
     * @return void
     */
    public static function render_comparison_table(bool $compact = false): void
    {
        $features = self::get_comparison_table_data();

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Feature', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Lite', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Pro', 'mhm-rentiva') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($features as $feature) {
            $lite_display = isset($feature['lite_icon']) && !$compact
                ? $feature['lite_icon'] . ' ' . $feature['lite']
                : $feature['lite'];

            $pro_display = isset($feature['pro_icon']) && !$compact
                ? $feature['pro_icon'] . ' ' . $feature['pro']
                : $feature['pro'];

            echo '<tr>';
            echo '<td><strong>' . esc_html($feature['name']) . '</strong></td>';
            echo '<td>' . esc_html($lite_display) . '</td>';
            echo '<td><strong>' . esc_html($pro_display) . '</strong></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }
}
