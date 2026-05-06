<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Directory;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Emits ItemList + BreadcrumbList JSON-LD for the Vendor Directory page.
 *
 * Inert if any major SEO plugin is active (Yoast, Rank Math, AIOSEO,
 * SEOPress, The SEO Framework, SmartCrawl) — they emit their own
 * schemas and Google forbids duplicates.
 *
 * XSS hardening: all string fields encoded with
 * `JSON_HEX_TAG | JSON_UNESCAPED_UNICODE` so hostile `</script>` injections
 * are hex-encoded but Türkçe characters survive (Phase 8 reviewer lesson
 * from v4.37.0).
 *
 * @since 4.38.0
 */
final class VendorDirectorySchema
{
	public static function register(): void
	{
		add_action('wp_head', [self::class, 'output_in_head'], 5);
	}

	public static function output_in_head(): void
	{
		if (!self::should_emit()) {
			return;
		}

		$directory_flag = (string) get_query_var(VendorDirectoryRewrite::QUERY_VAR);
		if ($directory_flag === '') {
			return;
		}

		// Defer to render() with the actual vendor list — Plugin.php wires
		// the call passing the Provider's current page result (Phase 9).
		do_action('mhm_rentiva_vendor_directory_emit_schema');
	}

	/**
	 * @param array<int, array<string, mixed>> $vendors
	 * @return array<string, array<string, mixed>>
	 */
	public static function build(array $vendors): array
	{
		if ($vendors === []) {
			return [];
		}

		$items = [];
		$position = 1;
		foreach ($vendors as $vendor) {
			$url = (string) ($vendor['profile_url'] ?? '');
			if ($url === '') {
				continue;
			}
			// Skip non-http(s) URLs — javascript:/data: schemes are inert in JSON-LD
			// (Google doesn't follow them) but we keep the schema strictly clean.
			$scheme = parse_url($url, PHP_URL_SCHEME);
			if (!in_array($scheme, ['http', 'https'], true)) {
				continue;
			}
			$items[] = [
				'@type' => 'ListItem',
				'position' => $position++,
				'url' => $url,
			];
		}

		if ($items === []) {
			return [];
		}

		return [
			'ItemList' => [
				'@context' => 'https://schema.org',
				'@type' => 'ItemList',
				'itemListElement' => $items,
				'numberOfItems' => count($items),
			],
			'BreadcrumbList' => [
				'@context' => 'https://schema.org',
				'@type' => 'BreadcrumbList',
				'itemListElement' => [
					[
						'@type' => 'ListItem',
						'position' => 1,
						'name' => __('Home', 'mhm-rentiva'),
						'item' => home_url('/'),
					],
					[
						'@type' => 'ListItem',
						'position' => 2,
						'name' => __('Vendors', 'mhm-rentiva'),
						'item' => VendorDirectoryUrlBase::url_for_directory(),
					],
				],
			],
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $vendors
	 */
	public static function render(array $vendors): void
	{
		$schema = self::build($vendors);
		if ($schema === []) {
			return;
		}

		$flags = JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

		foreach ($schema as $block) {
			$json = wp_json_encode($block, $flags);
			if ($json === false) {
				continue;
			}
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD body is JSON-encoded with JSON_HEX_TAG flag for XSS hardening.
		}
	}

	private static function should_emit(): bool
	{
		if (apply_filters('mhm_rentiva_vendor_directory_seo_disable', false)) {
			return false;
		}

		// SEO plugin probe — class/constant detection only (no plugin path assumptions).
		$probes = [
			'WPSEO_VERSION',                         // Yoast SEO
			'RANK_MATH_VERSION',                     // Rank Math
			'AIOSEO_VERSION',                        // AIOSEO
			'SEOPRESS_VERSION',                      // SEOPress
			'THE_SEO_FRAMEWORK_VERSION',             // The SEO Framework
			'SMARTCRAWL_VERSION',                    // SmartCrawl
		];
		foreach ($probes as $constant) {
			if (defined($constant)) {
				return false;
			}
		}

		// Class probes (some plugins load class before constant — Yoast Free, SmartCrawl).
		// Mirror VendorProfileSeo::is_seo_plugin_active() class set for parity.
		$class_probes = [
			'WPSEO_Frontend',                  // Yoast SEO (free + premium)
			'RankMath',                        // Rank Math
			'AIOSEO\\Plugin\\AIOSEO',          // AIOSEO Pro namespaced
			'Smartcrawl_Init',                 // SmartCrawl (WPMU DEV)
		];
		foreach ($class_probes as $cls) {
			if (class_exists($cls)) {
				return false;
			}
		}

		return true;
	}
}
