<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_Ajax_UnitTestCase;

/**
 * A public shortcode that shows five reviews must not load every review.
 *
 * Testimonials ran three unbounded reads per render: the booking reviews
 * (`posts_per_page => -1`), the count query (also `-1`, though it only ever
 * uses `found_posts`) and the vehicle comments (`number => 0`). The limit was
 * applied in PHP afterwards with array_slice, so the cost grew with the
 * booking history while the output stayed at five rows.
 *
 * The visible result must not change: same rows, same order, same total.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials
 */
final class TestimonialsQueriesAreBoundedTest extends WP_Ajax_UnitTestCase
{
	private const REVIEW_COUNT = 25;

	/** @var array<int, int> */
	private array $captured_page_sizes = array();

	public function setUp(): void
	{
		parent::setUp();

		$vehicle_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Reviewed Vehicle',
			)
		);

		for ( $i = 0; $i < self::REVIEW_COUNT; $i++ ) {
			$booking_id = self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_booking',
					'post_status' => 'publish',
					'post_title'  => 'Booking - Reviewed Vehicle',
				)
			);

			update_post_meta( $booking_id, '_mhmrentiva_vehicle_id', $vehicle_id );
			update_post_meta( $booking_id, '_mhmrentiva_customer_review', 'Review body number ' . $i );
			update_post_meta( $booking_id, '_mhmrentiva_review_approved', '1' );
			update_post_meta( $booking_id, '_mhmrentiva_customer_rating', 5 );
			update_post_meta( $booking_id, '_mhmrentiva_customer_name', 'Reviewer ' . $i );
		}

		$this->captured_page_sizes = array();
		add_action( 'pre_get_posts', array( $this, 'capture_booking_page_size' ) );

		wp_set_current_user( 0 );
	}

	public function tearDown(): void
	{
		remove_action( 'pre_get_posts', array( $this, 'capture_booking_page_size' ) );
		parent::tearDown();
	}

	public function capture_booking_page_size( \WP_Query $query ): void
	{
		if ( $query->get( 'post_type' ) === 'mhmrentiva_booking' ) {
			$this->captured_page_sizes[] = (int) $query->get( 'posts_per_page' );
		}
	}

	public function test_no_testimonials_query_asks_for_every_row(): void
	{
		do_shortcode( '[rentiva_testimonials limit="5"]' );

		$this->assertNotEmpty( $this->captured_page_sizes, 'No booking query ran; the probe measured nothing.' );
		$this->assertNotContains(
			-1,
			$this->captured_page_sizes,
			'A public testimonials render still asks the database for every booking review.'
		);
	}

	public function test_the_rendered_output_still_shows_the_requested_rows(): void
	{
		$output = do_shortcode( '[rentiva_testimonials limit="5"]' );

		$matches = preg_match_all( '/Review body number /', $output );

		$this->assertSame( 5, $matches, 'The shortcode should still render exactly the requested five reviews.' );
	}

	public function test_the_total_count_still_reports_every_review(): void
	{
		$_POST = array(
			'nonce' => wp_create_nonce( 'mhmrentiva_testimonials_nonce' ),
			'limit' => '5',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_load_testimonials' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the response is the check.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertSame(
			self::REVIEW_COUNT,
			(int) ( $response['data']['total_count'] ?? 0 ),
			'Bounding the queries must not change the reported total.'
		);
		$this->assertCount(
			5,
			$response['data']['testimonials'] ?? array(),
			'The endpoint should still return the requested page size.'
		);
	}
}
