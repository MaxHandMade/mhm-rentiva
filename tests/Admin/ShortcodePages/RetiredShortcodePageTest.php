<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\ShortcodePages;

use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions;

/**
 * A retired tag leaves the "create a page" list but stays visible to the
 * owner: they still need to find the pages that carry it, and factory reset
 * still has to clean up the demo page the plugin created.
 */
final class RetiredShortcodePageTest extends \WP_UnitTestCase {

    public function test_the_retired_tag_is_not_offered_for_creation(): void
    {
        $this->assertArrayNotHasKey('rentiva_user_dashboard', ( new ShortcodePageActions() )->get_config());
    }

    public function test_the_scan_still_finds_a_page_carrying_the_retired_shortcode(): void
    {
        $page_id = $this->factory->post->create(array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => '[rentiva_user_dashboard]',
        ));

        $rows = ShortcodePageActions::debug_search()['results'];
        $row  = current(array_filter($rows, static fn (array $r): bool => 'rentiva_user_dashboard' === $r['slug']));

        $this->assertNotFalse($row, 'The retired tag vanished from the scan.');
        $this->assertContains($page_id, array_column($row['found_in'], 'page_id'));
        $this->assertStringContainsStringIgnoringCase('deprecated', (string) $row['label']);
    }

    public function test_factory_reset_still_deletes_the_plugin_created_demo_page(): void
    {
        $page_id = $this->factory->post->create(array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => '[rentiva_user_dashboard]',
        ));
        update_post_meta($page_id, '_mhmrentiva_auto_created', 1);

        ( new ShortcodePageActions() )->reset_pages();

        $this->assertNull(get_post($page_id));
    }

    public function test_factory_reset_leaves_a_page_the_owner_wrote(): void
    {
        $page_id = $this->factory->post->create(array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => 'Our own page. [rentiva_user_dashboard]',
        ));

        ( new ShortcodePageActions() )->reset_pages();

        $this->assertInstanceOf(\WP_Post::class, get_post($page_id));
    }
}
