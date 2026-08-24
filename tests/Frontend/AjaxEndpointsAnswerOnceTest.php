<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\SecurityHelper;
use MHMRentiva\Admin\Emails\Ajax\EmailAjaxHandler;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle;
use WP_Ajax_UnitTestCase;

/**
 * An AJAX endpoint must write exactly one JSON document.
 *
 * `wp_send_json_*` ends the request through `wp_die()`. Called from inside a
 * `try` whose `catch` takes `Exception` or `Throwable`, that terminator is
 * swallowed and the catch appends a second, contradictory document after the
 * first. In production `wp_die()` really dies, so visitors never see it -- the
 * cost is that these endpoints cannot be measured at all: a test asking what
 * the endpoint answered gets a body that does not parse.
 *
 * That is not theoretical. It is why the booking-to-order sweep in this same
 * round had to be pinned at source level: with the refund endpoints unreadable,
 * there was no behavioural assertion to make.
 *
 * Two siblings already solved this by rethrowing the terminator when its class
 * name contains "WPAjaxDie" (CompareService, FavoritesService). That works but
 * makes production code branch on a test class name; the house pattern, used in
 * Testimonials in 6.0.6, is to keep guards and every `wp_send_json_*` OUTSIDE
 * the try and let the try wrap only the work that can actually throw.
 *
 * Scope: every AJAX endpoint in the class, `BookingForm` included. Its three
 * blocks (725, 1092, 1443 -- the largest was 348 lines around the booking WRITE
 * path) took a restructure rather than a move, so they got their own round and
 * their own browser verification, which this round performed.
 */
