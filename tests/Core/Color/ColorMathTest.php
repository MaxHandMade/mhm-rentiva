<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Color;

use MHMRentiva\Core\Color\ColorMath;
use PHPUnit\Framework\TestCase;

/**
 * ColorMath is pure: no WordPress call, so a plain TestCase.
 *
 * mix() is checked against an independent oracle (browser canvas paint of CSS
 * color-mix, tests/fixtures/accent-palette-oracle.json; plan R14).
 */
final class ColorMathTest extends TestCase
{
	/** @return array<string,mixed> */
	private static function oracle(): array
	{
		$raw = (string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/accent-palette-oracle.json'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture.
		$all = json_decode($raw, true);
		return is_array($all) ? array_filter($all, static fn ($k): bool => str_starts_with((string) $k, '#'), ARRAY_FILTER_USE_KEY) : array();
	}

	public function test_contrast_ratios(): void
	{
		$this->assertEqualsWithDelta(5.17, ColorMath::contrast('#2271b1', '#ffffff'), 0.01);
		$this->assertEqualsWithDelta(3.68, ColorMath::contrast('#1e88e5', '#ffffff'), 0.01);
		$this->assertEqualsWithDelta(4.45, ColorMath::contrast('#0067ff', ColorMath::mix('#0067ff', '#ffffff', 5)), 0.01);
	}

	public function test_mix_matches_css_color_mix(): void
	{
		$this->assertSame('#f4f8fb', ColorMath::mix('#2271b1', '#ffffff', 5));
		$this->assertSame('#deeaf3', ColorMath::mix('#2271b1', '#ffffff', 15));
		$this->assertSame('#0f4b78', ColorMath::mix('#135e96', '#000000', 80));

		$oracle = self::oracle();
		$this->assertCount(15, $oracle);
		foreach ($oracle as $accent => $row) {
			$accent = (string) $accent;
			$this->assertSame($row['bg'], ColorMath::mix($accent, '#ffffff', 5), "{$accent} 5% #fff");
			$this->assertSame($row['accent_100'], ColorMath::mix($accent, '#ffffff', 15), "{$accent} 15% #fff");
			$this->assertSame($row['deep_dark'], ColorMath::mix($accent, '#000000', 80), "{$accent} 80% #000");
			$this->assertSame($row['deep_light'], ColorMath::mix($accent, '#ffffff', 80), "{$accent} 80% #fff");
			$this->assertSame($row['accent_text'], ColorMath::palette($accent)['tokens']['accent-text'], "{$accent} accent-text fed to the second pass");
			$this->assertSame($row['text_hover'], ColorMath::mix($row['accent_text'], '#000000', 80), "{$accent} text 80% #000");
			$this->assertSame($row['sel_text'], ColorMath::mix($row['accent_text'], '#000000', 55), "{$accent} text 55% #000");
		}
	}

	/** @return array<string,array{0:mixed,1:?string}> */
	public static function normalize_cases(): array
	{
		return array(
			'short hex'           => array( '#ABC', '#aabbcc' ),
			'upper hex'           => array( '#2271B1', '#2271b1' ),
			'alpha ff'            => array( '#2271b1ff', '#2271b1' ),
			'alpha 80'            => array( '#2271b180', null ),
			'rgb'                 => array( 'rgb(34, 113, 177)', '#2271b1' ),
			'rgba 1'              => array( 'rgba(34,113,177,1)', '#2271b1' ),
			'rgba half'           => array( 'rgba(34,113,177,0.5)', null ),
			'var'                 => array( 'var(--global-palette1)', null ),
			'empty'               => array( '', null ),
			'null'                => array( null, null ),
			'array'               => array( array(), null ),
			'injection'           => array( '#fff;}body{x}', null ),
			'padded'              => array( ' #2271b1 ', '#2271b1' ),
			'channel out of range' => array( 'rgb(300,0,0)', null ),
		);
	}

	/**
	 * @dataProvider normalize_cases
	 * @param mixed $input Raw colour.
	 */
	public function test_normalize_table(mixed $input, ?string $expected): void
	{
		$this->assertSame($expected, ColorMath::normalize($input));
	}

	public function test_on_accent_choice(): void
	{
		$this->assertSame('#1d2327', ColorMath::palette('#6ec1e4')['tokens']['on-accent']);
		$this->assertSame('#000000', ColorMath::palette('#808080')['tokens']['on-accent']);
		$this->assertSame('#000000', ColorMath::palette('#777777')['tokens']['on-accent']);
		$this->assertSame('#ffffff', ColorMath::palette('#0067ff')['tokens']['on-accent']);
	}

	public function test_default_accent_returns_designer_literals(): void
	{
		$p = ColorMath::palette('#2271b1');
		$this->assertSame(ColorMath::DEFAULT_TOKENS, $p['tokens']);
		$this->assertSame(
			array(
				'text_darkened' => false,
				'fill_low'      => false,
			),
			$p['flags']
		);
		$this->assertSame(array(), $p['looped']);
		$this->assertSame(ColorMath::DEFAULT_TOKENS, ColorMath::palette('#2271B1')['tokens']);
	}

	public function test_black_like_accent_flips_deep_direction(): void
	{
		$p    = ColorMath::palette('#111111');
		$deep = $p['tokens']['accent-deep'];
		$this->assertGreaterThanOrEqual(1.3, ColorMath::contrast('#111111', $deep));
		$this->assertSame('#414141', $deep);
		$this->assertNotContains('accent-deep', $p['looped']);
	}

	/** @return array<string,array{0:string,1:string}> */
	public static function deep_loop_cases(): array
	{
		return array(
			'listeo kirki'  => array( '#f91942', '#fb637e' ),
			'listeo css'    => array( '#f30c0c', '#f75a5a' ),
			'upstudy'       => array( '#1cb098', '#5cc6b5' ),
		);
	}

	/** @dataProvider deep_loop_cases */
	public function test_deep_loops_in_the_direction_where_on_held(string $accent, string $expected_deep): void
	{
		$p    = ColorMath::palette($accent);
		$deep = $p['tokens']['accent-deep'];
		$this->assertSame($expected_deep, $deep);
		$this->assertGreaterThanOrEqual(4.5, ColorMath::contrast($p['tokens']['on-accent'], $deep));
		$this->assertGreaterThanOrEqual(1.3, ColorMath::contrast($accent, $deep));
		$this->assertContains('accent-deep', $p['looped']);
	}

	public function test_luminance_threshold_is_wcag22(): void
	{
		// 10/255 = 0.0392 is below the 0.04045 threshold: the linear branch.
		$this->assertEqualsWithDelta(0.0392156862745098 / 12.92, ColorMath::luminance('#0a0a0a'), 1e-12);
		$this->assertEqualsWithDelta(1.0, ColorMath::luminance('#ffffff'), 1e-12);
		$this->assertEqualsWithDelta(0.0, ColorMath::luminance('#000000'), 1e-12);
	}
}
