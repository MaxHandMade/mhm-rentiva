<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * Coverage for Mode helper isolation gates.
 *
 * v4.31.0 — Pro path requires both an active license AND a valid RSA-signed
 * feature token whose `site_hash` matches the local site. Legacy `isPro()`
 * fallback was removed; an "active" license without a token now fails closed.
 */
final class ModeIsolationTest extends WP_UnitTestCase
{
	private function seedLite(): void
	{
		update_option(LicenseManager::OPTION, array(), false);
		update_option('mhm_rentiva_disable_dev_mode', 1, false);
		delete_transient('mhm_rentiva_license_status_' . md5('MODE-TEST-KEY' . 'MODE-TEST-ACT'));
	}

	private function seedPro(): void
	{
		update_option(
			LicenseManager::OPTION,
			array(
				'key'           => 'MODE-TEST-KEY',
				'status'        => 'active',
				'plan'          => 'pro',
				'activation_id' => 'MODE-TEST-ACT',
				'expires_at'    => time() + DAY_IN_SECONDS,
				'feature_token' => $this->buildProToken(),
			),
			false
		);
		update_option('mhm_rentiva_disable_dev_mode', 1, false);
		set_transient('mhm_rentiva_license_status_' . md5('MODE-TEST-KEY' . 'MODE-TEST-ACT'), 1, 30);
	}

	public function test_lite_helpers_return_false(): void
	{
		$this->seedLite();

		$this->assertFalse(Mode::canUseVendorPayout());
		$this->assertFalse(Mode::canUseMessages());
		$this->assertFalse(Mode::canUseAdvancedReports());
		$this->assertFalse(Mode::canUseVendorMarketplace());
	}

	public function test_pro_helpers_return_true(): void
	{
		$this->seedPro();

		$this->assertTrue(Mode::canUseVendorPayout());
		$this->assertTrue(Mode::canUseMessages());
		$this->assertTrue(Mode::canUseAdvancedReports());
		$this->assertTrue(Mode::canUseVendorMarketplace());
	}

	private function buildProToken(): string
	{
		$privatePem = (string) file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
		$privateKey = openssl_pkey_get_private($privatePem);

		$payload = array(
			'expires_at'       => time() + DAY_IN_SECONDS,
			'features'         => array(
				'vendor_marketplace' => true,
				'messaging'          => true,
				'advanced_reports'   => true,
			),
			'issued_at'        => time(),
			'license_key_hash' => 'h',
			'plan'             => 'pro',
			'product_slug'     => 'mhm-rentiva',
			'site_hash'        => LicenseManager::instance()->getSiteHash(),
		);

		$canonical = (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$signature = '';
		openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256);

		$encode = static fn(string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

		return $encode($canonical) . '.' . $encode($signature);
	}
}
