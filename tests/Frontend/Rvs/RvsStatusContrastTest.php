<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Tests\Support\RvsTokenFile;
use WP_UnitTestCase;

/**
 * The seven status tag colour pairs read from rvs-tokens.css reach 4.5:1
 * (WCAG relative luminance). A token is resolved through `var()` from the same
 * file; one that does not resolve to hex fails.
 */
final class RvsStatusContrastTest extends WP_UnitTestCase
{
	public function test_every_status_pair_reaches_4_5_to_1(): void
	{
		foreach (array( 'confirmed', 'pending', 'payment', 'active', 'done', 'cancelled', 'available' ) as $m) {
			$bg = RvsTokenFile::resolve("--rvs-st-{$m}-bg");
			$fg = RvsTokenFile::resolve("--rvs-st-{$m}-fg");
			$this->assertNotSame('', $bg, "{$m} bg does not resolve to hex");
			$this->assertNotSame('', $fg, "{$m} fg does not resolve to hex");
			$ratio = RvsTokenFile::contrast($bg, $fg);
			$this->assertGreaterThanOrEqual(4.5, $ratio, "{$m} contrast {$ratio}");
		}
	}

	public function test_selected_pair_reaches_4_5_to_1(): void
	{
		$bg = RvsTokenFile::resolve('--rvs-sel-bg');
		$fg = RvsTokenFile::resolve('--rvs-sel-text');
		$this->assertNotSame('', $bg, 'sel bg does not resolve to hex');
		$this->assertNotSame('', $fg, 'sel text does not resolve to hex');
		$ratio = RvsTokenFile::contrast($bg, $fg);
		$this->assertGreaterThanOrEqual(4.5, $ratio, "sel contrast {$ratio}");
	}
}
