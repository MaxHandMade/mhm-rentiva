<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_Ajax_UnitTestCase;

/**
 * The review surfaces publish only what a visitor is meant to read.
 *
 * Three defects of the same two classes the 6.0.6 round swept, found by
 * applying those classes to the review surfaces rather than only to the
 * calendar:
 *
 * 1. Every testimonial row carried `customer_email` -- the booking's contact
 *    address, or the comment author's -- and the nopriv
 *    `mhmrentiva_load_testimonials` endpoint returned those rows verbatim.
 *    Nothing rendered the field; it existed only on the wire.
 * 2. The testimonials slider resolved a vehicle's name without asking whether
 *    the vehicle is published, so a vehicle deliberately taken off the site
 *    kept being named through its reviews.
 * 3. `mhmrentiva_get_vehicle_rating_list` checked the post type but not the
 *    status, and read comments with no ceiling.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm
 */
final class PublicReviewSurfacesRevealNoPrivateDataTest extends WP_Ajax_UnitTestCase
{
	private int $published_vehicle;
	private int $draft_vehicle;

	public function setUp(): void
	{
		parent::setUp();

		$this->published_vehicle = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Published Cabriolet',
			)
		);

		$this->draft_vehicle = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'draft',
				'post_title'  => 'Withdrawn Limousine',
			)
		);

		wp_set_current_user( 0 );
	}

	private function create_review( int $vehicle_id, string $author, string $email ): int
	{
		return (int) self::factory()->comment->create(
			array(
				'comment_post_ID'      => $vehicle_id,
				'comment_approved'     => '1',
				'comment_author'       => $author,
				'comment_author_email' => $email,
				'comment_content'      => 'A perfectly ordinary review.',
			)
		);
	}

	public function test_the_testimonials_endpoint_does_not_publish_reviewer_email_addresses(): void
	{
		$this->create_review( $this->published_vehicle, 'Ada Lovelace', 'ada@example.test' );

		$_POST = array(
			'nonce' => wp_create_nonce( 'mhmrentiva_testimonials_nonce' ),
			'limit' => '5',
			'page'  => '1',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_load_testimonials' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the response is the check.
		}

		$this->assertStringNotContainsString(
			'ada@example.test',
			$this->_last_response,
			'The public testimonials endpoint published a reviewer e-mail address.'
		);
		$this->assertStringNotContainsString(
			'customer_email',
			$this->_last_response,
			'The public testimonials payload still carries a customer_email field.'
		);
	}

	public function test_the_testimonials_slider_does_not_name_an_unpublished_vehicle(): void
	{
		$this->create_review( $this->draft_vehicle, 'Grace Hopper', 'grace@example.test' );

		$output = do_shortcode( '[rentiva_testimonials limit="20"]' );

		$this->assertStringNotContainsString(
			'Withdrawn Limousine',
			$output,
			'A vehicle removed from the site was still named through its reviews.'
		);
	}

	public function test_the_rating_list_endpoint_refuses_an_unpublished_vehicle(): void
	{
		$this->create_review( $this->draft_vehicle, 'Grace Hopper', 'grace@example.test' );

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_rating_nonce' ),
			'vehicle_id' => (string) $this->draft_vehicle,
		);

		try {
			$this->_handleAjax( 'mhmrentiva_get_vehicle_rating_list' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// See above.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse(
			(bool) ( $response['success'] ?? false ),
			'The public rating list served an unpublished vehicle: ' . $this->_last_response
		);
		$this->assertStringNotContainsString( 'Grace Hopper', $this->_last_response );
	}

	public function test_the_rating_list_endpoint_still_serves_a_published_vehicle(): void
	{
		$this->create_review( $this->published_vehicle, 'Ada Lovelace', 'ada@example.test' );

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_rating_nonce' ),
			'vehicle_id' => (string) $this->published_vehicle,
		);

		try {
			$this->_handleAjax( 'mhmrentiva_get_vehicle_rating_list' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// See above.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue(
			(bool) ( $response['success'] ?? false ),
			'A published vehicle must still return its reviews: ' . $this->_last_response
		);
		$this->assertStringNotContainsString(
			'ada@example.test',
			$this->_last_response,
			'The rating list published a reviewer e-mail address.'
		);
	}

	public function test_the_rating_list_endpoint_is_bounded(): void
	{
		// More rows than the ceiling, so the assertion measures the bound rather
		// than passing for free: without a `number` argument get_comments()
		// returns all 60.
		for ( $i = 0; $i < 60; $i++ ) {
			$this->create_review( $this->published_vehicle, 'Reviewer ' . $i, 'r' . $i . '@example.test' );
		}

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_rating_nonce' ),
			'vehicle_id' => (string) $this->published_vehicle,
		);

		try {
			$this->_handleAjax( 'mhmrentiva_get_vehicle_rating_list' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// See above.
		}

		$response = json_decode( $this->_last_response, true );
		$returned = is_array( $response['data']['ratings'] ?? null ) ? count( $response['data']['ratings'] ) : -1;

		$this->assertGreaterThan( 0, $returned, 'The endpoint returned nothing at all: ' . $this->_last_response );
		$this->assertLessThanOrEqual(
			50,
			$returned,
			'The nopriv rating list is unbounded; it returned ' . $returned . ' rows.'
		);
	}
}
