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
 * 3. The rating list checked the post type but not the status, and read comments
 *    with no ceiling. Its endpoint is gone (PR F); the render path holds the rule now.
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
				'comment_type'         => 'review',
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

	public function test_the_rating_form_shows_no_review_of_an_unpublished_vehicle(): void
	{
		$this->create_review( $this->draft_vehicle, 'Grace Hopper', 'grace@example.test' );

		$output = do_shortcode( '[rentiva_vehicle_rating_form vehicle_id="' . $this->draft_vehicle . '"]' );

		$this->assertStringNotContainsString( 'Grace', $output, 'The public rating list served an unpublished vehicle.' );
		$this->assertStringNotContainsString( 'A perfectly ordinary review.', $output );
	}

	public function test_the_rating_form_still_shows_a_published_vehicles_reviews(): void
	{
		$this->create_review( $this->published_vehicle, 'Ada Lovelace', 'ada@example.test' );

		$output = do_shortcode( '[rentiva_vehicle_rating_form vehicle_id="' . $this->published_vehicle . '"]' );

		$this->assertStringContainsString( 'Ada L.', $output, 'A published vehicle must still show its reviews.' );
		$this->assertStringNotContainsString( 'Lovelace', $output, 'The full name of a reviewer is not public.' );
		$this->assertStringNotContainsString( 'ada@example.test', $output, 'The rating list published a reviewer e-mail address.' );
	}

	public function test_the_rating_form_list_is_bounded(): void
	{
		// More rows than the ceiling, so the assertion measures the bound.
		for ( $i = 0; $i < 60; $i++ ) {
			$this->create_review( $this->published_vehicle, 'Reviewer ' . $i, 'r' . $i . '@example.test' );
		}

		$output = do_shortcode( '[rentiva_vehicle_rating_form vehicle_id="' . $this->published_vehicle . '"]' );
		$cards  = substr_count( $output, '<article class="rvs-review' );

		$this->assertGreaterThan( 0, $cards, 'The list rendered nothing at all.' );
		$this->assertLessThanOrEqual( 50, $cards, 'The public list is unbounded; it drew ' . $cards . ' cards.' );
	}
}
