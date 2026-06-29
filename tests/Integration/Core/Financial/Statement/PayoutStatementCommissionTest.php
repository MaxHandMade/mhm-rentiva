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
final class PayoutStatementCommissionTest extends WP_UnitTestCase {

	public function test_build_captures_per_line_commission_and_total(): void {
		$vendor = (int) $this->factory->user->create();
		Ledger::add_entry(new LedgerEntry('cc1_' . uniqid('', true), $vendor, 7, null, 'commission_credit', 800.0, 1000.0, 200.0, 20.0, 'TRY', 'vendor', 'cleared'));
		Ledger::add_entry(new LedgerEntry('cc2_' . uniqid('', true), $vendor, 8, null, 'commission_credit', 450.0, 500.0, 50.0, 10.0, 'TRY', 'vendor', 'cleared'));
		Ledger::add_entry(new LedgerEntry('pen_' . uniqid('', true), $vendor, null, null, 'withdrawal_penalty', -100.0, null, null, null, 'TRY', 'vendor', 'cleared'));

		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor, 'post_status' => 'pending'));
		update_post_meta($pid, '_mhm_payout_amount', 500.0);

		$s = PayoutStatement::build($pid);

		$this->assertSame(250.0, $s['commission_total'], 'Total commission = 200 + 50 (earnings only, penalty excluded)');
		// first earning line
		$earn = $s['lines'][0];
		$this->assertSame(1000.0, $earn['gross']);
		$this->assertSame(200.0, $earn['commission']);
		$this->assertSame(20.0, $earn['commission_rate']);
		$this->assertSame(800.0, $earn['amount']); // net unchanged
		// penalty line has no commission
		$pen = $s['lines'][2];
		$this->assertSame(0.0, $pen['gross']);
		$this->assertSame(0.0, $pen['commission']);
	}
}
