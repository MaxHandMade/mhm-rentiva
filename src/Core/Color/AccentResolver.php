<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Color;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Core\Color\Sources\AccentSource;
use MHMRentiva\Core\Color\Sources\AdapterSource;
use MHMRentiva\Core\Color\Sources\ElementorKitSource;
use MHMRentiva\Core\Color\Sources\ThemeJsonSource;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The front-end accent for this request: Custom mode's colour, or in Automatic
 * mode the first concrete colour of theme adapter > Elementor kit > theme.json,
 * else Rentiva #2271b1. Resolved once per request (static memory, no persistent
 * cache, so a theme/kit/setting change is right on the next request).
 *
 * Filters: `mhmrentiva_accent_sources` (ordered array<string, AccentSource>),
 * `mhmrentiva_accent_color` (string $hex, string $source_id; output normalised).
 */
final class AccentResolver {

	public const MODE_KEY  = 'mhmrentiva_accent_mode';
	public const COLOR_KEY = 'mhmrentiva_accent_color';

	/**
	 * @var array{hex: string, source: string, label: string, is_default: bool, flags: array{text_darkened: bool, fill_low: bool}, tokens: array<string,string>}|null
	 */
	private static ?array $memo = null;

	/**
	 * @var array{hex: string, source: string, label: string, is_default: bool, flags: array{text_darkened: bool, fill_low: bool}, tokens: array<string,string>}|null
	 */
	private static ?array $auto_memo = null;

	/**
	 * Custom mode's colour when valid (no source runs), else the automatic chain.
	 *
	 * @return array{hex: string, source: string, label: string, is_default: bool, flags: array{text_darkened: bool, fill_low: bool}, tokens: array<string,string>}
	 */
	public static function resolve(): array
	{
		if (null !== self::$memo) {
			return self::$memo;
		}

		// Plan R13: a stored colour is normalised on read too; a bad one means Automatic.
		$custom = ColorMath::normalize(SettingsCore::get(self::COLOR_KEY, ''));
		if ('custom' === SettingsCore::get(self::MODE_KEY, 'auto') && null !== $custom) {
			self::$memo = self::finish($custom, 'custom', '', false);
			return self::$memo;
		}

		self::$memo = self::automatic();
		return self::$memo;
	}

	/**
	 * The automatic chain, whatever the mode: first concrete colour of the
	 * sources, else Rentiva #2271b1, then the colour filter. Own request memory.
	 *
	 * @return array{hex: string, source: string, label: string, is_default: bool, flags: array{text_darkened: bool, fill_low: bool}, tokens: array<string,string>}
	 */
	public static function automatic(): array
	{
		if (null !== self::$auto_memo) {
			return self::$auto_memo;
		}

		$hex        = null;
		$source     = 'default';
		$label      = '';
		$is_default = false;

		$sources = apply_filters(
			'mhmrentiva_accent_sources',
			array(
				'adapter'    => new AdapterSource(),
				'elementor'  => new ElementorKitSource(),
				'theme_json' => new ThemeJsonSource(),
			)
		);
		foreach (( is_array($sources) ? $sources : array() ) as $candidate) {
			if (! $candidate instanceof AccentSource) {
				continue;
			}
			$found = ColorMath::normalize($candidate->resolve());
			if (null !== $found) {
				$hex    = $found;
				$source = $candidate->id();
				$label  = $candidate->label();
				if ($candidate instanceof AdapterSource) {
					$is_default = $candidate->is_default();
				}
				break;
			}
		}

		self::$auto_memo = self::finish($hex, $source, $label, $is_default);
		return self::$auto_memo;
	}

	/**
	 * Apply the colour filter (last word, Custom included) and build the result.
	 * A filter that changes the colour is the source (plan R5).
	 *
	 * @return array{hex: string, source: string, label: string, is_default: bool, flags: array{text_darkened: bool, fill_low: bool}, tokens: array<string,string>}
	 */
	private static function finish(?string $hex, string $source, string $label, bool $is_default): array
	{
		$base     = $hex ?? ColorMath::DEFAULT_ACCENT;
		$filtered = ColorMath::normalize(apply_filters('mhmrentiva_accent_color', $base, $source));
		if (null === $filtered) {
			$filtered   = ColorMath::DEFAULT_ACCENT;
			$source     = 'default';
			$label      = '';
			$is_default = false;
		} elseif ($filtered !== $base) {
			$source     = 'filter';
			$label      = '';
			$is_default = false;
		}

		$palette = ColorMath::palette($filtered);
		return array(
			'hex'        => $filtered,
			'source'     => $source,
			'label'      => $label,
			'is_default' => $is_default,
			'flags'      => $palette['flags'],
			'tokens'     => $palette['tokens'],
		);
	}

	/** Forget the request memory (tests). */
	public static function reset(): void
	{
		self::$memo      = null;
		self::$auto_memo = null;
	}
}
