<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use WP_UnitTestCase;

/**
 * A page-size taken from an anonymous request must carry a ceiling.
 *
 * `wp_ajax_nopriv_mhm_rentiva_filter_results` reads `per_page` and passes it
 * straight to `posts_per_page`; the nonce it checks is printed on every public
 * search page, so it is not a barrier to anyone who can load the site. Without
 * an upper bound, `per_page=1000000` makes one request query and render the
 * entire catalogue — the request costs the visitor nothing and the server a
 * great deal.
 *
 * The forms that produce these values declare their own maxima (the Elementor
 * search widget declares max 100, the testimonials widget max 50), so the
 * ceiling is not a new policy — it is the one already advertised, enforced on
 * the side that can be trusted.
 *
 * @covers \MHMRentiva\Admin\Core\Security\VerifiedRequest::intRange
 */
final class PublicRequestBoundsTest extends WP_UnitTestCase
{
	public function test_int_range_clamps_above_the_maximum(): void
	{
		$req = VerifiedRequest::from( array( 'per_page' => '1000000' ) );

		$this->assertSame( 100, $req->intRange( 'per_page', 12, 1, 100 ) );
	}

	public function test_int_range_clamps_below_the_minimum(): void
	{
		$req = VerifiedRequest::from( array( 'per_page' => '0' ) );

		$this->assertSame( 1, $req->intRange( 'per_page', 12, 1, 100 ) );
	}

	public function test_int_range_passes_a_value_inside_the_range(): void
	{
		$req = VerifiedRequest::from( array( 'per_page' => '24' ) );

		$this->assertSame( 24, $req->intRange( 'per_page', 12, 1, 100 ) );
	}

	public function test_int_range_uses_the_fallback_when_the_key_is_absent(): void
	{
		$req = VerifiedRequest::from( array() );

		$this->assertSame( 12, $req->intRange( 'per_page', 12, 1, 100 ) );
	}

	/**
	 * An array where a scalar is expected must not slip through as 1.
	 */
	public function test_int_range_rejects_an_array(): void
	{
		$req = VerifiedRequest::from( array( 'per_page' => array( '5', '6' ) ) );

		$this->assertSame( 12, $req->intRange( 'per_page', 12, 1, 100 ) );
	}

	/**
	 * The search endpoint is the reason this exists: prove the value it hands to
	 * WP_Query is bounded.
	 */
	public function test_the_search_endpoint_bounds_its_page_size(): void
	{
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Admin/Frontend/Shortcodes/SearchResults.php'
		);

		$this->assertStringNotContainsString(
			"'results_per_page'     => \$req->int('per_page', 12),",
			$source,
			'The anonymous search endpoint still takes an unbounded page size.'
		);

		$this->assertStringContainsString(
			"intRange('per_page'",
			$source,
			'The anonymous search endpoint should clamp per_page to the range its form declares.'
		);
	}
}
