<?php

/**
 * Transfer Data Export Tool (Simplified - No BOM)
 * 
 * Exports Transfer data directly to SQL file without BOM.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 4) . '/');
}

if (! defined('ABSPATH')) {
    exit;
}

// Load WordPress
$wp_load_path = ABSPATH . 'wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: WordPress not found.\n");
}

require_once $wp_load_path;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI export status output.

global $wpdb;

/**
 * Resolve table name (handles legacy vs new naming)
 * Checks if new table exists, falls back to legacy if not
 */
function resolve_table_name(string $new_suffix, string $legacy_suffix): string
{
    global $wpdb;

    $new_table = $wpdb->prefix . $new_suffix;
    $legacy_table = $wpdb->prefix . $legacy_suffix;

    // Check if new table exists
    $table_exists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $new_table)
    );

    return $table_exists ? $new_table : $legacy_table;
}

// Get table names (with fallback logic)
$locations_table = resolve_table_name('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations');
$routes_table = resolve_table_name('rentiva_transfer_routes', 'mhm_rentiva_transfer_routes');
$waypoints_table = resolve_table_name('rentiva_transfer_waypoints', 'mhm_rentiva_transfer_waypoints');

// Output buffer
$sql = '';

$sql .= "-- MHM Rentiva Transfer Data Export\n";
$sql .= "-- Generated: " . current_time('mysql') . "\n";
$sql .= "-- Site: " . get_site_url() . "\n\n";

// Export Locations (MUST be first - routes reference these)
// ONLY export Transfer-enabled locations (allow_transfer = 1)
$locations = $wpdb->get_results(
    "SELECT * FROM {$locations_table} WHERE allow_transfer = 1 ORDER BY id",
    ARRAY_A
);

if (!empty($locations)) {
    $sql .= "-- Transfer Locations (Transfer-enabled only)\n";
    $sql .= "-- Total: " . count($locations) . " locations\n\n";

    foreach ($locations as $location) {
        $values = [];
        foreach ($location as $key => $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . $wpdb->_escape($value) . "'";
            }
        }

        $sql .= "INSERT INTO `{$locations_table}` VALUES (" . implode(', ', $values) . ");\n";
    }

    $sql .= "\n";
}

// Export Routes
// ONLY export routes where BOTH origin and destination are Transfer-enabled
$routes = $wpdb->get_results(
    "SELECT r.* FROM {$routes_table} r
     INNER JOIN {$locations_table} origin ON r.origin_id = origin.id
     INNER JOIN {$locations_table} dest ON r.destination_id = dest.id
     WHERE origin.allow_transfer = 1 AND dest.allow_transfer = 1
     ORDER BY r.id",
    ARRAY_A
);

if (!empty($routes)) {
    $sql .= "-- Transfer Routes (between Transfer-enabled locations only)\n";
    $sql .= "-- Total: " . count($routes) . " routes\n\n";

    foreach ($routes as $route) {
        $values = [];
        foreach ($route as $key => $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . $wpdb->_escape($value) . "'";
            }
        }

        $sql .= "INSERT INTO `{$routes_table}` VALUES (" . implode(', ', $values) . ");\n";
    }

    $sql .= "\n";
}

// Export Waypoints
$waypoints = $wpdb->get_results("SELECT * FROM {$waypoints_table}", ARRAY_A);

if (!empty($waypoints)) {
    $sql .= "-- Transfer Waypoints\n";
    $sql .= "-- Total: " . count($waypoints) . " waypoints\n\n";

    foreach ($waypoints as $waypoint) {
        $values = [];
        foreach ($waypoint as $key => $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . $wpdb->_escape($value) . "'";
            }
        }

        $sql .= "INSERT INTO `{$waypoints_table}` VALUES (" . implode(', ', $values) . ");\n";
    }

    $sql .= "\n";
}

$sql .= "-- Export Complete\n";

// Write to file (no BOM)
$output_file = __DIR__ . '/transfer_data_clean.sql';
file_put_contents($output_file, $sql, LOCK_EX);

echo "Export complete: {$output_file}\n";
echo "Total locations: " . count($locations) . "\n";
echo "Total routes: " . count($routes) . "\n";
echo "Total waypoints: " . count($waypoints) . "\n";
