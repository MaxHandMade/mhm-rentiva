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
 * language pack. The pack is chosen instead, and complete_from_bundled() fills the
 * keys it lacks (strings added after the last GlotPress import) from the bundled file.
 * (PHP `.mo` catalogues are a different story: Plugin.php loads the bundled one
 * explicitly on `init`, so the pack's `.mo` is not read at all.)
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
		add_filter('load_script_translations', array( self::class, 'complete_from_bundled' ), 10, 4);
	}

	/**
	 * Fill the keys a chosen language pack lacks from the bundled catalogue.
	 *
	 * The pack only carries the strings of the last GlotPress import, so a string added to
	 * the plugin since then showed in English even though the bundled catalogue had it
	 * (119 strings on the dev site, 2026-10-19). The pack still wins wherever it has a key,
	 * and its header (plural rule) is kept; the bundled catalogue only adds what is missing.
	 *
	 * @param mixed  $translations JSON from the file core loaded.
	 * @param mixed  $file         The file core loaded (after `filter()`).
	 * @param string $handle       Script handle.
	 * @param string $domain       Text domain.
	 * @return mixed The completed JSON, or `$translations` untouched.
	 */
	public static function complete_from_bundled( $translations, $file, string $handle, string $domain )
	{
		if (! is_string($translations) || ! is_string($file) || '' === $file) {
			return $translations;
		}

		// Only a pack file that filter() chose for a Rentiva handle.
		if (self::normalize(dirname($file)) !== self::normalize(WP_LANG_DIR . '/plugins')) {
			return $translations;
		}

		$scripts = wp_scripts();
		if (! isset($scripts->registered[ $handle ])) {
			return $translations;
		}
		$path = $scripts->registered[ $handle ]->translations_path;
		if (! is_string($path) || '' === $path || ! in_array(self::normalize($path), self::known_dirs(), true)) {
			return $translations;
		}

		$bundled_file = trailingslashit(wp_normalize_path($path)) . basename($file);
		if (! is_readable($bundled_file)) {
			return $translations;
		}

		$pack        = json_decode($translations, true);
		$bundled     = json_decode( (string) file_get_contents($bundled_file), true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local translation file.
		$pack_key    = self::locale_data_key($pack, $domain);
		$bundled_key = self::locale_data_key($bundled, $domain);
		if (null === $pack_key || null === $bundled_key) {
			return $translations;
		}

		// Pack keys (and its '' header) override; bundled keys fill the gaps.
		$pack['locale_data'][ $pack_key ] = array_replace($bundled['locale_data'][ $bundled_key ], $pack['locale_data'][ $pack_key ]);

		$merged = wp_json_encode($pack);

		return is_string($merged) ? $merged : $translations;
	}

	/**
	 * The `locale_data` key a catalogue's strings live under, chosen the way core reads it
	 * (WP_Scripts::print_translations): the text domain first, then `messages`.
	 *
	 * @param mixed  $catalogue Decoded JSON catalogue.
	 * @param string $domain    Text domain.
	 * @return string|null The key, or null when the catalogue has neither.
	 */
	private static function locale_data_key( $catalogue, string $domain ): ?string
	{
		if (! is_array($catalogue) || ! isset($catalogue['locale_data']) || ! is_array($catalogue['locale_data'])) {
			return null;
		}

		foreach (array( $domain, 'messages' ) as $key) {
			if (isset($catalogue['locale_data'][ $key ]) && is_array($catalogue['locale_data'][ $key ])) {
				return $key;
			}
		}

		return null;
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
