<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonManager;
use WP_UnitTestCase;

/**
 * WordPress.org T8 #2, swept beyond the reported line.
 *
 * The review named /customers/bulk. Walking the class rather than the example
 * found the same shape here: handle_bulk_actions() took whatever IDs the
 * request supplied, checked manage_options once for the batch, and then ran
 * wp_delete_post( $id, true ) on each one. Nothing asked whether the target was
 * an add-on, so the endpoint was a permanent delete for any post on the site --
 * pages, other plugins' content -- and update_post_meta() on any post for the
 * enable/disable branches.
 *
 * The tell was inside the same file: the price handler a hundred lines down
 * already refused a target whose post_type was not mhmrentiva_addon. One path
 * had the guard and its neighbour did not.
 */
final class AddonBulkActionTargetTest extends WP_UnitTestCase
{
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin_id = (int) self::factory()->user->create(array('role' => 'administrator'));
        wp_set_current_user($this->admin_id);

        $_POST['nonce'] = wp_create_nonce('mhmrentiva_addon_list_nonce');

        // wp_send_json() only routes through wp_die() -- and therefore through
        // the test suite's throwing handler -- when wp_doing_ajax() is true.
        // Outside an AJAX context it calls a bare `die`, which no filter can
        // intercept: the first run of this file ended after printing the JSON
        // body, with no test summary, because it took the PHPUnit process with
        // it. This handler is registered on wp_ajax_mhmrentiva_bulk_addon_action,
        // so an AJAX context is also the truthful one to test it in.
        add_filter('wp_doing_ajax', '__return_true');

        $thrower = static function (): callable {
            return static function ($message = '' ): void {
                throw new \WPDieException(is_scalar($message) ? (string) $message : '');
            };
        };
        add_filter('wp_die_ajax_handler', $thrower);
    }

    public function tearDown(): void
    {
        unset($_POST['nonce'], $_POST['bulk_action'], $_POST['addon_ids']);
        wp_set_current_user(0);
        parent::tearDown();
    }

    /**
     * The handler answers over wp_send_json_*, which throws WPDieException in
     * the test environment. The assertions are about what happened to the posts,
     * not about the JSON body, so the exception is swallowed here.
     */
    private function dispatchBulk(string $action, array $ids): void
    {
        $_POST['bulk_action'] = $action;
        $_POST['addon_ids']   = array_map('strval', $ids);

        ob_start();
        try {
            AddonManager::handle_bulk_actions();
        } catch (\WPDieException $e) {
            // Expected: wp_send_json_* terminates.
        } finally {
            ob_end_clean();
        }
    }

    public function test_bulk_delete_refuses_a_post_that_is_not_an_addon(): void
    {
        $page_id = (int) self::factory()->post->create(
            array(
                'post_type'  => 'page',
                'post_title' => 'Rental Terms',
            )
        );

        $this->dispatchBulk('delete', array($page_id));

        $survivor = get_post($page_id);
        $this->assertNotNull($survivor, 'A page must not be deletable through the add-on bulk action.');
        $this->assertSame('page', $survivor->post_type);
    }

    public function test_bulk_disable_does_not_write_meta_onto_a_foreign_post(): void
    {
        $page_id = (int) self::factory()->post->create(array('post_type' => 'page'));

        $this->dispatchBulk('disable_addons', array($page_id));

        $this->assertSame(
            '',
            (string) get_post_meta($page_id, 'mhmrentiva_addon_enabled', true),
            'The add-on enabled flag must not be written onto a post that is not an add-on.'
        );
    }

    public function test_bulk_delete_still_deletes_a_real_addon(): void
    {
        $addon_id = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_addon'));

        $this->dispatchBulk('delete', array($addon_id));

        $this->assertNull(get_post($addon_id), 'The guard must not cost the feature its actual job.');
    }

    public function test_a_mixed_batch_deletes_only_the_addon(): void
    {
        $addon_id = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_addon'));
        $page_id  = (int) self::factory()->post->create(array('post_type' => 'page'));

        $this->dispatchBulk('delete', array($addon_id, $page_id));

        $this->assertNull(get_post($addon_id));
        $this->assertNotNull(get_post($page_id));
    }
}
