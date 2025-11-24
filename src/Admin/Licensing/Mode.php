<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

final class Mode
{
    // Gateways
    public const FEATURE_GATEWAY_OFFLINE = 'gateway_offline';

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
            case self::FEATURE_GATEWAY_OFFLINE:
                return true;
            case self::FEATURE_EXPORT:
            case self::FEATURE_REPORTS_ADV:
            case self::FEATURE_MESSAGES:
                return false;
        }

        return false;
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

        if (self::featureEnabled(self::FEATURE_GATEWAY_OFFLINE)) {
            $allowed[] = 'offline';
        }

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
}
