<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Menu;

use MHMRentiva\Admin\Addons\AddonPostType;
use MHMRentiva\Admin\Utilities\Menu\Menu;
use MHMRentiva\Tests\Support\UserManagementCapabilities;

/**
 * The Additional Services menu entry moves off WordPress's native list screen
 * and onto this plugin's own page.
 *
 * WHY THE NATIVE SCREEN IS NOT REMOVED
 * ------------------------------------
 * Only the menu link changes. `edit.php?post_type=mhmrentiva_addon` keeps
 * working, because it is the way back: if the custom screen breaks, or a site
 * ends up with far more add-ons than the design assumes, the operator still has
 * a list they can sort, search, bulk-edit and page through. Removing the CPT's
 * UI would take that away and would also break `post-new.php` and `post.php`,
 * which the custom screen links to for full editing.
 *
 * So the assertions below are a pair, and the pair is the point: the menu must
 * move AND the native screen must survive. A change that satisfies only the
 * first one has broken the fallback.
 *
 * @group admin-menu
 * @covers \MHMRentiva\Admin\Utilities\Menu\Menu::add_menu
 */
final class AddonsScreenMenuTest extends \WP_UnitTestCase
{
    use UserManagementCapabilities;

    /** The custom screen's slug. */
    private const SCREEN_SLUG = 'mhm-rentiva-addons';

    /** What the menu entry used to point at. */
    private const NATIVE_LIST_URL = 'edit.php?post_type=mhmrentiva_addon';

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

    public function test_additional_services_entry_points_at_the_custom_screen(): void
    {
        Menu::add_menu();

        $this->assertContains(
            self::SCREEN_SLUG,
            $this->registered_slugs(),
            'The Additional Services menu entry must open the plugin\'s own add-ons screen.'
        );
    }

    public function test_the_menu_no_longer_links_the_native_list_screen(): void
    {
        Menu::add_menu();

        $this->assertNotContains(
            self::NATIVE_LIST_URL,
            $this->registered_slugs(),
            'Two menu entries for the same content would be two doors to two different designs.'
        );
    }

    /**
     * The other half of the pair. This is what stops the change from becoming
     * "the native screen was removed": the post type keeps its admin UI, so the
     * fallback URL, `post-new.php` and `post.php` all keep resolving.
     */
    public function test_the_native_add_on_screen_stays_reachable(): void
    {
        $post_type = get_post_type_object(AddonPostType::POST_TYPE);

        $this->assertNotNull($post_type, 'The add-on post type must exist.');
        $this->assertTrue(
            (bool) $post_type->show_ui,
            'show_ui must stay true: it is what keeps edit.php, post-new.php and post.php reachable.'
        );
    }

    /**
     * Positive control. Stripping Menu.php down to nothing, or pointing every
     * entry at the new slug, would satisfy both assertions above while
     * destroying the rest of the admin.
     */
    public function test_the_other_core_screens_still_register(): void
    {
        Menu::add_menu();

        $slugs = $this->registered_slugs();

        foreach (array( 'mhm-rentiva-dashboard', 'mhm-rentiva-customers', 'mhm-rentiva-settings' ) as $slug) {
            $this->assertContains($slug, $slugs, sprintf('"%s" must still be registered.', $slug));
        }
    }
}
