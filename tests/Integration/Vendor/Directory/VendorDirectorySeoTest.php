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
}
