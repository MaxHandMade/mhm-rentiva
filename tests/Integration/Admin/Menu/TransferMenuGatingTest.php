<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Menu;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Utilities\Menu\Menu;

/**
 * "Konumlar" and "Transfer Guzergahlari" must not appear without a licence.
 *
 * THE BUG THIS PINS
 * -----------------
 * Menu::add_menu() guarded both Transfer screens with class_exists() alone. That
 * is right for a Lite-only tree (no class, no menu) and wrong for the case the
 * owner was actually looking at: Pro INSTALLED but UNLICENSED. There the class
 * exists, so both screens registered and rendered in full, for free. The owner
 * found it by opening the browser with isPro=false.
 *
 * Menu registration is its own code path. The seam gate added to the shortcode,
 * block and Elementor registries never reached it, and no test asked -- so a
 * feature that was closed everywhere else stayed open here.
 *
 * WHY THE STAND-IN CLASS (and why the obvious test is worthless)
 * -------------------------------------------------------------
 * This suite runs against the real Lite tree, where TransferAdmin is genuinely
 * absent. Asserting "the Transfer menu is not registered" therefore passes just as
 * happily with the fix REVERTED -- class_exists() is already false, so the
 * assertion proves nothing about the licence gate. That tautology is exactly the
 * trap that let this ship.
 *
 * So class_alias() makes a real class answer to the TransferAdmin FQN, which is
 * what class_exists() asks. That reproduces "class present, licence absent"
 * honestly, and it is the only shape that fails when the Mode gate is removed.
 *
 * The stand-in carries the two render_* methods because Menu.php constructs the
 * class and passes [$obj, 'render_locations_page'] as the menu callback. The
 * callback is never invoked here -- only registration is under test.
 *
 * @group admin-menu
 * @group transfer-gating
 * @covers \MHMRentiva\Admin\Utilities\Menu\Menu::add_menu
 */
final class TransferMenuGatingTest extends \WP_UnitTestCase
{

    private const TRANSFER_ADMIN_FQN = '\MHMRentiva\Admin\Transfer\TransferAdmin';

    public static function set_up_before_class(): void
    {
        parent::set_up_before_class();

        // Only alias if the real Pro class is not loaded -- never shadow the real
        // thing if this suite is ever run against a tree that has it.
        if (! class_exists(self::TRANSFER_ADMIN_FQN)) {
            class_alias(TransferAdminStandIn::class, 'MHMRentiva\Admin\Transfer\TransferAdmin');
        }
    }

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
     * Guards the premise. Without BOTH of these the assertions below are vacuous:
     * if the class were absent the class guard would do the work, and if a licence
     * were active the menu would be allowed to appear.
     */
    public function test_premise_transfer_class_is_present_and_the_tree_is_unlicensed(): void
    {
        $this->assertTrue(
            class_exists(self::TRANSFER_ADMIN_FQN),
            'Stand-in failed: class_exists() is false, so the test below would pass for the wrong reason.'
        );
        $this->assertFalse(Mode::isPro(), 'Premise failed: this tree reports a Pro licence.');
    }

    /**
     * Mutation proof: drop `&& Mode::isPro()` from Menu::add_menu() and this fails
     * -- the stand-in class exists, so both screens would register.
     *
     * @dataProvider transfer_slug_provider
     */
    public function test_transfer_screens_are_not_registered_without_a_licence(string $slug, string $label): void
    {
        Menu::add_menu();

        $this->assertNotContains(
            $slug,
            $this->registered_slugs(),
            sprintf(
                'The "%s" screen (%s) registered with isPro=false. An unlicensed site must not get Transfer.',
                $label,
                $slug
            )
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function transfer_slug_provider(): array
    {
        return array(
            'Locations'      => array( 'mhm-rentiva-transfer-locations', 'Konumlar' ),
            'Transfer Routes' => array( 'mhm-rentiva-transfer-routes', 'Transfer Guzergahlari' ),
        );
    }

    /**
     * Positive control. A gate that registered NOTHING would satisfy the
     * assertions above while destroying the admin, and would make them vacuous.
     * Core screens are not licensed and must survive.
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

    /**
     * The Licence screen is the deliberate exception: it is guarded by
     * class_exists() ONLY, with no Mode gate, and that is correct. It is the screen
     * a customer uses to ENTER a key, so gating it behind having a key would lock
     * every unlicensed customer out of ever becoming licensed.
     */
    public function test_the_licence_screen_is_not_gated_by_the_licence(): void
    {
        if (! class_exists('\MHMRentiva\Admin\Licensing\LicenseAdmin')) {
            $this->markTestSkipped('LicenseAdmin is a Pro seam and is absent from this tree.');
        }

        Menu::add_menu();

        $this->assertContains(
            'mhm-rentiva-license',
            $this->registered_slugs(),
            'The Licence screen must remain reachable without a licence -- it is how a key gets entered.'
        );
    }
}

/**
 * Stands in for the carved-out Pro class so class_exists() answers true.
 * Not a mock of TransferAdmin's behaviour -- only its PRESENCE is under test.
 */
final class TransferAdminStandIn
{
    public function render_locations_page(): void
    {
    }

    public function render_routes_page(): void
    {
    }
}
