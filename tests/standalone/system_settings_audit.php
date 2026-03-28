<?php

/**
 * System Settings Audit Script
 * 
 * Verifies that all System & Performance settings can be saved and retrieved accurately.
 */

// Load WordPress environment
require_once __DIR__ . '/../../../../../wp-load.php';

use MHMRentiva\Admin\Settings\Core\SettingsCore;

// Simulate admin user for CLI test
wp_set_current_user(1);
if (!current_user_can('manage_options')) {
    // Fallback if user 1 isn't admin
    die('Access Denied (User 1 is not admin)');
}

echo "<h1>System & Performance Settings Audit</h1>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%;'>";
echo "<tr style='background:#f0f0f0;'><th>Key</th><th>Test Value</th><th>DB Value (After Save)</th><th>SettingsCore::get()</th><th>Status</th></tr>";

$settings_to_test = [
    // Cache & Performance
    'mhm_rentiva_cache_enabled' => '1',
    'mhm_rentiva_cache_default_ttl' => 12.5,
    'mhm_rentiva_cache_lists_ttl' => 65,
    'mhm_rentiva_cache_reports_ttl' => 45,
    'mhm_rentiva_cache_charts_ttl' => 20,
    'mhm_rentiva_db_auto_optimize' => '0',
    'mhm_rentiva_db_performance_threshold' => 250,
    'mhm_rentiva_wp_optimization_enabled' => '0',
    'mhm_rentiva_wp_memory_limit' => 512,

    // Security
    'mhm_rentiva_ip_whitelist_enabled' => '1',
    'mhm_rentiva_ip_whitelist' => "127.0.0.1\n192.168.1.100",
    'mhm_rentiva_ip_blacklist_enabled' => '1',
    'mhm_rentiva_ip_blacklist' => "10.0.0.5\n10.0.0.6",
    'mhm_rentiva_country_restriction_enabled' => '1',
    'mhm_rentiva_allowed_countries' => 'TR,US,DE',
    'mhm_rentiva_brute_force_protection' => '1',
    'mhm_rentiva_max_login_attempts' => 8,
    'mhm_rentiva_login_lockout_duration' => 45,
    'mhm_rentiva_sql_injection_protection' => '1',
    'mhm_rentiva_xss_protection' => '1',
    'mhm_rentiva_csrf_protection' => '0',

    // Logging
    'mhm_rentiva_log_level' => 'debug',
    'mhm_rentiva_log_cleanup_enabled' => '0',
    'mhm_rentiva_log_retention_days' => 60,
    'mhm_rentiva_log_max_size' => 25,

    // Debugging
    'mhm_rentiva_debug_mode' => '1',

    // DB Cleanup
    'mhm_rentiva_clean_data_on_uninstall' => '1',

    // Reconciliation
    'mhm_rentiva_reconcile_enabled' => '1',
    'mhm_rentiva_reconcile_frequency' => 'hourly',
    'mhm_rentiva_reconcile_timeout' => 45,
    'mhm_rentiva_reconcile_notify_errors' => '0',
];

foreach ($settings_to_test as $key => $test_val) {
    // 1. Update Option
    update_option($key, $test_val);

    // 2. Read direct from DB
    $db_val = get_option($key);

    // 3. Read via SettingsCore
    $core_val = SettingsCore::get($key);

    // Compare
    // Note: get_option might return strings for numbers, cast for comparison if needed or loose compare
    $match_db = ($db_val == $test_val);
    $match_core = ($core_val == $test_val);

    $status_icon = ($match_db && $match_core) ? '✅' : '❌';

    // Formatting for display
    $display_test_val = is_array($test_val) ? implode(',', $test_val) : (string)$test_val;
    $display_db_val = is_array($db_val) ? implode(',', $db_val) : (string)$db_val;
    $display_core_val = is_array($core_val) ? implode(',', $core_val) : (string)$core_val;

    echo "<tr>";
    echo "<td>{$key}</td>";
    echo "<td><pre>{$display_test_val}</pre></td>";
    echo "<td><pre>{$display_db_val}</pre></td>";
    echo "<td><pre>{$display_core_val}</pre></td>";
    echo "<td style='text-align:center;'>{$status_icon}</td>";
    echo "</tr>";
}

echo "</table>";
