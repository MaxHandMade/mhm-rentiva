<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Services\SettingsService;
use MHMRentiva\Admin\Settings\Settings;
use WP_UnitTestCase;

/**
 * Task A6b seam inversion: Lite no longer names the Transfer /
 * Vendor-Marketplace / Messages settings-provider classes anywhere in
 * src/Admin/Settings/.
 *
 * - Settings::init() no longer hardcodes 'vendor-marketplace' =>
 *   VendorMarketplaceSettings; Pro registers it via the pre-existing
 *   `mhmrentiva_register_settings_providers` action.
 * - SettingsService::match() resolves the transfer/vendor-marketplace/
 *   messages provider class through Settings::get_provider() (the same
 *   registry `register_provider()` writes to) instead of a hardcoded
 *   class-string, and returns null with no subscriber.
 * - SettingsCore's two group lists (register_sub_groups() and
 *   get_defaults()) and SettingsService::initialize_defaults_on_activation()
 *   no longer name the Pro classes directly; each is filterable so Pro adds
 *   its own groups/providers back.
 *
 * @covers \MHMRentiva\Admin\Settings\Settings::get_provider
 * @covers \MHMRentiva\Admin\Settings\Services\SettingsService::reset_defaults
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsCore::init_settings_registration
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsCore::get_defaults
 */
final class SettingsProviderSeamTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('mhmrentiva_settings_groups');
        remove_all_filters('mhmrentiva_settings_tabs');
        delete_option('mhmrentiva_settings');
        wp_set_current_user(0);
        parent::tearDown();
    }

    /**
     * Without a Pro subscriber, the registry has nothing under the three
     * carved-out tab slugs.
     */
    public function test_get_provider_is_null_for_pro_tabs_without_a_subscriber(): void
    {
        $this->assertNull(Settings::get_provider('transfer'));
        $this->assertNull(Settings::get_provider('vendor-marketplace'));
        $this->assertNull(Settings::get_provider('messages'));
    }

    /**
     * register_provider() is the same mechanism Pro's
     * `mhmrentiva_register_settings_providers` subscriber calls -- proves
     * the registry SettingsService::match() reads is genuinely writable by a
     * third party, not just a stub.
     */
    public function test_get_provider_reflects_a_registered_provider(): void
    {
        Settings::register_provider('transfer', FakeProviderForSeamTest::class);

        $this->assertSame(FakeProviderForSeamTest::class, Settings::get_provider('transfer'));
    }

    /**
     * Core tabs (not part of the A6b carve) must still resolve their
     * provider class exactly as before -- reset_defaults() must still work
     * for a tab whose class is hardcoded in SettingsService::match().
     */
    public function test_core_tab_still_resolves_and_resets(): void
    {
        wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

        update_option('mhmrentiva_settings', array( 'mhmrentiva_max_login_attempts' => '999' ));

        $this->assertTrue(SettingsService::reset_defaults('system'));
    }

    /**
     * Mutation proof for the seam itself: without a Pro subscriber,
     * resetting a carved-out tab is a no-op (same behaviour as before the
     * carve, just reached through the registry instead of a hardcoded
     * string). The pro_tabs write-gate in reset_defaults() already covers
     * this at the licence-gate layer (SettingsSanitizerProTabGateTest); this
     * pins that removing the match() cases didn't also break resolution for
     * a *licensed* subscriber with no provider class registered.
     */
    public function test_reset_of_carved_out_tab_is_still_a_no_op_without_a_provider(): void
    {
        wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

        add_filter('mhmrentiva_settings_tabs', static function (array $tabs): array {
            $tabs['transfer'] = true;
            return $tabs;
        });

        // Tab is "licensed" (subscriber enabled it) but no provider class was
        // ever registered for it -- must still be a no-op, not a fatal error.
        $this->assertFalse(SettingsService::reset_defaults('transfer'));
    }

    /**
     * The `mhmrentiva_settings_groups` filter (SettingsCore) is applied:
     * a subscriber-added group class gets its register() method invoked
     * during the central settings registration pass, exactly like Pro's
     * TransferSettings/VendorMarketplaceSettings used to be hardcoded.
     */
    public function test_settings_groups_filter_is_applied_during_registration(): void
    {
        FakeGroupForSeamTest::$registered = false;

        add_filter('mhmrentiva_settings_groups', static function (array $groups): array {
            $groups[] = FakeGroupForSeamTest::class;
            return $groups;
        });

        SettingsCore::init_settings_registration();

        $this->assertTrue(FakeGroupForSeamTest::$registered, 'A group added via the mhmrentiva_settings_groups filter must have register() invoked.');
    }
}

/**
 * Minimal provider double: only needs to satisfy Settings::register_provider()'s
 * class_exists()/method_exists('get_default_settings') guard.
 */
final class FakeProviderForSeamTest
{
    public static function get_default_settings(): array
    {
        return array();
    }
}

/**
 * Minimal group double: only needs to satisfy SettingsCore's
 * class_exists()/method_exists('register') guard in register_sub_groups().
 */
final class FakeGroupForSeamTest
{
    public static bool $registered = false;

    public static function register(): void
    {
        self::$registered = true;
    }
}
