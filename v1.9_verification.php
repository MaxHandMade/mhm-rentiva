<?php

declare(strict_types=1);

/**
 * SaaS Orchestration Layer (v1.9) Physical Verification Script.
 * 
 * Tests:
 * 1. Atomic Tenant Provisioning
 * 2. Metered Usage Tracking (Ledger & Payouts)
 * 3. Quota Enforcement (Hard Block)
 * 4. Tenant Lifecycle Governance (Suspension)
 * 5. Risk Event Metering
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
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use MHMRentiva\Core\Financial\AtomicPayoutService;
use MHMRentiva\Core\Financial\GovernanceService;

if (php_sapi_name() !== 'cli' && !current_user_can('manage_options')) {
    die('Unauthorized');
}

header('Content-Type: text/plain');

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "--- MHM RENTIVA v1.9 SAAS VERIFICATION ---\n\n";

global $wpdb;

// Cleanup previous test data
$wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_tenants WHERE tenant_id = 9999");
$wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_usage_metrics WHERE tenant_id = 9999");
$wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_title LIKE 'Test Payout v1.9%'");

// 1. Provision New Tenant
echo "1. Provisioning Tenant...\n";
$test_tenant_id = 9999;
$success = TenantProvisioner::provision(
    $test_tenant_id,
    'test-saas-v1.9.local',
    '/',
    ['payouts' => 10, 'ledger_entries' => 100]
);

if (!$success) {
    die("Provisioning failed. Check logs.\n");
}
$tenant_id = $test_tenant_id;
echo "✓ Tenant #{$tenant_id} provisioned successfully.\n";

// Associate with a blog for resolver (In non-multisite it defaults to 1)
update_site_option('mhm_rentiva_test_tenant_id', $tenant_id);

// 2. Metering Verification (Ledger)
echo "\n2. Testing Ledger Metering...\n";
// Manually set context for the script
$context = new \MHMRentiva\Core\Tenancy\TenantContext($tenant_id, 'test_tenant', 'tr_TR', 'pro');
TenantResolver::set_context($context);

$entry = new LedgerEntry(
    'test_metering_' . uniqid(),
    999,
    null,
    null,
    'test_entry',
    100.0,
    null,
    null,
    null,
    'TRY',
    'test',
    'cleared'
);

Ledger::add_entry($entry);
echo "✓ Ledger entry added.\n";

$usage = $wpdb->get_var($wpdb->prepare(
    "SELECT metric_value FROM {$wpdb->prefix}mhm_rentiva_usage_metrics WHERE tenant_id = %d AND metric_type = 'ledger_entries'",
    $tenant_id
));
echo "✓ Current Ledger Usage: {$usage}\n";

// 3. Quota Enforcement
echo "\n3. Testing Quota Enforcement...\n";
// Artificially lower quota for testing
$wpdb->update(
    "{$wpdb->prefix}mhm_rentiva_tenants",
    ['quota_ledger_entries_limit' => 2],
    ['tenant_id' => $tenant_id]
);

echo "Attempting second entry (Allowed)...\n";
Ledger::add_entry($entry); // Usage becomes 2

try {
    echo "Attempting third entry (Should be Blocked)...\n";
    Ledger::add_entry($entry);
    echo "❌ FAIL: Quota exceeded but entry allowed.\n";
} catch (\MHMRentiva\Core\Orchestration\Exceptions\QuotaExceededException $e) {
    echo "✓ PASS: Got QuotaExceededException: " . $e->getMessage() . "\n";
}

// 4. Lifecycle Governance (Suspension)
echo "\n4. Testing Tenant Suspension...\n";
$wpdb->update(
    "{$wpdb->prefix}mhm_rentiva_tenants",
    ['status' => 'suspended'],
    ['tenant_id' => $tenant_id]
);

try {
    echo "Attempting entry on suspended tenant...\n";
    Ledger::add_entry($entry);
    echo "❌ FAIL: Suspended tenant allowed to write.\n";
} catch (\RuntimeException $e) {
    echo "✓ PASS: Operation blocked for suspended tenant: " . $e->getMessage() . "\n";
}

// 5. Risk Event Metering
echo "\n5. Testing Risk Event Metering...\n";
$wpdb->update(
    "{$wpdb->prefix}mhm_rentiva_tenants",
    ['status' => 'active'],
    ['tenant_id' => $tenant_id]
);

// We need a real payout post to trigger risk events in GovernanceService
$payout_id = wp_insert_post([
    'post_title' => 'Test Payout v1.9 Risk',
    'post_status' => 'pending',
    'post_type' => 'mhm_payout',
    'post_author' => 1
]);
update_post_meta($payout_id, '_mhm_payout_amount', 5000000); // Massive amount to trigger High Risk

echo "Processing high-risk approval (Should trigger ACTION_FLAGGED)...\n";
$result = GovernanceService::process_approval($payout_id);

if (is_wp_error($result) && $result->get_error_code() === 'governance_frozen_risk') {
    echo "✓ Payout flagged correctly.\n";
    $risk_usage = $wpdb->get_var($wpdb->prepare(
        "SELECT metric_value FROM {$wpdb->prefix}mhm_rentiva_usage_metrics WHERE tenant_id = %d AND metric_type = 'risk_events'",
        $tenant_id
    ));
    echo "✓ Risk Metrics Captured: {$risk_usage}\n";
    if ($risk_usage > 0) {
        echo "✓ PASS: Risk event metering verified.\n";
    } else {
        echo "❌ FAIL: Risk event usage remains 0.\n";
    }
} else {
    echo "❌ FAIL: Unexpected result from high-risk approval.\n";
}

echo "\n--- VERIFICATION COMPLETE ---\n";
