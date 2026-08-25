<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Resolve a translated string in the SITE's locale, whoever is asking.
 *
 * Some translated strings are not labels on a screen -- they are identifiers
 * that end up in a URL, a rewrite rule or a query var. Those have to answer the
 * same way on every request, and `determine_locale()` does not: inside wp-admin
 * it returns `get_user_locale()` (wp-includes/l10n.php:150), so the same call
 * gives one answer to an administrator reading the dashboard and another to a
 * visitor reading the site.
 *
 * That matters because rewrite rules have no locale dimension. They live in one
 * global `rewrite_rules` option, so a slug resolved from the asker's locale
 * lets an administrator whose profile language differs from the site's rewrite
 * the URLs every visitor sees -- WordPress Trac #40298 -- and makes two flush
 * triggers disagree about the endpoint set on alternating requests.
 *
 * @since 6.1.1
 */
final class SiteLocaleString {

	/**
	 * Run $resolver with the site locale in effect.
	 *
	 * Switches only when the current locale actually differs, because
	 * switch_to_locale() reloads every text domain and this runs inside slug
	 * resolution, which is hot.
	 *
	 * @param callable():string $resolver Returns the translated string.
	 */
	public static function resolve( callable $resolver ): string {
		$site     = self::site_locale();
		$switched = determine_locale() !== $site && switch_to_locale( $site );

		try {
			return (string) $resolver();
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * The locale the SITE is stored as, read past the `locale` filter.
	 *
	 * 🔴 Deliberately not get_locale(). Measured: get_locale() applies the
	 * `locale` filter, and that is exactly where WP_Locale_Switcher installs
	 * itself -- so inside a switch it reports the switched locale, and a helper
	 * built on it is a no-op in the one situation it exists for.
	 *
	 * Reading the stored option is the most stable definition available, which
	 * is what a global rewrite rule needs. The trade is that a multilingual
	 * plugin which sets the site locale through the `locale` filter rather than
	 * the option is bypassed here -- correct for this purpose: those plugins
	 * translate URLs through their own rewrite layer, not by moving the site's
	 * locale under everyone else.
	 */
	public static function site_locale(): string {
		$stored = is_multisite() ? get_site_option( 'WPLANG' ) : get_option( 'WPLANG' );

		return is_string( $stored ) && '' !== $stored ? $stored : 'en_US';
	}
}
