<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Emails\Ajax\EmailAjaxHandler;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
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
 * Scope: the four blocks whose fix is a move rather than a restructure.
 * `BookingForm` carries three more (725, 1092, 1443 -- the largest is 348 lines
 * around the booking WRITE path) and they are deliberately left for their own
 * round with their own browser verification.
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
	}

	/**
	 * @return array<string, mixed>|null null when the body is not one document
	 */
	private function sole_json_document(): ?array
	{
		$decoded = json_decode( $this->_last_response, true );

		return is_array( $decoded ) ? $decoded : null;
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
}
