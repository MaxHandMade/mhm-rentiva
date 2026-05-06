<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Directory;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Resolves the public URL base for the Vendor Directory page (`/{base}/`).
 *
 * Source priority:
 *   1. `apply_filters('mhm_rentiva_vendor_directory_url_base', $translated)` — site-owner override
 *   2. `_x('vendors', 'URL slug', 'mhm-rentiva')` — translation context
 *   3. Hardcoded default `'vendors'` if both yield empty
 *
 * The returned value is always ASCII-safe (`sanitize_title(remove_accents())`),
 * which avoids URL encoding on display and matches WordPress core slug behavior.
 *
 * Locale change handling: see {@see check_for_locale_change()}.
 *
 * @since 4.38.0
 */
final class VendorDirectoryUrlBase
{
	private const DEFAULT_BASE   = 'vendors';
	private const CACHED_OPTION  = 'mhm_rentiva_vendor_directory_url_base_cached';
	private const FILTER_HOOK    = 'mhm_rentiva_vendor_directory_url_base';
	private const CHANGE_ACTION  = 'mhm_rentiva_vendor_directory_url_base_changed';

	public static function resolve(): string
	{
		/* translators: URL slug used in /{vendors}/ public vendor directory page.
		 * Lowercase ASCII recommended (no spaces, no diacritics) — used in browser
		 * address bar and shared links. Examples: "vendors" (EN), "bayiler" (TR),
		 * "anbieter" (DE), "partenaires" (FR), "vendedores" (ES). */
		$translated = _x('vendors', 'URL slug', 'mhm-rentiva');
		$candidate  = (string) apply_filters(self::FILTER_HOOK, $translated);

		// Defensive: filter could return '' or non-ASCII; urldecode in case
		// a site owner passes an already-encoded value (Phase 5 reviewer Bulgu 1
		// of v4.37.0).
		$candidate = urldecode($candidate);
		$candidate = sanitize_title(remove_accents($candidate));

		if ($candidate === '') {
			return self::DEFAULT_BASE;
		}

		return $candidate;
	}

	public static function url_for_directory(): string
	{
		return home_url('/' . self::resolve() . '/');
	}

	/**
	 * Compares the current resolved base to the cached option and fires the
	 * change action when they diverge. Wired in `Plugin.php` after textdomain load.
	 */
	public static function check_for_locale_change(): void
	{
		$current = self::resolve();
		$cached  = (string) get_option(self::CACHED_OPTION, '');

		if ($current === $cached) {
			return;
		}

		update_option(self::CACHED_OPTION, $current, false);
		do_action(self::CHANGE_ACTION, $current, $cached);
	}
}
