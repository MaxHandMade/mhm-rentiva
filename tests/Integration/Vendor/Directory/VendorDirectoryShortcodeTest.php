<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Directory;

use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_UnitTestCase;

/**
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorDirectory
 */
final class VendorDirectoryShortcodeTest extends WP_UnitTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// Seed an active license + force the dev-mode bypass filter so
		// Mode::canUseVendorMarketplace() returns true. Mirrors the working
		// pattern from VendorProfileShortcodeTest — Mode::featureGranted()
		// requires isPro() to be true BEFORE the bypass filter is consulted.
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

		// Register shortcode if not already registered
		if (!shortcode_exists('rentiva_vendor_directory')) {
			\MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorDirectory::register();
		}
	}

	protected function tearDown(): void
	{
		remove_all_filters('mhm_rentiva_dev_pro_bypass');
		delete_option(LicenseManager::OPTION);
		parent::tearDown();
	}

	public function test_renders_with_no_filters_and_zero_vendors(): void
	{
		$html = do_shortcode('[rentiva_vendor_directory]');
		$this->assertStringContainsString('mhm-vendor-directory', $html);
		$this->assertStringContainsString(__('No vendors registered yet. Coming soon!', 'mhm-rentiva'), $html);
	}

	public function test_renders_card_for_active_vendor(): void
	{
		$vendor = self::factory()->user->create(['role' => 'vendor', 'display_name' => 'Test Vendor']);
		update_user_meta($vendor, '_rentiva_vendor_status', 'active');
		update_user_meta($vendor, '_rentiva_vendor_city', 'Istanbul');
		update_user_meta($vendor, '_rentiva_vendor_slug', 'test-vendor-' . $vendor);

		$html = do_shortcode('[rentiva_vendor_directory]');
		$this->assertStringContainsString('Test Vendor', $html);
		$this->assertStringContainsString('Istanbul', $html);
		$this->assertStringContainsString('mhm-vendor-directory-card', $html);
	}

	public function test_filter_query_string_filters_results(): void
	{
		$istanbul = self::factory()->user->create(['role' => 'vendor', 'display_name' => 'Istanbul Vendor']);
		update_user_meta($istanbul, '_rentiva_vendor_status', 'active');
		update_user_meta($istanbul, '_rentiva_vendor_city', 'Istanbul');

		$ankara = self::factory()->user->create(['role' => 'vendor', 'display_name' => 'Ankara Vendor']);
		update_user_meta($ankara, '_rentiva_vendor_status', 'active');
		update_user_meta($ankara, '_rentiva_vendor_city', 'Ankara');

		$_GET['city'] = 'Istanbul';
		$html = do_shortcode('[rentiva_vendor_directory]');
		unset($_GET['city']);

		$this->assertStringContainsString('Istanbul Vendor', $html);
		$this->assertStringNotContainsString('Ankara Vendor', $html);
	}

	public function test_lite_user_gets_empty_render(): void
	{
		remove_all_filters('mhm_rentiva_dev_pro_bypass');
		add_filter('mhm_rentiva_dev_pro_bypass', '__return_false');
		delete_option(LicenseManager::OPTION);

		$html = do_shortcode('[rentiva_vendor_directory]');
		$this->assertSame('', $html, 'Lite users must get empty shortcode output (Pro gate render-time).');
	}
}
