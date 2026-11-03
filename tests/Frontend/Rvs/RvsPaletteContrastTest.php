<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Tests\Support\RvsTokenFile;
use WP_UnitTestCase;

/**
 * The accent pairs of the front-end palette reach their contrast floors.
 *
 * PR 1 (colour setting) checks the default literal palette of rvs-tokens.css;
 * PR 2 adds the accent fixture rows (accent-palette-cases.json) to this file and
 * runs them through the same pairs.
 */
final class RvsPaletteContrastTest extends WP_UnitTestCase
{
	/**
	 * A token name (resolved from rvs-tokens.css) or a literal hex.
	 */
	private static function colour(string $ref): string
	{
		return str_starts_with($ref, '#') ? strtolower($ref) : RvsTokenFile::resolve($ref);
	}

	/**
	 * @return list<array{0:string,1:string,2:float}> foreground, background, floor
	 */
	private static function pairs(): array
	{
		return array(
			array( '--rvs-on-accent', '--rvs-accent-strong', 4.5 ),
			array( '--rvs-on-accent', '--rvs-accent-deep', 4.5 ),
			array( '--rvs-accent-strong', '--rvs-accent-deep', 1.3 ),
			array( '--rvs-accent-text', '#ffffff', 4.5 ),
			array( '--rvs-accent-text', '--rvs-bg', 4.5 ),
			array( '--rvs-accent-text-hover', '#ffffff', 4.5 ),
			array( '--rvs-accent-text-hover', '--rvs-bg', 4.5 ),
			// Not in spec §5's pair list: the hover text sits on the accent-100 fill
			// in four rules (plan debt B1). Pinned here for the default palette.
			array( '--rvs-accent-text-hover', '--rvs-accent-100', 4.5 ),
			array( '--rvs-sel-text', '--rvs-accent-100', 7.0 ),
			array( '--rvs-st-confirmed-fg', '--rvs-st-confirmed-bg', 4.5 ),
		);
	}

	public function test_default_literal_palette_pairs(): void
	{
		foreach (self::pairs() as list( $fg_ref, $bg_ref, $floor )) {
			$fg = self::colour($fg_ref);
			$bg = self::colour($bg_ref);
			$this->assertNotSame('', $fg, "{$fg_ref} does not resolve to hex");
			$this->assertNotSame('', $bg, "{$bg_ref} does not resolve to hex");
			$ratio = RvsTokenFile::contrast($fg, $bg);
			$this->assertGreaterThanOrEqual($floor, $ratio, sprintf('%s on %s: %.2f < %.1f', $fg_ref, $bg_ref, $ratio, $floor));
		}
	}
}
