<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Directory;

use MHMRentiva\Admin\Vendor\Directory\VendorDirectorySchema;
use WP_UnitTestCase;

/**
 * @covers \MHMRentiva\Admin\Vendor\Directory\VendorDirectorySchema
 */
final class VendorDirectorySchemaTest extends WP_UnitTestCase
{
	public function test_build_emits_itemlist_with_vendor_urls(): void
	{
		$vendors = [
			['id' => 1, 'profile_url' => 'https://example.com/bayi/akif/'],
			['id' => 2, 'profile_url' => 'https://example.com/bayi/zeynep/'],
		];

		$schema = VendorDirectorySchema::build($vendors);

		$this->assertSame('ItemList', $schema['ItemList']['@type']);
		$this->assertSame(2, $schema['ItemList']['numberOfItems']);
		$this->assertCount(2, $schema['ItemList']['itemListElement']);
		$this->assertSame('https://example.com/bayi/akif/', $schema['ItemList']['itemListElement'][0]['url']);
		$this->assertSame(1, $schema['ItemList']['itemListElement'][0]['position']);

		$this->assertSame('BreadcrumbList', $schema['BreadcrumbList']['@type']);
		$this->assertCount(2, $schema['BreadcrumbList']['itemListElement']);
	}

	public function test_build_returns_empty_for_zero_vendors(): void
	{
		$schema = VendorDirectorySchema::build([]);
		$this->assertSame([], $schema, 'Empty vendor list must yield no schema (Phase 8 ORTA-3 invariant).');
	}

	public function test_render_hex_encodes_hostile_script_tags(): void
	{
		$vendors = [
			[
				'id' => 1,
				'profile_url' => 'https://example.com/bayi/x/',
				'display_name' => 'Hostile</script><script>alert(1)</script>',
			],
		];

		ob_start();
		VendorDirectorySchema::render($vendors);
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString('</script><script>', $output,
			'Phase 8 YÜKSEK-1: JSON_HEX_TAG must hex-encode script tags.');
		$this->assertStringContainsString('<', $output,
			'Hex-encoded < expected for hostile tag.');
	}

	public function test_build_skips_non_http_scheme_urls(): void
	{
		$vendors = [
			['id' => 1, 'profile_url' => 'javascript:alert(1)'],
			['id' => 2, 'profile_url' => 'data:text/html,<script>alert(1)</script>'],
			['id' => 3, 'profile_url' => 'https://example.com/bayi/legit/'],
		];

		$schema = VendorDirectorySchema::build($vendors);

		$this->assertCount(1, $schema['ItemList']['itemListElement'],
			'Only http(s) URL must survive scheme filter.');
		$this->assertSame('https://example.com/bayi/legit/',
			$schema['ItemList']['itemListElement'][0]['url']);
	}
}
