<?php

/**
 * Transfer Data Export/Import Tool
 * 
 * Usage:
 * 1. Export: php tools/transfer-export.php > transfer_data.sql
 * 2. Import: mysql -u username -p database_name < transfer_data.sql
 * 
 * Or use WordPress admin to run export and download SQL file.
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
    die("Error: WordPress not found. Run this from plugin directory.\n");
}

require_once $wp_load_path;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI export stream output.

// Force UTF-8 output
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
header('Content-Type: text/plain; charset=utf-8');

global $wpdb;

// Get table names
$routes_table = $wpdb->prefix . 'rentiva_transfer_routes';
$waypoints_table = $wpdb->prefix . 'rentiva_transfer_waypoints';

// Check if tables exist
$routes_exists = $wpdb->get_var("SHOW TABLES LIKE '{$routes_table}'") === $routes_table;
$waypoints_exists = $wpdb->get_var("SHOW TABLES LIKE '{$waypoints_table}'") === $waypoints_table;

if (!$routes_exists && !$waypoints_exists) {
    die("Error: Transfer tables not found.\n");
}

echo "-- MHM Rentiva Transfer Data Export\n";
echo "-- Generated: " . current_time('mysql') . "\n";
echo "-- Site: " . get_site_url() . "\n\n";

// Export Routes
if ($routes_exists) {
    $routes = $wpdb->get_results("SELECT * FROM {$routes_table}", ARRAY_A);

    if (!empty($routes)) {
        echo "-- Transfer Routes ({$routes_table})\n";
        echo "-- Total: " . count($routes) . " routes\n\n";

        foreach ($routes as $route) {
            $columns = array_keys($route);
            $values = array_map(function ($val) use ($wpdb) {
                return $val === null ? 'NULL' : $wpdb->prepare('%s', $val);
            }, array_values($route));

            $columns_str = implode(', ', array_map(function ($col) {
                return "`{$col}`";
            }, $columns));

            $values_str = implode(', ', $values);

            echo "INSERT INTO `{$routes_table}` ({$columns_str}) VALUES ({$values_str});\n";
        }

        echo "\n";
    }
}

// Export Waypoints
if ($waypoints_exists) {
    $waypoints = $wpdb->get_results("SELECT * FROM {$waypoints_table}", ARRAY_A);

    if (!empty($waypoints)) {
        echo "-- Transfer Waypoints ({$waypoints_table})\n";
        echo "-- Total: " . count($waypoints) . " waypoints\n\n";

        foreach ($waypoints as $waypoint) {
            $columns = array_keys($waypoint);
            $values = array_map(function ($val) use ($wpdb) {
                return $val === null ? 'NULL' : $wpdb->prepare('%s', $val);
            }, array_values($waypoint));

            $columns_str = implode(', ', array_map(function ($col) {
                return "`{$col}`";
            }, $columns));

            $values_str = implode(', ', $values);

            echo "INSERT INTO `{$waypoints_table}` ({$columns_str}) VALUES ({$values_str});\n";
        }

        echo "\n";
    }
}

echo "-- Export Complete\n";
