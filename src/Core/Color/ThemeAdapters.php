<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Color;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Registry of known themes that keep their accent colour somewhere a CSS
 * variable chain cannot reach (spec §2.1). Each adapter returns the theme's
 * *effective* colour: the saved value, else the theme's own default.
 *
 * Detection reads get_template(), so child themes are covered. A theme in an
 * unexpected shape (missing function, wrong option type, broken JSON) returns
 * null without a PHP warning and the chain moves to the next source.
 *
 * Filter: `mhmrentiva_theme_adapters` (array<string, array{label, detect, read}>).
 */
final class ThemeAdapters {

	/** Avanam's palette1 default (Base\Options\Component::palette_defaults(), measured; plan R7). */
	private const AVANAM_DEFAULT = '#2b6cb0';

	/** The fallback listeo's own CSS uses (inc/customizer.php:55). */
	private const LISTEO_DEFAULT = '#f30c0c';

	private const UPSTUDY_DEFAULT = '#1cb098';

	private const AVANAM_SETS = array( 'palette', 'second-palette', 'third-palette' );

	/**
	 * The filtered registry; invalid entries (not an array, detect/read not
	 * callable) are dropped.
	 *
	 * @return array<string, array{label: string, detect: callable, read: callable}>
	 */
	public static function all(): array
	{
		$registry = apply_filters('mhmrentiva_theme_adapters', self::builtin());
		if (! is_array($registry)) {
			return array();
		}

		$out = array();
		foreach ($registry as $id => $adapter) {
			if (! is_string($id) || '' === $id || ! is_array($adapter)) {
				continue;
			}
			if (! isset($adapter['detect'], $adapter['read']) || ! is_callable($adapter['detect']) || ! is_callable($adapter['read'])) {
				continue;
			}
			$out[ $id ] = array(
				'label'  => isset($adapter['label']) && is_string($adapter['label']) ? $adapter['label'] : $id,
				'detect' => $adapter['detect'],
				'read'   => $adapter['read'],
			);
		}
		return $out;
	}

	/**
	 * The reading of the first adapter that recognises the active theme.
	 *
	 * @return array{id: string, hex: string, is_default: bool}|null
	 */
	public static function read_active(): ?array
	{
		foreach (self::all() as $id => $adapter) {
			if (true !== (bool) call_user_func($adapter['detect'])) {
				continue;
			}
			$read = call_user_func($adapter['read']);
			if (! is_array($read) || ! isset($read['hex'])) {
				return null;
			}
			$hex = ColorMath::normalize($read['hex']);
			if (null === $hex) {
				return null;
			}
			return array(
				'id'         => $id,
				'hex'        => $hex,
				'is_default' => ! empty($read['is_default']),
			);
		}
		return null;
	}

	/**
	 * @return array<string, array{label: string, detect: callable, read: callable}>
	 */
	private static function builtin(): array
	{
		return array(
			'thembay' => array(
				'label'  => 'Thembay',
				'detect' => static fn (): bool => function_exists(self::template() . '_tbay_get_config'),
				'read'   => array( self::class, 'read_thembay' ),
			),
			'avanam'  => array(
				'label'  => 'Avanam',
				'detect' => static fn (): bool => 'avanam' === self::template(),
				'read'   => array( self::class, 'read_avanam' ),
			),
			'listeo'  => array(
				'label'  => 'listeo',
				'detect' => static fn (): bool => 'listeo' === self::template(),
				'read'   => static fn (): ?array => self::effective(get_option('pp_main_color', self::LISTEO_DEFAULT), self::LISTEO_DEFAULT),
			),
			'politic' => array(
				'label'  => 'politic',
				'detect' => static fn (): bool => 'politic' === self::template(),
				'read'   => array( self::class, 'read_politic' ),
			),
			'upstudy' => array(
				'label'  => 'upstudy',
				'detect' => static fn (): bool => 'upstudy' === self::template(),
				'read'   => static fn (): ?array => self::effective(get_theme_mod('primary_color'), self::UPSTUDY_DEFAULT),
			),
			'astra'   => array(
				'label'  => 'Astra',
				'detect' => static fn (): bool => function_exists('astra_get_palette_colors'),
				'read'   => array( self::class, 'read_astra' ),
			),
		);
	}

