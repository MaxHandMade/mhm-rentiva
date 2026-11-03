<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Color;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Pure colour arithmetic for the front-end accent palette (no WordPress calls).
 *
 * One accent drives eight literal tokens of rvs-tokens.css. palette() applies the
 * fixed percentages the CSS uses first (color-mix in sRGB) and loops only when a
 * contrast floor is still missed; the loop is recorded in `looped`.
 *
 * Contrast is WCAG 2.2 relative luminance (channel threshold 0.04045).
 */
final class ColorMath {

	public const DEFAULT_ACCENT = '#2271b1';

	/**
	 * The designer's literal palette of rvs-tokens.css. The formula does not reach
	 * these values from #2271b1 (mix(#2271b1, #fff, 5%) is #f4f8fb, not #f0f6fc),
	 * so the default accent returns them verbatim and prints nothing inline.
	 * Key order is the print order of RvsColorTokens::TOKENS.
	 */
	public const DEFAULT_TOKENS = array(
		'bg'                => '#f0f6fc',
		'accent-strong'     => '#2271b1',
		'accent-deep'       => '#135e96',
		'accent-100'        => '#dcecf9',
		'sel-text'          => '#0a3c63',
		'on-accent'         => '#ffffff',
		'accent-text'       => '#2271b1',
		'accent-text-hover' => '#135e96',
	);

	private const WHITE = '#ffffff';
	private const BLACK = '#000000';
	private const INK   = '#1d2327';

