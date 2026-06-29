<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementBranding;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementBrandingTest extends WP_UnitTestCase {

	public function test_returns_settings_values_when_set(): void {
		update_option('mhm_rentiva_settings', array(
			'statement_company_name'    => 'Acme Kiralama A.Ş.',
			'statement_company_address' => "Bağdat Cad. No:1\nKadıköy/İstanbul",
			'statement_company_tax_office' => 'Kadıköy',
			'statement_company_tax_number' => '1234567890',
			'statement_company_phone'   => '+90 216 000 00 00',
			'statement_company_email'   => 'muhasebe@acme.test',
			'statement_logo_id'         => 0,
			'statement_footer_note'     => 'Teşekkür ederiz.',
		));

		$b = PayoutStatementBranding::get();

		$this->assertSame('Acme Kiralama A.Ş.', $b['company_name']);
		$this->assertStringContainsString('Kadıköy', $b['address']);
		$this->assertSame('1234567890', $b['tax_number']);
		$this->assertSame('muhasebe@acme.test', $b['email']);
		$this->assertSame('', $b['logo_url']);
		$this->assertSame('Teşekkür ederiz.', $b['footer_note']);
	}

	public function test_company_name_falls_back_to_site_name_when_empty(): void {
		update_option('mhm_rentiva_settings', array('statement_company_name' => ''));
		$this->assertSame(get_bloginfo('name'), PayoutStatementBranding::get()['company_name']);
	}

	public function test_logo_url_resolves_from_attachment_id(): void {
		$att = (int) $this->factory->post->create(array(
			'post_type' => 'attachment',
			'guid'      => 'http://example.org/wp-content/uploads/logo.png',
		));
		update_option('mhm_rentiva_settings', array('statement_logo_id' => $att));
		$this->assertSame('http://example.org/wp-content/uploads/logo.png', PayoutStatementBranding::get()['logo_url']);
	}
}
