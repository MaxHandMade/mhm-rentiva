<?php

/**
 * Transfer Data Inspector
 * Shows all Transfer locations and routes with their properties
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 4) . '/');
}

if (! defined('ABSPATH')) {
    exit;
}

require_once ABSPATH . 'wp-load.php';

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic output.

global $wpdb;

// Resolve table names
function resolve_table_name(string $new_suffix, string $legacy_suffix): string
{
    global $wpdb;
    $new_table = $wpdb->prefix . $new_suffix;
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new_table));
    return $table_exists ? $new_table : ($wpdb->prefix . $legacy_suffix);
}

$locations_table = resolve_table_name('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations');
$routes_table = resolve_table_name('rentiva_transfer_routes', 'mhm_rentiva_transfer_routes');

echo "=== TRANSFER LOCATIONS ===\n\n";
$locations = $wpdb->get_results("SELECT * FROM {$locations_table} ORDER BY id", ARRAY_A);

foreach ($locations as $loc) {
    echo "ID: {$loc['id']}\n";
    echo "Name: {$loc['name']}\n";
    echo "Type: {$loc['type']}\n";
    echo "Allow Rental: " . ($loc['allow_rental'] ? 'YES' : 'NO') . "\n";
    echo "Allow Transfer: " . ($loc['allow_transfer'] ? 'YES' : 'NO') . "\n";
    echo "---\n";
}

echo "\n=== TRANSFER ROUTES ===\n\n";
$routes = $wpdb->get_results("SELECT * FROM {$routes_table} ORDER BY id", ARRAY_A);

foreach ($routes as $route) {
    $origin = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$locations_table} WHERE id = %d", $route['origin_id']));
    $dest = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$locations_table} WHERE id = %d", $route['destination_id']));

    echo "ID: {$route['id']}\n";
    echo "Route: {$origin} → {$dest}\n";
    echo "Distance: {$route['distance_km']} km\n";
    echo "Duration: {$route['duration_min']} min\n";
    echo "Pricing: {$route['pricing_method']}\n";
    echo "Base Price: {$route['base_price']}\n";
    echo "---\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total Locations: " . count($locations) . "\n";
echo "Transfer-enabled Locations: " . count(array_filter($locations, fn($l) => $l['allow_transfer'])) . "\n";
echo "Rental-only Locations: " . count(array_filter($locations, fn($l) => $l['allow_rental'] && !$l['allow_transfer'])) . "\n";
echo "Total Routes: " . count($routes) . "\n";

// Also write to file (UTF-8, no BOM)
ob_start();
echo "=== TRANSFER LOCATIONS ===\n\n";
foreach ($locations as $loc) {
    echo "ID: {$loc['id']}\n";
    echo "Name: {$loc['name']}\n";
    echo "Type: {$loc['type']}\n";
    echo "Allow Rental: " . ($loc['allow_rental'] ? 'YES' : 'NO') . "\n";
    echo "Allow Transfer: " . ($loc['allow_transfer'] ? 'YES' : 'NO') . "\n";
    echo "---\n";
}

echo "\n=== TRANSFER ROUTES ===\n\n";
foreach ($routes as $route) {
    $origin = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$locations_table} WHERE id = %d", $route['origin_id']));
    $dest = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$locations_table} WHERE id = %d", $route['destination_id']));

    echo "ID: {$route['id']}\n";
    echo "Route: {$origin} → {$dest}\n";
    echo "Distance: {$route['distance_km']} km\n";
    echo "Duration: {$route['duration_min']} min\n";
    echo "Pricing: {$route['pricing_method']}\n";
    echo "Base Price: {$route['base_price']}\n";
    echo "---\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total Locations: " . count($locations) . "\n";
echo "Transfer-enabled Locations: " . count(array_filter($locations, fn($l) => $l['allow_transfer'])) . "\n";
echo "Rental-only Locations: " . count(array_filter($locations, fn($l) => $l['allow_rental'] && !$l['allow_transfer'])) . "\n";
echo "Total Routes: " . count($routes) . "\n";

$output = ob_get_clean();
file_put_contents(__DIR__ . '/transfer_inspection_utf8.txt', $output, LOCK_EX);
