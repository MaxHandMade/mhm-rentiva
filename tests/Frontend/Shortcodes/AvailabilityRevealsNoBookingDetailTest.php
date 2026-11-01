<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_Ajax_UnitTestCase;

/**
 * The public calendar says whether a day is free, not who booked it.
 *
 * To colour a day the calendar needs one thing: the day's state. It once carried
 * more -- each day shipped an array of the bookings covering it, with the post
 * ID, the booking's title and its payment status -- through a nopriv response
 * and into the markup. These tests pin both halves on the endpoints that replaced
 * it (PR E): the state survives, the identity stays out.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::ajax_months
 */
final class AvailabilityRevealsNoBookingDetailTest extends WP_Ajax_UnitTestCase
{
	private const FAR_MONTH = '2099-03';

	private int $vehicle_id;
	private int $booking_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Detail Test Vehicle',
			)
		);

		$this->booking_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_title'  => 'Booking - Detail Test Vehicle',
			)
		);

		update_post_meta( $this->booking_id, '_mhmrentiva_vehicle_id', $this->vehicle_id );
		update_post_meta( $this->booking_id, '_mhmrentiva_start_date', self::FAR_MONTH . '-10' );
		update_post_meta( $this->booking_id, '_mhmrentiva_end_date', self::FAR_MONTH . '-20' );
		update_post_meta( $this->booking_id, '_mhmrentiva_start_ts', (int) strtotime( self::FAR_MONTH . '-10 10:00:00 UTC' ) );
		update_post_meta( $this->booking_id, '_mhmrentiva_end_ts', (int) strtotime( self::FAR_MONTH . '-20 10:00:00 UTC' ) );
		update_post_meta( $this->booking_id, '_mhmrentiva_status', 'confirmed' );
		update_post_meta( $this->booking_id, '_mhmrentiva_payment_status', 'paid' );

		wp_set_current_user( 0 );
	}

	private function months_response(): string
	{
		$_POST = array(
			'nonce'          => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id'     => (string) $this->vehicle_id,
			'start_month'    => self::FAR_MONTH,
			'months_to_show' => '1',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_calendar_months' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			unset( $e ); // wp_send_json_* terminates; the response is the check.
		}

		return (string) $this->_last_response;
	}

	public function test_the_public_response_still_marks_the_day_booked(): void
	{
		$html = (string) ( json_decode( $this->months_response(), true )['data']['html'] ?? '' );

		$this->assertMatchesRegularExpression(
			'/<td class="rvs-cal__day is-booked" data-date="' . self::FAR_MONTH . '-15"/',
			$html,
			'The calendar lost the state it exists to show.'
		);
	}

	public function test_the_public_response_carries_no_booking_identity(): void
	{
		$response = $this->months_response();

		$this->assertStringNotContainsString( 'Booking - Detail Test Vehicle', $response, 'A booking title reached an anonymous caller.' );
		$this->assertStringNotContainsString( 'payment_status', $response, 'Payment state reached an anonymous caller.' );
		$this->assertStringNotContainsString( '"id":' . $this->booking_id, $response, 'A booking post ID reached an anonymous caller.' );
	}

	public function test_the_quote_carries_no_booking_identity(): void
	{
		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id' => (string) $this->vehicle_id,
			'start_date' => self::FAR_MONTH . '-09',
			'end_date'   => self::FAR_MONTH . '-12',
		);
		try {
			$this->_handleAjax( 'mhmrentiva_calendar_quote' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$this->assertStringNotContainsString( 'Booking - Detail Test Vehicle', (string) $this->_last_response );
		$this->assertStringNotContainsString( (string) $this->booking_id . '"', (string) $this->_last_response );
	}

	public function test_the_rendered_markup_carries_no_booking_title(): void
	{
		$output = do_shortcode( '[rentiva_availability_calendar vehicle_id="' . $this->vehicle_id . '" start_month="' . self::FAR_MONTH . '"]' );

		$this->assertStringContainsString( 'is-booked', $output, 'The rendered calendar lost the booked state.' );
		$this->assertStringNotContainsString( 'Booking - Detail Test Vehicle', $output, 'A booking title was printed into the public calendar markup.' );
	}
}
