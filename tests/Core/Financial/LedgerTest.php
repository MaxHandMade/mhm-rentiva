<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

class LedgerTest extends WP_UnitTestCase
{
    private int $vendor_id;

    public function setUp(): void
    {
        parent::setUp();

        // Ensure ledger table exists in test DB (Test DBs drop dynamically)
        LedgerMigration::create_table();

        $this->vendor_id = self::factory()->user->create(array('role' => 'mhm_rentiva_vendor'));
    }

    public function test_duplicate_uuid_silent_ignore(): void
    {
        $uuid = 'test_uuid_' . wp_generate_password(8, false);

        $entry1 = new LedgerEntry(
            $uuid,
            $this->vendor_id,
            100,
            200,
            'commission_credit',
            50.0,
            100.0, // gross
            50.0,  // commission
            15.0,  // rate
            'TRY',
            'vendor',
            'cleared'
        );

        // First acts normally
        Ledger::add_entry($entry1);

        // Second should identically abort without exception (Idempotent architecture rule)
        Ledger::add_entry($entry1);

        $balance = Ledger::get_balance($this->vendor_id);
        $this->assertEquals(50.0, $balance, 'Idempotency failed. Ledger duplicated the DB record resulting in cumulative drift.');
    }

    public function test_pending_vs_cleared_separation(): void
    {
        // 1_pending_x
        $pending = new LedgerEntry(wp_generate_password(12, false), $this->vendor_id, 1, 1, 'commission_credit', 100.0, 100.0, 0.0, 0.0, 'TRY', 'vendor', 'pending');
        Ledger::add_entry($pending);

        // 2_cleared_y
        $cleared = new LedgerEntry(wp_generate_password(12, false), $this->vendor_id, 2, 2, 'commission_credit', 50.0, 50.0, 0.0, 0.0, 'TRY', 'vendor', 'cleared');
        Ledger::add_entry($cleared);

        // Pending
        $this->assertEquals(100.0, Ledger::get_pending_balance($this->vendor_id));

        // Cleared
        $this->assertEquals(50.0, Ledger::get_balance($this->vendor_id));
    }

    public function test_refund_negative_entry_deduction(): void
    {
        // Initial credit 
        Ledger::add_entry(new LedgerEntry(wp_generate_password(12, false), $this->vendor_id, 11, 11, 'commission_credit', 1500.0, 1500.0, 0.0, 0.0, 'TRY', 'vendor', 'cleared'));

        // Reversal / refund logic injecting negative balances
        Ledger::add_entry(new LedgerEntry(wp_generate_password(12, false), $this->vendor_id, 11, 11, 'commission_refund', -300.0, -300.0, 0.0, 0.0, 'TRY', 'vendor', 'cleared'));

        $this->assertEquals(1200.0, Ledger::get_balance($this->vendor_id));
    }

    public function test_payout_debit_balance_reduction(): void
    {
        Ledger::add_entry(new LedgerEntry(wp_generate_password(12, false), $this->vendor_id, null, null, 'commission_credit', 5000.0, 5000.0, 0.0, 0.0, 'TRY', 'vendor', 'cleared'));

        Ledger::add_entry(new LedgerEntry(wp_generate_password(12, false), $this->vendor_id, null, null, 'payout_debit', -4000.0, -4000.0, 0.0, 0.0, 'TRY', 'vendor', 'cleared'));

        $this->assertEquals(1000.0, Ledger::get_balance($this->vendor_id));
        $this->assertEquals(5000.0, Ledger::get_total_earned($this->vendor_id), 'Total earned should rigidly ignore payout deductions resolving strict aggregate gross boundaries.');
    }

    public function test_multiple_vendor_isolation(): void
    {
        $vendor_two = self::factory()->user->create(array('role' => 'mhm_rentiva_vendor'));

        // V1
        Ledger::add_entry(new LedgerEntry(wp_generate_password(12, false), $this->vendor_id, 1, 1, 'commission_credit', 700.0, 700.0, 0.0, 0.0, 'TRY', 'vendor', 'cleared'));
        // V2
        Ledger::add_entry(new LedgerEntry(wp_generate_password(12, false), $vendor_two, 2, 2, 'commission_credit', 300.0, 300.0, 0.0, 0.0, 'TRY', 'vendor', 'cleared'));

        $this->assertEquals(700.0, Ledger::get_balance($this->vendor_id));
        $this->assertEquals(300.0, Ledger::get_balance($vendor_two));
    }
}
