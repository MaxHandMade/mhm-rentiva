<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Settings\Services\SettingsService;
use MHMRentiva\Admin\Settings\View\TabRendererRegistry;
use WP_UnitTestCase;

/**
 * Task A6 seam inversion: SettingsService::reset_defaults() and
 * SettingsSanitizer::sanitize() no longer read
 * \MHMRentiva\Admin\Licensing\Mode directly for the transfer/
 * vendor-marketplace/messages tab gates -- both read the shared
 * SettingsCore::settings_tabs() helper, which is a thin wrapper over
 * apply_filters('mhm_rentiva_settings_tabs', array()). Lite's own default is
 * an empty array; only Pro's SettingsExtensions subscribes.
 *
 * TabRendererRegistry no longer registers the Vendor Marketplace/Messages/
 * Transfer tab renderers itself either -- that moved to Pro, wired through
 * the pre-existing `mhm_rentiva_settings_register_renderers` action.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsCore::settings_tabs
 * @covers \MHMRentiva\Admin\Settings\Services\SettingsService::reset_defaults
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::sanitize
 * @covers \MHMRentiva\Admin\Settings\View\TabRendererRegistry
 */
final class SettingsTabsFilterTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('mhm_rentiva_settings_tabs');
        remove_all_filters('mhm_rentiva_sanitize_settings_tab');
    }

    protected function tearDown(): void
    {
        remove_all_filters('mhm_rentiva_settings_tabs');
        remove_all_filters('mhm_rentiva_sanitize_settings_tab');
        delete_option('mhm_rentiva_settings');
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_settings_tabs_is_empty_without_a_subscriber(): void
    {
        $this->assertSame(array(), SettingsCore::settings_tabs());
    }

    public function test_a_subscriber_can_enable_a_tab(): void
    {
        add_filter('mhm_rentiva_settings_tabs', static function (array $tabs): array {
            $tabs['transfer'] = true;
            return $tabs;
        });

        $tabs = SettingsCore::settings_tabs();

        $this->assertTrue($tabs['transfer'] ?? false);
    }

    /**
     * Lite must not offer admin tabs for the three Pro settings surfaces
     * without a subscriber -- the renderer registration itself (not just the
     * rendered content) moved to Pro.
     */
    public function test_lite_registers_no_vendor_messages_or_transfer_renderer_without_a_subscriber(): void
    {
        $slugs = $this->get_registered_tab_slugs();

        $this->assertNotContains('vendor-marketplace', $slugs);
        $this->assertNotContains('messages', $slugs);
        $this->assertNotContains('transfer', $slugs);
    }

    /**
     * Positive control: Lite's own tabs must still register, proving the
     * registry populated at all.
     */
    public function test_core_settings_tabs_still_register(): void
    {
        $slugs = $this->get_registered_tab_slugs();

        $this->assertContains('vehicle', $slugs);
        $this->assertContains('booking', $slugs);
        $this->assertContains('frontend', $slugs);
    }

    /**
     * A subscriber to the renderer-registration action can add a tab the
     * removed Lite blocks used to add directly -- proves the seam (the
     * `mhm_rentiva_settings_register_renderers` action) still works exactly
     * as before, only the subscriber moved.
     */
    public function test_a_subscriber_can_register_the_transfer_renderer_slug(): void
    {
        add_action('mhm_rentiva_settings_register_renderers', function ($registry): void {
            $mock = $this->createMock(\MHMRentiva\Admin\Settings\View\TabRendererInterface::class);
            $mock->method('get_slug')->willReturn('transfer');
            $registry->register($mock);
        });

        $slugs = $this->get_registered_tab_slugs();

        $this->assertContains('transfer', $slugs);

        remove_all_actions('mhm_rentiva_settings_register_renderers');
    }

    /**
     * Without a subscriber, an unlicensed-shaped save of the transfer tab must
     * still be a no-op (the existing SettingsSanitizerProTabGateTest pins this
     * for the pre-inversion Mode:: gate; this re-pins it post-inversion for the
     * filter-based gate reading an empty default).
     */
    public function test_unlicensed_shaped_transfer_save_is_still_a_no_op_without_a_subscriber(): void
    {
        update_option('mhm_rentiva_settings', array( 'mhm_transfer_deposit_rate' => 33 ));

        $result = SettingsSanitizer::sanitize(array(
            'current_active_tab'        => 'transfer',
            'mhm_transfer_deposit_rate' => '77',
        ));

        $this->assertSame(33, $result['mhm_transfer_deposit_rate']);
    }

    /**
     * A subscriber enabling the transfer tab lifts the sanitizer's fail-closed
     * gate -- proves the write-protection reads the filter, not a hardcoded
     * false.
     */
    public function test_a_subscriber_enabling_transfer_lifts_the_sanitizer_gate(): void
    {
        add_filter('mhm_rentiva_settings_tabs', static function (array $tabs): array {
            $tabs['transfer'] = true;
            return $tabs;
        });

        update_option('mhm_rentiva_settings', array( 'mhm_transfer_deposit_rate' => 33 ));

        $result = SettingsSanitizer::sanitize(array(
            'current_active_tab'        => 'transfer',
            'mhm_transfer_deposit_rate' => '77',
        ));

        $this->assertSame(77, $result['mhm_transfer_deposit_rate']);
    }

    /**
     * SettingsService::reset_defaults() bypasses the sanitizer and has its own
     * copy of the gate (Fable Y3): an unlicensed-shaped reset of a Pro tab must
     * stay a no-op without a subscriber, re-pinning
     * SettingsSanitizerProTabGateTest's pre-inversion expectation against the
     * post-inversion filter-based gate.
     */
    public function test_unlicensed_shaped_reset_is_still_blocked_without_a_subscriber(): void
    {
        wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

        $this->assertFalse(SettingsService::reset_defaults('transfer'));
        $this->assertFalse(SettingsService::reset_defaults('vendor-marketplace'));
        $this->assertFalse(SettingsService::reset_defaults('messages'));
    }

    /**
     * @return array<int, string>
     */
    private function get_registered_tab_slugs(): array
    {
        $registry = new TabRendererRegistry();

        $slugs = array();
        foreach ($registry->get_all() as $renderer) {
            $slugs[] = $renderer->get_slug();
        }

        return $slugs;
    }
}
