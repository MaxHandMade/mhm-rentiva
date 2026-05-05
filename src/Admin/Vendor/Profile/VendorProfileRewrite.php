<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * /{translated_base}/{slug}/ rewrite rule registration + 404/301 dispatch.
 *
 * Listens to mhm_rentiva_vendor_url_base_changed (fired by VendorProfileUrlBase
 * on locale change) and flushes rewrite rules once per change instead of
 * every request.
 *
 * @since 4.37.0
 */
final class VendorProfileRewrite
{
	public const QUERY_VAR = 'mhm_rentiva_vendor_slug';

	/**
	 * Wire all hooks. Safe to call multiple times — WP de-dupes identical
	 * (callback, priority) pairs. If called after init has already fired
	 * (e.g. during integration tests, Plugin.php is loaded after init),
	 * we register the rewrite rule + query var directly so no first-fire
	 * is missed.
	 */
	public static function register(): void
	{
		add_action('init', [self::class, 'register_rewrite']);
		add_filter('query_vars', [self::class, 'register_query_var']);
		add_action('template_redirect', [self::class, 'handle_request']);
		add_filter('template_include', [self::class, 'filter_template_include'], 99);
		add_action('mhm_rentiva_vendor_url_base_changed', [self::class, 'flush_on_base_change']);

		if (did_action('init') > 0) {
			self::register_rewrite();

			global $wp;
			if ($wp instanceof \WP) {
				$wp->public_query_vars = array_values(
					array_unique(self::register_query_var($wp->public_query_vars))
				);
			}
		}
	}

	public static function register_rewrite(): void
	{
		$base = VendorProfileUrlBase::resolve();
		add_rewrite_rule(
			'^' . preg_quote($base, '/') . '/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
		add_rewrite_tag('%' . self::QUERY_VAR . '%', '([^&]+)');
	}

	/**
	 * @param array<int,string> $vars
	 * @return array<int,string>
	 */
	public static function register_query_var(array $vars): array
	{
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function flush_on_base_change(): void
	{
		flush_rewrite_rules(false);
	}

	/**
	 * Swap the default WP template for our vendor profile page wrapper when
	 * the request is a `/{base}/{slug}/` for an active vendor.
	 *
	 * Theme override path follows WP convention: drop a copy at
	 * `<active-theme>/mhm-rentiva/vendor-profile-page.php` and `locate_template`
	 * will pick it up before the plugin's bundled file.
	 *
	 * Non-vendor requests are passthrough — the original `$template` is
	 * returned untouched so unrelated pages keep their normal hierarchy.
	 */
	public static function filter_template_include(string $template): string
	{
		$slug = (string) get_query_var(self::QUERY_VAR);
		if ($slug === '') {
			return $template;
		}

		$user_id = self::lookup_user_for_request($slug);
		if ($user_id <= 0) {
			// Unknown slug or non-active vendor — handle_request() already
			// dispatched the 404 / 301; let the theme's 404 template run.
			return $template;
		}

		$theme_override = locate_template([ 'mhm-rentiva/vendor-profile-page.php' ]);
		if ($theme_override !== '') {
			return $theme_override;
		}

		$plugin_template = self::default_template_path();
		return file_exists($plugin_template) ? $plugin_template : $template;
	}

	/**
	 * Resolve the bundled plugin template path. Defined as a separate method
	 * so tests can mock it without depending on the MHM_RENTIVA_PLUGIN_FILE
	 * constant being set in every bootstrap.
	 */
	public static function default_template_path(): string
	{
		if (defined('MHM_RENTIVA_PLUGIN_FILE')) {
			return plugin_dir_path(MHM_RENTIVA_PLUGIN_FILE) . 'templates/frontend/vendor-profile-page.php';
		}
		// Fallback: walk up from this file (src/Admin/Vendor/Profile → plugin root).
		return dirname(__DIR__, 4) . '/templates/frontend/vendor-profile-page.php';
	}

	/**
	 * Resolve the slug from the request to a vendor user_id.
	 * Returns 0 if not an active vendor.
	 */
	public static function lookup_user_for_request(string $slug): int
	{
		return VendorProfileProvider::lookup_by_slug($slug);
	}

	/**
	 * On a /{base}/{slug}/ request: dispatch render (active vendor),
	 * 301 redirect (history match), or 404 (unknown slug).
	 *
	 * Render flow itself lands in Phase 6 via the template_loader filter;
	 * here we only resolve the dispatch decision.
	 */
	public static function handle_request(): void
	{
		$slug = (string) get_query_var(self::QUERY_VAR);
		if ($slug === '') {
			return;
		}

		$user_id = self::lookup_user_for_request($slug);
		if ($user_id > 0) {
			// Active vendor — clear the default WP_Query 404/home state so
			// the body renders as a real 200 page. Without this WordPress
			// flips `is_404` true (no matching post) and the theme's 404
			// branch fires instead of our profile template, even though the
			// `template_include` swap below would still pick our file.
			global $wp_query;
			if ($wp_query instanceof \WP_Query) {
				$wp_query->is_404      = false;
				$wp_query->is_home     = false;
				$wp_query->is_singular = false;
				$wp_query->is_archive  = false;
			}
			status_header(200);
			return;
		}

		$redirect = self::resolve_redirect_target($slug);
		if ($redirect !== '') {
			wp_safe_redirect($redirect, 301);
			exit;
		}

		// Unknown slug — 404.
		global $wp_query;
		if ($wp_query instanceof \WP_Query) {
			$wp_query->set_404();
		}
		status_header(404);
		nocache_headers();
	}

	/**
	 * If $slug appears in any vendor's slug history AND that vendor is active,
	 * return the URL of their current slug. Otherwise return ''.
	 *
	 * Powers SEO-preserving 301 redirects when a vendor renames their slug.
	 */
	public static function resolve_redirect_target(string $slug): string
	{
		$user_id = VendorSlugManager::find_user_by_history_slug($slug);
		if ($user_id <= 0) {
			return '';
		}

		$status = (string) get_user_meta($user_id, '_rentiva_vendor_status', true);
		if ($status !== 'active') {
			return '';
		}

		$current_slug = (string) get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true);
		if ($current_slug === '') {
			return '';
		}

		return VendorProfileUrlBase::url_for_slug($current_slug);
	}
}
