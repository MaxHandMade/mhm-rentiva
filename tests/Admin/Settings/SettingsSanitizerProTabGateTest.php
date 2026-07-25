<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Settings\Services\SettingsService;
use WP_UnitTestCase;

/**
 * An unlicensed site must not be able to PERSIST Pro settings tabs (F9).
 *
 * The render layer shows a placeholder instead of the Transfer / Vendor-Marketplace
 * form, but a forged or replayed POST could still reach SettingsSanitizer::sanitize().
 * The gate there fails closed: for a Pro tab whose licence is absent it returns the
 * untouched current values (a no-op save). Task A6 inverted the gate itself to read
 * SettingsCore::settings_tabs() (a thin wrapper over
 * apply_filters('mhm_rentiva_settings_tabs', array())) instead of
 * \MHMRentiva\Admin\Licensing\Mode directly; this tree has no subscriber to that
 * filter, so settings_tabs() is empty — exactly the unlicensed state.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::sanitize
 */
final class SettingsSanitizerProTabGateTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        delete_option('mhm_rentiva_settings');
        remove_all_filters('mhm_rentiva_settings_tabs');
        parent::tearDown();
    }

    public function test_premise_this_tree_has_no_pro_tabs_subscribed(): void
    {
        $this->assertSame(array(), SettingsCore::settings_tabs(), 'Premise failed: a Pro tab is subscribed in the Lite test tree.');
    }

    /**
     * Mutation proof: drop the `$pro_tab_gates` short-circuit and this fails — the
     * submitted 77 would be sanitized and persisted instead of the stored 33.
     */
    public function test_unlicensed_transfer_save_does_not_persist(): void
    {
        update_option('mhm_rentiva_settings', array( 'mhm_transfer_deposit_rate' => 33 ));

        $result = SettingsSanitizer::sanitize(array(
            'current_active_tab'        => 'transfer',
            'mhm_transfer_deposit_rate' => '77',
        ));

        $this->assertSame(33, $result['mhm_transfer_deposit_rate'], 'Unlicensed transfer save must be a no-op.');
    }

    public function test_unlicensed_vendor_marketplace_save_does_not_persist(): void
    {
        update_option('mhm_rentiva_settings', array( 'vendor_listing_duration_days' => 90 ));

        $result = SettingsSanitizer::sanitize(array(
            'current_active_tab'           => 'vendor-marketplace',
            'vendor_listing_duration_days' => '365',
        ));

        $this->assertSame(90, $result['vendor_listing_duration_days'], 'Unlicensed vendor-marketplace save must be a no-op.');
    }

    /**
     * Positive control: the gate must not turn the whole sanitizer into a no-op. A
     * core (non-Pro) tab must still persist its input.
     */
    public function test_core_tab_save_still_persists(): void
    {
        $result = SettingsSanitizer::sanitize(array(
            'current_active_tab'     => 'system',
            'mhm_rentiva_log_level'  => 'debug',
        ));

        $this->assertSame('debug', $result['mhm_rentiva_log_level'], 'A core settings tab must still save.');
    }

    // -- Y3: reset is a WRITE too, and it bypasses the sanitizer -----------------
    //
    // SettingsService::reset_defaults() calls update_option directly, so the
    // sanitizer gate above never sees it. Without its own gate an unlicensed admin
    // could overwrite Pro tab options with defaults. These pin that reset refuses
    // Pro tabs unlicensed but still works for a core tab.

    private function as_admin(): void
    {
        wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
    }

    /** @return string[] */
    public static function pro_reset_tabs(): array
    {
        return array( array( 'transfer' ), array( 'vendor-marketplace' ), array( 'messages' ) );
    }

    /**
     * @dataProvider pro_reset_tabs
     * Mutation proof: drop the `$pro_tab_gates` guard in reset_defaults() and this
     * returns true (the write happens) instead of false.
     */
    public function test_unlicensed_pro_tab_reset_is_blocked(string $tab): void
    {
        $this->as_admin();

        $this->assertFalse(
            SettingsService::reset_defaults($tab),
            "Unlicensed reset of the '{$tab}' Pro tab must be a no-op."
        );
    }

    public function test_core_tab_reset_still_runs(): void
    {
        $this->as_admin();

        $this->assertTrue(
            SettingsService::reset_defaults('general'),
            'A core settings tab must still be resettable.'
        );
    }
}
