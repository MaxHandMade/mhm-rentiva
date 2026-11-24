<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Frontend\Account\AccountDashboardView;
use MHMRentiva\Admin\Frontend\Account\BookingTimeline;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use WP_UnitTestCase;

/**
 * The end a customer is shown can differ from the end the calendar keeps
 * (an add-on may hold the vehicle longer than the trip lasts). One neutral
 * filter lets it say which instant to print. With nobody listening every
 * screen prints exactly what it printed before the filter existed.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\BookingTimeline::planned
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountDashboardView::return_text
 * @covers \MHMRentiva\Admin\Emails\Core\Mailer::getBookingContext
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::get_item_data
 */
final class BookingShownEndFilterTest extends WP_UnitTestCase
{
	private const FILTER = 'mhmrentiva_booking_shown_end';

	public function tearDown(): void
	{
		remove_all_filters( self::FILTER );
		update_option( 'date_format', 'F j, Y' );
		update_option( 'time_format', 'g:i a' );
		parent::tearDown();
	}

	private function booking( array $meta = array() ): int
	{
		$end = (int) SiteTime::instant( '2026-11-23', '17:00' );

		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'meta_input'  => $meta + array(
					'_mhmrentiva_pickup_date'  => '2026-11-20',
					'_mhmrentiva_pickup_time'  => '09:30',
					'_mhmrentiva_start_time'   => '09:30',
					'_mhmrentiva_dropoff_date' => '2026-11-23',
					'_mhmrentiva_dropoff_time' => '17:00',
					'_mhmrentiva_end_time'     => '17:00',
					'_mhmrentiva_start_ts'     => (int) SiteTime::instant( '2026-11-20', '09:30' ),
					'_mhmrentiva_end_ts'       => $end,
				),
			)
		);
	}

	private function shown_end( int $ts ): void
	{
		add_filter( self::FILTER, static fn (): int => $ts );
	}

	private function formats(): void
	{
		update_option( 'date_format', 'd.m.Y' );
		update_option( 'time_format', 'H:i' );
	}

	public function test_timeline_return_unchanged_without_subscribers(): void
	{
		$booking_id = $this->booking();

		$this->assertSame( SiteTime::instant( '2026-11-23', '17:00' ), BookingTimeline::planned( $booking_id )['return'] );
	}

	public function test_timeline_return_uses_the_filtered_instant(): void
	{
		$booking_id = $this->booking();
		$shown      = (int) SiteTime::instant( '2026-11-23', '16:00' );
		$received   = array();
		add_filter(
			self::FILTER,
			static function ( $end, $id ) use ( &$received, $shown ) {
				$received[] = array( $end, $id );
				return $shown;
			},
			10,
			2
		);

		$this->assertSame( $shown, BookingTimeline::planned( $booking_id )['return'] );
		$this->assertSame( array( array( SiteTime::instant( '2026-11-23', '17:00' ), $booking_id ) ), $received );
	}

	/**
	 * A subscriber replaces the end; it cannot hide it. A non-instant or a
	 * null answer keeps the stored end in every customer view alike.
	 *
	 * @dataProvider not_an_instant
	 */
	public function test_a_non_instant_keeps_the_stored_end_everywhere( $answer ): void
	{
		$booking_id = $this->booking();
		$end        = (int) SiteTime::instant( '2026-11-23', '17:00' );
		add_filter( self::FILTER, static fn () => $answer );

		$this->assertSame( $end, BookingTimeline::planned( $booking_id )['return'] );
		$this->assertSame( BookingTimeline::moment_text( $end ), AccountDashboardView::return_text( $booking_id ) );
	}

	public static function not_an_instant(): array
	{
		return array(
			'null'     => array( null ),
			'string'   => array( 'tomorrow' ),
			'zero'     => array( 0 ),
			'negative' => array( -5 ),
		);
	}

	public function test_dashboard_return_text_unchanged_without_subscribers(): void
	{
		$booking_id = $this->booking();
		$end        = (int) SiteTime::instant( '2026-11-23', '17:00' );

		$this->assertSame( BookingTimeline::moment_text( $end ), AccountDashboardView::return_text( $booking_id ) );
	}

	public function test_dashboard_return_text_uses_the_filtered_instant(): void
	{
		$booking_id = $this->booking();
		$shown      = (int) SiteTime::instant( '2026-11-23', '16:00' );
		$this->shown_end( $shown );

		$this->assertSame( BookingTimeline::moment_text( $shown ), AccountDashboardView::return_text( $booking_id ) );
	}

	public function test_email_return_values_unchanged_without_subscribers(): void
	{
		$this->formats();
		$booking = Mailer::getBookingContext( $this->booking() )['booking'];

		$this->assertSame( '2026-11-23', $booking['return_date_raw'] );
		$this->assertSame( '17:00', $booking['return_time_raw'] );
		$this->assertSame( '23.11.2026', $booking['return_date'] );
		$this->assertSame( '17:00', $booking['return_time'] );
		$this->assertSame( '23.11.2026, 17:00', $booking['return_datetime'] );
	}

	public function test_email_return_values_unchanged_when_the_subscriber_keeps_the_end(): void
	{
		$this->formats();
		$plain = Mailer::getBookingContext( $this->booking() )['booking'];
		add_filter( self::FILTER, static fn ( $end ) => $end );
		$kept = Mailer::getBookingContext( $this->booking() )['booking'];

		foreach ( array( 'return_date_raw', 'return_time_raw', 'return_date', 'return_time', 'return_datetime' ) as $key ) {
			$this->assertSame( $plain[ $key ], $kept[ $key ], $key );
		}
	}

	public function test_email_return_values_rebuilt_from_the_filtered_instant(): void
	{
		$this->formats();
		$this->shown_end( (int) SiteTime::instant( '2026-11-24', '08:05' ) );
		$booking = Mailer::getBookingContext( $this->booking() )['booking'];

		$this->assertSame( '2026-11-24', $booking['return_date_raw'] );
		$this->assertSame( '08:05', $booking['return_time_raw'] );
		$this->assertSame( '24.11.2026', $booking['return_date'] );
		$this->assertSame( '08:05', $booking['return_time'] );
		$this->assertSame( '24.11.2026, 08:05', $booking['return_datetime'] );
	}

	/**
	 * @return list<string>
	 */
	private function cart_keys( int $booking_id ): array
	{
		$rows = WooCommerceBridge::get_item_data( array(), array( 'mhmrentiva_booking_id' => $booking_id ) );

		return array_map( static fn ( array $row ): string => wp_strip_all_tags( html_entity_decode( (string) $row['key'] ) ), $rows );
	}

	public function test_saved_booking_cart_line_keeps_the_return_row_for_a_rental(): void
	{
		$this->assertContains( 'Return Date & Time', $this->cart_keys( $this->booking() ) );
	}

	public function test_saved_booking_cart_line_has_no_return_row_for_a_transfer(): void
	{
		$booking_id = $this->booking( array( '_mhmrentiva_transfer_origin_id' => 7 ) );
		$keys       = $this->cart_keys( $booking_id );

		$this->assertContains( 'Pickup Date & Time', $keys );
		$this->assertNotContains( 'Return Date & Time', $keys );
	}
}
