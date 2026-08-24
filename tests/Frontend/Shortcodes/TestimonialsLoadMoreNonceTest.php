<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Testimonials;
use ReflectionMethod;
use WP_Ajax_UnitTestCase;

/**
 * The nonce the testimonials page prints must be the nonce its endpoint accepts.
 *
 * `AbstractShortcode::get_localized_data()` mints the token from the shortcode
 * tag -- `mhmrentiva_rentiva_testimonials_nonce` -- while the handler verifies
 * `mhmrentiva_testimonials_nonce`. The two names never meet, so "Load More"
 * failed closed for every visitor since the endpoint was written: the button
 * answers "Security check failed" and no further reviews are ever rendered.
 *
 * The test deliberately takes the token from the same producer the page uses
 * rather than writing the action name a second time. A test that spells the
 * action itself passes even when the two sides disagree -- it pins the string
 * it was given, not the handshake the visitor performs.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::ajax_load_testimonials
 */
final class TestimonialsLoadMoreNonceTest extends WP_Ajax_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();

		// The plugin's own hooks are not registered in this environment; a test
		// that leans on the hook chain has to register that chain itself, or it
		// reports a defect production does not have.
		Testimonials::register();

		// The endpoint is nopriv; measure it as an anonymous visitor.
		wp_set_current_user( 0 );
	}

	/**
	 * The token as the page hands it to JavaScript, taken from the producer
	 * rather than re-spelled here.
	 */
	private function nonce_the_page_prints(): string
	{
		$method = new ReflectionMethod( Testimonials::class, 'get_localized_data' );
		$method->setAccessible( true );

		$data = $method->invoke( null );

		$this->assertArrayHasKey( 'nonce', $data, 'The testimonials script is localised without a nonce.' );

		return (string) $data['nonce'];
	}

	public function test_the_endpoint_is_registered_for_anonymous_visitors(): void
	{
		$this->assertNotFalse(
			has_action( 'wp_ajax_nopriv_mhmrentiva_load_testimonials' ),
			'Load More is unreachable: the nopriv action is not registered.'
		);
	}

	public function test_the_nonce_the_page_prints_opens_the_load_more_endpoint(): void
	{
		$_POST = array(
			'nonce' => $this->nonce_the_page_prints(),
			'page'  => '1',
			'limit' => '5',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_load_testimonials' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the response below is the assertion.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response, 'Endpoint returned no JSON: ' . $this->_last_response );
		$this->assertTrue(
			(bool) ( $response['success'] ?? false ),
			'The nonce printed on the page was rejected by the endpoint: ' . $this->_last_response
		);
	}

	/**
	 * A token minted for a different surface must still be turned away -- the
	 * fix must make the two names agree, not drop the check.
	 */
	public function test_a_foreign_nonce_is_still_rejected(): void
	{
		$_POST = array(
			'nonce' => wp_create_nonce( 'mhmrentiva_some_other_surface_nonce' ),
			'page'  => '1',
			'limit' => '5',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_load_testimonials' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the response below is the assertion.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response, 'Endpoint returned no JSON: ' . $this->_last_response );
		$this->assertFalse(
			(bool) ( $response['success'] ?? false ),
			'A nonce minted for another surface opened the endpoint.'
		);
	}
}
