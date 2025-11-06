<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ VEHICLE PRICING SETTINGS - Configurable Pricing Settings
 * 
 * Moves fixed pricing values to central settings
 */
final class VehiclePricingSettings
{
    const OPTION_NAME = 'mhm_rentiva_vehicle_pricing_settings';
    
    /**
     * Default settings
     */
    public static function get_default_settings(): array
    {
        return [
            'seasonal_multipliers' => [
                'spring' => [
                    'name' => __('Spring', 'mhm-rentiva'),
                    'months' => [3, 4, 5],
                    'multiplier' => 1.0,
                    'description' => __('Standard pricing', 'mhm-rentiva')
                ],
                'summer' => [
                    'name' => __('Summer', 'mhm-rentiva'),
                    'months' => [6, 7, 8],
                    'multiplier' => 1.3,
                    'description' => __('High season pricing', 'mhm-rentiva')
                ],
                'autumn' => [
                    'name' => __('Autumn', 'mhm-rentiva'),
                    'months' => [9, 10, 11],
                    'multiplier' => 1.1,
                    'description' => __('Mid season pricing', 'mhm-rentiva')
                ],
                'winter' => [
                    'name' => __('Winter', 'mhm-rentiva'),
                    'months' => [12, 1, 2],
                    'multiplier' => 0.8,
                    'description' => __('Low season pricing', 'mhm-rentiva')
                ]
            ],
            
            'discount_options' => [
                'weekly' => [
                    'name' => __('Weekly Discount', 'mhm-rentiva'),
                    'description' => __('7 days or more rental', 'mhm-rentiva'),
                    'min_days' => 7,
                    'discount_percent' => 10,
                    'type' => 'percentage',
                    'enabled' => true
                ],
                'monthly' => [
                    'name' => __('Monthly Discount', 'mhm-rentiva'),
                    'description' => __('Rental of 30 days or more', 'mhm-rentiva'),
                    'min_days' => 30,
                    'discount_percent' => 20,
                    'type' => 'percentage',
                    'enabled' => true
                ],
                'early_booking' => [
                    'name' => __('Early Booking', 'mhm-rentiva'),
                    'description' => __('Booking 30 days in advance', 'mhm-rentiva'),
                    'advance_days' => 30,
                    'discount_percent' => 5,
                    'type' => 'percentage',
                    'enabled' => true
                ],
                'loyalty' => [
                    'name' => __('Loyalty Discount', 'mhm-rentiva'),
                    'description' => __('Regular customer discount', 'mhm-rentiva'),
                    'discount_percent' => 15,
                    'type' => 'percentage',
                    'enabled' => false
                ]
            ],
            
            
            'currency_settings' => [
                'default_currency' => 'USD'
            ],
            
            'deposit_settings' => [
                'enable_deposit' => true,
                'deposit_type' => 'both', // 'fixed', 'percentage', 'both'
                'allow_no_deposit' => true,
                'deposit_refund_policy' => __('Deposit is non-refundable, deducted from total rental amount.', 'mhm-rentiva'),
                'deposit_payment_methods' => ['credit_card', 'cash', 'bank_transfer'],
                'show_deposit_in_listing' => true,
                'show_deposit_in_detail' => true,
                'required_for_booking' => false
            ],
            
            'general_settings' => [
                'min_rental_days' => 1,
                'max_rental_days' => 365,
                'default_rental_days' => 3,
                'price_calculation_method' => 'daily', // daily, weekly, monthly
                'round_prices' => true,
                'decimal_places' => 2
            ]
        ];
    }
    
    /**
     * Get settings
     */
    public static function get_settings(): array
    {
        return SettingsCore::get('vehicle_pricing', self::get_default_settings());
    }

    /**
     * Get seasonal multiplier for specific date
     */
    public static function get_seasonal_multiplier_for_date(string $date): float
    {
        $month = (int) date('n', strtotime($date));
        return self::get_seasonal_multiplier_for_month($month);
    }

