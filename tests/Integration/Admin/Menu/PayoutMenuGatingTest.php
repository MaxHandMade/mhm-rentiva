<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Menu;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Utilities\Menu\Menu;

/**
 * Regression: the "Payout Requests" (Bayi Ödeme Talepleri) submenu is a
 * Vendor-Marketplace Pro feature and must NOT appear in Lite. Its sibling
 * vendor menus are gated behind the license, but the payout submenu was
 * registered unconditionally, so it leaked into Lite installs.
 *
 * Calls the real `Menu::add_bayi_menus()` and inspects the global `$submenu`
 * so the test exercises the actual wiring, not a re-implemented guard.
 *
 * @group admin-menu
 * @group vendor-gating
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
        delete_option(LicenseManager::OPTION);
        remove_all_filters('mhm_rentiva_dev_pro_bypass');
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

    private function activate_pro_license(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-PAYOUT-MENU-TEST',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a-payout-menu',
        ], false);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');
    }

    public function test_payout_menu_hidden_in_lite(): void
    {
        // No license, no bypass -> Mode::canUseVendorPayout() is false.
        Menu::add_bayi_menus();

        $this->assertFalse(
            $this->payout_submenu_registered(),
            'Payout Requests submenu must NOT be registered in Lite mode.'
        );
    }

    public function test_payout_menu_visible_in_pro(): void
    {
        $this->activate_pro_license();

        Menu::add_bayi_menus();

        $this->assertTrue(
            $this->payout_submenu_registered(),
            'Payout Requests submenu must be registered when Vendor Marketplace is licensed.'
        );
    }
}
