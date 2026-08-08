<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Assets;

use WP_UnitTestCase;

/**
 * Keeps the shipped library banner and public dependency disclosure aligned.
 */
final class VendoredLibraryVersionTest extends WP_UnitTestCase {

	public function test_swiper_bundle_and_readme_declare_the_audited_release(): void {
		$plugin_root = dirname(__DIR__, 3);
		$script      = file_get_contents($plugin_root . '/assets/vendor/swiper-bundle.min.js');
		$readme      = file_get_contents($plugin_root . '/readme.txt');

		$this->assertIsString($script);
		$this->assertIsString($readme);
		$this->assertStringContainsString('Swiper 14.1.0', $script);
		$this->assertStringContainsString('**Swiper** 14.1.0', $readme);
	}
}
