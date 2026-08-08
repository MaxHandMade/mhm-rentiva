<?php
/**
 * Addon Pricing Type Enum.
 *
 * @package MHMRentiva\Admin\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pricing-type enum for vehicle add-ons.
 * Constants are the canonical strings stored in `_mhmrentiva_addon_pricing_type` post meta.
 */
final class AddonPricingType {

    public const PER_BOOKING   = 'per_booking';
    public const PER_DAY       = 'per_day';
    public const PER_PASSENGER = 'per_passenger';

    /**
     * Returns every valid pricing-type value.
     *
     * @return array<int,string>
     */
    public static function all(): array {
        return array( self::PER_BOOKING, self::PER_DAY, self::PER_PASSENGER );
    }

    /**
     * Coerce any value to a valid enum string. Unknown values fall back to PER_BOOKING.
     *
     * @param mixed $value
     */
    public static function sanitize( $value ): string {
        if ( ! is_string( $value ) ) {
            return self::PER_BOOKING;
        }
        return in_array( $value, self::all(), true ) ? $value : self::PER_BOOKING;
    }

    /** Localised label for admin UI. */
    public static function label( string $type ): string {
        switch ( $type ) {
            case self::PER_DAY:
                return __( 'Per day (rental days × price)', 'mhm-rentiva' );
            case self::PER_PASSENGER:
                return __( 'Per passenger (passenger count × price)', 'mhm-rentiva' );
            case self::PER_BOOKING:
            default:
                return __( 'Per booking (fixed)', 'mhm-rentiva' );
        }
    }
}
