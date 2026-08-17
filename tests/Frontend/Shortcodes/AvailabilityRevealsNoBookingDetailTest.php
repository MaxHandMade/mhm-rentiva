<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_Ajax_UnitTestCase;

/**
 * The public calendar says whether a day is free, not who booked it.
 *
 * To colour a day the calendar needs one thing: the day's state. It was
 * carrying more -- each day shipped an array of the bookings covering it, with
 * the WordPress post ID, the booking's title and its payment status -- and that
 * array went out through the nopriv `mhmrentiva_availability_unified` response
 * and into the server-rendered markup as a tooltip and a per-booking `title`
 * attribute.
 *
 * Booking titles are composed as "Booking - <vehicle>", so this is not a name
 * or an address leak; it is internal record identity and payment state handed
 * to anyone who loads the page. The state the calendar draws from must survive
 * the change -- these tests pin both halves.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::ajax_unified_availability
 */
final class AvailabilityRevealsNoBookingDetailTest extends WP_Ajax_UnitTestCase
{
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

		$month = gmdate( 'Y-m' );
		update_post_meta( $this->booking_id, '_mhmrentiva_vehicle_id', $this->vehicle_id );
		update_post_meta( $this->booking_id, '_mhmrentiva_start_date', $month . '-10' );
		update_post_meta( $this->booking_id, '_mhmrentiva_end_date', $month . '-20' );
		update_post_meta( $this->booking_id, '_mhmrentiva_status', 'confirmed' );
		update_post_meta( $this->booking_id, '_mhmrentiva_payment_status', 'paid' );

		wp_set_current_user( 0 );
	}

	/**
	 * @return array<string, mixed> The day the booking covers.
	 */
	private function booked_day_from_endpoint(): array
	{
		$_POST = array(
			'nonce'          => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id'     => (string) $this->vehicle_id,
			'months_to_show' => '1',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_availability_unified' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the response is the check.
		}

		$response = json_decode( $this->_last_response, true );
		$month    = gmdate( 'Y-m' );

		return $response['data']['availability_data'][ $month ]['days'][ $month . '-15' ] ?? array();
	}

	public function test_the_public_response_still_marks_the_day_booked(): void
	{
		$day = $this->booked_day_from_endpoint();

		$this->assertSame( 'booked', $day['status'] ?? '', 'The calendar lost the state it exists to show.' );
		$this->assertSame( 1, $day['occupancy'] ?? 0, 'Occupancy must survive.' );
	}

	public function test_the_public_response_carries_no_booking_identity(): void
	{
		$day = $this->booked_day_from_endpoint();

		$this->assertArrayNotHasKey(
			'bookings',
			$day,
			'The public response still ships the booking records covering the day.'
		);
		$this->assertStringNotContainsString(
			'Booking - Detail Test Vehicle',
			$this->_last_response,
			'A booking title reached an anonymous caller.'
		);
		$this->assertStringNotContainsString(
			'payment_status',
			$this->_last_response,
			'Payment state reached an anonymous caller.'
		);
		$this->assertStringNotContainsString(
			'"id":' . $this->booking_id,
			$this->_last_response,
			'A booking post ID reached an anonymous caller.'
		);
	}

	public function test_the_rendered_markup_carries_no_booking_title(): void
	{
		$output = do_shortcode( '[rentiva_availability_calendar vehicle_id="' . $this->vehicle_id . '"]' );

		$this->assertStringContainsString(
			'rv-day-booked',
			$output,
			'The rendered calendar lost the booked state.'
		);
		$this->assertStringNotContainsString(
			'Booking - Detail Test Vehicle',
			$output,
			'A booking title was printed into the public calendar markup.'
		);
	}
}
