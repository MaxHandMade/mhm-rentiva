<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Directory;

use MHMRentiva\Admin\Licensing\Mode;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registers the `/{base}/` rewrite rule and dispatches requests for the
 * Vendor Directory page.
 *
 * Pro-gated: if `Mode::canUseVendorMarketplace()` is false, the rewrite is
 * still registered (so we can serve a deterministic 404 on `template_redirect`)
 * but `handle_request()` short-circuits to 404 — Lite users never see render.
 *
 * @since 4.38.0
 */
final class VendorDirectoryRewrite
{
	public const QUERY_VAR = 'mhm_rentiva_vendor_directory';

	public static function register(): void
	{
		add_action('init', [self::class, 'add_rewrite_rule']);
		add_filter('query_vars', [self::class, 'add_query_var']);
		add_action('template_redirect', [self::class, 'handle_request'], 10);

		// Idempotent self-registration if Plugin.php loads us late
		// (e.g., test bootstrap fires init before this register() runs).
		if (did_action('init') > 0) {
			self::add_rewrite_rule();
		}
	}

	public static function add_rewrite_rule(): void
	{
		$base = VendorDirectoryUrlBase::resolve();
		if ($base === '') {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote($base, '/') . '/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public static function add_query_var(array $vars): array
	{
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public static function handle_request(): void
	{
		$flag = get_query_var(self::QUERY_VAR);
		if ($flag === '' || $flag === null) {
			return;
		}

		// Two-layer Pro gate (dispatch-time + render-time).
		// This is dispatch-time; defense-in-depth in case Pro lapses between
		// rewrite cache build and request.
		if (!Mode::canUseVendorMarketplace()) {
			global $wp_query;
			if ($wp_query instanceof \WP_Query) {
				$wp_query->set_404();
			}
			status_header(404);
			return;
		}

		// Pro user — clear 404/home/singular/archive flags so template_include
		// can serve our wrapper template without theme hierarchy interference
		// (Phase 9 dispatch gap fix from v4.37.0 — VendorProfileRewrite parity).
		global $wp_query;
		if ($wp_query instanceof \WP_Query) {
			$wp_query->is_404      = false;
			$wp_query->is_home     = false;
			$wp_query->is_singular = false;
			$wp_query->is_archive  = false;
		}
		status_header(200);
	}
}
