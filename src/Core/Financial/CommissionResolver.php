<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pure Domain Layer executing fractional evaluation for vendor income matrices.
 */
final class CommissionResolver
{
    /**
     * Default fallback percentage (15%).
     */
    public const DEFAULT_COMMISSION_RATE = 15.0;

    /**
     * Calculate a distinct financial breakdown dynamically parsing target vendor requirements.
     */
    public static function calculate(float $payment_amount, int $vendor_id): CommissionResult
    {
        if ($payment_amount < 0.0) {
            throw new \InvalidArgumentException('Gross payment amount securely guarded against resolving negative values.');
        }

        $rate = self::resolve_vendor_rate($vendor_id);

        // PHP strict casting prevents arbitrary exponential boundary failure
        $commission_amount = round(($payment_amount * $rate) / 100.0, 2);
        $vendor_net_amount = round($payment_amount - $commission_amount, 2);

        return new CommissionResult(
            $payment_amount,
            $commission_amount,
            $vendor_net_amount,
            $rate
        );
    }

    /**
     * Extracts and computes the hierarchical commission rate applicable contextually.
     */
    private static function resolve_vendor_rate(int $vendor_id): float
    {
        // Extract direct user override via private meta schema.
        $custom_rate = get_user_meta($vendor_id, '_mhm_vendor_commission_rate', true);
        if (is_numeric($custom_rate) && (float) $custom_rate >= 0) {
            return (float) $custom_rate;
        }

        // Fallback toward global default setting logic.
        $global_setting = get_option('mhm_rentiva_default_commission_rate');
        if (is_numeric($global_setting) && (float) $global_setting >= 0) {
            return (float) $global_setting;
        }

        return self::DEFAULT_COMMISSION_RATE;
    }
}
