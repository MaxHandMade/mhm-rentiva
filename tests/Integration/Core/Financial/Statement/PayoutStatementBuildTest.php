<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatement;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementBuildTest extends WP_UnitTestCase {

	private function credit(int $vendor, float $amount, int $booking = 0): void {
		Ledger::add_entry(new LedgerEntry(
			'c_' . uniqid('', true), $vendor, $booking ?: null, null,
			'commission_credit', $amount, null, null, null, 'TRY', 'vendor', 'cleared'
		));
	}
	private function penalty(int $vendor, float $amount): void {
		Ledger::add_entry(new LedgerEntry(
			'p_' . uniqid('', true), $vendor, null, null,
			'withdrawal_penalty', $amount, null, null, null, 'TRY', 'vendor', 'cleared'
		));
	}

	public function test_build_totals_and_paid_and_carried_balance(): void {
		$vendor = (int) $this->factory->user->create();
		update_user_meta($vendor, '_rentiva_vendor_iban', 'TR99');
		update_user_meta($vendor, '_rentiva_vendor_tax_number', '123');

		$this->credit($vendor, 300.0, 7);   // earning
		$this->penalty($vendor, -50.0);      // deduction
		// balance now 250. Vendor requests a partial payout of 200.
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor));
		update_post_meta($pid, '_mhm_payout_amount', 200.0);

		$s = PayoutStatement::build($pid);

		$this->assertSame(300.0, $s['gross']);
		$this->assertSame(50.0, $s['penalties']);
		$this->assertSame(250.0, $s['net_activity']);
		$this->assertSame(200.0, $s['paid']);
		$this->assertSame(250.0, $s['carried_balance']); // payout debit not yet written at build time
		$this->assertCount(2, $s['lines']);
		$this->assertSame('TR99', $s['vendor_snapshot']['iban']);
		$this->assertGreaterThan(0, $s['last_entry_id']);
	}

	public function test_build_only_covers_entries_after_previous_statement(): void {
		$vendor = (int) $this->factory->user->create();
		$this->credit($vendor, 100.0);
		$prevEnd = Ledger::get_max_entry_id($vendor);
		// First payout already settled up to $prevEnd.
		$p1 = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor));
		update_post_meta($p1, '_mhm_statement_last_entry_id', $prevEnd);
		update_post_meta($p1, '_mhm_statement_number', 'MKB-2026-0001');

		$this->credit($vendor, 40.0); // new activity after first statement
		$p2 = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor));
		update_post_meta($p2, '_mhm_payout_amount', 40.0);

		$s = PayoutStatement::build($p2);

		$this->assertCount(1, $s['lines'], 'Only activity after the previous statement is covered.');
		$this->assertSame(40.0, $s['gross']);
	}
}