final class AjaxEndpointsAnswerOnceTest extends WP_Ajax_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();

		// The plugin's hooks are not registered in this environment, so a test
		// that goes through _handleAjax has to register the chain it measures.
		EmailAjaxHandler::register();
		AccountController::register();
		SearchResults::register();
		BookingForm::register();
	}

	/**
	 * @return array<string, mixed>|null null when the body is not one document
	 */
	private function sole_json_document(): ?array
	{
		$decoded = json_decode( $this->_last_response, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * One parseable document AND the refusal this test names.
	 *
	 * The document count alone is a weak lock: if a fixture stops reaching the
	 * branch it was written for -- a raised rate limit, a validator that refuses
	 * earlier -- some other guard answers, the body still parses, and the test
	 * stays green while measuring nothing. Pinning the message makes that drift
	 * fail loudly instead.
	 */
	private function assertAnsweredOnceWith( string $expected_message, string $endpoint ): void
	{
		$document = $this->sole_json_document();

		$this->assertNotNull(
			$document,
			$endpoint . ' wrote more than one JSON document: ' . $this->_last_response
		);

		$this->assertSame(
			$expected_message,
			$document['data']['message'] ?? null,
			$endpoint . ' answered once, but not from the branch this test names'
		);
	}

	private function dispatch( string $action ): void
	{
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the body is the assertion.
		}
	}

	public function test_account_update_answers_once(): void
	{
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'customer' ) ) );

		// No valid nonce: the endpoint's own security guard is the branch under
		// test. It used to sit inside the try, which is what produced the second
		// document; this case is what keeps it outside.
		$_POST = array( 'nonce' => 'not-a-real-nonce' );

		$this->dispatch( 'mhmrentiva_update_account' );

		$this->assertNotNull(
			$this->sole_json_document(),
			'The account endpoint wrote more than one JSON document: ' . $this->_last_response
		);
	}

	public function test_email_preview_answers_once(): void
	{
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			'nonce'        => wp_create_nonce( 'mhmrentiva_email_preview_action' ),
			'booking_id'   => (string) self::factory()->post->create( array( 'post_type' => 'post' ) ),
			'template_key' => 'booking_confirmed',
		);

		$this->dispatch( 'mhmrentiva_preview_email_ajax' );

		$this->assertNotNull(
			$this->sole_json_document(),
			'The email preview endpoint wrote more than one JSON document: ' . $this->_last_response
		);
	}

	public function test_test_email_answers_once(): void
	{
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			'nonce'        => wp_create_nonce( 'mhmrentiva_send_template_test' ),
			'template_key' => 'booking_confirmed',
			'to'           => 'someone@example.test',
		);

		$this->dispatch( 'mhmrentiva_send_test_email_ajax' );

		$this->assertNotNull(
			$this->sole_json_document(),
			'The test-email endpoint wrote more than one JSON document: ' . $this->_last_response
		);
	}

	public function test_search_filter_answers_once(): void
	{
		wp_set_current_user( 0 );

		$_POST = array(
			'nonce'    => wp_create_nonce( 'mhmrentiva_search_results' ),
			'per_page' => '12',
		);

		$this->dispatch( 'mhmrentiva_filter_results' );

		$this->assertNotNull(
			$this->sole_json_document(),
			'The search filter endpoint wrote more than one JSON document: ' . $this->_last_response
		);
	}

	/**
	 * All three BookingForm endpoints share one nonce, so each gets the same
	 * first case: the guard that used to fire from inside the try.
	 */
	public function test_booking_submit_answers_once_when_the_nonce_is_invalid(): void
	{
		wp_set_current_user( 0 );

		$_POST = array( 'nonce' => 'not-a-real-nonce' );

		$this->dispatch( 'mhmrentiva_booking_form' );

		$this->assertAnsweredOnceWith( 'Security check failed.', 'The booking submit endpoint' );
	}

	/**
	 * A refusal raised deep in the body, not by the opening guard: the vehicle
	 * resolves and is publicly readable, but its status is not active.
	 */
	public function test_booking_submit_answers_once_when_the_vehicle_is_not_active(): void
	{
		wp_set_current_user( 0 );

		$vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => Vehicle::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $vehicle_id, MetaKeys::VEHICLE_STATUS, 'maintenance' );

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_booking_form_nonce' ),
			'vehicle_id' => (string) $vehicle_id,
		);

		$this->dispatch( 'mhmrentiva_booking_form' );

		$this->assertAnsweredOnceWith(
			'Selected vehicle is not available (Status: maintenance).',
			'The booking submit endpoint'
		);
	}

	public function test_calculate_price_answers_once_when_the_nonce_is_invalid(): void
	{
		wp_set_current_user( 0 );

		$_POST = array( 'nonce' => 'not-a-real-nonce' );

		$this->dispatch( 'mhmrentiva_calculate_price' );

		$this->assertAnsweredOnceWith( 'Security check failed.', 'The price endpoint' );
	}

	/**
	 * The refusal here is written by SecurityHelper rather than by the endpoint
	 * itself -- a second place the terminator was being swallowed, and one no
	 * amount of reading the endpoint's own wp_send_json_* calls would show.
	 */
	public function test_calculate_price_answers_once_when_the_rate_limit_is_exhausted(): void
	{
		wp_set_current_user( 0 );

		for ( $i = 0; $i < 30; $i++ ) {
			SecurityHelper::check_rate_limit( 'price_calculation', 30, 60 );
		}

		$_POST = array( 'nonce' => wp_create_nonce( 'mhmrentiva_booking_form_nonce' ) );

		$this->dispatch( 'mhmrentiva_calculate_price' );

		$this->assertAnsweredOnceWith(
			'Too many price calculation requests. Please wait.',
			'The price endpoint'
		);
	}

	public function test_check_availability_answers_once_when_the_nonce_is_invalid(): void
	{
		wp_set_current_user( 0 );

		$_POST = array( 'nonce' => 'not-a-real-nonce' );

		$this->dispatch( 'mhmrentiva_check_availability' );

		$this->assertAnsweredOnceWith( 'Security check failed.', 'The availability endpoint' );
	}

	public function test_check_availability_answers_once_when_the_rate_limit_is_exhausted(): void
	{
		wp_set_current_user( 0 );

		for ( $i = 0; $i < 20; $i++ ) {
			SecurityHelper::check_rate_limit( 'availability_check', 20, 300 );
		}

		$_POST = array( 'nonce' => wp_create_nonce( 'mhmrentiva_booking_form_nonce' ) );

		$this->dispatch( 'mhmrentiva_check_availability' );

		$this->assertAnsweredOnceWith(
			'Too many availability checks. Please wait.',
			'The availability endpoint'
		);
	}
}
