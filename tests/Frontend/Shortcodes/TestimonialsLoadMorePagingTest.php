<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Testimonials;
use WP_Ajax_UnitTestCase;

/**
 * "Load More" must load MORE, not the same page again.
 *
 * `ajax_load_testimonials()` reads `page` from the request and uses it for one
 * thing only -- computing `has_more` -- while the attributes it passes to
 * `get_testimonials()` carry limit, rating and vehicle_id and no offset. Every
 * click therefore re-fetched the first N reviews and appended them below the
 * ones already on screen.
 *
 * This survived unseen because the endpoint had never answered at all: its nonce
 * was split, so nobody ever got as far as a second click. Fixing the token in
 * this same round is what made the paging defect reachable, and a browser is
 * where it showed -- the gates were green and the endpoint answered
 * `success: true` with a well-formed payload the whole time.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::ajax_load_testimonials
 */
final class TestimonialsLoadMorePagingTest extends WP_Ajax_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();

		Testimonials::register();
		wp_set_current_user( 0 );

		// Four published vehicles, each with one approved review, dated apart so
		// the ordering is deterministic.
		for ( $i = 1; $i <= 4; $i++ ) {
			$vehicle_id = (int) self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_vehicle',
					'post_status' => 'publish',
					'post_title'  => 'Paging Vehicle ' . $i,
				)
			);

			$comment_id = (int) self::factory()->comment->create(
				array(
					'comment_post_ID'  => $vehicle_id,
					'comment_approved' => '1',
					'comment_author'   => 'Reviewer ' . $i,
					'comment_content'  => 'Review number ' . $i,
					'comment_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . $i . ' days' ) ),
				)
			);

			update_comment_meta( $comment_id, 'mhmrentiva_rating', 5 );
		}
	}

	/**
	 * @return list<string> the review texts the endpoint returned
	 */
	private function reviews_on_page( int $page, int $limit ): array
	{
		$_POST = array(
			'nonce' => wp_create_nonce( 'mhmrentiva_testimonials_nonce' ),
			'page'  => (string) $page,
			'limit' => (string) $limit,
		);

		try {
			$this->_handleAjax( 'mhmrentiva_load_testimonials' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the body is the assertion.
		}

		$decoded = json_decode( $this->_last_response, true );

		$this->assertIsArray( $decoded, 'Endpoint returned no JSON: ' . $this->_last_response );
		$this->assertTrue(
			(bool) ( $decoded['success'] ?? false ),
			'Endpoint refused the request: ' . $this->_last_response
		);

		$texts = array();

		foreach ( (array) ( $decoded['data']['testimonials'] ?? array() ) as $row ) {
			$texts[] = (string) ( $row['review'] ?? '' );
		}

		return $texts;
	}

	public function test_the_second_page_returns_different_reviews_than_the_first(): void
	{
		$first = $this->reviews_on_page( 1, 2 );

		$this->assertCount( 2, $first, 'The first page did not fill.' );

		// _last_response accumulates across dispatches within a test, so the
		// second call needs a clean slate to be read on its own.
		$this->_last_response = '';

		$second = $this->reviews_on_page( 2, 2 );

		$this->assertNotEmpty( $second, 'The second page came back empty.' );
		$this->assertSame(
			array(),
			array_intersect( $first, $second ),
			"Load More returned reviews already on screen.\n  page 1: " . implode( ' | ', $first )
			. "\n  page 2: " . implode( ' | ', $second )
		);
	}

	/**
	 * Negative control: paging must not silently invent rows beyond the end.
	 */
	public function test_a_page_past_the_end_is_empty(): void
	{
		$this->assertSame( array(), $this->reviews_on_page( 9, 2 ) );
	}
}
