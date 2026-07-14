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
		// No MHM_RENTIVA_DEV_PRO define() here: the filter alone drives the
		// bypass, and defining the constant would leak process-wide (PHP
		// constants cannot be undefined within a single PHPUnit run).
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

	/**
	 * @group v4.38.1
	 */
	public function test_pagination_renders_with_scoped_current_class_when_results_overflow(): void
	{
		// Seed 5 active vendors and request per_page=2 via shortcode attr.
		for ($i = 1; $i <= 5; $i++) {
			$id = self::factory()->user->create([
				'role'         => 'vendor',
				'display_name' => 'Vendor ' . $i,
			]);
			update_user_meta($id, '_rentiva_vendor_status', 'active');
			update_user_meta($id, '_rentiva_vendor_city', 'Istanbul');
			update_user_meta($id, '_rentiva_vendor_slug', 'vendor-' . $i);
		}

		$html = do_shortcode('[rentiva_vendor_directory per_page="2"]');

		$this->assertStringContainsString('mhm-vendor-directory-pagination', $html,
			'Pagination wrapper must appear when total > per_page.');
		$this->assertStringContainsString('paged=2', $html,
			'Page link for paged=2 must be rendered.');
		// Regression: the .current selector in vendor-directory.css is scoped under
		// .mhm-vendor-directory-pagination — verify the class still emits inside
		// that wrapper so theme-level .current rules (Astra menu item, etc.) can't
		// hijack the pagination styling.
		$this->assertMatchesRegularExpression(
			'#mhm-vendor-directory-pagination[\s\S]*?\bcurrent\b#',
			$html,
			'.current span must live inside the .mhm-vendor-directory-pagination wrapper.'
		);
	}

	/**
	 * @group v4.38.1
	 */
	public function test_alpha_sort_combined_with_city_filter(): void
	{
		$zubeyde = self::factory()->user->create(['role' => 'vendor', 'display_name' => 'Zubeyde Otomotiv']);
		update_user_meta($zubeyde, '_rentiva_vendor_status', 'active');
		update_user_meta($zubeyde, '_rentiva_vendor_city', 'Izmir');
		update_user_meta($zubeyde, '_rentiva_vendor_slug', 'zubeyde');

		$ali = self::factory()->user->create(['role' => 'vendor', 'display_name' => 'Ali Otomotiv']);
		update_user_meta($ali, '_rentiva_vendor_status', 'active');
		update_user_meta($ali, '_rentiva_vendor_city', 'Izmir');
		update_user_meta($ali, '_rentiva_vendor_slug', 'ali');

		$ankara = self::factory()->user->create(['role' => 'vendor', 'display_name' => 'Ankara Vendor']);
		update_user_meta($ankara, '_rentiva_vendor_status', 'active');
		update_user_meta($ankara, '_rentiva_vendor_city', 'Ankara');

		$_GET['city'] = 'Izmir';
		$_GET['sort'] = 'alpha';
		$html = do_shortcode('[rentiva_vendor_directory]');
		unset($_GET['city'], $_GET['sort']);

		$this->assertStringNotContainsString('Ankara Vendor', $html, 'city filter must exclude Ankara');

		$pos_ali = strpos($html, 'Ali Otomotiv');
		$pos_zub = strpos($html, 'Zubeyde Otomotiv');
		$this->assertNotFalse($pos_ali, 'Ali must appear');
		$this->assertNotFalse($pos_zub, 'Zubeyde must appear');
		$this->assertLessThan($pos_zub, $pos_ali, 'alpha sort must place Ali before Zubeyde under city=Izmir filter.');
	}

	/**
	 * @group v4.38.1
	 */
	public function test_paged_query_string_advances_to_second_page(): void
	{
		for ($i = 1; $i <= 4; $i++) {
			$id = self::factory()->user->create([
				'role'         => 'vendor',
				'display_name' => 'Page Vendor ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
			]);
			update_user_meta($id, '_rentiva_vendor_status', 'active');
			update_user_meta($id, '_rentiva_vendor_city', 'Istanbul');
			update_user_meta($id, '_rentiva_vendor_slug', 'page-vendor-' . $i);
		}

		$_GET['paged'] = '2';
		$html_p2 = do_shortcode('[rentiva_vendor_directory per_page="2"]');
		unset($_GET['paged']);

		// Page 2 of a 4-vendor list at per_page=2 must show exactly 2 cards.
		$card_count = substr_count($html_p2, 'mhm-vendor-directory-card-link');
		$this->assertSame(2, $card_count,
			'paged=2 with per_page=2 of a 4-vendor list must render exactly 2 cards, got ' . $card_count);
	}
}
