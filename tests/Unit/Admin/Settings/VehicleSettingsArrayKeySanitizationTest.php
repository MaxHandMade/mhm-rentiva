<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Settings;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

/**
 * WP.org T4 #6 (Task B-G1f): VehicleSettings::register_settings() registered
 * a `sanitize_callback` for six array-shaped options (`mhm_selected_details`,
 * `mhm_selected_features`, `mhm_selected_equipment`, `mhm_custom_details`,
 * `mhm_custom_features`, `mhm_custom_equipment`) that ran every VALUE through
 * `sanitize_text_field()` via `array_map()` but never touched the array
 * KEYS. `array_map()` cannot see keys, so an attacker-controlled key (e.g.
 * submitted through the Settings API's options.php, or reaching
 * `update_option()` -- which core's `sanitize_option()` always routes
 * through the registered callback for) could persist raw markup as an
 * option array key.
 *
 * The `mhm_custom_*` keys are internal slugs: they gate `isset()` lookups,
 * get suffixed onto postmeta keys (`_mhm_rentiva_<key>`), and are normally
 * server-generated (`custom_<time>_<rand>`) or taxonomy-derived
 * (`tax_<taxonomy>_<slug>`) -- both already `[a-z0-9_-]`. `sanitize_key()`
 * is therefore lossless for real data and closes the injection vector.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings
 */
final class VehicleSettingsArrayKeySanitizationTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // register_setting() is normally hooked on admin_init; call directly,
        // per-test, so the sanitize_option_{$option} filters are present
        // regardless of whether admin_init fired and survive WP core's
        // per-test hook-snapshot reset between test methods/classes.
        VehicleSettings::register_settings();
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function arrayOptionNameProvider(): array
    {
        return array(
            'mhm_selected_details'   => array( 'mhm_selected_details' ),
            'mhm_selected_features'  => array( 'mhm_selected_features' ),
            'mhm_selected_equipment' => array( 'mhm_selected_equipment' ),
            'mhm_custom_details'     => array( 'mhm_custom_details' ),
            'mhm_custom_features'    => array( 'mhm_custom_features' ),
            'mhm_custom_equipment'   => array( 'mhm_custom_equipment' ),
        );
    }

    /**
     * RED proof / GREEN guard: a dirty key must not survive the round trip
     * through update_option() -> sanitize_option() -> the registered
     * sanitize_callback -> get_option().
     *
     * @dataProvider arrayOptionNameProvider
     */
    public function test_dirty_key_is_neutralized_on_save(string $option_name): void
    {
        update_option(
            $option_name,
            array(
                '<script>alert(1)</script>' => '<b>evil label</b>',
            )
        );

        $stored = get_option( $option_name );

        $this->assertIsArray( $stored, "$option_name must remain an array after sanitization." );

        foreach ( array_keys( $stored ) as $key ) {
            $this->assertIsString( $key, "$option_name: sanitized keys must be strings." );
            $this->assertStringNotContainsString( '<', $key, "$option_name: a sanitized key must not carry '<'." );
            $this->assertStringNotContainsString( '>', $key, "$option_name: a sanitized key must not carry '>'." );
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9_-]*$/',
                $key,
                "$option_name: sanitized key must be a sanitize_key()-clean slug."
            );
        }

        foreach ( $stored as $value ) {
            $this->assertStringNotContainsString( '<', (string) $value, "$option_name: value must remain sanitize_text_field()-clean." );
        }
    }

    /**
     * Legitimate keys must round-trip unmangled -- proves the fix does not
     * over-sanitize real data. mhm_custom_* keys mirror the two real-world
     * shapes: server-generated ('custom_<time>_<rand>') and taxonomy-derived
     * ('tax_<taxonomy>_<slug>'), both already [a-z0-9_-].
     *
     * @dataProvider arrayOptionNameProvider
     */
    public function test_legitimate_key_and_value_round_trip_unmangled(string $option_name): void
    {
        $input = array(
            'custom_1721488091_1234' => 'Roof Rack',
            'tax_vehicle_type_suv'   => 'SUV',
            'ok_key'                 => 'Ok Label',
        );

        update_option( $option_name, $input );

        $stored = get_option( $option_name );

        $this->assertSame(
            $input,
            $stored,
            "$option_name: legitimate slug keys and their labels must survive sanitization unchanged."
        );
    }

    /**
     * A non-array payload must not fatal and must normalize to an empty
     * array, matching the pre-fix contract.
     *
     * @dataProvider arrayOptionNameProvider
     */
    public function test_non_array_input_normalizes_to_empty_array(string $option_name): void
    {
        // sanitize_option() only invokes the registered callback when the
        // option already differs from the incoming value, so seed a non-empty
        // array first to guarantee the callback actually runs.
        update_option( $option_name, array( 'seed_key' => 'seed' ) );

        update_option( $option_name, 'not-an-array' );

        $this->assertSame( array(), get_option( $option_name ) );
    }
}
