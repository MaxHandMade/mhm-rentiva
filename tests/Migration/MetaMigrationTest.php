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
        add_filter('mhm_rentiva_lite_max_vehicles', function () {
            return 999;
        });
        add_filter('mhm_rentiva_lite_max_bookings', function () {
            return 999;
        });

        $this->migration_script = rtrim(dirname(dirname(__DIR__)), '/\\') . '/bin/mhm-migrate-meta.php';

        // Clean up any existing vehicles to ensure isolation
        $existing = get_posts(['post_type' => 'vehicle', 'posts_per_page' => -1, 'fields' => 'ids']);
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
     * Test mapping: yes/no/1/0 -> active/inactive
     */
    public function test_enum_mapping_strict()
    {
        $v1 = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($v1, '_mhm_vehicle_availability', 'yes');

        $v2 = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($v2, '_mhm_rentiva_availability', 'no');

        $v3 = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($v3, '_mhm_vehicle_availability', '1');

        $v4 = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($v4, '_mhm_vehicle_availability', 'unknown'); // Should be skipped

        $this->run_migration(false);

        $this->assertEquals('active', get_post_meta($v1, '_mhm_vehicle_status', true));
        $this->assertEquals('inactive', get_post_meta($v2, '_mhm_vehicle_status', true));
        $this->assertEquals('active', get_post_meta($v3, '_mhm_vehicle_status', true));
        $this->assertEquals('', get_post_meta($v4, '_mhm_vehicle_status', true)); // Skipped
    }

    /**
     * Test Conflict: Standard Key Wins
     */
    public function test_conflict_resolution_standard_wins()
    {
        $v1 = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($v1, '_mhm_vehicle_status', 'maintenance'); // Standard
        update_post_meta($v1, '_mhm_vehicle_availability', 'active'); // Legacy conflict

        $this->run_migration(false);

        // Standard should remain 'maintenance', legacy should be deleted
        $this->assertEquals('maintenance', get_post_meta($v1, '_mhm_vehicle_status', true));
        $this->assertEquals('', get_post_meta($v1, '_mhm_vehicle_availability', true));
    }

    /**
     * Test Idempotency: Second run should result in 0 changes
     */
    public function test_idempotency()
    {
        $v1 = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($v1, '_mhm_vehicle_availability', 'active');

        // First run
        $output1 = $this->run_migration(false);
        $this->assertStringContainsString('Status migrated:          1', $output1);
        $this->assertEquals('active', get_post_meta($v1, '_mhm_vehicle_status', true));

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
            $id = $this->factory->post->create(['post_type' => 'vehicle']);
            update_post_meta($id, '_mhm_vehicle_availability', 'active');
            $ids[] = $id;
        }

        // Manually migrate 5 of them
        for ($i = 0; $i < 5; $i++) {
            update_post_meta($ids[$i], '_mhm_vehicle_status', 'active');
            delete_post_meta($ids[$i], '_mhm_vehicle_availability');
        }

        // Run migration for the remaining
        $output = $this->run_migration(false);

        // Should report 5 migrated status (the ones that weren't manually done)
        $this->assertStringContainsString('Status migrated:          5', $output);

        // All 10 should now have the status
        foreach ($ids as $id) {
            $this->assertEquals('active', get_post_meta($id, '_mhm_vehicle_status', true));
            $this->assertEquals('', get_post_meta($id, '_mhm_vehicle_availability', true));
        }
    }
}
