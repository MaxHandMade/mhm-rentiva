<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Settings;

use MHMRentiva\Admin\Settings\Groups\VendorMarketplaceSettings;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * @group settings
 */
final class StatementSettingsTest extends WP_UnitTestCase {

	public function test_defaults_include_statement_keys(): void {
		$d = VendorMarketplaceSettings::get_default_settings();
		foreach (array(
			'statement_company_name','statement_company_address','statement_company_tax_office',
			'statement_company_tax_number','statement_company_phone','statement_company_email',
			'statement_logo_id','statement_footer_note',
		) as $key) {
			$this->assertArrayHasKey($key, $d, "default missing: {$key}");
		}
		$this->assertSame(0, $d['statement_logo_id']);
	}

	public function test_sanitizer_cleans_statement_fields(): void {
		$input = array(
			'statement_company_name'  => '  Acme A.Ş. <b>x</b> ',
			'statement_company_email' => 'not-an-email',
			'statement_logo_id'       => '42abc',
			'statement_footer_note'   => "Line1\nLine2",
		);
		$out = SettingsSanitizer::sanitize_vendor_marketplace_settings($input, VendorMarketplaceSettings::get_default_settings());

		$this->assertSame('Acme A.Ş. x', $out['statement_company_name']); // tags stripped, trimmed
		$this->assertSame('', $out['statement_company_email']);            // invalid email blanked
		$this->assertSame(42, $out['statement_logo_id']);                  // absint
		$this->assertStringContainsString("Line1", $out['statement_footer_note']);
	}

	public function test_sanitizer_accepts_valid_email(): void {
		$out = SettingsSanitizer::sanitize_vendor_marketplace_settings(
			array('statement_company_email' => 'a@b.test'),
			VendorMarketplaceSettings::get_default_settings()
		);
		$this->assertSame('a@b.test', $out['statement_company_email']);
	}
}
