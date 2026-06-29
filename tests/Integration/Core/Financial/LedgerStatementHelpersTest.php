<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial;

use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class LedgerStatementHelpersTest extends WP_UnitTestCase {

	private function seed(int $vendor, string $type, float $amount): void {
		Ledger::add_entry(new LedgerEntry(
			wp_generate_password(12, false),
			$vendor, 123, null, $type, $amount, null, null, null, 'TRY', 'vendor', 'cleared'
		));
	}

	public function test_get_max_entry_id_zero_when_no_entries(): void {
		$this->assertSame(0, Ledger::get_max_entry_id(999321));
	}

	public function test_get_statement_lines_returns_only_in_range_cleared_activity(): void {
		$vendor = (int) $this->factory->user->create();
		$this->seed($vendor, 'commission_credit', 100.0);
		$start  = Ledger::get_max_entry_id($vendor); // boundary: covered range is id > $start
		$this->seed($vendor, 'commission_credit', 50.0);
		$this->seed($vendor, 'withdrawal_penalty', -20.0);
		$end    = Ledger::get_max_entry_id($vendor);

		$lines = Ledger::get_statement_lines($vendor, $start, $end);

		$this->assertCount(2, $lines, 'Only the two entries after the boundary are covered.');
		$amounts = array_map(static fn($r) => (float) $r->amount, $lines);
		$this->assertSame(array(50.0, -20.0), $amounts);
	}

	public function test_get_statement_lines_excludes_payout_debit(): void {
		$vendor = (int) $this->factory->user->create();
		$this->seed($vendor, 'commission_credit', 100.0);
		$this->seed($vendor, 'payout_debit', -100.0);
		$end = Ledger::get_max_entry_id($vendor);

		$lines = Ledger::get_statement_lines($vendor, 0, $end);

		$this->assertCount(1, $lines, 'payout_debit is a payment, not statement activity.');
		$this->assertSame('commission_credit', $lines[0]->type);
	}
}
