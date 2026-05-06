<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Directory;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Page title + meta description override for the Vendor Directory page.
 *
 * Inert if any major SEO plugin is active (Yoast / Rank Math / AIOSEO /
 * SEOPress / The SEO Framework / SmartCrawl). Mirrors the SEO probe set
 * used by VendorDirectorySchema (post-Phase 4 reviewer fix — both constant
 * AND class detection).
 *
 * @since 4.38.0
 */
final class VendorDirectorySeo
{
	/**
	 * Wires SEO output hooks unless an SEO plugin owns the contract or the
	 * site owner explicitly disabled emission. Mirrors Profile's register
	 * guard pattern (v4.38.1 paper-cut Phase 5: parity with VendorProfileSeo).
	 *
	 * `should_emit()` is also called in each hook callback as a defensive
	 * second layer because SEO plugins may load AFTER our `register()` runs.
	 */
	public static function register(): void
	{
		if (!self::should_emit()) {
			return;
		}

		add_filter('document_title_parts', [self::class, 'filter_title'], 10);
		add_action('wp_head', [self::class, 'output_meta_description'], 1);
	}

	public static function build_title(): string
	{
		$default = sprintf(
			/* translators: %s: site name */
			__('Vendors — %s', 'mhm-rentiva'),
			get_bloginfo('name')
		);

		return (string) apply_filters('mhm_rentiva_vendor_directory_page_title', $default);
	}

	/**
	 * Builds the default meta description for the directory page and runs it
	 * through the `mhm_rentiva_vendor_directory_meta_description` filter so
	 * site owners can override the copy without dropping the page entirely.
	 *
	 * Filter signature (3 arguments):
	 *
	 * ```php
	 * add_filter(
	 *     'mhm_rentiva_vendor_directory_meta_description',
	 *     function (string $default, int $vendor_count, int $vehicle_count): string {
	 *         return sprintf('Antalya Rent a Car Marketplace — %d vendors', $vendor_count);
	 *     },
	 *     10,
	 *     3
	 * );
	 * ```
	 *
	 * @param int $vendor_count  Number of active vendors site-wide.
	 * @param int $vehicle_count Number of active vehicles site-wide.
	 */
	public static function build_meta_description(int $vendor_count, int $vehicle_count): string
	{
		$default = sprintf(
			/* translators: 1: vendor count, 2: vehicle count */
			__('Discover all our vendors. %1$d vendors · %2$d vehicles', 'mhm-rentiva'),
			$vendor_count,
			$vehicle_count
		);

		return (string) apply_filters('mhm_rentiva_vendor_directory_meta_description', $default, $vendor_count, $vehicle_count);
	}

	/**
	 * @param array<string, string> $title_parts
	 * @return array<string, string>
	 */
	public static function filter_title(array $title_parts): array
	{
		if (!self::should_emit() || !self::is_directory_page()) {
			return $title_parts;
		}

		$title_parts['title'] = self::build_title();
		unset($title_parts['site']); // build_title already includes site name

		return $title_parts;
	}

	public static function output_meta_description(): void
	{
		if (!self::should_emit() || !self::is_directory_page()) {
			return;
		}

		// Plugin.php wires the actual counts via filter (Phase 9)
		$description = self::build_meta_description(0, 0);
		echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
	}

	private static function is_directory_page(): bool
	{
		$flag = (string) get_query_var(VendorDirectoryRewrite::QUERY_VAR);
		return $flag !== '';
	}

	private static function should_emit(): bool
	{
		if (apply_filters('mhm_rentiva_vendor_directory_seo_disable', false)) {
			return false;
		}

		$constant_probes = [
			'WPSEO_VERSION',
			'RANK_MATH_VERSION',
			'AIOSEO_VERSION',
			'SEOPRESS_VERSION',
			'THE_SEO_FRAMEWORK_VERSION',
			'SMARTCRAWL_VERSION',
		];
		foreach ($constant_probes as $constant) {
			if (defined($constant)) {
				return false;
			}
		}

		// Class probes — some plugins load class before constant
		$class_probes = [
			'WPSEO_Frontend',
			'RankMath',
			'AIOSEO\\Plugin\\AIOSEO',
			'Smartcrawl_Init',
		];
		foreach ($class_probes as $cls) {
			if (class_exists($cls)) {
				return false;
			}
		}

		return true;
	}
}
