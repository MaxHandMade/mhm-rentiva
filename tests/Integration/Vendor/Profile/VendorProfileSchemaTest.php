<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileSchema;

/**
 * @group vendor-profile
 * @group vendor-schema
 */
final class VendorProfileSchemaTest extends \WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		VendorProfileSchema::register();
	}

	private function make_vendor_with_reviews(int $review_count, float $avg_rating): int
	{
		$user_id = self::factory()->user->create([ 'display_name' => 'Akif Otomotiv' ]);
		update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'akif');
		update_user_meta($user_id, '_rentiva_vendor_status', 'active');
		update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));
		update_user_meta($user_id, '_rentiva_vendor_city', 'Antalya');
		update_user_meta($user_id, '_rentiva_vendor_bio', 'Trusted local rental, est. 2018.');

		$vehicle_id = self::factory()->post->create([
			'post_type'   => 'vehicle',
			'post_author' => $user_id,
			'post_status' => 'publish',
		]);
		update_post_meta($vehicle_id, '_mhm_vehicle_lifecycle_status', 'active');
		update_post_meta($vehicle_id, '_mhm_rentiva_rating_average', $avg_rating);
		update_post_meta($vehicle_id, '_mhm_rentiva_rating_count', $review_count);

		return $user_id;
	}

	public function test_schema_includes_local_business_type_and_name(): void
	{
		$user_id = $this->make_vendor_with_reviews(15, 4.5);

		$json = VendorProfileSchema::build_for_user($user_id);

		$this->assertSame('https://schema.org', $json['@context']);
		$this->assertSame('LocalBusiness', $json['@type']);
		$this->assertSame('Akif Otomotiv', $json['name']);
	}

	public function test_schema_includes_aggregate_rating_when_reviews_exist(): void
	{
		$user_id = $this->make_vendor_with_reviews(15, 4.5);

		$json = VendorProfileSchema::build_for_user($user_id);

		$this->assertArrayHasKey('aggregateRating', $json);
		$this->assertSame('AggregateRating', $json['aggregateRating']['@type']);
		$this->assertEqualsWithDelta(4.5, (float) $json['aggregateRating']['ratingValue'], 0.01);
		$this->assertSame(15, (int) $json['aggregateRating']['reviewCount']);
	}

	public function test_schema_omits_aggregate_rating_when_zero_reviews(): void
	{
		$user_id = $this->make_vendor_with_reviews(0, 0.0);

		$json = VendorProfileSchema::build_for_user($user_id);

		$this->assertArrayNotHasKey('aggregateRating', $json);
	}

	public function test_canonical_filter_yoast_returns_vendor_url(): void
	{
		$this->make_vendor_with_reviews(5, 4.0);
		set_query_var(\MHMRentiva\Admin\Vendor\Profile\VendorProfileRewrite::QUERY_VAR, 'akif');

		$canonical = apply_filters('wpseo_canonical', 'http://something/wrong/');

		$this->assertStringEndsWith('/vendor/akif/', $canonical);
	}

	public function test_canonical_filter_rankmath_returns_vendor_url(): void
	{
		$this->make_vendor_with_reviews(5, 4.0);
		set_query_var(\MHMRentiva\Admin\Vendor\Profile\VendorProfileRewrite::QUERY_VAR, 'akif');

		$canonical = apply_filters('rank_math/frontend/canonical', 'http://something/wrong/');

		$this->assertStringEndsWith('/vendor/akif/', $canonical);
	}

	public function test_canonical_filter_passes_through_for_non_vendor_pages(): void
	{
		// No QUERY_VAR set — simulates a non-vendor page request.
		$original = 'http://example.com/some-other-page/';

		$canonical = apply_filters('wpseo_canonical', $original);

		$this->assertSame($original, $canonical);
	}

	/**
	 * Regression — reviewer-driven YÜKSEK 1.
	 *
	 * A hostile or careless display_name containing a literal `</script>`
	 * must NOT be able to terminate the JSON-LD <script> wrapper. The
	 * JSON_HEX_TAG flag is what enforces this; without it `</script>`
	 * passes through verbatim and breaks the page (potentially XSS).
	 */
	public function test_output_in_head_escapes_script_tag_in_display_name(): void
	{
		$user_id = self::factory()->user->create([
			'display_name' => 'Akif</script><script>alert(1)</script>',
		]);
		update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'akif');
		update_user_meta($user_id, '_rentiva_vendor_status', 'active');
		update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));
		set_query_var(\MHMRentiva\Admin\Vendor\Profile\VendorProfileRewrite::QUERY_VAR, 'akif');

		ob_start();
		VendorProfileSchema::output_in_head();
		$output = ob_get_clean();

		// Wrapper must close exactly once; injected `</script>` payload must be hex-encoded.
		$this->assertSame(1, substr_count($output, '</script>'));
		$this->assertStringNotContainsString('</script><script>alert(1)', $output);
		$this->assertStringContainsString('<', $output);
	}

	/**
	 * Regression — reviewer-driven ORTA 3.
	 *
	 * A vendor without a current slug (freshly approved before
	 * VendorSlugManager runs, or mid-migration) cannot have a usable
	 * canonical URL. build_for_user() must drop the schema rather than
	 * emit a LocalBusiness entity with an empty `url` field, which
	 * Google Rich Results flags as invalid.
	 */
	public function test_build_for_user_returns_empty_when_vendor_has_no_slug(): void
	{
		$user_id = self::factory()->user->create([ 'display_name' => 'Slugless Vendor' ]);
		// Activate the vendor but DO NOT assign a VENDOR_SLUG.
		update_user_meta($user_id, '_rentiva_vendor_status', 'active');
		update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));

		$json = VendorProfileSchema::build_for_user($user_id);

		$this->assertSame([], $json);
	}
}
