<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Meta;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;

/**
 * Vehicle-level commission rate override.
 *
 * Writes `_mhmrentiva_vendor_commission_rate` post meta — the same key
 * CommissionResolver::calculate() reads for its highest-priority
 * override layer (see CommissionResolver.php, Layer 1). An empty
 * field means "no override", falling back to the vendor/tier/global
 * rate. Entirely generic: AbstractMetaBox handles rendering, nonce,
 * and saving from the get_fields() config below.
 *
 * @since 4.64.0
 */
final class VehicleCommissionRateMetaBox extends AbstractMetaBox {

    protected static function get_post_type(): string
    {
        return 'vehicle';
    }

    protected static function get_meta_box_id(): string
    {
        return 'mhmrentiva_vehicle_commission_rate';
    }

    protected static function get_title(): string
    {
        return __('Commission Rate Override', 'mhm-rentiva');
    }

    protected static function get_fields(): array
    {
        return array(
            self::get_meta_box_id() => array(
                'title'    => self::get_title(),
                'context'  => 'side',
                'priority' => 'default',
                'fields'   => array(
                    '_mhmrentiva_vendor_commission_rate' => array(
                        'type'              => 'number',
                        'label'             => __('Commission Rate (%)', 'mhm-rentiva'),
                        'description'       => __('Leave empty to use the vendor or platform-wide rate.', 'mhm-rentiva'),
                        'min'               => '0',
                        'max'               => '100',
                        'step'              => '0.01',
                        'sanitize_callback' => array( self::class, 'sanitize_rate' ),
                    ),
                ),
            ),
        );
    }

    /**
     * Sanitize the raw POST value for the commission-rate override.
     *
     * The HTML `min`/`max` attributes only constrain the browser's number
     * input — a direct POST can still submit any string. This is the
     * server-side backstop so an out-of-range or garbage value can never
     * reach CommissionResolver::calculate() as a usable override.
     *
     * - Empty stays empty ("no override" — CommissionResolver's
     *   is_numeric('') check already treats this as "fall through").
     * - Non-numeric input becomes '' rather than '0', since 0 is itself a
     *   meaningful commission rate and must not be silently assumed.
     * - Numeric input is clamped into [0, 100].
     *
     * @param mixed $value Raw unslashed POST value.
     * @return string Sanitized value to persist as post meta.
     */
    public static function sanitize_rate($value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }

        if (! is_numeric($value)) {
            return '';
        }

        $clamped = max(0.0, min(100.0, (float) $value));

        return (string) $clamped;
    }
}
