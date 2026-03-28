<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * Coverage for Mode helper isolation gates.
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
}

