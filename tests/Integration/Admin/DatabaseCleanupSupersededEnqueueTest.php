<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Settings\View\Tabs\DatabaseCleanupRenderer;
use MHMRentiva\Admin\Utilities\Database\DatabaseCleanupPage;
use WP_UnitTestCase;

/**
 * WP.org T8 Görev 13 (rows 26-27, NonceVerification.Recommended on
 * DatabaseCleanupPage.php:59's `$_GET['tab']` read): `enqueue_assets()`'s own
 * docblock already said "kept for backward compatibility but may not be
 * called". Traced end-to-end before deleting it:
 *
 * - No test anywhere referenced `DatabaseCleanupPage::enqueue_assets` or the
 *   'mhmrentiva_db_cleanup_vars' / 'mhm-rentiva-database-cleanup' localize
 *   payload before this file was added (confirmed by grep).
 * - `DatabaseCleanupRenderer::enqueue_cleanup_assets()` (called from
 *   `render()`, which `TabRendererRegistry` only invokes when the
 *   `database-cleanup` tab is the one actually selected) enqueues the same
 *   script/style handle with a MUCH larger `wp_localize_script()` payload.
 *   `assets/js/admin/database-cleanup.js` reads keys (`restoring_text`,
 *   `clean_invalid_meta_text`, `type_invalid_meta_text`, ...) that exist ONLY
 *   in the renderer's payload -- proving the renderer's payload is what
 *   actually drove the UI even before this deletion, since
 *   `admin_enqueue_scripts` (the old method's hook) always fires before the
 *   page body renders, and `wp_localize_script()` overwrites (not merges) a
 *   handle's data on a second call.
 * - Pro (`mhm-rentiva-pro`) does not reference `DatabaseCleanupPage` at all.
 *
 * @covers \MHMRentiva\Admin\Utilities\Database\DatabaseCleanupPage::register
 * @covers \MHMRentiva\Admin\Settings\View\Tabs\DatabaseCleanupRenderer::render
 */
final class DatabaseCleanupSupersededEnqueueTest extends WP_UnitTestCase
{
    private const HANDLE = 'mhm-rentiva-database-cleanup';

    public function tearDown(): void
    {
        wp_dequeue_script(self::HANDLE);
        wp_deregister_script(self::HANDLE);
        wp_dequeue_style(self::HANDLE);
        wp_deregister_style(self::HANDLE);
        parent::tearDown();
    }

    public function test_the_superseded_enqueue_method_is_gone(): void
    {
        $this->assertFalse(
            method_exists(DatabaseCleanupPage::class, 'enqueue_assets'),
            'enqueue_assets() was a dead, superseded duplicate of DatabaseCleanupRenderer::enqueue_cleanup_assets() -- it must be gone, not just unhooked.'
        );
    }

    /**
     * Positive control: register() must still wire every AJAX handler --
     * only the admin_enqueue_scripts registration for the dead method was
     * removed.
     */
    public function test_register_still_wires_every_ajax_handler(): void
    {
        DatabaseCleanupPage::register();

        foreach (
            array(
                'wp_ajax_mhmrentiva_analyze_database',
                'wp_ajax_mhmrentiva_cleanup_orphaned',
                'wp_ajax_mhmrentiva_cleanup_transients',
                'wp_ajax_mhmrentiva_optimize_autoload',
                'wp_ajax_mhmrentiva_optimize_tables',
                'wp_ajax_mhmrentiva_cleanup_invalid_meta',
                'wp_ajax_mhmrentiva_list_backups',
                'wp_ajax_mhmrentiva_download_backup',
                'wp_ajax_mhmrentiva_restore_backup',
                'wp_ajax_mhmrentiva_delete_backup',
                'wp_ajax_mhmrentiva_create_full_backup',
                'wp_ajax_mhmrentiva_list_full_backups',
                'wp_ajax_mhmrentiva_download_full_backup',
                'wp_ajax_mhmrentiva_delete_full_backup',
                'wp_ajax_mhmrentiva_repair_table',
                'wp_ajax_mhmrentiva_cleanup_logs',
            ) as $hook
        ) {
            $this->assertNotFalse(
                has_action($hook),
                "register() must still wire '{$hook}' -- only enqueue_assets() was removed."
            );
        }
    }

    /**
     * The live path: TabRendererRegistry only calls this render() when the
     * database-cleanup tab is the one selected, and it enqueues its own
     * complete payload without reading $_GET at all (the tab is already
     * known -- it is the renderer that was picked).
     */
    public function test_live_renderer_still_enqueues_the_full_localized_payload(): void
    {
        $renderer = new DatabaseCleanupRenderer();

        ob_start();
        $renderer->render();
        ob_end_clean();

        $this->assertTrue(wp_script_is(self::HANDLE, 'enqueued'), 'The live renderer must still enqueue the database-cleanup script.');
        $this->assertTrue(wp_style_is(self::HANDLE, 'enqueued'), 'The live renderer must still enqueue the database-cleanup style.');

        $raw = wp_scripts()->get_data(self::HANDLE, 'data');
        $this->assertIsString($raw, 'Premise: the renderer must localize data onto the handle.');

        $this->assertMatchesRegularExpression('/var mhmrentiva_db_cleanup_vars = (\{.*\});/', $raw);
        preg_match('/var mhmrentiva_db_cleanup_vars = (\{.*\});/', $raw, $matches);
        $payload = json_decode($matches[1], true);
        $this->assertIsArray($payload);

        // Keys that exist ONLY in DatabaseCleanupRenderer's payload, never in
        // the deleted method's smaller 14-key one -- and that
        // database-cleanup.js actually reads (assets/js/admin/database-cleanup.js
        // :210, :301, :376). Their presence proves the real, live enqueue path
        // is untouched by this deletion.
        foreach (array('restoring_text', 'clean_invalid_meta_text', 'type_invalid_meta_text') as $key) {
            $this->assertArrayHasKey($key, $payload, "database-cleanup.js reads '{$key}' -- it must survive in the live payload.");
        }
        $this->assertArrayHasKey('nonce', $payload);
        $this->assertNotEmpty($payload['nonce']);
    }
}
