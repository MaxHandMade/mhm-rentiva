<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Menu;

use MHMRentiva\Admin\Utilities\Menu\Menu;

/**
 * FORMER PURPOSE (kept for history; do not resurrect the mechanism below)
 * ------------------------------------------------------------------------
 * This file used to mutation-proof `class_exists(TransferAdmin) &&
 * Mode::isPro()` inside Menu::add_menu() (Locations + Transfer Routes) and a
 * bare `class_exists(LicenseAdmin)` guard for the License screen, using a
 * class_alias() stand-in to simulate "Pro is installed but unlicensed" -- the
 * exact shape that once leaked both Transfer admin screens to an unlicensed
 * site for free (see git history for the incident writeup).
 *
 * The Task A7 seam inversion removed that mechanism entirely. Menu.php no
 * longer references TransferAdmin, LicenseAdmin or Mode:: at all -- Locations,
 * Transfer Routes and License Management are re-added by Pro's own
 * mhm-rentiva-pro/src/Pro/Extensions/MenuExtensions.php on admin_menu, gated
 * through \MHMRentiva\Pro\Edition instead. The class_alias() stand-in trick
 * is gone with it: there is no gate left in this file's tree to defeat, so
 * simulating "class present" here would prove nothing about Menu.php's actual
 * source, which is why the pinned assertions moved to
 * mhm-rentiva/tests/Integration/Admin/Menu/MenuNoProSubmenusTest.php
 * (source-level absence, run against the real Lite tree) and
 * mhm-rentiva-pro/tests/Integration/Pro/MenuExtensionsTest.php (the actual
 * licensed/unlicensed/License-survives gating, run against the real Pro
 * classes).
 *
 * What remains here is the positive control that is still true regardless of
 * mechanism: the core admin screens must survive whatever gates the carved-out
 * ones.
 *
 * @covers \MHMRentiva\Admin\Utilities\Menu\Menu::add_menu
 */
final class TransferMenuGatingTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
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
     * Positive control. A gate that registered NOTHING would satisfy other
     * assertions in this suite while destroying the admin, and would make
     * them vacuous. Core screens are not licensed and must survive.
     */
    public function test_core_screens_still_register_without_a_licence(): void
    {
        Menu::add_menu();
        $slugs = $this->registered_slugs();

        $this->assertNotSame(array(), $slugs, 'Premise failed: the menu registered nothing at all.');

        foreach (array( 'mhm-rentiva-dashboard', 'mhm-rentiva-customers', 'mhm-rentiva-settings' ) as $core) {
            $this->assertContains($core, $slugs, sprintf('Core screen "%s" must always register.', $core));
        }
    }
}