    /**
     * Get seasonal multiplier for specific month
     */
    public static function get_seasonal_multiplier_for_month(int $month): float
    {
        $seasonal_multipliers = self::get_seasonal_multipliers();
        
        foreach ($seasonal_multipliers as $season) {
            if (in_array($month, $season['months'])) {
                return $season['multiplier'];
            }
        }
        
        return 1.0;
    }

    /**
     * Get season name for specific month
     */
    public static function get_season_name_for_month(int $month): string
    {
        $seasonal_multipliers = self::get_seasonal_multipliers();
        
        foreach ($seasonal_multipliers as $key => $season) {
            if (in_array($month, $season['months'])) {
                return $key;
            }
        }
        
        return 'spring';
    }

    /**
     * Discount calculation
     */
    public static function calculate_discounts(int $days, string $start_date, float $price): array
    {
        $discounts = [];
        $total_discount = 0;
        $discount_options = self::get_enabled_discounts();

        foreach ($discount_options as $key => $discount) {
            $apply_discount = false;
            $discount_amount = 0;

            switch ($key) {
                case 'weekly':
                    if ($days >= $discount['min_days']) {
                        $apply_discount = true;
                        $discount_amount = $price * ($discount['discount_percent'] / 100);
                    }
                    break;
                    
                case 'monthly':
                    if ($days >= $discount['min_days']) {
                        $apply_discount = true;
                        $discount_amount = $price * ($discount['discount_percent'] / 100);
                    }
                    break;
                    
                case 'early_booking':
                    $advance_days = (new \DateTime($start_date))->diff(new \DateTime())->days;
                    if ($advance_days >= $discount['advance_days']) {
                        $apply_discount = true;
                        $discount_amount = $price * ($discount['discount_percent'] / 100);
                    }
                    break;
                    
                case 'loyalty':
                    $apply_discount = true;
                    $discount_amount = $price * ($discount['discount_percent'] / 100);
                    break;
            }

            if ($apply_discount && $discount_amount > 0) {
                $discounts[$key] = [
                    'name' => $discount['name'],
                    'amount' => $discount_amount,
                    'percent' => $discount['discount_percent']
                ];
                $total_discount += $discount_amount;
            }
        }

        return [
            'discounts' => $discounts,
            'total_discount' => $total_discount
        ];
    }

    /**
     * Additional service price calculation (no longer used - AddonManager is used)
     */
    public static function calculate_addon_prices(array $addons, int $days): float
    {
        return 0;
    }


    /**
     * Sadece etkin indirimleri getir
     */
    public static function get_enabled_discounts(): array
    {
        $discount_options = self::get_discount_options();
        return array_filter($discount_options, function($discount) {
            return $discount['enabled'] ?? false;
        });
    }

    
    /**
     * Get seasonal multipliers
     */
    public static function get_seasonal_multipliers(): array
    {
        $settings = self::get_settings();
        return $settings['seasonal_multipliers'] ?? self::get_default_settings()['seasonal_multipliers'];
    }
    
    /**
     * Get discount options
     */
    public static function get_discount_options(): array
    {
        $settings = self::get_settings();
        return $settings['discount_options'] ?? self::get_default_settings()['discount_options'];
    }
    
    
    
    
    /**
     * Get currency settings
     */
    public static function get_currency_settings(): array
    {
        $settings = self::get_settings();
        return $settings['currency_settings'] ?? self::get_default_settings()['currency_settings'];
    }
    
    /**
     * Get general settings
     */
    public static function get_general_settings(): array
    {
        $settings = self::get_settings();
        return $settings['general_settings'] ?? self::get_default_settings()['general_settings'];
    }
    
    /**
     * Get deposit settings
     */
    public static function get_deposit_settings(): array
    {
        $settings = self::get_settings();
        return $settings['deposit_settings'] ?? self::get_default_settings()['deposit_settings'];
    }
    
    
    /**
     * Save settings
     */
    public static function save_settings(array $settings): bool
    {
        return SettingsCore::set('vehicle_pricing', $settings);
    }
    
    /**
     * Clear settings
     */
    public static function clear_settings(): bool
    {
        return SettingsCore::delete('vehicle_pricing');
    }
    
    /**
     * Reset settings
     */
    public static function reset_settings(): bool
    {
        return self::save_settings(self::get_default_settings());
    }
}
