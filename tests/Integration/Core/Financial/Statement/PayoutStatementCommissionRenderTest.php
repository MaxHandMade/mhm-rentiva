<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementRenderer;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementCommissionRenderTest extends WP_UnitTestCase {

	/** @var array<string,string> */
	private array $original_number_format = array();

	public function setUp(): void {
		parent::setUp();
		global $wp_locale;
		$this->original_number_format = $wp_locale->number_format;
		$wp_locale->number_format['thousands_sep'] = '.';
		$wp_locale->number_format['decimal_point'] = ',';
	}

	public function tearDown(): void {
		global $wp_locale;
		$wp_locale->number_format = $this->original_number_format;
		parent::tearDown();
	}

	private function stmt(array $lines, float $commission_total): array {
		return array(
			'number' => 'MKB-2026-0001', 'generated_at' => '2026-06-29 10:00:00', 'currency' => 'TRY',
			'period_start' => '', 'period_end' => '', 'lines' => $lines,
			'gross' => 800.0, 'penalties' => 0.0, 'net_activity' => 800.0,
			'commission_total' => $commission_total, 'paid' => 500.0, 'carried_balance' => 300.0,
			'vendor_snapshot' => array('name'=>'V','tax_office'=>'','tax_number'=>'','account_holder'=>'','iban'=>''),
		);
	}

	public function test_earning_line_shows_gross_rate_commission_and_total(): void {
		$html = PayoutStatementRenderer::render($this->stmt(array(
			array('date'=>'2026-06-10','type'=>'commission_credit','ref'=>7,'description'=>'Earnings','amount'=>800.0,'gross'=>1000.0,'commission'=>200.0,'commission_rate'=>20.0),
		), 200.0));

		$this->assertStringContainsString('1.000,00', $html); // gross
		$this->assertStringContainsString('%20', $html);       // rate
		$this->assertStringContainsString('200,00', $html);    // commission amount + total
		$this->assertStringContainsString('800,00', $html);    // net
		$this->assertStringContainsString('Total commission deducted', $html);
	}

	public function test_penalty_line_shows_dash_for_gross_commission(): void {
		$html = PayoutStatementRenderer::render($this->stmt(array(
			array('date'=>'2026-06-15','type'=>'withdrawal_penalty','ref'=>0,'description'=>'Withdrawal penalty','amount'=>-100.0,'gross'=>0.0,'commission'=>0.0,'commission_rate'=>0.0),
		), 0.0));

		$this->assertStringContainsString('Withdrawal penalty', $html);
		$this->assertStringContainsString('—', $html);                           // dash for gross/commission
		$this->assertStringNotContainsString('Total commission deducted', $html); // no commission → no row
	}
}
