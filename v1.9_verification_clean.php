<?php

declare(strict_types=1);

/**
 * Isolated SaaS v1.9 Verification.
 */

if (file_exists('/var/www/html/wp-load.php')) {
    require_once '/var/www/html/wp-load.php';
} else {
    require_once __DIR__ . '/../../wp/wp-load.php';
}

use MHMRentiva\Core\Orchestration\TenantProvisioner;
use MHMRentiva\Core\Orchestration\MeteredUsageTracker;
use MHMRentiva\Core\Orchestration\ControlPlaneGuard;
use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Tenancy\TenantContext;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use MHMRentiva\Core\Financial\GovernanceService;

if (php_sapi_name() !== 'cli') {
    die('CLI required');
}

echo "--- MHM RENTIVA v1.9 ISOLATED VERIFICATION ---\n";

global $wpdb;
$test_tenant_id = 9999;

// 0. CLEANUP
$wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_tenants WHERE tenant_id = $test_tenant_id");
$wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_usage_metrics WHERE tenant_id = $test_tenant_id");
$wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_title LIKE 'Test Payout v1.9%'");

// 1. PROVISIONING
echo "[1] Provisioning...\n";
$success = TenantProvisioner::provision($test_tenant_id, 'test.local', '/', ['ledger_entries' => 2]);
if (!$success) die("Provisioning Failed\n");
echo "✓ Success\n";

// 2. CONTEXT SETUP
$context = new TenantContext($test_tenant_id, 'test', 'tr_TR', 'pro');
TenantResolver::set_context($context);
echo "[2] Context Set to #{$test_tenant_id}\n";

// 3. METERING & QUOTA
echo "[3] Testing Metering & Quota...\n";
$entry = new LedgerEntry('tx_' . uniqid(), 1, null, null, 'test', 10.0, null, null, null, 'TRY', 'test', 'cleared');

try {
    echo "Entry 1: ";
    Ledger::add_entry($entry);
    echo "OK\n";

    echo "Entry 2: ";
    $entry2 = new LedgerEntry('tx_' . uniqid(), 1, null, null, 'test', 10.0, null, null, null, 'TRY', 'test', 'cleared');
    Ledger::add_entry($entry2);
    echo "OK\n";

    $usage = MeteredUsageTracker::get_usage($test_tenant_id, 'ledger_entries');
    echo "Current Usage: $usage (Limit: 2)\n";

    echo "Entry 3 (Should Block): ";
    $entry3 = new LedgerEntry('tx_' . uniqid(), 1, null, null, 'test', 10.0, null, null, null, 'TRY', 'test', 'cleared');
    Ledger::add_entry($entry3);
    echo "❌ FAIL: Allowed\n";
} catch (\MHMRentiva\Core\Orchestration\Exceptions\QuotaExceededException $e) {
    echo "✓ PASS: Blocked (" . $e->getMessage() . ")\n";
}

// 4. SUSPENSION
echo "[4] Testing Suspension...\n";
$wpdb->update($wpdb->prefix . 'mhm_rentiva_tenants', ['status' => 'suspended'], ['tenant_id' => $test_tenant_id]);

try {
    $entry4 = new LedgerEntry('tx_' . uniqid(), 1, null, null, 'test', 10.0, null, null, null, 'TRY', 'test', 'cleared');
    Ledger::add_entry($entry4);
    echo "❌ FAIL: Allowed on suspended\n";
} catch (\RuntimeException $e) {
    echo "✓ PASS: Blocked (" . $e->getMessage() . ")\n";
}

// 5. RISK METERING
echo "[5] Testing Risk Metering...\n";
$wpdb->update($wpdb->prefix . 'mhm_rentiva_tenants', ['status' => 'ACTIVE'], ['tenant_id' => $test_tenant_id]);

$payout_id = wp_insert_post([
    'post_title' => 'Test Payout v1.9 Risk',
    'post_type' => 'mhm_payout',
    'post_status' => 'pending',
    'post_author' => 1
]);
update_post_meta($payout_id, '_mhm_payout_amount', 5000000); // Trigger high risk

$result = GovernanceService::process_approval($payout_id);
if (is_wp_error($result) && $result->get_error_code() === 'governance_frozen_risk') {
    $risk_usage = MeteredUsageTracker::get_usage($test_tenant_id, 'risk_events');
    echo "✓ Risk Captured: $risk_usage\n";
} else {
    echo "❌ FAIL: Risk not captured or different error\n";
}

echo "\n--- VERIFICATION COMPLETE ---\n";
