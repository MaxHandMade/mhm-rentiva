<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Resolves the i18n-aware base slug for the public vendor profile URL.
 *
 * Pattern mirrors WooCommerce's translatable /shop/, /product/ slugs.
 * Default EN: "vendor". TR translation maps it to "bayi".
 * Filter mhm_rentiva_vendor_profile_url_base allows site-owner override.
 *
 * @since 4.37.0
 */
final class VendorProfileUrlBase
{
	public const CACHE_OPTION = 'mhm_rentiva_vendor_url_base_cached';
	public const FALLBACK     = 'vendor';

	/**
	 * Resolve the current base slug, applying translation + filter, then
	 * defensive ASCII sanitization. Always returns a non-empty ASCII slug.
	 */
	public static function resolve(): string
	{
		/* translators: URL slug used in /vendor/{vendor-slug}/ public profile URLs.
		 * Lowercase ASCII recommended (no spaces, no diacritics) — used in browser
		 * address bar and shared links. Examples: "vendor" (EN), "bayi" (TR),
		 * "anbieter" (DE), "partenaire" (FR), "vendedor" (ES). */
		$translated = _x('vendor', 'URL slug', 'mhm-rentiva');

		$filtered = apply_filters('mhm_rentiva_vendor_profile_url_base', $translated);
		// urldecode first: a filter returning 'foo%2fbar' must collapse the
		// percent-encoded slash to '-' (via sanitize_title) so the rule
		// regex matches Apache/Nginx-decoded request URIs. Without this the
		// rewrite registers '^foo%2fbar/...' which never matches anything.
		$clean = sanitize_title(remove_accents(urldecode((string) $filtered)));

		if ($clean === '') {
			return self::FALLBACK;
		}

		return $clean;
	}

	/**
	 * Build full URL for a given slug.
	 *
	 * Returns '' when slug is empty — protects callers (templates, widgets,
	 * 301 redirect target builder) from emitting a malformed '/{base}//' URL.
	 */
	public static function url_for_slug(string $slug): string
	{
		if ($slug === '') {
			return '';
		}

		return home_url('/' . self::resolve() . '/' . $slug . '/');
	}

	/**
	 * Build full URL for a vendor user_id (looks up their slug).
	 *
	 * Returns '' when the user has no slug stored yet.
	 */
	public static function url_for_user(int $user_id): string
	{
		if ($user_id <= 0) {
			return '';
		}

		$slug = (string) get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true);
		if ($slug === '') {
			return '';
		}

		return self::url_for_slug($slug);
	}

	/**
	 * Compare current resolved base against cached option. If different,
	 * update option and fire the mhm_rentiva_vendor_url_base_changed action.
	 * Listeners (rewrite flush) hook this action.
	 *
	 * Wired post-textdomain-load in Plugin.php so locale changes (TR ↔ EN)
	 * trigger a single rewrite flush instead of flushing every request.
	 */
	public static function check_for_locale_change(): void
	{
		$current = self::resolve();
		$cached  = (string) get_option(self::CACHE_OPTION, '');

		if ($cached === $current) {
			return;
		}

		// $autoload = false: option is only read inside check_for_locale_change()
		// on textdomain load (once per request), no benefit from autoload.
		update_option(self::CACHE_OPTION, $current, false);
		do_action('mhm_rentiva_vendor_url_base_changed', $current, $cached);
	}
}
