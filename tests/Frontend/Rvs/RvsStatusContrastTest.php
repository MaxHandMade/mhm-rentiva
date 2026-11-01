<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use WP_UnitTestCase;

/**
 * The six status tag colour pairs read from rvs-tokens.css reach 4.5:1
 * (WCAG relative luminance). A token that is not plain hex fails the test.
 */
final class RvsStatusContrastTest extends WP_UnitTestCase
{
	private static function token(string $css, string $name): string
	{
		if (! preg_match('/' . preg_quote($name, '/') . ':\s*(#[0-9a-fA-F]{6})\s*;/', $css, $m)) {
			return '';
		}
		return $m[1];
	}

	private static function luminance(string $hex): float
	{
		$out = array();
		foreach (array( 1, 3, 5 ) as $i) {
			$c     = hexdec(substr($hex, $i, 2)) / 255;
			$out[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		}
		return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
	}

	public function test_every_status_pair_reaches_4_5_to_1(): void
	{
		$css = (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'assets/css/rvs/rvs-tokens.css'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a shipped asset.
		foreach (array( 'confirmed', 'pending', 'payment', 'active', 'done', 'cancelled' ) as $m) {
			$bg = self::token($css, "--rvs-st-{$m}-bg");
			$fg = self::token($css, "--rvs-st-{$m}-fg");
			$this->assertNotSame('', $bg, "{$m} bg is not plain hex");
			$this->assertNotSame('', $fg, "{$m} fg is not plain hex");
			$l1    = self::luminance($bg);
			$l2    = self::luminance($fg);
			$ratio = (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
			$this->assertGreaterThanOrEqual(4.5, $ratio, "{$m} contrast {$ratio}");
		}
	}
}
