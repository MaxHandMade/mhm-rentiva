<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementRenderer;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementRendererTest extends WP_UnitTestCase {

	private function sample(): array {
		return array(
			'payout_id' => 1, 'vendor_id' => 2, 'number' => 'MKB-2026-0007',
			'generated_at' => '2026-06-28 10:00:00', 'currency' => 'TRY',
			'period_start' => '2026-06-01 00:00:00', 'period_end' => '2026-06-28 00:00:00',
			'last_entry_id' => 12,
			'lines' => array(
				array('date'=>'2026-06-10 09:00:00','type'=>'commission_credit','ref'=>5,'description'=>'Earnings — booking #5','amount'=>300.0),
				array('date'=>'2026-06-15 09:00:00','type'=>'withdrawal_penalty','ref'=>0,'description'=>'Withdrawal penalty','amount'=>-50.0),
			),
			'gross' => 300.0, 'penalties' => 50.0, 'net_activity' => 250.0,
			'paid' => 200.0, 'carried_balance' => 50.0,
			'vendor_snapshot' => array('name'=>'Mehmet <b>Çelik</b>','tax_office'=>'Kadıköy','tax_number'=>'1234567890','account_holder'=>'Mehmet Çelik','iban'=>'TR000000000000000000000000'),
		);
	}

	public function test_render_contains_number_amounts_and_footer(): void {
		$html = PayoutStatementRenderer::render($this->sample());
		$this->assertStringContainsString('MKB-2026-0007', $html);
		$this->assertStringContainsString('Earnings — booking #5', $html);
		$this->assertStringContainsString('Withdrawal penalty', $html);
		$this->assertStringContainsString('not an official invoice', $html); // footer note present
		$this->assertStringContainsString('window.print', $html); // print button
		$this->assertStringContainsString('TRY', $html); // money currency rendered
	}

	public function test_render_escapes_vendor_name(): void {
		$html = PayoutStatementRenderer::render($this->sample());
		$this->assertStringNotContainsString('<b>Çelik</b>', $html, 'Vendor name must be escaped.');
		$this->assertStringContainsString('&lt;b&gt;', $html);
	}
}
