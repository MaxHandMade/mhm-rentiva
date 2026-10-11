<?php
declare(strict_types=1);

namespace MHMRentiva\I18n;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Prefer the WP.org language pack over a bundled script translation catalogue.
 *
 * When a script's translations are set with an explicit `$path`
 * (`wp_set_script_translations()`), WordPress reads the bundled JSON from that
 * path BEFORE `WP_LANG_DIR/plugins/`, so a bundled catalogue would shadow a newer
 * language pack. `.mo` files already load the pack first; this filter gives
 * script catalogues the same order.
 *
 * The filter looks at the handle's registered `translations_path`, never at the
 * text domain, so it covers every script whose catalogue lives in a Rentiva
 * `languages/` directory regardless of which code registered it (blocks here,
 * React admin pages through ui-core). Known directories are Lite's own plus
 * whatever add-ons contribute via `mhmrentiva_translation_dirs`.
 *
 * @package MHMRentiva\I18n
 */
final class LanguagePackPreference {

	/**
	 * Normalised known directories, computed on the first filter call.
	 *
	 * Computed lazily (not on `init`) so that an add-on hooking
	 * `mhmrentiva_translation_dirs` later in the load order is still included.
	 *
	 * @var array<int,string>|null
	 */
	private static ?array $dirs = null;

	/**
	 * Hook the filter.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		add_filter('load_script_translation_file', array( self::class, 'filter' ), 10, 3);
	}

	/**
	 * Swap a bundled catalogue for the same-named language pack file, if present.
	 *
	 * @param mixed  $file   Candidate file path, or false (core's last-resort call).
	 * @param string $handle Script handle.
	 * @param string $domain Text domain.
	 * @return mixed The pack path, or `$file` untouched.
	 */
	public static function filter( $file, string $handle, string $domain )
	{
		unset($domain);

		// Core also calls the filter with `false`; never touch a non-string.
		if (! is_string($file) || '' === $file) {
			return $file;
		}

		$scripts = wp_scripts();
		if (! isset($scripts->registered[ $handle ])) {
			return $file;
		}

		// Unset (null) unless wp_set_script_translations() ran for the handle.
		$path = $scripts->registered[ $handle ]->translations_path;
		if (! is_string($path) || '' === $path) {
			return $file;
		}

		if (! in_array(self::normalize($path), self::known_dirs(), true)) {
			return $file;
		}

		$pack = WP_LANG_DIR . '/plugins/' . basename($file);
		if (is_readable($pack)) {
			return $pack;
		}

		return $file;
	}

	/**
	 * Known Rentiva `languages/` directories, normalised.
	 *
	 * @return array<int,string>
	 */
	private static function known_dirs(): array
	{
		if (null === self::$dirs) {
			$dirs       = (array) apply_filters('mhmrentiva_translation_dirs', array( MHMRENTIVA_PLUGIN_DIR . 'languages/' ));
			self::$dirs = array();
			foreach ($dirs as $dir) {
				if (is_string($dir) && '' !== $dir) {
					self::$dirs[] = self::normalize($dir);
				}
			}
		}

		return self::$dirs;
	}

	/**
	 * Compare paths independent of separator style and trailing slash.
	 *
	 * @param string $path Directory path.
	 * @return string
	 */
	private static function normalize( string $path ): string
	{
		return untrailingslashit(wp_normalize_path($path));
	}
}
