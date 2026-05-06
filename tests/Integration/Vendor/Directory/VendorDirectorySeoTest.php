<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Directory;

use MHMRentiva\Admin\Vendor\Directory\VendorDirectorySeo;
use WP_UnitTestCase;

/**
 * @covers \MHMRentiva\Admin\Vendor\Directory\VendorDirectorySeo
 */
final class VendorDirectorySeoTest extends WP_UnitTestCase
{
	public function test_default_title_format(): void
	{
		$title = VendorDirectorySeo::build_title();
		$this->assertStringContainsString('Vendors', $title);
		$this->assertStringContainsString(get_bloginfo('name'), $title);
	}

	public function test_filter_overrides_title(): void
	{
		add_filter('mhm_rentiva_vendor_directory_page_title', static fn(): string => 'Custom Directory Title');
		$title = VendorDirectorySeo::build_title();
		$this->assertSame('Custom Directory Title', $title);
		remove_all_filters('mhm_rentiva_vendor_directory_page_title');
	}

	public function test_build_meta_description_default_includes_counts(): void
	{
		$desc = VendorDirectorySeo::build_meta_description(7, 42);

		$this->assertStringContainsString('7', $desc);
		$this->assertStringContainsString('42', $desc);
	}

	public function test_build_meta_description_filter_receives_three_args(): void
	{
		$captured = [];
		add_filter(
			'mhm_rentiva_vendor_directory_meta_description',
			static function (string $default, int $vendors, int $vehicles) use (&$captured): string {
				$captured = ['default' => $default, 'vendors' => $vendors, 'vehicles' => $vehicles];
				return 'Override: ' . $vendors . '/' . $vehicles;
			},
			10,
			3
		);

		$desc = VendorDirectorySeo::build_meta_description(5, 30);

		$this->assertSame('Override: 5/30', $desc);
		$this->assertSame(5, $captured['vendors']);
		$this->assertSame(30, $captured['vehicles']);
		$this->assertNotEmpty($captured['default'], 'default value must be passed as first arg');

		remove_all_filters('mhm_rentiva_vendor_directory_meta_description');
	}

	public function test_register_skips_hooks_when_disable_filter_active(): void
	{
		remove_all_filters('document_title_parts');
		remove_all_actions('wp_head');

		add_filter('mhm_rentiva_vendor_directory_seo_disable', '__return_true');

		VendorDirectorySeo::register();

		$this->assertFalse(
			has_filter('document_title_parts', [VendorDirectorySeo::class, 'filter_title']),
			'register must not attach title filter when seo_disable returns true'
		);
		$this->assertFalse(
			has_action('wp_head', [VendorDirectorySeo::class, 'output_meta_description']),
			'register must not attach meta-description action when seo_disable returns true'
		);

		remove_all_filters('mhm_rentiva_vendor_directory_seo_disable');
	}

	public function test_register_attaches_hooks_when_no_seo_plugin_and_not_disabled(): void
	{
		remove_all_filters('document_title_parts');
		remove_all_actions('wp_head');

		VendorDirectorySeo::register();

		$this->assertNotFalse(
			has_filter('document_title_parts', [VendorDirectorySeo::class, 'filter_title']),
			'register must attach title filter when no SEO plugin and not disabled'
		);
		$this->assertNotFalse(
			has_action('wp_head', [VendorDirectorySeo::class, 'output_meta_description']),
			'register must attach meta-description action when no SEO plugin and not disabled'
		);
	}
}
