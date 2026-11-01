<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\CalendarView;
use WP_Ajax_UnitTestCase;

/**
 * The months endpoint must bound the month span it renders.
 *
 * `wp_ajax_nopriv_mhmrentiva_calendar_months` reads `months_to_show` and walks
 * that many grids. The nonce it checks is printed on every page carrying the
 * calendar, so it is not a barrier to anyone who can load the site. Without a
 * ceiling one request can ask for thousands of months. Twelve is the attribute's
 * own largest value (R9).
 *
 * Sibling of PublicRequestBoundsTest, which pinned the same class down for the
 * search endpoint.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::ajax_months
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
	 * A plain decode of the whole body is the assertion that only one answer was
	 * written (see AjaxEndpointsAnswerOnceTest).
	 *
	 * @param array<string, string> $post
	 * @return array<string, mixed>
	 */
	private function months( array $post ): array
	{
		$_POST = $post + array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id' => (string) $this->vehicle_id,
		);
		// One test may ask twice; the response buffer accumulates.
		$this->_last_response = '';

		try {
			$this->_handleAjax( 'mhmrentiva_calendar_months' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			unset( $e ); // wp_send_json_* terminates; the response below is the check.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'Endpoint returned no JSON.' );
		$this->assertTrue( (bool) ( $response['success'] ?? false ), 'Endpoint failed outright: ' . $this->_last_response );

		return (array) $response['data'];
	}

	public function test_the_months_endpoint_bounds_the_month_span(): void
	{
		$data = $this->months( array( 'months_to_show' => '25' ) );

		$this->assertSame(
			CalendarView::MAX_MONTH_SPAN,
			substr_count( (string) $data['html'], 'data-rvs-calendar' ),
			'An anonymous request asked for 25 months: twelve are drawn.'
		);
	}

	/**
	 * A malformed start month must not be fed to a date parser.
	 */
	public function test_a_malformed_start_month_falls_back_to_the_current_month(): void
	{
		$data = $this->months( array( 'start_month' => 'not-a-month' ) );

		$this->assertSame( CalendarView::current_month(), $data['start_month'] );
	}

	public function test_a_zero_month_span_falls_back_to_a_usable_calendar(): void
	{
		$data = $this->months( array( 'months_to_show' => '0' ) );

		$this->assertSame( 1, substr_count( (string) $data['html'], 'data-rvs-calendar' ) );
	}

	/**
	 * R9: no walking into the past; the answer tells the script to close the button.
	 */
	public function test_past_months_stay_closed(): void
	{
		$past = $this->months( array( 'start_month' => '2001-05' ) );
		$this->assertSame( CalendarView::current_month(), $past['start_month'] );
		$this->assertFalse( $past['prev_allowed'] );

		$future = $this->months( array( 'start_month' => '2099-05' ) );
		$this->assertSame( '2099-05', $future['start_month'] );
		$this->assertTrue( $future['prev_allowed'] );
	}

	/**
	 * R11, m5: a bucket of its own, 60 a minute.
	 */
	public function test_the_months_endpoint_is_rate_limited(): void
	{
		for ( $i = 0; $i < 60; $i++ ) {
			\MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit( 'calendar_months', 60, 60 );
		}

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id' => (string) $this->vehicle_id,
		);
		try {
			$this->_handleAjax( 'mhmrentiva_calendar_months' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( (bool) ( $response['success'] ?? true ) );
		$this->assertArrayNotHasKey( 'html', (array) $response['data'] );
	}
}
