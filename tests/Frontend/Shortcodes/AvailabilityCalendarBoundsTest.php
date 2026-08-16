<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_Ajax_UnitTestCase;

/**
 * The availability endpoint must bound the month span it renders.
 *
 * `wp_ajax_nopriv_mhmrentiva_availability_unified` reads `months_to_show` and
 * hands it to two loops -- the day-by-day availability walk and the calendar
 * pricing walk -- and the value also becomes part of the cache key. The nonce
 * it checks is printed on every page carrying the calendar, so it is not a
 * barrier to anyone who can load the site. Without a ceiling one request can
 * ask for thousands of months.
 *
 * The ceiling is not a new policy: the calendar's own markup declares
 * `data-months-to-show` of 1 (shortcode default) to 3 (template fallback), and
 * the client walks further spans by moving `start_month`, not by widening the
 * request. Twelve months is a generous year-long cap on that advertised range.
 *
 * Sibling of PublicRequestBoundsTest, which pinned the same class down for the
 * search endpoint and left this one open.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::ajax_unified_availability
 */
final class AvailabilityCalendarBoundsTest extends WP_Ajax_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Bounds Test Vehicle',
			)
		);

		// The endpoint is nopriv; measure it as an anonymous visitor.
		wp_set_current_user( 0 );
	}

	/**
	 * The endpoint must answer once. Guarding wp_send_json_* with
	 * catch(Exception) swallows the terminator wp_die() raises and appends a
	 * second, contradictory document; a plain decode of the whole body is the
	 * assertion that only one answer was written.
	 */
	private function sole_json_document( string $raw ): ?array
	{
		return json_decode( $raw, true );
	}

	public function test_the_availability_endpoint_bounds_the_month_span(): void
	{
		$_POST = array(
			'nonce'          => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id'     => (string) $this->vehicle_id,
			'months_to_show' => '25',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_availability_unified' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the response assertion below is the check.
		}

		$response = $this->sole_json_document( $this->_last_response );

		$this->assertIsArray( $response, 'Endpoint returned no JSON.' );
		$this->assertTrue(
			(bool) ( $response['success'] ?? false ),
			'Endpoint failed outright: ' . $this->_last_response
		);
		$this->assertLessThanOrEqual(
			12,
			count( $response['data']['availability_data'] ?? array() ),
			'An anonymous request asked for 25 months and the endpoint built every one of them.'
		);
	}

	/**
	 * A malformed start month must not be fed to strtotime(), which reads it as
	 * false and renders January 1970.
	 */
	public function test_a_malformed_start_month_falls_back_to_the_current_month(): void
	{
		$_POST = array(
			'nonce'       => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id'  => (string) $this->vehicle_id,
			'start_month' => 'not-a-month',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_availability_unified' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// See above.
		}

		$response = $this->sole_json_document( $this->_last_response );
		$months   = array_keys( $response['data']['availability_data'] ?? array() );

		$this->assertSame(
			array( gmdate( 'Y-m' ) ),
			$months,
			'A malformed start_month should fall back to the current month.'
		);
	}

	/**
	 * A month span below the floor must not disable the calendar either.
	 */
	public function test_a_zero_month_span_falls_back_to_a_usable_calendar(): void
	{
		$_POST = array(
			'nonce'          => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id'     => (string) $this->vehicle_id,
			'months_to_show' => '0',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_availability_unified' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// See above.
		}

		$response = $this->sole_json_document( $this->_last_response );

		$this->assertGreaterThanOrEqual(
			1,
			count( $response['data']['availability_data'] ?? array() ),
			'A zero span should clamp up to one month, not render an empty calendar.'
		);
	}
}
