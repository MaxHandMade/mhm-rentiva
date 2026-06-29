<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial;

use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class LedgerCommissionLinesTest extends WP_UnitTestCase {

	public function test_statement_lines_carry_commission_fields(): void {
		$vendor = (int) $this->factory->user->create();
		// commission_credit: amount=net(800), gross=1000, commission=200, rate=20
		Ledger::add_entry(new LedgerEntry(
			'cc_' . uniqid('', true), $vendor, 4002, null,
			'commission_credit', 800.0, 1000.0, 200.0, 20.0, 'TRY', 'vendor', 'cleared'
		));
		// penalty: no commission
		Ledger::add_entry(new LedgerEntry(
			'pen_' . uniqid('', true), $vendor, null, null,
			'withdrawal_penalty', -50.0, null, null, null, 'TRY', 'vendor', 'cleared'
		));
		$end = Ledger::get_max_entry_id($vendor);

		$lines = Ledger::get_statement_lines($vendor, 0, $end);

		$this->assertCount(2, $lines);
		$earn = $lines[0];
		$this->assertSame('commission_credit', $earn->type);
		$this->assertSame(1000.0, (float) $earn->gross_amount);
		$this->assertSame(200.0, (float) $earn->commission_amount);
		$this->assertSame(20.0, (float) $earn->commission_rate);
		$pen = $lines[1];
		$this->assertSame('withdrawal_penalty', $pen->type);
		$this->assertSame(0.0, (float) $pen->commission_amount); // null → 0.0 cast
	}
}
