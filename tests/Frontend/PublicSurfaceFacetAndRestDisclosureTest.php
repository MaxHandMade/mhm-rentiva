<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm;
use MHMRentiva\Admin\REST\Availability;
use WP_Ajax_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * The same sweep, continued: the surfaces that leak unpublished inventory
 * without ever naming a vehicle id in the request.
 *
 * The search sidebar was the widest of them. Its six facet queries scanned
 * `wp_postmeta` on the meta_key alone -- no join to `wp_posts`, so no post type
 * and no post status. They described the whole database rather than the
 * catalogue: a draft vehicle's brand appeared in the public brand list, and a
 * draft vehicle's price set the public price slider's maximum. No id guessing
 * required; the disclosure was rendered into every visitor's sidebar and then
 * cached for 24 hours.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::get_filter_options
 * @covers \MHMRentiva\Admin\REST\Availability::check
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm::ajax_get_vehicle_rating_list
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::get_testimonials_data
 */
final class PublicSurfaceFacetAndRestDisclosureTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private int $published_id;
	private int $draft_id;

	public function setUp(): void
	{
		parent::setUp();

		// See the sibling test: without registration the rating-list assertions
		// would pass because the action does not exist, not because it is gated.
		VehicleRatingForm::register();

		$this->published_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Published Runabout',
			)
		);
		$this->draft_id     = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'draft',
				'post_title'  => 'Secret Draft Hypercar',
			)
		);

		update_post_meta( $this->published_id, '_mhmrentiva_brand', 'PublicBrand' );
		update_post_meta( $this->published_id, '_mhmrentiva_price_per_day', 1000 );
		update_post_meta( $this->published_id, '_mhmrentiva_year', 2020 );
		update_post_meta( $this->published_id, '_mhmrentiva_status', 'active' );

		update_post_meta( $this->draft_id, '_mhmrentiva_brand', 'SecretBrand' );
		update_post_meta( $this->draft_id, '_mhmrentiva_price_per_day', 99999 );
		update_post_meta( $this->draft_id, '_mhmrentiva_year', 2099 );
		update_post_meta( $this->draft_id, '_mhmrentiva_status', 'active' );

		delete_transient( 'mhmrentiva_search_filters_v2' );
		wp_set_current_user( 0 );
	}

	public function tearDown(): void
	{
		delete_transient( 'mhmrentiva_search_filters_v2' );
		parent::tearDown();
	}

	// ------------------------------------------------- surface: search facets

	public function test_search_facets_exclude_unpublished_vehicles(): void
	{
		$facets = $this->filter_options();

		$this->assertNotContains( 'SecretBrand', (array) ( $facets['brands'] ?? array() ) );
		$this->assertLessThan(
			99999,
			(float) ( $facets['price_range']['max'] ?? $facets['max_price'] ?? 0 ),
			'A draft vehicle must not set the public price slider maximum.'
		);
		$this->assertLessThan(
			2099,
			(int) ( $facets['year_range']['max'] ?? $facets['max_year'] ?? 0 ),
			'A draft vehicle must not widen the public year range.'
		);
	}

	public function test_search_facets_still_describe_published_vehicles(): void
	{
		$facets = $this->filter_options();

		$this->assertContains(
			'PublicBrand',
			(array) ( $facets['brands'] ?? array() ),
			'Excluding drafts must not empty the facets -- that would be the fix breaking the feature.'
		);
		$this->assertSame(
			1000.0,
			(float) ( $facets['price_range']['max'] ?? $facets['max_price'] ?? 0 )
		);
	}

	// --------------------------------------------------- surface: REST route

	public function test_rest_availability_refuses_an_unpublished_vehicle(): void
	{
		$response = $this->dispatch_availability( $this->draft_id );
		$data     = $response->get_data();

		$this->assertFalse( $data['ok'] );
		$this->assertSame(
			'vehicle_not_found',
			$data['code'],
			'An unpublished vehicle must be indistinguishable from a nonexistent one, or the difference is an enumeration oracle.'
		);
		$this->assertArrayNotHasKey( 'price_per_day', $data );
	}

	public function test_rest_availability_still_prices_a_published_vehicle(): void
	{
		$response = $this->dispatch_availability( $this->published_id );
		$data     = $response->get_data();

		$this->assertTrue( $data['ok'], 'The route must still answer for published inventory.' );
		$this->assertSame( 1000.0, (float) $data['price_per_day'] );
	}

	public function test_rest_availability_answers_a_missing_id_the_same_way(): void
	{
		$data = $this->dispatch_availability( 987654 )->get_data();

		$this->assertSame( 'vehicle_not_found', $data['code'] );
	}

	// -------------------------------------------- surface: rating list AJAX

	public function test_rating_list_refuses_an_unpublished_vehicle(): void
	{
		$this->seed_approved_comment( $this->draft_id, 'Reviewer Rachel', 'Draft-only review body' );

		$response = $this->dispatch_rating_list( $this->draft_id );

		$this->assertFalse( $response['success'] );
		$this->assertStringNotContainsString( 'Reviewer Rachel', wp_json_encode( $response ) );
	}

	public function test_rating_list_refuses_a_post_that_is_not_a_vehicle(): void
	{
		// The parameter is called vehicle_id; nothing used to make it one.
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$this->seed_approved_comment( $page, 'Unrelated Ursula', 'Comment on an unrelated post' );

		$response = $this->dispatch_rating_list( $page );

		$this->assertFalse( $response['success'] );
		$this->assertStringNotContainsString( 'Unrelated Ursula', wp_json_encode( $response ) );
	}

	public function test_rating_list_still_serves_a_published_vehicles_reviews(): void
	{
		$this->seed_approved_comment( $this->published_id, 'Reviewer Rachel', 'Great car' );

		$response = $this->dispatch_rating_list( $this->published_id );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Reviewer Rachel', $response['data']['ratings'][0]['display_name'] );
	}

	// ------------------------------------------- surface: testimonials shape

	public function test_testimonials_payload_carries_no_customer_email_field(): void
	{
		/*
		 * Shape test, deliberately. The endpoint that serves this array is
		 * currently unreachable (its nonce action does not match the one any
		 * page mints), so a behavioural test would pass for the wrong reason and
		 * keep passing if that typo were "fixed". Asserting on the row shape is
		 * what actually holds: the field cannot come back without failing here.
		 */
		$booking_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $booking_id, '_mhmrentiva_customer_review', 'Lovely trip' );
		update_post_meta( $booking_id, '_mhmrentiva_customer_rating', 5 );
		update_post_meta( $booking_id, '_mhmrentiva_review_approved', '1' );
		update_post_meta( $booking_id, '_mhmrentiva_customer_name', 'Jane Doe' );
		update_post_meta( $booking_id, '_mhmrentiva_customer_email', 'jane@example.com' );
		update_post_meta( $booking_id, '_mhmrentiva_vehicle_id', $this->published_id );

		$rows = $this->invoke_testimonials_rows();

		$this->assertNotEmpty( $rows, 'Without a row, the absence assertion below proves nothing.' );
		foreach ( $rows as $row ) {
			$this->assertArrayNotHasKey( 'customer_email', $row );
		}
		$this->assertStringNotContainsString( 'jane@example.com', wp_json_encode( $rows ) );
	}

	public function test_testimonials_payload_still_carries_the_public_fields(): void
	{
		$booking_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $booking_id, '_mhmrentiva_customer_review', 'Lovely trip' );
		update_post_meta( $booking_id, '_mhmrentiva_customer_rating', 5 );
		update_post_meta( $booking_id, '_mhmrentiva_review_approved', '1' );
		update_post_meta( $booking_id, '_mhmrentiva_customer_name', 'Jane Doe' );
		update_post_meta( $booking_id, '_mhmrentiva_vehicle_id', $this->published_id );

		$rows = $this->invoke_testimonials_rows();

		$this->assertNotEmpty( $rows );
		$this->assertSame( 'Jane Doe', $rows[0]['customer_name'] );
		$this->assertSame( 'Lovely trip', $rows[0]['review'] );
	}

	// ------------------------------------------------------------- fixtures

	/**
	 * @return array<string, mixed>
	 */
	private function filter_options(): array
	{
		$method = new \ReflectionMethod( SearchResults::class, 'get_filter_options' );
		$method->setAccessible( true );

		return (array) $method->invoke( null, array() );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function invoke_testimonials_rows(): array
	{
		$method = new \ReflectionMethod(
			\MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::class,
			'get_booking_reviews'
		);
		$method->setAccessible( true );

		return array_values( (array) $method->invoke( null, array( 'limit' => 10 ) ) );
	}

	private function seed_approved_comment( int $post_id, string $author, string $body ): void
	{
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
				'comment_author'   => $author,
				'comment_content'  => $body,
			)
		);
		update_comment_meta( $comment_id, 'mhmrentiva_rating', 5 );
	}

	private function dispatch_availability( int $vehicle_id ): \WP_REST_Response
	{
		// Routes must be registered ON `rest_api_init`, not merely before a
		// dispatch -- core emits a doing_it_wrong otherwise, which this suite
		// promotes to a failure.
		global $wp_rest_server;
		add_action( 'rest_api_init', array( Availability::class, 'register_routes' ) );
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
		remove_action( 'rest_api_init', array( Availability::class, 'register_routes' ) );

		$request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/availability' );
		$request->set_param( 'vehicle_id', $vehicle_id );
		$request->set_param( 'pickup_date', gmdate( 'Y-m-20' ) );
		$request->set_param( 'pickup_time', '10:00' );
		$request->set_param( 'dropoff_date', gmdate( 'Y-m-22' ) );
		$request->set_param( 'dropoff_time', '10:00' );

		// The route's own permission_callback is nonce + rate limit, neither of
		// which is an authorization check; call the callback directly so the
		// test pins the callback's gate rather than the harness's nonce setup.
		return Availability::check( $request );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function dispatch_rating_list( int $vehicle_id ): array
	{
		$_POST = array(
			'action'     => 'mhmrentiva_get_vehicle_rating_list',
			'nonce'      => wp_create_nonce( 'mhmrentiva_rating_nonce' ),
			'vehicle_id' => (string) $vehicle_id,
		);
		$_REQUEST = $_POST;

		$ob_level = ob_get_level();

		try {
			$this->_handleAjax( 'mhmrentiva_get_vehicle_rating_list' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			unset( $e );
		} finally {
			// See the sibling test's note: the die handler closes one buffer
			// more than _handleAjax() opened, so restore in both directions.
			while ( ob_get_level() > $ob_level ) {
				ob_end_clean();
			}
			while ( ob_get_level() < $ob_level ) {
				ob_start();
			}
		}

		$response             = $this->decode_first_json( (string) $this->_last_response );
		$this->_last_response = '';

		return $response;
	}

	/**
	 * Decode the FIRST JSON payload in the buffered AJAX response.
	 *
	 * `_handleAjax()` fires both `wp_ajax_{action}` and
	 * `wp_ajax_nopriv_{action}`, and these handlers are registered on both -- so
	 * the buffer ends up holding two concatenated payloads. The second is an
	 * artifact of the harness, not of production: `wp_send_json_*()` calls
	 * `wp_die()`, which really exits in a live request but here only throws
	 * `WPAjaxDieContinueException`, which the handlers' own `catch ( Exception )`
	 * then swallows into a generic error payload on the replay.
	 *
	 * A real caller receives the first payload, so that is what these tests read.
	 * Brace-depth scanning (string- and escape-aware) rather than a `}{` split,
	 * because booking titles and messages legitimately contain braces.
	 *
	 * @return array<string, mixed>
	 */
	private function decode_first_json( string $buffer ): array
	{
		$depth     = 0;
		$in_string = false;
		$escaped   = false;

		for ( $i = 0, $len = strlen( $buffer ); $i < $len; $i++ ) {
			$char = $buffer[ $i ];

			if ( $in_string ) {
				if ( $escaped ) {
					$escaped = false;
				} elseif ( '\\' === $char ) {
					$escaped = true;
				} elseif ( '"' === $char ) {
					$in_string = false;
				}
				continue;
			}

			if ( '"' === $char ) {
				$in_string = true;
			} elseif ( '{' === $char ) {
				$depth++;
			} elseif ( '}' === $char ) {
				$depth--;
				if ( 0 === $depth ) {
					$decoded = json_decode( substr( $buffer, 0, $i + 1 ), true );
					return is_array( $decoded ) ? $decoded : array( 'success' => false );
				}
			}
		}

		return array( 'success' => false );
	}
}
