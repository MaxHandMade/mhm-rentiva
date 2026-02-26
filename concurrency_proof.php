<?php

/**
 * Sprint 14 Concurrency & Isolation Proof (Fixed v2)
 * Verifies that Ledger data and Tenant Context are strictly isolated.
 */

use MHMRentiva\Core\Tenancy\TenantContext;
use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;

try {
    echo "--- MHM Rentiva v1.8 Isolation Proof Start ---\n";

    $vendorId = 999;
    $currency = 'USD';

    // 1. Setup Tenant A
    $tenantA = new TenantContext(101, 'tenant_a', 'en_US', 'eu');
    TenantResolver::set_context($tenantA);

    echo "Tenant A (ID 101) active. Adding 50.00 to Ledger...\n";
    $entryA = new LedgerEntry(
        wp_generate_uuid4(), // transaction_uuid
        $vendorId,           // vendor_id
        null,                // booking_id
        null,                // order_id
        'commission_credit', // type
        50.00,               // amount
        50.00,               // gross_amount
        0.0,                 // commission_amount
        0.0,                 // commission_rate
        $currency,           // currency
        'Test Isolation A',  // context
        'cleared'            // status
    );
    Ledger::add_entry($entryA);

    $balanceA = Ledger::get_balance($vendorId);
    echo "Tenant A Balance: " . $balanceA . "\n";

    // 2. Switch to Tenant B
    $tenantB = new TenantContext(102, 'tenant_b', 'tr_TR', 'global');
    TenantResolver::set_context($tenantB);

    echo "Tenant B (ID 102) active. Adding 75.00 to Ledger...\n";
    $entryB = new LedgerEntry(
        wp_generate_uuid4(), // transaction_uuid
        $vendorId,           // vendor_id
        null,                // booking_id
        null,                // order_id
        'commission_credit', // type
        75.00,               // amount
        75.00,               // gross_amount
        0.0,                 // commission_amount
        0.0,                 // commission_rate
        $currency,           // currency
        'Test Isolation B',  // context
        'cleared'            // status
    );
    Ledger::add_entry($entryB);

    $balanceB = Ledger::get_balance($vendorId);
    echo "Tenant B Balance: " . $balanceB . "\n";

    // Isolation check
    if ($balanceA === $balanceB) {
        throw new \Exception("ISOLATION BREACH: Tenant B sees Tenant A balance!");
    }

    // 3. Re-verify Tenant A
    TenantResolver::set_context($tenantA);
    $reCheckA = Ledger::get_balance($vendorId);
    echo "Tenant A Re-Check Balance: " . $reCheckA . "\n";

    if ($reCheckA !== 50.00) {
        throw new \Exception("DATA LEAK: Tenant A balance affected by Tenant B!");
    }

    echo "--- ISOLATION VERIFIED: SUCCESS ---\n";

    // 4. Test Hard Fallback Guard
    echo "Testing Hard Fallback Guard (Resetting context)...\n";
    TenantResolver::reset();
    try {
        Ledger::get_balance($vendorId);
        echo "FAIL: System allowed query without tenant context!\n";
        exit(1);
    } catch (\MHMRentiva\Core\Tenancy\Exceptions\TenantResolutionException $e) {
        echo "SUCCESS: System blocked query without context. Message: " . $e->getMessage() . "\n";
    }

    echo "--- v1.8 CERTIFICATION COMPLETE ---\n";
} catch (\Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
