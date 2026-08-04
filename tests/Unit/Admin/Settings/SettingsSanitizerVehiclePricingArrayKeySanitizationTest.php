<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * WP.org T4 #6 follow-up: the same key-unsanitized `foreach` pattern found in
 * VehicleSettings.php (see VehicleSettingsArrayKeySanitizationTest) also
 * existed in SettingsSanitizer::sanitize_vehicle_pricing_settings() --
 * `seasonal_multipliers` iterated `foreach ( $in[...] as $key => $value )`
 * and sanitized the VALUE (floatval()/absint()/(bool)) but wrote
 * `$current_pricing[...][ $key ]` with the raw, unsanitized array KEY.
 *
 * The keys are internal slugs (spring/summer/autumn/winter -- see
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
 * T8 Görev 10c-A (K5-F1): the equivalent `discount_options` arm (and the
 * VehiclePricingSettings discount trio it solely served) was deleted
 * outright rather than fixed -- zero callers anywhere in either repo
 * (task-10a-endpoint-table.md §F1). Its two dirty-key/round-trip cases
 * below were removed with it; `weekly` no longer round-trips through this
 * sanitizer because nothing ever reads it back.
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

        $this->assertNotEmpty( $seasonal, 'The seeded seasons must survive -- a rejected key must not wipe the block.' );

        // T8 final review I-1: a sanitize_key()-clean but UNKNOWN slug is now
        // dropped outright rather than written, because writing it created a
        // season with no 'months' and the public booking form's read path then
        // TypeError'd on it. This assertion is the tighter successor to the
        // original "must still be written" expectation, which was true only
        // while any slug could create a season.
        $this->assertArrayNotHasKey( 'scriptalert1script', $seasonal, 'A sanitized-but-unknown slug must not create a season.' );

        foreach ( array_keys( $seasonal ) as $key ) {
            $this->assertIsString( $key );
            $this->assertStringNotContainsString( '<', $key, 'seasonal_multipliers key must not carry "<".' );
            $this->assertStringNotContainsString( '>', $key, 'seasonal_multipliers key must not carry ">".' );
            $this->assertMatchesRegularExpression( '/^[a-z0-9_-]*$/', $key, 'seasonal_multipliers key must be sanitize_key()-clean.' );
        }
    }

    /**
     * Legitimate slug keys (the real default: spring) must round-trip
     * unmangled -- proves the fix does not over-sanitize real data.
     */
    public function test_legitimate_seasonal_key_rounds_trip_unmangled(): void
    {
        $result = SettingsSanitizer::sanitize( array(
            'current_active_tab' => 'vehicle',
            'vehicle_pricing'    => array(
                'seasonal_multipliers' => array(
                    'spring' => array( 'multiplier' => '1.25' ),
                ),
            ),
        ) );

        $this->assertArrayHasKey( 'spring', $result['vehicle_pricing']['seasonal_multipliers'] ?? array() );
        $this->assertSame( 1.25, $result['vehicle_pricing']['seasonal_multipliers']['spring']['multiplier'] );
    }

    /**
     * T8 Görev 10c-A (K5-F1): the discount_options arm is gone -- submitting
     * it must be a silent no-op (dropped, not persisted, not fataled), not a
     * dirty-key injection vector reborn one field over.
     */
    public function test_submitting_discount_options_is_now_a_silent_no_op(): void
    {
        $result = SettingsSanitizer::sanitize( array(
            'current_active_tab' => 'vehicle',
            'vehicle_pricing'    => array(
                'discount_options' => array(
                    '<img src=x onerror=alert(1)>' => array( 'enabled' => true ),
                ),
            ),
        ) );

        $this->assertArrayNotHasKey(
            'discount_options',
            $result['vehicle_pricing'] ?? array(),
            'discount_options must not be written back at all -- the arm that used to write it is deleted (K5-F1).'
        );
    }
}
