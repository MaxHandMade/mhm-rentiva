<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Core\Color\ColorMath;
use MHMRentiva\Tests\Support\RvsTokenFile;
use WP_UnitTestCase;

/**
 * The accent pairs of the front-end palette reach their contrast floors.
 *
 * The default literal palette is read from rvs-tokens.css; the accent fixture
 * rows (tests/fixtures/accent-palette-cases.json) run ColorMath::palette() through
 * the same pairs (spec §Test; plan R5 adds hover text on the accent-100 fill).
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
			// in four rules (plan R5 / debt B1).
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

	/** @return array<string,array{0:array<string,mixed>}> */
	public static function fixture_rows(): array
	{
		$raw  = (string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/accent-palette-cases.json'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture.
		$data = json_decode($raw, true);
		$out  = array();
		foreach (( is_array($data) ? $data['cases'] : array() ) as $row) {
			$out[ (string) $row['accent'] ] = array( $row );
		}
		return $out;
	}

	public function test_fixture_has_the_sixteen_spec_accents(): void
	{
		$this->assertSame(
			array( '#2271b1', '#0067ff', '#cc0000', '#feb000', '#0d6dd7', '#f91942', '#f30c0c', '#1cb098', '#2b6cb0', '#6ec1e4', '#ffee58', '#111111', '#000000', '#ffffff', '#808080', '#777777' ),
			array_keys(self::fixture_rows())
		);
	}

	/**
	 * @dataProvider fixture_rows
	 * @param array<string,mixed> $row
	 */
	public function test_fixture_palettes_meet_contrast_pairs(array $row): void
	{
		$accent  = (string) $row['accent'];
		$palette = ColorMath::palette($accent);
		$t       = $palette['tokens'];

		$this->assertSame($row['php'], $t, "{$accent}: palette() != fixture php column");
		$this->assertSame($row['looped'], $palette['looped'], "{$accent}: looped tokens");

		$tok   = static fn (string $ref): string => '#' === $ref[0] ? $ref : $t[ $ref ];
		$pairs = array(
			array( 'on-accent', 'accent-strong', 4.5 ),
			array( 'on-accent', 'accent-deep', 4.5 ),
			array( 'accent-strong', 'accent-deep', 1.3 ),
			array( 'accent-text', '#ffffff', 4.5 ),
			array( 'accent-text', 'bg', 4.5 ),
			array( 'accent-text-hover', 'accent-100', 4.5 ),
			array( 'accent-text-hover', '#ffffff', 4.5 ),
			array( 'sel-text', 'accent-100', 7.0 ),
		);
		foreach ($pairs as list( $fg, $bg, $floor )) {
			$ratio = ColorMath::contrast($tok($fg), $tok($bg));
			$this->assertGreaterThanOrEqual($floor, $ratio, sprintf('%s: %s on %s %.2f < %.1f', $accent, $fg, $bg, $ratio, $floor));
		}

		if (! empty($row['literal'])) {
			$this->assertSame(ColorMath::DEFAULT_TOKENS, $row['php']);
			$this->assertSame(ColorMath::DEFAULT_TOKENS, $row['css']);
		}
		foreach ($row['css'] as $name => $css) {
			if (! in_array($name, $palette['looped'], true)) {
				$this->assertSame($css, $t[ $name ], "{$accent}: {$name} php != css (not looped)");
			}
		}
	}

	public function test_fixture_flags(): void
	{
		$this->assertTrue(ColorMath::palette('#0067ff')['flags']['text_darkened']);
		foreach (array( '#feb000', '#1cb098', '#ffee58', '#ffffff' ) as $accent) {
			$this->assertTrue(ColorMath::palette($accent)['flags']['fill_low'], "{$accent} fill_low");
		}
		$this->assertSame(
			array(
				'text_darkened' => false,
				'fill_low'      => false,
			),
			ColorMath::palette('#cc0000')['flags']
		);
	}
}
