<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings;
use MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings;
use WP_UnitTestCase;

/**
 * Görev 9 / F19 — the seasonal-multiplier controls used to exist ONLY inside
 * VehiclePricingSettings::render_settings_section(), a method with zero
 * callers: VehicleManagementSettings::render_settings_section() is the
 * actual live renderer for the Vehicle tab's SECTION_PRICING (see
 * VehicleManagementSettings.php:60 -> SettingsViewHelper::render_section_cleanly()).
 * An administrator could never reach the fields, so the price Rentiva
 * actually quotes never carried a seasonal multiplier a real admin had set:
 * BookingForm.php's seasonal branch always read the untouched default
 * (VehiclePricingSettings::get_seasonal_multiplier_for_date(), consumed at
 * BookingForm.php:~1124) because nothing could ever write anything else.
 *
 * These tests render the LIVE tab (not the orphan) and drive a save through
 * the real sanitize_callback, proving the field is now both reachable and
 * that its POST is not dropped on the way to the option the reader consumes.
 *
 * @covers \MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer
 * @covers \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings
 */
final class VehicleTabSeasonalPricingFieldsTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Populates $wp_settings_sections / $wp_settings_fields for the
        // 'vehicle' tab. add_settings_field()/add_settings_section() overwrite
        // by id, so calling this unconditionally is safe regardless of test order.
        VehicleManagementSettings::register();
    }

    protected function tearDown(): void
    {
        delete_option('mhmrentiva_settings');
        parent::tearDown();
    }

    private function render_live_vehicle_tab(): string
    {
        ob_start();
        VehicleManagementSettings::render_settings_section();
        return (string) ob_get_clean();
    }

    /**
     * The duplicate-render guard the brief requires: each moved field name
     * must appear in the live tab's own output EXACTLY once. This also
     * doubles as the reachability proof — before the fix, the count is 0
     * (never rendered anywhere), not 1.
     */
    public function test_all_seasonal_multiplier_fields_render_exactly_once_on_the_live_vehicle_tab(): void
    {
        $html    = $this->render_live_vehicle_tab();
        $seasons = VehiclePricingSettings::get_seasonal_multipliers();
        $this->assertNotEmpty($seasons, 'Sanity: default seasons must exist for this test to mean anything.');

        foreach (array_keys($seasons) as $key) {
            $field_name = 'mhmrentiva_settings[vehicle_pricing][seasonal_multipliers][' . $key . '][multiplier]';
            $this->assertSame(
                1,
                substr_count($html, 'name="' . $field_name . '"'),
                sprintf('Seasonal-multiplier field for "%s" must render exactly once on the live Vehicle tab.', $key)
            );
        }
    }

    /**
     * Save+read end to end through the EXACT field name the live tab renders:
     * a rendered field whose POST the sanitizer drops is wired-but-unreachable
     * all over again — the defect class this whole round exists to kill.
     */
    public function test_seasonal_multiplier_saved_via_the_live_field_name_is_read_back_by_the_booking_form_consumer(): void
    {
        $field_name = 'mhmrentiva_settings[vehicle_pricing][seasonal_multipliers][summer][multiplier]';

        $html_before = $this->render_live_vehicle_tab();
        // Default from VehiclePricingSettings::get_default_settings(): summer = 1.3.
        $this->assertStringContainsString(
            'name="' . $field_name . '" value="1.3"',
            $html_before,
            'Live tab must render the current (default) summer multiplier before any save.'
        );

        // Exactly the shape templates/admin/settings-page.php:67 submits:
        // current_active_tab travels INSIDE mhmrentiva_settings[], not as a
        // sibling $_POST key — the sanitize_callback only ever sees the
        // mhmrentiva_settings[] payload.
        $sanitized = SettingsSanitizer::sanitize(array(
            'current_active_tab' => 'vehicle',
            'vehicle_pricing'    => array(
                'seasonal_multipliers' => array(
                    'summer' => array( 'multiplier' => '2.75' ),
                ),
            ),
        ));
        update_option('mhmrentiva_settings', $sanitized);

        $this->assertSame(
            2.75,
            VehiclePricingSettings::get_seasonal_multiplier_for_date('2026-07-15'),
            'BookingForm.php consumer (get_seasonal_multiplier_for_date) must see the value saved through the live tab field.'
        );

        $html_after = $this->render_live_vehicle_tab();
        $this->assertSame(
            1,
            substr_count($html_after, 'name="' . $field_name . '"'),
            'Field must still render exactly once after a save (no duplicate registration).'
        );
        $this->assertStringContainsString(
            'name="' . $field_name . '" value="2.75"',
            $html_after,
            'Live tab must reflect the newly-saved value on re-render.'
        );
    }

    /**
     * SettingsSanitizer::sanitize_vehicle_pricing_settings() fell back to a
     * bare array() when 'vehicle_pricing' did not exist in the option yet
     * (SettingsCore::get_defaults() never carries that key). On a
     * from-scratch save that made a single-season save both TypeError
     * (get_seasonal_multiplier_for_month() indexing the missing 'months')
     * and silently drop the other three seasons' month mapping and
     * multiplier. Only reachable once the field itself was reachable, so
     * this is a direct consequence of the fields this task moves.
     */
    public function test_saving_one_season_multiplier_does_not_corrupt_the_others_on_a_fresh_install(): void
    {
        $sanitized = SettingsSanitizer::sanitize(array(
            'current_active_tab' => 'vehicle',
            'vehicle_pricing'    => array(
                'seasonal_multipliers' => array(
                    'summer' => array( 'multiplier' => '2.75' ),
                ),
            ),
        ));
        update_option('mhmrentiva_settings', $sanitized);

        // Spring was never touched by the submitted payload; its default
        // month mapping (and multiplier) must survive the summer-only save.
        $this->assertSame(
            1.0,
            VehiclePricingSettings::get_seasonal_multiplier_for_date('2026-04-10'),
            'Saving only the summer multiplier must not corrupt spring\'s stored months/multiplier.'
        );
        $this->assertSame(
            2.75,
            VehiclePricingSettings::get_seasonal_multiplier_for_date('2026-07-15')
        );
    }

    /**
     * Regression lock for the deletion step: the dead monolith renderer must
     * not come back without a caller.
     */
    public function test_orphan_monolith_renderer_no_longer_exists(): void
    {
        $this->assertFalse(
            method_exists(VehiclePricingSettings::class, 'render_settings_section'),
            'VehiclePricingSettings::render_settings_section() is the dead-code monolith Görev 9 retires; it rendered a full second settings table (incl. currency/deposit) that nothing ever called.'
        );
    }
}
