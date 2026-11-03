<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Reads the front-end design tokens (assets/css/rvs/rvs-tokens.css) the way the
 * colour tests need them: the raw declarations of the one token rule, a name
 * resolved through `var()` from the same file, and WCAG contrast.
 *
 * One resolver for every test that reads a token value, so a token bound to
 * another (`--rvs-sel-bg: var(--rvs-accent-100)`) is judged by the hex it
 * reaches, not by whether it is spelled as hex.
 */
final class RvsTokenFile
{
	private const MAX_DEPTH = 8;

	public static function path(): string
	{
		return MHMRENTIVA_PLUGIN_DIR . 'assets/css/rvs/rvs-tokens.css';
	}

	public static function css(): string
	{
		return (string) file_get_contents(self::path()); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a shipped asset.
	}

	/**
	 * Name => raw value, from the single rule that declares the tokens for its
	 * four scopes (.rvs-shell, the account wrapper, body.rvs-rental, .rvs-cart-line).
	 *
	 * @return array<string,string>
	 */
	public static function declarations(): array
	{
		$css = (string) preg_replace('!/\*.*?\*/!s', '', self::css());
		if (! preg_match('/\{([^}]*)\}/', $css, $block)) {
			return array();
		}

		$out = array();
		preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $block[1], $m, PREG_SET_ORDER);
		foreach ($m as $row) {
			$out[ $row[1] ] = trim($row[2]);
		}
		return $out;
	}

	/**
	 * '#rrggbb' in lower case, or '' when the token is missing, is not a colour,
	 * runs deeper than eight `var()` hops or loops back on itself.
	 */
	public static function resolve(string $name): string
	{
		return self::resolve_in(self::declarations(), $name, array());
	}

	/**
	 * @param array<string,string> $decls
	 * @param array<string,bool>   $seen
	 */
	private static function resolve_in(array $decls, string $name, array $seen): string
	{
		if (isset($seen[ $name ]) || count($seen) >= self::MAX_DEPTH || ! isset($decls[ $name ])) {
			return '';
		}
		$seen[ $name ] = true;
		$value         = $decls[ $name ];

		if (preg_match('/^#([0-9a-f]{6})$/i', $value, $hex)) {
			return '#' . strtolower($hex[1]);
		}
		if (preg_match('/^var\(\s*(--[a-z0-9-]+)\s*\)$/i', $value, $ref)) {
			return self::resolve_in($decls, $ref[1], $seen);
		}
		return '';
	}

	/**
	 * WCAG 2.2 relative luminance, shared with the product (ColorMath, threshold
	 * 0.04045; on 8-bit channels the old 0.03928 gives the same value).
	 */
	public static function luminance(string $hex): float
	{
		return \MHMRentiva\Core\Color\ColorMath::luminance($hex);
	}

	public static function contrast(string $a, string $b): float
	{
		$l1 = self::luminance($a);
		$l2 = self::luminance($b);
		return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
	}
}
