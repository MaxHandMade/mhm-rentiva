<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Settings;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_Ajax_UnitTestCase;

/**
 * WP.org T4 #6, second half: the AJAX write path.
 *
 * VehicleSettingsArrayKeySanitizationTest locks the `register_setting()`
 * sanitize_callback -- the option-filter side of the defect.
 * This test locks the OTHER side: the handler that writes the options.
 * `save_definitions_payload()` built its payload with
 * `array_map( 'sanitize_text_field', ... )`, which cannot touch array KEYS,
 * so a submitted key such as `<script>k</script>` reached `update_option()`
 * raw.
 *
 * The handler is invoked directly rather than through `_handleAjax()` ON
 * PURPOSE: `_handleAjax()` fires `admin_init`, which runs
 * VehicleSettings::register_settings() and installs the
 * `sanitize_option_mhm_custom_*` filters. Those filters would sanitize the
 * keys on the way into the database no matter what the handler did, so a test
 * that went through `_handleAjax()` stayed green with the handler's own
 * sanitization deleted -- it would have locked nothing. Calling
 * ajax_save_settings() directly keeps the option filters out of the picture,
 * so the assertions below can only pass if the handler sanitizes keys itself.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings
 */
final class VehicleSettingsDefinitionsPayloadKeySanitizationTest extends WP_Ajax_UnitTestCase
{
    public function tearDown(): void
    {
        $_POST    = array();
        $_REQUEST = array();
        wp_set_current_user(0);
        parent::tearDown();
    }

    /**
     * Run a real AJAX handler in-process. wp_send_json_success() echoes and
     * then dies, so the output is buffered and the die exception swallowed.
     * The suite's wp_die() handler calls ob_get_clean() itself, so unwind back
     * to the entry level rather than assuming one buffer is still open.
     *
     * @param callable():void $handler
     */
    private function invoke_handler(callable $handler): void
    {
        $level = ob_get_level();
        ob_start();
        try {
            $handler();
        } catch (\WPAjaxDieContinueException $e) {
            // Expected: wp_send_json_success() dies in the test suite.
        }
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }

    private function invoke_save_settings(): void
    {
        $this->invoke_handler(static function (): void {
            VehicleSettings::ajax_save_settings();
        });
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function seed_request(string $post_key, array $payload): void
    {
        wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

        $_POST['action']     = 'mhm_rentiva_save_vehicle_settings';
        $_POST['nonce']      = wp_create_nonce('vehicle_settings_nonce');
        $_POST['sub_action'] = 'save_all';
        $_POST[ $post_key ]  = $payload;
        $_REQUEST            = $_POST;
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function customOptionProvider(): array
    {
        return array(
            'custom_details'   => array( 'custom_details', 'mhm_custom_details' ),
            'custom_features'  => array( 'custom_features', 'mhm_custom_features' ),
            'custom_equipment' => array( 'custom_equipment', 'mhm_custom_equipment' ),
        );
    }

    /**
     * @dataProvider customOptionProvider
     */
    public function test_custom_definition_keys_are_sanitized_before_persist(string $post_key, string $option_name): void
    {
        delete_option($option_name);

        $this->seed_request(
            $post_key,
            array(
                '<script>k</script>' => '<b>Label</b>',
                'ok_key'             => 'Ok',
            )
        );

        $this->invoke_save_settings();

        $saved = get_option($option_name);

        $this->assertIsArray($saved, "$option_name must be stored as an array.");
        $this->assertNotEmpty($saved, "$option_name must have been written by the handler.");

        foreach (array_keys($saved) as $k) {
            $this->assertSame(sanitize_key((string) $k), (string) $k, "key was not sanitized: $k");
        }

        $this->assertArrayHasKey('ok_key', $saved, 'A legitimate slug key must survive unmangled.');
        $this->assertSame('Ok', $saved['ok_key'], 'A legitimate value must survive unmangled.');
    }

    /**
     * Values keep their existing sanitize_text_field() treatment, and the two
     * real-world key shapes -- server-generated (`custom_<time>_<rand>`) and
     * taxonomy-derived (`tax_<taxonomy>_<slug>`) -- round-trip unchanged, so
     * the key fix cannot be accused of over-sanitizing real data.
     */
    public function test_values_stay_sanitized_and_legitimate_keys_round_trip(): void
    {
        delete_option('mhm_custom_details');

        $this->seed_request(
            'custom_details',
            array(
                'custom_1721488091_1234' => 'Roof Rack',
                'tax_vehicle_type_suv'   => 'SUV',
                '<script>k</script>'     => '<b>Label</b>',
            )
        );

        $this->invoke_save_settings();

        $saved = get_option('mhm_custom_details');

        $this->assertIsArray($saved);
        $this->assertSame('Roof Rack', $saved['custom_1721488091_1234'] ?? null);
        $this->assertSame('SUV', $saved['tax_vehicle_type_suv'] ?? null);

        foreach ($saved as $key => $value) {
            $this->assertStringNotContainsString('<', (string) $key, 'A stored key must not carry markup.');
            $this->assertStringNotContainsString('<', (string) $value, 'A stored value must not carry markup.');
        }
    }

    /**
     * ajax_update_field_labels() runs its own key loop over the same option
     * family and must normalize keys the same way. It cannot create a new
     * option entry (it only rewrites keys that already exist), so what is
     * locked here is the normalization itself: a label submitted under an
     * unnormalized spelling of an existing slug must reach that slug.
     * `sanitize_text_field()` alone left `OK_KEY` as-is and the isset() lookup
     * missed, silently dropping the rename; `sanitize_key()` lowercases it.
     */
    public function test_field_label_keys_are_normalized_before_lookup(): void
    {
        update_option('mhm_custom_details', array( 'ok_key' => 'Old' ));

        wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

        $_POST['action'] = 'mhm_rentiva_update_field_labels';
        $_POST['nonce']  = wp_create_nonce('vehicle_settings_nonce');
        $_POST['type']   = 'details';
        $_POST['labels'] = array( 'OK_KEY' => 'New Label' );
        $_REQUEST        = $_POST;

        $this->invoke_handler(static function (): void {
            VehicleSettings::ajax_update_field_labels();
        });

        $stored = get_option('mhm_custom_details');

        $this->assertIsArray($stored);
        $this->assertSame('New Label', $stored['ok_key'] ?? null, 'The rename must land on the sanitize_key() slug.');

        foreach (array_keys($stored) as $k) {
            $this->assertSame(sanitize_key((string) $k), (string) $k, "stored key was not sanitized: $k");
        }
    }
}
