<?php

/**
 * MHM Rentiva — Meta Migration Tool (CLI)
 *
 * Usage: wp eval-file bin/mhm-migrate-meta.php dry-run batch-size=100
 *        wp eval-file bin/mhm-migrate-meta.php cleanup-empty-legacy
 *
 * @package MHMRentiva
 * @since 4.9.9
 * @deprecated 4.9.9 Removal scheduled for v4.10.x
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// 1. Parsing Arguments
$args_raw = $args ?? []; // wp eval-file passes args via $args
$is_dry_run = false;
$batch_size = 100;
$cleanup_empty = false;

foreach ($args_raw as $arg) {
    if ($arg === 'dry-run' || $arg === '--dry-run') {
        $is_dry_run = true;
    }
    if ($arg === 'cleanup-empty-legacy' || $arg === '--cleanup-empty-legacy') {
        $cleanup_empty = true;
    }
    if (strpos($arg, 'batch-size=') === 0 || strpos($arg, '--batch-size=') === 0) {
        $batch_size = (int)str_replace(['batch-size=', '--batch-size='], '', $arg);
    }
}

// 2. Constants & Allowed Values (Strict Mapping)
$allowed_status = ['active', 'inactive', 'maintenance'];
$status_mapping = [
    'yes'      => 'active',
    'no'       => 'inactive',
    '1'        => 'active',
    'active'   => 'active',
    '0'        => 'inactive',
    'inactive' => 'inactive',
    'passive'  => 'inactive'
];

$meta_mappings = [
    'featured' => [
        'target' => '_mhm_rentiva_featured',
        'legacy' => ['_mhm_rentiva_is_featured']
    ],
    'status'   => [
        'target' => '_mhm_vehicle_status',
        'legacy' => ['_mhm_vehicle_availability', '_mhm_rentiva_availability']
    ]
];

// 3. Stats
$stats = [
    'scanned'  => 0,
    'featured' => 0,
    'status'   => 0,
    'removed'  => 0,
    'conflict' => 0,
    'invalid'  => 0,
    'skipped'  => 0
];

echo "\n🚀 MHM Rentiva: Meta Migration Starting...\n";
if ($is_dry_run) {
    echo "⚠️  DRY-RUN MODE: No changes will be persisted. Writing is DISABLED.\n";
}
echo "📦 Batch Size: $batch_size\n";
echo "🔐 Policy: Standard Key Wins | Strict Enum Mapping\n\n";

// 4. Batch Processing
$offset = 0;

// Transaction Check (MyISAM detection)
$is_myisam = false;
$table_status = $wpdb->get_row($wpdb->prepare("SHOW TABLE STATUS LIKE %s", $wpdb->postmeta));
if ($table_status && strtolower($table_status->Engine) === 'myisam') {
    $is_myisam = true;
    echo "ℹ️  Note: MyISAM detected. Atomicity will be handled per-batch manually.\n";
}

while (true) {
    $vehicles = $wpdb->get_results($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'vehicle' ORDER BY ID ASC LIMIT %d OFFSET %d",
        $batch_size,
        $offset
    ));

    if (empty($vehicles)) {
        break;
    }

    $use_transaction = !$is_dry_run && !$is_myisam;

    if ($use_transaction) {
        $wpdb->query('START TRANSACTION');
    }

    try {
        foreach ($vehicles as $vehicle) {
            $vehicle_id = (int)$vehicle->ID;
            $stats['scanned']++;

            // --- FEATURED MIGRATION ---
            $target_featured_key = $meta_mappings['featured']['target'];
            $current_featured = get_post_meta($vehicle_id, $target_featured_key, true);

            foreach ($meta_mappings['featured']['legacy'] as $legacy_key) {
                $legacy_val = get_post_meta($vehicle_id, $legacy_key, true);
                if ($legacy_val === '' || $legacy_val === false) {
                    if ($cleanup_empty && $legacy_val === '') {
                        if (!$is_dry_run) {
                            delete_post_meta($vehicle_id, $legacy_key);
                        }
                        echo "[CLEANUP] Vehicle ID #$vehicle_id: Removing empty legacy featured key '$legacy_key'\n";
                        $stats['removed']++;
                    }
                    continue;
                }

                // POLICY: Standard key wins
                if ($current_featured !== '') {
                    // If target exists, ignore legacy (Standard Wins)
                    if ((string)$current_featured !== (string)$legacy_val) {
                        echo "[CONFLICT] Vehicle ID #$vehicle_id: Target '$target_featured_key' dominates (val: $current_featured vs legacy: $legacy_val in $legacy_key)\n";
                        $stats['conflict']++;
                    } else {
                        echo "[REDUNDANT] Vehicle ID #$vehicle_id: Legacy key $legacy_key matches target. Cleaning up.\n";
                    }
                } else {
                    // Migrating
                    if (!$is_dry_run) {
                        update_post_meta($vehicle_id, $target_featured_key, $legacy_val);
                    }
                    echo "[MIGRATE] Vehicle ID #$vehicle_id: Moving $legacy_key -> $target_featured_key (val: $legacy_val)\n";
                    $stats['featured']++;
                    $current_featured = $legacy_val; // Update local state for subsequent legacy keys of the same vehicle
                }

                // Cleanup legacy key
                if (!$is_dry_run) {
                    delete_post_meta($vehicle_id, $legacy_key);
                }
                $stats['removed']++;
            }

            // --- STATUS MIGRATION ---
            $target_status_key = $meta_mappings['status']['target'];
            $current_status = get_post_meta($vehicle_id, $target_status_key, true);

            foreach ($meta_mappings['status']['legacy'] as $legacy_key) {
                $legacy_val = get_post_meta($vehicle_id, $legacy_key, true);
                if ($legacy_val === '' || $legacy_val === false) continue;

                // STRICT MAPPING
                $mapped_val = $status_mapping[$legacy_val] ?? null;

                if ($mapped_val === null) {
                    echo "[INVALID] Vehicle ID #$vehicle_id: Unrecognized value '$legacy_val' in $legacy_key. ";
                    if ($cleanup_empty && $legacy_val === '') {
                        if (!$is_dry_run) {
                            delete_post_meta($vehicle_id, $legacy_key);
                        }
                        echo "Cleaning up empty legacy record.\n";
                        $stats['removed']++;
                    } else {
                        echo "Skipping.\n";
                        $stats['invalid']++;
                    }
                    continue;
                }

                // POLICY: Standard key wins
                if ($current_status !== '') {
                    if ((string)$current_status !== (string)$mapped_val) {
                        echo "[CONFLICT] Vehicle ID #$vehicle_id: Target '$target_status_key' dominates (val: $current_status vs legacy: $mapped_val in $legacy_key)\n";
                        $stats['conflict']++;
                    } else {
                        echo "[REDUNDANT] Vehicle ID #$vehicle_id: Legacy key $legacy_key matches target. Cleaning up.\n";
                    }
                } else {
                    // Migrating
                    if (!$is_dry_run) {
                        update_post_meta($vehicle_id, $target_status_key, $mapped_val);
                    }
                    echo "[MIGRATE] Vehicle ID #$vehicle_id: Moving $legacy_key -> $target_status_key (val: $mapped_val)\n";
                    $stats['status']++;
                    $current_status = $mapped_val; // Update local state
                }

                // Cleanup legacy key
                if (!$is_dry_run) {
                    delete_post_meta($vehicle_id, $legacy_key);
                }
                $stats['removed']++;
            }

            // Memory Leak Prevention
            wp_cache_delete($vehicle_id, 'post_meta');
        }

        if ($use_transaction) {
            $wpdb->query('COMMIT');
        }
    } catch (\Exception $e) {
        if ($use_transaction) {
            $wpdb->query('ROLLBACK');
        }
        echo "❌ FATAL ERROR in Batch Offset $offset: " . $e->getMessage() . "\n";
        exit(1);
    }

    echo "✅ Chunk processed. Total scanned: " . $stats['scanned'] . "\r";
    $offset += $batch_size;
}

// 5. Final Report
echo "\n\n***************************************\n";
echo "📊 MIGRATION SUMMARY REPORT\n";
echo "***************************************\n";
echo "Vehicles scanned:         " . $stats['scanned'] . "\n";
echo "Featured migrated:        " . $stats['featured'] . "\n";
echo "Status migrated:          " . $stats['status'] . "\n";
echo "Legacy keys removed:      " . $stats['removed'] . "\n";
echo "Conflicts detected:       " . $stats['conflict'] . " (Standard Wins rule applied)\n";
echo "Skipped (invalid values): " . $stats['invalid'] . "\n";
echo "***************************************\n";

if (!$is_dry_run) {
    echo "✨ Migration complete. Clearing caches...\n";
    if (class_exists('\MHMRentiva\Admin\Core\Utilities\CacheManager')) {
        \MHMRentiva\Admin\Core\Utilities\CacheManager::clear_vehicle_cache(0);
    }
} else {
    echo "🧪 Dry-run complete. No data was changed.\n";
}
echo "\n";
