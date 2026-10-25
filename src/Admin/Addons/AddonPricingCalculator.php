<?php
/**
 * Addon Pricing Calculator.
 *
 * @package MHMRentiva\Admin\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single source of truth for add-on price calculation across rental and transfer flows.
 * Reads `mhmrentiva_addon_price` and `_mhmrentiva_addon_pricing_type` from post meta.
 *
 * Context shape: array{rental_days?:int, adults?:int, children?:int, quantity?:int}
 * (`quantity`: how many of the add-on were taken, default 1 -- Aşama 2 K12).
 */
final class AddonPricingCalculator {

    /**
     * @param array<string,int|float> $context
     */
    public static function calculate( int $addon_id, array $context ): float {
        $price    = max( 0.0, (float) get_post_meta( $addon_id, 'mhmrentiva_addon_price', true ) );
        $type     = AddonPricingType::sanitize( get_post_meta( $addon_id, '_mhmrentiva_addon_pricing_type', true ) );
        $quantity = max( 1, (int) ( $context['quantity'] ?? 1 ) );

        switch ( $type ) {
            case AddonPricingType::PER_DAY:
                $days = max( 1, (int) ( $context['rental_days'] ?? 1 ) );
                return $price * $days * $quantity;

            case AddonPricingType::PER_PASSENGER:
                $adults   = (int) ( $context['adults'] ?? 0 );
                $children = (int) ( $context['children'] ?? 0 );
                $pax      = max( 1, $adults + $children );
                return $price * $pax * $quantity;

            case AddonPricingType::PER_BOOKING:
            default:
                return $price * $quantity;
        }
    }

    /**
     * Convenience helper returning the multiplier for UI display
     * (e.g. "× 3 yolcu" or "× 5 gün"). Returns 1 for per_booking.
     *
     * @param array<string,int|float> $context
     */
    public static function multiplier( string $type, array $context ): int {
        switch ( AddonPricingType::sanitize( $type ) ) {
            case AddonPricingType::PER_DAY:
                return max( 1, (int) ( $context['rental_days'] ?? 1 ) );
            case AddonPricingType::PER_PASSENGER:
                return max( 1, (int) ( $context['adults'] ?? 0 ) + (int) ( $context['children'] ?? 0 ) );
            case AddonPricingType::PER_BOOKING:
            default:
                return 1;
        }
    }
}
