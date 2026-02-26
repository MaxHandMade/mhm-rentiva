<?php

/**
 * Transfer Data Import Tool
 * 
 * Usage:
 * 1. Place transfer_data.sql in tools/ directory
 * 2. Run: php tools/transfer-import.php transfer_data.sql
 * 
 * Or use phpMyAdmin to import the SQL file directly.
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

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic output.

global $wpdb;

// Get SQL file from argument
$sql_file = $argv[1] ?? '';

if (empty($sql_file)) {
    die("Usage: php tools/transfer-import.php <sql_file>\n");
}

$sql_path = __DIR__ . '/' . basename($sql_file);

if (!file_exists($sql_path)) {
    die("Error: SQL file not found: {$sql_path}\n");
}

echo "Importing Transfer data from: {$sql_file}\n";

// Read SQL file
$sql_content = file_get_contents($sql_path);

if ($sql_content === false) {
    die("Error: Could not read SQL file.\n");
}

// Split into individual queries by semicolon
$queries = array_filter(
    array_map('trim', explode(";", $sql_content)),
    function ($query) {
        $query = trim($query);
        if (empty($query)) {
            return false;
        }
        // If it still starts with -- after trimming, it might be a pure comment block (unlikely after split by ;)
        // but we'll handle stripping comments inside the loop.
        return true;
    }
);

$success_count = 0;
$error_count = 0;

foreach ($queries as $query) {
    // Strip comments from the query
    $clean_query = preg_replace('/^--.*$/m', '', $query);
    $clean_query = trim($clean_query);

    if (empty($clean_query)) {
        continue;
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL import tool intentionally executes vetted SQL file content.
    $result = $wpdb->query($clean_query);

    if ($result === false) {
        echo "Error executing query: " . $wpdb->last_error . "\n";
        echo "Query: " . substr($query, 0, 100) . "...\n";
        $error_count++;
    } else {
        $success_count++;
    }
}

echo "\nImport Complete:\n";
echo "- Successful queries: {$success_count}\n";
echo "- Failed queries: {$error_count}\n";

if ($error_count === 0) {
    echo "\n✅ All Transfer data imported successfully!\n";
} else {
    echo "\n⚠️ Some queries failed. Check errors above.\n";
}
