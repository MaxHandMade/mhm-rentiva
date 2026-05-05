<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Schema.org LocalBusiness JSON-LD output for vendor profile pages.
 *
 * Also re-points the canonical URL exposed by Yoast SEO and Rank Math to
 * the vendor's current public profile URL so SEO plugins do not contradict
 * the rewrite layer when a slug is changed.
 *
 * @since 4.37.0
 */
final class VendorProfileSchema
{
	/**
	 * Wire JSON-LD <head> emission and SEO-plugin canonical filters.
	 *
	 * Hooked at PHP_INT_MAX-10 so the vendor URL wins over both Yoast and
	 * Rank Math defaults but still leaves room for site owners to override
	 * with priority PHP_INT_MAX.
	 */
	public static function register(): void
	{
		add_action('wp_head', [ self::class, 'output_in_head' ], 5);
		add_filter('wpseo_canonical', [ self::class, 'filter_canonical' ], PHP_INT_MAX - 10);
		add_filter('rank_math/frontend/canonical', [ self::class, 'filter_canonical' ], PHP_INT_MAX - 10);
	}

	/**
	 * Build the JSON-LD data array for a vendor.
	 *
	 * Returns an empty array when the vendor is missing or inactive,
	 * letting callers cleanly skip the <script> tag emission.
	 *
	 * @return array<string,mixed>
	 */
	public static function build_for_user(int $user_id): array
	{
		$data = VendorProfileProvider::get_profile_data($user_id);
		if (empty($data)) {
			return [];
		}

		// LocalBusiness requires a usable URL — emitting JSON-LD without a
		// canonical URL produces invalid Rich Results. When the vendor has
		// no slug yet (mid-migration / freshly created), drop the schema
		// entirely rather than emit a malformed entity.
		$url = VendorProfileUrlBase::url_for_user($user_id);
		if ($url === '') {
			return [];
		}

		$json = [
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'name'     => (string) ( $data['display_name'] ?? '' ),
			'url'      => $url,
		];

		if (! empty($data['avatar_url'])) {
			$json['image'] = (string) $data['avatar_url'];
		}

		if (! empty($data['bio'])) {
			$description = wp_strip_all_tags((string) $data['bio']);
			if (mb_strlen($description) > 250) {
				$description = mb_substr($description, 0, 250) . '…';
			}
			$json['description'] = $description;
		}

		if (! empty($data['city'])) {
			$json['address'] = [
				'@type'           => 'PostalAddress',
				'addressLocality' => (string) $data['city'],
			];
		}

		$rating_count = isset($data['rating']['count']) ? (int) $data['rating']['count'] : 0;
		if ($rating_count > 0) {
			$rating_avg                = isset($data['rating']['average']) ? (float) $data['rating']['average'] : 0.0;
			$json['aggregateRating']   = [
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) number_format($rating_avg, 1, '.', ''),
				'reviewCount' => (string) $rating_count,
			];
		}

		return $json;
	}

	/**
	 * Re-point the canonical URL to the vendor's current public profile.
	 *
	 * On non-vendor requests the original canonical is preserved; if the
	 * resolved user has no current slug (e.g. mid-migration) we likewise
	 * fall through. Hooked on both `wpseo_canonical` and
	 * `rank_math/frontend/canonical`.
	 */
	public static function filter_canonical(string $canonical): string
	{
		$slug = (string) get_query_var(VendorProfileRewrite::QUERY_VAR);
		if ($slug === '') {
			return $canonical;
		}

		$user_id = VendorProfileProvider::lookup_by_slug($slug);
		if ($user_id <= 0) {
			return $canonical;
		}

		$current_slug = (string) get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true);
		if ($current_slug === '') {
			return $canonical;
		}

		return VendorProfileUrlBase::url_for_slug($current_slug);
	}

	/**
	 * Emit the JSON-LD <script> tag inside <head> when the current request
	 * resolves to a vendor profile page.
	 */
	public static function output_in_head(): void
	{
		$slug = (string) get_query_var(VendorProfileRewrite::QUERY_VAR);
		if ($slug === '') {
			return;
		}

		$user_id = VendorProfileProvider::lookup_by_slug($slug);
		if ($user_id <= 0) {
			return;
		}

		$json = self::build_for_user($user_id);
		if (empty($json)) {
			return;
		}

		// JSON_HEX_TAG hex-encodes `<` and `>` so a hostile vendor cannot
		// terminate the surrounding <script> via display_name / bio / city.
		// JSON_UNESCAPED_UNICODE keeps Turkish characters intact (TR is the
		// primary locale; \u-escaping every accented character would bloat
		// the payload and confuse Rich Results testing).
		echo "\n<script type=\"application/ld+json\">"
			. wp_json_encode($json, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
			. "</script>\n";
	}
}
