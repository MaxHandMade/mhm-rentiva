<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\BookingPlaces;
use MHMRentiva\Admin\Frontend\Account\BookingTimeline;
use WP_UnitTestCase;

/**
 * Where a booking starts and ends, as the customer is shown it. A rental
 * starts and ends where the vehicle stands; a transfer runs from its origin
 * to its destination, named by whichever add-on knows the locations.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\BookingPlaces
 * @covers \MHMRentiva\Admin\Frontend\Account\BookingTimeline::for_booking
 */
final class BookingPlacesTest extends WP_UnitTestCase
{
	private const FILTER = 'mhmrentiva_location_by_id';

	public function tearDown(): void
	{
		remove_all_filters( self::FILTER );
		parent::tearDown();
	}

	private function booking( array $meta = array() ): int
	{
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'meta_input'  => $meta,
			)
		);
	}

	private function locations(): void
	{
		add_filter(
			self::FILTER,
			static function ( $found, int $id ) {
				$names = array(
					5 => 'Esenboğa Airport',
					9 => 'Kızılay Hotel',
				);

				return isset( $names[ $id ] ) ? (object) array( 'id' => $id, 'name' => $names[ $id ], 'city' => 'Ankara' ) : $found;
			},
			10,
			2
		);
	}

	public function test_transfer_runs_from_origin_to_destination(): void
	{
		$this->locations();
		$booking = $this->booking(
			array(
				'_mhmrentiva_transfer_origin_id'      => 5,
				'_mhmrentiva_transfer_destination_id' => 9,
			)
		);

		$places = BookingPlaces::for_booking( $booking );

		$this->assertTrue( $places['transfer'] );
		$this->assertSame( 'Esenboğa Airport', $places['pickup'] );
		$this->assertSame( 'Kızılay Hotel', $places['return'] );
		$this->assertSame( 'Esenboğa Airport → Kızılay Hotel', BookingPlaces::route_text( $places ) );
	}

	public function test_transfer_without_a_listener_names_no_place(): void
	{
		$booking = $this->booking(
			array(
				'_mhmrentiva_transfer_origin_id'      => 5,
				'_mhmrentiva_transfer_destination_id' => 9,
			)
		);

		$places = BookingPlaces::for_booking( $booking );

		$this->assertTrue( $places['transfer'] );
		$this->assertNull( $places['pickup'] );
		$this->assertNull( $places['return'] );
		$this->assertSame( '', BookingPlaces::route_text( $places ) );
	}

	public function test_transfer_with_one_known_end_keeps_that_end_only(): void
	{
		$this->locations();
		$booking = $this->booking(
			array(
				'_mhmrentiva_transfer_origin_id'      => 5,
				'_mhmrentiva_transfer_destination_id' => 77,
			)
		);

		$places = BookingPlaces::for_booking( $booking );

		$this->assertSame( 'Esenboğa Airport', $places['pickup'] );
		$this->assertNull( $places['return'] );
		$this->assertSame( '', BookingPlaces::route_text( $places ) );
	}

	private function detail_html( array $vehicle ): string
	{
		return (string) Templates::render(
			'partials/rvs/account-booking-detail',
			array(
				'booking_id' => 1,
				'number'     => '1',
				'vehicle'    => $vehicle + array(
					'name'   => 'Honda Civic',
					'image'  => '',
					'pickup' => '25 November 2026 · 12:35',
					'return' => '25 November 2026 · 13:05',
				),
			),
			true
		);
	}

	public function test_detail_shows_origin_and_destination_for_a_transfer(): void
	{
		$html = $this->detail_html(
			array(
				'transfer'     => true,
				'pickup_place' => 'Esenboğa Airport',
				'return_place' => 'Kızılay Hotel',
				'location'     => '',
			)
		);

		$this->assertStringContainsString( '<span>Esenboğa Airport</span>', $html );
		$this->assertStringContainsString( '<span>Kızılay Hotel</span>', $html );
		$this->assertStringContainsString( '<dt>Arrival</dt>', $html );
		$this->assertStringNotContainsString( '<dt>Return</dt>', $html );
	}

	public function test_detail_of_a_rental_keeps_one_place_and_return(): void
	{
		$html = $this->detail_html(
			array(
				'transfer'     => false,
				'pickup_place' => 'Downtown Office',
				'return_place' => 'Downtown Office',
				'location'     => 'Downtown Office',
			)
		);

		$this->assertSame( 2, substr_count( $html, '<span>Downtown Office</span>' ) );
		$this->assertStringContainsString( '<dt>Return</dt>', $html );
	}

	public function test_detail_from_an_older_caller_prints_its_location_twice(): void
	{
		$this->assertSame( 2, substr_count( $this->detail_html( array( 'location' => 'Old Office' ) ), '<span>Old Office</span>' ) );
	}

	public function test_timeline_names_the_end_of_a_transfer_arrival(): void
	{
		$booking = $this->booking(
			array(
				'_mhmrentiva_transfer_origin_id' => 5,
				'_mhmrentiva_status'             => 'confirmed',
			)
		);

		$labels = array_column( BookingTimeline::for_booking( $booking ), 'label', 'key' );

		$this->assertSame( 'Arrival', $labels['return'] );
	}

	public function test_timeline_of_a_rental_keeps_return(): void
	{
		$labels = array_column( BookingTimeline::for_booking( $this->booking( array( '_mhmrentiva_status' => 'confirmed' ) ) ), 'label', 'key' );

		$this->assertSame( 'Return', $labels['return'] );
	}

	public function test_rental_on_lite_alone_names_no_place(): void
	{
		$places = BookingPlaces::for_booking( $this->booking( array( '_mhmrentiva_vehicle_id' => 0 ) ) );

		$this->assertFalse( $places['transfer'] );
		$this->assertNull( $places['pickup'] );
		$this->assertNull( $places['return'] );
	}
}
