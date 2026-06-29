<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementRenderer;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementBrandingRenderTest extends WP_UnitTestCase {

	private function sample(): array {
		return array(
			'number' => 'MKB-2026-0001', 'generated_at' => '2026-06-29 10:00:00', 'currency' => 'TRY',
			'period_start' => '', 'period_end' => '', 'lines' => array(),
			'gross' => 0.0, 'penalties' => 0.0, 'net_activity' => 0.0, 'paid' => 100.0, 'carried_balance' => 0.0,
			'vendor_snapshot' => array('name'=>'V','tax_office'=>'','tax_number'=>'','account_holder'=>'','iban'=>''),
		);
	}

	public function test_header_shows_company_and_footer_note_plus_disclaimer(): void {
		update_option('mhm_rentiva_settings', array(
			'statement_company_name' => 'Acme Kiralama',
			'statement_company_address' => 'Kadıköy',
			'statement_footer_note' => 'Özel not buraya.',
		));
		$html = PayoutStatementRenderer::render($this->sample());

		$this->assertStringContainsString('Acme Kiralama', $html);
		$this->assertStringContainsString('Kadıköy', $html);
		$this->assertStringContainsString('Özel not buraya.', $html);
		$this->assertStringContainsString('not an official invoice', $html); // disclaimer ALWAYS present
	}

	public function test_header_falls_back_to_site_name_and_escapes(): void {
		update_option('mhm_rentiva_settings', array('statement_company_name' => '<b>Hack</b>'));
		$html = PayoutStatementRenderer::render($this->sample());
		$this->assertStringNotContainsString('<b>Hack</b>', $html, 'Company name must be escaped.');
		$this->assertStringContainsString('&lt;b&gt;Hack', $html);
	}
}