	/**
	 * '#rrggbb' in lower case, or null.
	 *
	 * Accepts #rgb, #rrggbb, #rrggbbaa with alpha ff, rgb()/rgba() with alpha 1.
	 * Everything else (var(), names, partial alpha, out-of-range channels) is null.
	 *
	 * @param mixed $value Raw colour.
	 */
	public static function normalize(mixed $value): ?string
	{
		if (! is_string($value)) {
			return null;
		}
		$v = strtolower(trim($value));
		if ('' === $v) {
			return null;
		}

		if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $v, $m)) {
			$v = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		} elseif (preg_match('/^#([0-9a-f]{6})ff$/', $v, $m)) {
			$v = '#' . $m[1];
		} elseif (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([0-9.]+)\s*)?\)$/', $v, $m)) {
			if (isset($m[4]) && 1.0 !== (float) $m[4]) {
				return null;
			}
			$hex = '#';
			foreach (array( $m[1], $m[2], $m[3] ) as $channel) {
				$c = (int) $channel;
				if ($c > 255) {
					return null;
				}
				$hex .= str_pad(dechex($c), 2, '0', STR_PAD_LEFT);
			}
			$v = $hex;
		}

		return 1 === preg_match('/^#[0-9a-f]{6}$/', $v) ? $v : null;
	}

	/** WCAG 2.2 relative luminance of a '#rrggbb' colour. */
	public static function luminance(string $hex): float
	{
		$out = array();
		foreach (self::channels($hex) as $channel) {
			$c     = $channel / 255;
			$out[] = $c <= 0.04045 ? $c / 12.92 : pow(( $c + 0.055 ) / 1.055, 2.4);
		}
		return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
	}

	public static function contrast(string $a, string $b): float
	{
		$l1 = self::luminance($a);
		$l2 = self::luminance($b);
		return ( max($l1, $l2) + 0.05 ) / ( min($l1, $l2) + 0.05 );
	}

	/**
	 * CSS color-mix(in srgb, $a $percent_a%, $b): linear interpolation of the
	 * gamma-encoded channels, each rounded half away from zero.
	 */
	public static function mix(string $a, string $b, int $percent_a): string
	{
		$percent_a = max(0, min(100, $percent_a));
		$ca        = self::channels($a);
		$cb        = self::channels($b);
		$hex       = '#';
		for ($i = 0; $i < 3; $i++) {
			$c    = (int) round(( $ca[ $i ] * $percent_a + $cb[ $i ] * ( 100 - $percent_a ) ) / 100);
			$hex .= str_pad(dechex($c), 2, '0', STR_PAD_LEFT);
		}
		return $hex;
	}

	/**
	 * The eight literal tokens for one accent, the admin flags and the tokens that
	 * went past their fixed first step.
	 *
	 * @return array{tokens: array<string,string>, flags: array{text_darkened: bool, fill_low: bool}, looped: list<string>}
	 */
	public static function palette(string $accent): array
	{
		$a = self::normalize($accent) ?? self::DEFAULT_ACCENT;
		if (self::DEFAULT_ACCENT === $a) {
			return array(
				'tokens' => self::DEFAULT_TOKENS,
				'flags'  => array(
					'text_darkened' => false,
					'fill_low'      => false,
				),
				'looped' => array(),
			);
		}

		$looped = array();

		// 1. Text on the accent: the first of white, ink, black that reaches 4.5.
		$on = self::BLACK;
		foreach (array( self::WHITE, self::INK, self::BLACK ) as $candidate) {
			if (self::contrast($candidate, $a) >= 4.5) {
				$on = $candidate;
				break;
			}
		}

		// 2. Hover fill (spec §5 step 2, ruling R16).
		$deep_ok = static fn (string $d): bool => self::contrast($on, $d) >= 4.5 && self::contrast($a, $d) >= 1.3;
		$first   = self::WHITE === $on ? self::BLACK : self::WHITE;
		$flipped = self::WHITE === $first ? self::BLACK : self::WHITE;
		$deep    = self::mix($a, $first, 80);
		if (! $deep_ok($deep)) {
			$turned = self::mix($a, $flipped, 80);
			if (self::contrast($a, $deep) < 1.3 && $deep_ok($turned)) {
				$deep = $turned;
			} else {
				$looped[] = 'accent-deep';
				for ($step = 1; $step <= 10; $step++) {
					$deep = self::mix($a, $first, 80 - 4 * $step);
					if ($deep_ok($deep)) {
						break;
					}
				}
			}
		}

		// 3-4. Light fill and tinted page background.
		$accent_100 = self::mix($a, self::WHITE, 15);
		$bg         = self::mix($a, self::WHITE, 5);

		// 5. Accent as text: readable on white and on the tinted background.
		$text_ok       = static fn (string $t): bool => self::contrast($t, self::WHITE) >= 4.5 && self::contrast($t, $bg) >= 4.5;
		$text          = $a;
		$text_darkened = false;
		if (! $text_ok($text)) {
			$text_darkened = true;
			$text          = self::mix($a, self::BLACK, 80);
			if (! $text_ok($text)) {
				$looped[] = 'accent-text';
				for ($step = 1; $step <= 20; $step++) {
					$text = self::mix($a, self::BLACK, 80 - 4 * $step);
					if ($text_ok($text)) {
						break;
					}
				}
			}
		}

		// 6. Text hover; also on the accent-100 fill (ruling R5).
		$hover_ok = static fn (string $h): bool => self::contrast($h, $accent_100) >= 4.5 && self::contrast($h, self::WHITE) >= 4.5 && self::contrast($h, $bg) >= 4.5;
		$hover    = self::mix($text, self::BLACK, 80);
		if (! $hover_ok($hover)) {
			$looped[] = 'accent-text-hover';
			for ($step = 1; $step <= 20; $step++) {
				$hover = self::mix($text, self::BLACK, 80 - 4 * $step);
				if ($hover_ok($hover)) {
					break;
				}
			}
		}

		// Fable PR 2 M1, ruling R2: a near-black accent leaves the darker hover
		// indistinguishable from the text, so it turns lighter. A flipped fixed
		// step is not a loop (same definition as accent-deep, R16).
		if (self::contrast($text, $hover) < 1.3) {
			$flipped_hover = self::mix($text, self::WHITE, 80);
			if ($hover_ok($flipped_hover) && self::contrast($text, $flipped_hover) >= 1.3) {
				$hover = $flipped_hover;
			}
		}

		// 7. Selected-day text on the light fill, 7:1.
		$sel = self::mix($text, self::BLACK, 55);
		if (self::contrast($sel, $accent_100) < 7.0) {
			$looped[] = 'sel-text';
			for ($step = 1; $step <= 11; $step++) {
				$sel = self::mix($text, self::BLACK, 55 - 5 * $step);
				if (self::contrast($sel, $accent_100) >= 7.0) {
					break;
				}
			}
		}

		return array(
			'tokens' => array(
				'bg'                => $bg,
				'accent-strong'     => $a,
				'accent-deep'       => $deep,
				'accent-100'        => $accent_100,
				'sel-text'          => $sel,
				'on-accent'         => $on,
				'accent-text'       => $text,
				'accent-text-hover' => $hover,
			),
			'flags'  => array(
				'text_darkened' => $text_darkened,
				// 8. A fill hard to tell from white; the value does not change.
				'fill_low'      => self::contrast($a, self::WHITE) < 3.0,
			),
			'looped' => $looped,
		);
	}

	/**
	 * @return array{0:int,1:int,2:int}
	 */
	private static function channels(string $hex): array
	{
		return array(
			(int) hexdec(substr($hex, 1, 2)),
			(int) hexdec(substr($hex, 3, 2)),
			(int) hexdec(substr($hex, 5, 2)),
		);
	}
}
