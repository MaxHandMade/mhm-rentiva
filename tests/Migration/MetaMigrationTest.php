<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use WP_UnitTestCase;

/**
 * MetaMigrationTest
 * 
 * Verifies the M3 Meta Migration logic including idempotency, 
 * conflict resolution (Standard Wins), and partial migration.
 */
class MetaMigrationTest extends WP_UnitTestCase
{
    private $vehicle_ids = [];
    private $migration_script;

    public function setUp(): void
    {
        parent::setUp();

        // Bypass Lite version limit for tests
        add_filter('mhmrentiva_lite_max_vehicles', function () {
            return 999;
        });
        add_filter('mhmrentiva_lite_max_bookings', function () {
            return 999;
        });

        $this->migration_script = rtrim(dirname(dirname(__DIR__)), '/\\') . '/bin/mhm-migrate-meta.php';

        // Clean up any existing vehicles to ensure isolation
        $existing = get_posts(['post_type' => 'mhmrentiva_vehicle', 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($existing as $id) {
            wp_delete_post($id, true);
        }
    }

    /**
     * Helper to run migration script
     */
    private function run_migration(bool $dry_run = false, int $batch_size = 100)
    {
        $args = ($dry_run) ? ['dry-run'] : [];
        $args[] = "batch-size=$batch_size";

        ob_start();
        include $this->migration_script;
        return ob_get_clean();
    }

    /**
     * Write a meta row the way a LEGACY row exists: already in the table.
     *
     * update_post_meta() cannot build these fixtures any more, and that is a
     * correction rather than an inconvenience. Meta registration used to sit
     * behind `if (is_admin())`, so in this non-admin suite nothing was registered
     * and nothing sanitised -- 'yes' and 'unknown' went into the table verbatim.
     * Now that registration happens on every request, update_post_meta() applies
     * the registered sanitize_callback, which rewrites 'unknown' to 'active' and
     * 'yes' to 'active' before the migration ever sees them.
     *
     * The values this test migrates are by definition older than that callback.
     * On a real site they are already sitting in postmeta and are never
     * re-sanitised -- sanitisation happens on write, not on read -- so the
     * migration still meets them exactly as written here. Writing them directly
     * is what reproduces that; going through update_post_meta() would be testing
     * today's writer instead of yesterday's data.
     */
    private function store_legacy_meta(int $post_id, string $meta_key, string $value): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->postmeta,
            array(
                'post_id'    => $post_id,
                'meta_key'   => $meta_key,
                'meta_value' => $value,
            )
        );

        wp_cache_delete($post_id, 'post_meta');

        $this->assertSame(
            $value,
            get_post_meta($post_id, $meta_key, true),
            'Premise: the legacy value must reach the table unaltered, or this test is migrating something else.'
        );
    }

    /**
     * Test mapping: yes/no/1/0 -> active/inactive
     */
    public function test_enum_mapping_strict()
    {
        $v1 = $this->factory->post->create(['post_type' => 'mhmrentiva_vehicle']);
        $this->store_legacy_meta($v1, '_mhmrentiva_vehicle_availability', 'yes');

        $v2 = $this->factory->post->create(['post_type' => 'mhmrentiva_vehicle']);
        $this->store_legacy_meta($v2, '_mhmrentiva_availability', 'no');

        $v3 = $this->factory->post->create(['post_type' => 'mhmrentiva_vehicle']);
        $this->store_legacy_meta($v3, '_mhmrentiva_vehicle_availability', '1');

        $v4 = $this->factory->post->create(['post_type' => 'mhmrentiva_vehicle']);
        $this->store_legacy_meta($v4, '_mhmrentiva_vehicle_availability', 'unknown'); // Should be skipped

        $this->run_migration(false);

        $this->assertEquals('active', get_post_meta($v1, '_mhmrentiva_vehicle_status', true));
        $this->assertEquals('inactive', get_post_meta($v2, '_mhmrentiva_vehicle_status', true));
        $this->assertEquals('active', get_post_meta($v3, '_mhmrentiva_vehicle_status', true));
        $this->assertEquals('', get_post_meta($v4, '_mhmrentiva_vehicle_status', true)); // Skipped
    }

    /**
     * Test Conflict: Standard Key Wins
     */
    public function test_conflict_resolution_standard_wins()
    {
        $v1 = $this->factory->post->create(['post_type' => 'mhmrentiva_vehicle']);
        update_post_meta($v1, '_mhmrentiva_vehicle_status', 'maintenance'); // Standard
        update_post_meta($v1, '_mhmrentiva_vehicle_availability', 'active'); // Legacy conflict

        $this->run_migration(false);

        // Standard should remain 'maintenance', legacy should be deleted
        $this->assertEquals('maintenance', get_post_meta($v1, '_mhmrentiva_vehicle_status', true));
        $this->assertEquals('', get_post_meta($v1, '_mhmrentiva_vehicle_availability', true));
    }

    /**
     * Test Idempotency: Second run should result in 0 changes
     */
    public function test_idempotency()
    {
        $v1 = $this->factory->post->create(['post_type' => 'mhmrentiva_vehicle']);
        update_post_meta($v1, '_mhmrentiva_vehicle_availability', 'active');

        // First run
        $output1 = $this->run_migration(false);
        $this->assertStringContainsString('Status migrated:          1', $output1);
        $this->assertEquals('active', get_post_meta($v1, '_mhmrentiva_vehicle_status', true));

        // Second run
        $output2 = $this->run_migration(false);
        $this->assertStringContainsString('Status migrated:          0', $output2);
        $this->assertStringContainsString('Legacy keys removed:      0', $output2);
    }

    /**
     * Test Partial Migration: Handle interrupted flows
     */
    public function test_partial_migration()
    {
        // Create 10 vehicles
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $id = $this->factory->post->create(['post_type' => 'mhmrentiva_vehicle']);
            update_post_meta($id, '_mhmrentiva_vehicle_availability', 'active');
            $ids[] = $id;
        }

        // Manually migrate 5 of them
        for ($i = 0; $i < 5; $i++) {
            update_post_meta($ids[$i], '_mhmrentiva_vehicle_status', 'active');
            delete_post_meta($ids[$i], '_mhmrentiva_vehicle_availability');
        }

        // Run migration for the remaining
        $output = $this->run_migration(false);

        // Should report 5 migrated status (the ones that weren't manually done)
        $this->assertStringContainsString('Status migrated:          5', $output);

        // All 10 should now have the status
        foreach ($ids as $id) {
            $this->assertEquals('active', get_post_meta($id, '_mhmrentiva_vehicle_status', true));
            $this->assertEquals('', get_post_meta($id, '_mhmrentiva_vehicle_availability', true));
        }
    }
}
