<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Menu;

use MHMRentiva\Admin\Utilities\Menu\Menu;
use MHMRentiva\Tests\Support\UserManagementCapabilities;

/**
 * Task A7 seam inversion: Menu::add_menu()/add_bayi_menus() no longer name
 * TransferAdmin, Reports, Messages\Core\Messages, Export, LicenseAdmin,
 * AdminVendorApplicationsPage, VendorReportsAdminPage or PayoutAdminPage
 * anywhere -- not even behind a class_exists()/Mode:: gate. All nine carved
 * submenus (Locations, Transfer Routes, Reports, Messages, Export, License,
 * Vendor Management, Vendor Reports, Payout Requests) are now exclusively
 * Pro's concern, re-added by
 * mhm-rentiva-pro/src/Pro/Extensions/MenuExtensions.php on admin_menu
 * priority 11/16. See
 * mhm-rentiva-pro/tests/Integration/Pro/MenuExtensionsTest.php for the
 * licensed/unlicensed/License-survives coverage that belongs to that class.
 *
 * Unlike the older TransferMenuGatingTest/PayoutMenuGatingTest (which had to
 * class_alias() a stand-in to reproduce "class present, licence absent"
 * because Menu.php still *referenced* the Pro FQN), no such trick is needed
 * or even possible here: this file runs against the real Lite tree, where
 * none of the eight Pro classes exist, and Menu.php's source no longer
 * mentions any of them -- so the absence proven below is structural, not a
 * licence-gate outcome that happens to be false.
 *
 * @group admin-menu
 * @covers \MHMRentiva\Admin\Utilities\Menu\Menu::add_menu
 * @covers \MHMRentiva\Admin\Utilities\Menu\Menu::add_bayi_menus
 */
final class MenuNoProSubmenusTest extends \WP_UnitTestCase
{
    use UserManagementCapabilities;

    /** The eight submenu slugs Task A7 carved out of Lite entirely. */
    private const REMOVED_SLUGS = array(
        'mhm-rentiva-transfer-locations',
        'mhm-rentiva-transfer-routes',
        'mhm-rentiva-reports',
        'mhm-rentiva-messages',
        'mhm-rentiva-export',
        'mhm-rentiva-license',
        'mhm-rentiva-vendors',
        'mhm-rentiva-vendor-reports',
        'mhm-rentiva-payouts',
    );

    protected function setUp(): void
    {
        parent::setUp();
        $actor_id = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
        // The Customers submenu is registered with the edit_users capability,
        // and add_submenu_page() simply does not register a screen the current
        // user cannot access. On a network an administrator does not hold
        // edit_users, so without this the screen is legitimately absent and the
        // assertion below would be measuring core's capability rewrite instead
        // of this plugin's menu wiring. No-op on a single site.
        $this->grant_user_management_privilege($actor_id);
        wp_set_current_user($actor_id);
    }

    protected function tearDown(): void
    {
        global $menu, $submenu;
        $menu    = array();
        $submenu = array();
        parent::tearDown();
    }

    /**
     * @return array<int, string>
     */
    private function registered_slugs(): array
    {
        global $submenu;
        $slugs = array();
        foreach (( $submenu['mhm-rentiva'] ?? array() ) as $item) {
            $slugs[] = (string) ( $item[2] ?? '' );
        }
        return $slugs;
    }

    /**
     * @dataProvider removed_slug_provider
     */
    public function test_carved_pro_submenu_is_not_registered_by_lite(string $slug): void
    {
        Menu::add_menu();
        Menu::add_bayi_menus();

        $this->assertNotContains(
            $slug,
            $this->registered_slugs(),
            sprintf('"%s" must not be registered by Lite\'s Menu class -- it is a Pro-only seam (Task A7).', $slug)
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function removed_slug_provider(): array
    {
        $cases = array();
        foreach (self::REMOVED_SLUGS as $slug) {
            $cases[$slug] = array( $slug );
        }
        return $cases;
    }

    /**
     * Positive control. A change that stripped Menu.php down to nothing would
     * satisfy every assertion above while destroying the Lite admin -- core
     * screens are not a Pro seam and must always survive.
     */
    public function test_core_screens_still_register(): void
    {
        Menu::add_menu();
        $slugs = $this->registered_slugs();

        $this->assertNotSame(array(), $slugs, 'Premise failed: the menu registered nothing at all.');

        foreach (array( 'mhm-rentiva-dashboard', 'mhm-rentiva-customers', 'mhm-rentiva-settings' ) as $core) {
            $this->assertContains($core, $slugs, sprintf('Core screen "%s" must always register.', $core));
        }
    }

    /**
     * No test file may reference a Pro class name; grep-clean is the actual
     * A7 acceptance gate (see task report), but pinning source-level absence
     * here too means a future re-introduction of one of these gates inside
     * Menu.php fails this suite even before the grep check runs in CI.
     */
    public function test_menu_source_names_no_pro_admin_page_class(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 4) . '/src/Admin/Utilities/Menu/Menu.php'
        );

        foreach (array(
            'TransferAdmin',
            'Reports\\Reports',
            'Messages\\Core\\Messages',
            'Utilities\\Export\\Export',
            'LicenseAdmin',
            'AdminVendorApplicationsPage',
            'VendorReportsAdminPage',
            'PayoutAdminPage',
            'Licensing\\Mode',
        ) as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $source,
                sprintf('Menu.php must not reference "%s" -- that seam belongs to Pro\'s MenuExtensions now.', $needle)
            );
        }
    }
}