	/**
	 * Thembay (lasa, ruza, zota): Redux `{tpl}_tbay_theme_options['main_color']`,
	 * else the theme's `{tpl}_tbay_default_theme_primary_color()['main_color']`
	 * when that function takes no required argument (plan R7).
	 *
	 * @return array{hex: string, is_default: bool}|null
	 */
	public static function read_thembay(): ?array
	{
		$tpl     = self::template();
		$options = get_option($tpl . '_tbay_theme_options');
		$saved   = is_array($options) && isset($options['main_color']) ? $options['main_color'] : null;

		$default = null;
		$fn      = $tpl . '_tbay_default_theme_primary_color';
		if (function_exists($fn)) {
			try {
				$required = ( new \ReflectionFunction($fn) )->getNumberOfRequiredParameters();
			} catch (\ReflectionException $e) {
				$required = -1;
			}
			if (0 === $required) {
				$colors = $fn();
				if (is_array($colors) && isset($colors['main_color']) && is_string($colors['main_color'])) {
					$default = $colors['main_color'];
				}
			}
		}

		return self::effective($saved, $default);
	}

	/**
	 * Avanam: JSON option `base_global_palette`, the active set's first entry when
	 * its slug is palette1; else palette1's default.
	 *
	 * @return array{hex: string, is_default: bool}|null
	 */
	public static function read_avanam(): ?array
	{
		$raw     = get_option('base_global_palette');
		$palette = is_string($raw) ? json_decode($raw, true) : ( is_array($raw) ? $raw : null );
		$saved   = null;
		if (is_array($palette) && isset($palette['active']) && is_string($palette['active']) && in_array($palette['active'], self::AVANAM_SETS, true)) {
			$set   = $palette[ $palette['active'] ] ?? null;
			$first = is_array($set) && isset($set[0]) && is_array($set[0]) ? $set[0] : null;
			if (null !== $first && isset($first['slug'], $first['color']) && 'palette1' === $first['slug']) {
				$saved = $first['color'];
			}
		}
		return self::effective($saved, self::AVANAM_DEFAULT);
	}

	/**
	 * The politic theme: `politicem_opt['politicem_primary_color']`; it has no default.
	 *
	 * @return array{hex: string, is_default: bool}|null
	 */
	public static function read_politic(): ?array
	{
		$options = get_option('politicem_opt');
		$saved   = is_array($options) && isset($options['politicem_primary_color']) ? $options['politicem_primary_color'] : null;
		return self::effective($saved, null);
	}

	/**
	 * Astra: the active global palette's first colour (astra_get_palette_colors()
	 * falls back to Astra's own default palette by itself).
	 *
	 * @return array{hex: string, is_default: bool}|null
	 */
	public static function read_astra(): ?array
	{
		$palette_class = 'Astra_Global_Palette';
		$active        = array( $palette_class, 'astra_get_active_global_palette' );
		if (! function_exists('astra_get_palette_colors') || ! is_callable($active)) {
			return null;
		}
		$colors = astra_get_palette_colors();
		$key    = call_user_func($active);
		if (! is_string($key) || ! is_array($colors) || ! isset($colors['palettes']) || ! is_array($colors['palettes'])) {
			return null;
		}
		$set = $colors['palettes'][ $key ] ?? null;
		$hex = is_array($set) && isset($set[0]) ? ColorMath::normalize($set[0]) : null;
		if (null === $hex) {
			return null;
		}

		$defaults_fn = array( $palette_class, 'get_default_color_palette' );
		if (is_callable($defaults_fn)) {
			$defaults   = call_user_func($defaults_fn);
			$default    = is_array($defaults) && isset($defaults['palettes'][ $key ][0]) ? ColorMath::normalize($defaults['palettes'][ $key ][0]) : null;
			$is_default = null !== $default && $default === $hex;
		} else {
			$is_default = false === get_option('astra-color-palettes');
		}

		return array(
			'hex'        => $hex,
			'is_default' => $is_default,
		);
	}

	/**
	 * Saved value ?: the theme's default, both normalised.
	 *
	 * @param mixed $saved Raw saved value.
	 * @return array{hex: string, is_default: bool}|null
	 */
	private static function effective(mixed $saved, ?string $default): ?array
	{
		$d   = null === $default ? null : ColorMath::normalize($default);
		$hex = ColorMath::normalize($saved) ?? $d;
		if (null === $hex) {
			return null;
		}
		return array(
			'hex'        => $hex,
			'is_default' => null !== $d && $d === $hex,
		);
	}

	private static function template(): string
	{
		return (string) get_template();
	}
}
