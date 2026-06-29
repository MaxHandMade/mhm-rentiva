<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementRepository;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementCommissionRepoTest extends WP_UnitTestCase {

	private function base(int $pid, int $vendor): array {
		return array(
			'payout_id' => $pid, 'vendor_id' => $vendor, 'number' => 'MKB-2026-0001',
			'generated_at' => '2026-06-29 10:00:00', 'currency' => 'TRY',
			'period_start' => '', 'period_end' => '', 'last_entry_id' => 9,
			'lines' => array(array('date'=>'2026-06-10','type'=>'commission_credit','ref'=>7,'description'=>'x','amount'=>800.0,'gross'=>1000.0,'commission'=>200.0,'commission_rate'=>20.0)),
			'gross' => 800.0, 'penalties' => 0.0, 'net_activity' => 800.0,
			'commission_total' => 200.0, 'paid' => 500.0, 'carried_balance' => 300.0,
			'vendor_snapshot' => array('name'=>'V','tax_office'=>'','tax_number'=>'','account_holder'=>'','iban'=>''),
		);
	}

	public function test_commission_total_and_line_fields_roundtrip(): void {
		$vendor = (int) $this->factory->user->create();
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor, 'post_status' => 'pending'));
		PayoutStatementRepository::save($pid, $this->base($pid, $vendor));

		$got = PayoutStatementRepository::get($pid);
		$this->assertSame(200.0, $got['commission_total']);
		$this->assertEquals(1000.0, $got['lines'][0]['gross']);
		$this->assertEquals(200.0, $got['lines'][0]['commission']);
		$this->assertEquals(20.0, $got['lines'][0]['commission_rate']);
	}

	public function test_old_statement_without_commission_total_returns_zero(): void {
		$vendor = (int) $this->factory->user->create();
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor, 'post_status' => 'pending'));
		// Simulate a pre-feature statement: a number exists but no commission_total meta.
		update_post_meta($pid, '_mhm_statement_number', 'MKB-2025-0009');
		$got = PayoutStatementRepository::get($pid);
		$this->assertSame(0.0, $got['commission_total']);
	}
}
