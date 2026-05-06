<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Directory;

use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_UnitTestCase;

/**
 * Phase 9 of v4.38.0 — Pro gate enforcement on the directory shortcode.
 *
 * Mode::featureGranted() short-circuits to false when isPro() returns false
 * (LicenseManager::isActive() === false), so a seeded license is the
 * minimum prerequisite for the dev-bypass filter to even be consulted.
 * The "dev bypass" branch then bypasses the RSA-signed feature token
 * verification — useful for local development when the server isn't
 * reachable but a license row exists.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorDirectory
 */
final class VendorDirectoryGatingTest extends WP_UnitTestCase
{
	protected function tearDown(): void
	{
		remove_all_filters('mhm_rentiva_dev_pro_bypass');
		delete_option(LicenseManager::OPTION);
		parent::tearDown();
	}

	public function test_pro_user_passes_render_gate(): void
	{
		if (!defined('MHM_RENTIVA_DEV_PRO')) {
			define('MHM_RENTIVA_DEV_PRO', true);
		}
		update_option(LicenseManager::OPTION, [
			'key'           => 'TEST-DEV-001',
			'status'        => 'active',
			'plan'          => 'monthly',
			'expires_at'    => time() + 86400,
			'activation_id' => 'a1',
		], false);
		add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

		if (!shortcode_exists('rentiva_vendor_directory')) {
			\MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorDirectory::register();
		}

		$html = do_shortcode('[rentiva_vendor_directory]');
		$this->assertStringContainsString('mhm-vendor-directory', $html);
	}

	public function test_lite_user_gets_empty_render(): void
	{
		add_filter('mhm_rentiva_dev_pro_bypass', '__return_false');
		delete_option(LicenseManager::OPTION);

		if (!shortcode_exists('rentiva_vendor_directory')) {
			\MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorDirectory::register();
		}

		$html = do_shortcode('[rentiva_vendor_directory]');
		$this->assertSame('', $html, 'Lite user must get empty shortcode output.');
	}

	public function test_dev_bypass_filter_works(): void
	{
		if (!defined('MHM_RENTIVA_DEV_PRO')) {
			define('MHM_RENTIVA_DEV_PRO', true);
		}
		// License seeded so isPro() returns true; the bypass filter then
		// substitutes for RSA feature-token verification (which would otherwise
		// fail under unit-test conditions where no real server-issued token
		// is in storage).
		update_option(LicenseManager::OPTION, [
			'key'           => 'TEST-DEV-002',
			'status'        => 'active',
			'plan'          => 'monthly',
			'expires_at'    => time() + 86400,
			'activation_id' => 'a2',
		], false);
		add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

		if (!shortcode_exists('rentiva_vendor_directory')) {
			\MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorDirectory::register();
		}

		$html = do_shortcode('[rentiva_vendor_directory]');
		$this->assertStringContainsString('mhm-vendor-directory', $html);
	}
}
