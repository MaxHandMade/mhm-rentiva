<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Menu;

use MHMRentiva\Admin\Utilities\Menu\Menu;

/**
 * FORMER PURPOSE (kept for history; do not resurrect the mechanism below)
 * ------------------------------------------------------------------------
 * This file used to pin that the "Payout Requests" (Bayi Ödeme Talepleri)
 * submenu -- a Vendor-Marketplace Pro feature -- stayed unregistered in
 * Menu::add_bayi_menus() by calling the real method and inspecting the global
 * $submenu, catching a real regression where Payout leaked in unconditionally
 * while its sibling vendor menus were gated.
 *
 * The Task A7 seam inversion removed Payout (and Vendor Management / Vendor
 * Reports) from add_bayi_menus() entirely -- the method is now an empty hook
 * placeholder, and the submenu is re-added by Pro's own
 * mhm-rentiva-pro/src/Pro/Extensions/MenuExtensions::add_pro_bayi_menu_items(),
 * gated on \MHMRentiva\Pro\Edition::canUseVendorPayout(). The licensed/
 * unlicensed coverage that used to live here now lives in
 * mhm-rentiva-pro/tests/Integration/Pro/MenuExtensionsTest.php (real
 * LicenseManager + real PayoutAdminPage), and the "Lite emits nothing at all"
 * side is covered structurally by
 * mhm-rentiva/tests/Integration/Admin/Menu/MenuNoProSubmenusTest.php.
 *
 * What remains here is the shape that is still meaningful in Lite alone: the
 * hook fires without fataling and adds nothing.
 *
 * @group admin-menu
 * @group vendor-gating
 * @covers \MHMRentiva\Admin\Utilities\Menu\Menu::add_bayi_menus
 */
final class PayoutMenuGatingTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        wp_get_current_user()->add_cap('mhm_rentiva_approve_payout');

        // The parent menu must exist for add_submenu_page() to attach to it.
        add_menu_page('MHM Rentiva', 'MHM Rentiva', 'manage_options', 'mhm-rentiva', '__return_null');
    }

    protected function tearDown(): void
    {
        global $menu, $submenu;
        $menu    = [];
        $submenu = [];
        parent::tearDown();
    }

    private function payout_submenu_registered(): bool
    {
        global $submenu;
        foreach (($submenu['mhm-rentiva'] ?? []) as $item) {
            if (($item[2] ?? '') === 'mhm-rentiva-payouts') {
                return true;
            }
        }
        return false;
    }

    public function test_add_bayi_menus_registers_nothing_in_lite(): void
    {
        Menu::add_bayi_menus();

        $this->assertFalse(
            $this->payout_submenu_registered(),
            'Payout Requests submenu must NOT be registered by Lite -- it is a Pro-only seam (Task A7).'
        );
    }
}
