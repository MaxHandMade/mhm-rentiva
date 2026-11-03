<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Color\Sources;

use MHMRentiva\Core\Color\ColorMath;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Source 3: theme.json (spec §2 item 3).
 *
 * (a) The global button background, variables resolved; core's own #32373c is
 *     skipped. (b) Palette slugs `primary`, then `accent`, in the `custom` origin
 *     (Site Editor) before `theme`; the `default` origin is never read, core
 *     colours exist on every site. A value still in `var(...)` is rejected.
 */
final class ThemeJsonSource implements AccentSource
{
	private const CORE_BUTTON = '#32373c';

	private string $label = '';

	public function id(): string
	{
		return 'theme_json';
	}

	/** 'button' or the palette slug (raw; plan R8). */
	public function label(): string
	{
		return $this->label;
	}

	public function resolve(): ?string
	{
		if (function_exists('wp_get_global_styles')) {
			$button = ColorMath::normalize(
				wp_get_global_styles(
					array( 'elements', 'button', 'color', 'background' ),
					array( 'transforms' => array( 'resolve-variables' ) )
				)
			);
			if (null !== $button && self::CORE_BUTTON !== $button) {
				$this->label = 'button';
				return $button;
			}
		}

		if (! function_exists('wp_get_global_settings')) {
			return null;
		}
		$palette = wp_get_global_settings(array( 'color', 'palette' ));
		if (! is_array($palette)) {
			return null;
		}
		foreach (array( 'custom', 'theme' ) as $origin) {
			$entries = isset($palette[ $origin ]) && is_array($palette[ $origin ]) ? $palette[ $origin ] : array();
			foreach (array( 'primary', 'accent' ) as $slug) {
				foreach ($entries as $entry) {
					if (is_array($entry) && isset($entry['slug'], $entry['color']) && $slug === $entry['slug']) {
						$hex = ColorMath::normalize($entry['color']);
						if (null !== $hex) {
							$this->label = $slug;
							return $hex;
						}
					}
				}
			}
		}
		return null;
	}
}
