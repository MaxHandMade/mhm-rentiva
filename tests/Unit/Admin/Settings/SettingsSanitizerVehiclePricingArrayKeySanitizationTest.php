<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * WP.org T4 #6 follow-up: the same key-unsanitized `foreach` pattern found in
 * VehicleSettings.php (see VehicleSettingsArrayKeySanitizationTest) also
 * existed in SettingsSanitizer::sanitize_vehicle_pricing_settings() --
 * `seasonal_multipliers` and `discount_options` iterated
 * `foreach ( $in[...] as $key => $value )` and sanitized the VALUE
 * (floatval()/absint()/(bool)) but wrote `$current_pricing[...][ $key ]`
 * with the raw, unsanitized array KEY.
 *
 * The keys are internal slugs (spring/summer/autumn/winter and
 * weekly/monthly/early_booking/loyalty -- see
 * VehiclePricingSettings::get_default_settings()) used purely as array
 * lookups, so `sanitize_key()` is the correct, lossless treatment -- same
 * decision as VehicleSettings.php's custom_* options.
 *
 * Note: this vehicle_pricing config is separately known to be orphaned/
 * unreachable in the current UI (tracked elsewhere) -- that reachability gap
 * is NOT this test's concern. This test only proves the sanitizer itself
 * can no longer persist a dirty key if/when that path is ever driven by
 * real input (Settings API form, or any future direct `update_option()`
 * call on `mhmrentiva_settings`).
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer
 */
final class SettingsSanitizerVehiclePricingArrayKeySanitizationTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        delete_option( 'mhmrentiva_settings' );
        parent::tearDown();
    }

    public function test_dirty_seasonal_multiplier_key_is_neutralized(): void
    {
        $result = SettingsSanitizer::sanitize( array(
            'current_active_tab' => 'vehicle',
            'vehicle_pricing'    => array(
                'seasonal_multipliers' => array(
                    '<script>alert(1)</script>' => array( 'multiplier' => '1.75' ),
                ),
            ),
        ) );

        $seasonal = $result['vehicle_pricing']['seasonal_multipliers'] ?? array();

        $this->assertNotEmpty( $seasonal, 'A sanitized (non-empty) key must still be written -- the record must not be dropped entirely.' );

        foreach ( array_keys( $seasonal ) as $key ) {
            $this->assertIsString( $key );
            $this->assertStringNotContainsString( '<', $key, 'seasonal_multipliers key must not carry "<".' );
            $this->assertStringNotContainsString( '>', $key, 'seasonal_multipliers key must not carry ">".' );
            $this->assertMatchesRegularExpression( '/^[a-z0-9_-]*$/', $key, 'seasonal_multipliers key must be sanitize_key()-clean.' );
        }
    }

    public function test_dirty_discount_option_key_is_neutralized(): void
    {
        $result = SettingsSanitizer::sanitize( array(
            'current_active_tab' => 'vehicle',
            'vehicle_pricing'    => array(
                'discount_options' => array(
                    '<img src=x onerror=alert(1)>' => array(
                        'enabled'          => true,
                        'min_days'         => 7,
                        'advance_days'     => 0,
                        'discount_percent' => 10,
                    ),
                ),
            ),
        ) );

        $discounts = $result['vehicle_pricing']['discount_options'] ?? array();

        $this->assertNotEmpty( $discounts, 'A sanitized (non-empty) key must still be written -- the record must not be dropped entirely.' );

        foreach ( array_keys( $discounts ) as $key ) {
            $this->assertIsString( $key );
            $this->assertStringNotContainsString( '<', $key, 'discount_options key must not carry "<".' );
            $this->assertStringNotContainsString( '>', $key, 'discount_options key must not carry ">".' );
            $this->assertMatchesRegularExpression( '/^[a-z0-9_-]*$/', $key, 'discount_options key must be sanitize_key()-clean.' );
        }
    }

    /**
     * Legitimate slug keys (the real defaults: spring/weekly) must round-trip
     * unmangled -- proves the fix does not over-sanitize real data.
     */
    public function test_legitimate_seasonal_and_discount_keys_round_trip_unmangled(): void
    {
        $result = SettingsSanitizer::sanitize( array(
            'current_active_tab' => 'vehicle',
            'vehicle_pricing'    => array(
                'seasonal_multipliers' => array(
                    'spring' => array( 'multiplier' => '1.25' ),
                ),
                'discount_options'     => array(
                    'weekly' => array(
                        'enabled'          => true,
                        'min_days'         => 7,
                        'advance_days'     => 0,
                        'discount_percent' => 10,
                    ),
                ),
            ),
        ) );

        $this->assertArrayHasKey( 'spring', $result['vehicle_pricing']['seasonal_multipliers'] ?? array() );
        $this->assertSame( 1.25, $result['vehicle_pricing']['seasonal_multipliers']['spring']['multiplier'] );

        $this->assertSame(
            array(
                'enabled'          => true,
                'min_days'         => 7,
                'advance_days'     => 0,
                'discount_percent' => 10,
            ),
            $result['vehicle_pricing']['discount_options']['weekly'] ?? null
        );
    }
}
