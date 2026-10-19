<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * An admin edit of a booking is the one booking-writing path that never asked
 * whether the vehicle is free. Moving a booking onto someone else's dates, or
 * re-confirming a cancelled booking whose dates have since been sold, simply
 * saved. Worse, both save handlers wrote the new dates but not
 * `_mhmrentiva_start_ts` / `_mhmrentiva_end_ts`, which the overlap query reads
 * first -- so after a move the vehicle still looked busy on the OLD dates and
 * free on the NEW ones, and the new dates could be sold again.
 *
 * The handlers are called directly and in hook order (prio 5, then 10): the
 * plugin's own hooks are not registered under PHPUnit.
 *
 * Locker::withLock() issues START TRANSACTION, which implicitly commits the
 * test's own transaction, so every post created here is deleted in tearDown().
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::guard_edit_availability
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::has_overlap_locked
 */
final class BookingEditAvailabilityTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	/** @var list<int> */
	private array $created = array();

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = $this->track(
			self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_vehicle',
					'post_status' => 'publish',
				)
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');

		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		BookingMeta::reset_edit_availability_verdicts();
		$_POST = array();
	}

	public function tearDown(): void
	{
		$_POST = array();
		foreach ($this->created as $post_id) {
			wp_delete_post($post_id, true);
		}
		BookingMeta::reset_edit_availability_verdicts();
		parent::tearDown();
	}

	private function track(int $post_id): int
	{
		$this->created[] = $post_id;

		return $post_id;
	}

	private function booking(string $status, string $pickup, string $dropoff): int
	{
		$range = Util::parse_datetimes($pickup, '10:00', $dropoff, '10:00');

		return $this->track(
			self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_booking',
					'post_status' => 'publish',
					'meta_input'  => array(
						'_mhmrentiva_vehicle_id'    => $this->vehicle_id,
						'_mhmrentiva_status'        => $status,
						'_mhmrentiva_payment_type'  => 'deposit',
						'_mhmrentiva_pickup_date'   => $pickup,
						'_mhmrentiva_dropoff_date'  => $dropoff,
						'_mhmrentiva_start_time'    => '10:00',
						'_mhmrentiva_end_time'      => '10:00',
						'_mhmrentiva_pickup_time'   => '10:00',
						'_mhmrentiva_dropoff_time'  => '10:00',
						'_mhmrentiva_start_ts'      => $range['start_ts'],
						'_mhmrentiva_end_ts'        => $range['end_ts'],
					),
				)
			)
		);
	}

	/**
	 * Run both save handlers the way one click on "Update" does.
	 *
	 * @param array<string, string> $fields
	 */
	private function admin_save(int $booking_id, array $fields): void
	{
		$_POST = array_merge(
			array(
				'_wpnonce'                      => wp_create_nonce('update-post_' . $booking_id),
				'mhmrentiva_edit_vehicle_id'    => (string) $this->vehicle_id,
				'mhmrentiva_edit_pickup_date'   => (string) get_post_meta($booking_id, '_mhmrentiva_pickup_date', true),
				'mhmrentiva_edit_pickup_time'   => '10:00',
				'mhmrentiva_edit_dropoff_date'  => (string) get_post_meta($booking_id, '_mhmrentiva_dropoff_date', true),
				'mhmrentiva_edit_dropoff_time'  => '10:00',
				'mhmrentiva_edit_status'        => (string) get_post_meta($booking_id, '_mhmrentiva_status', true),
				'mhmrentiva_edit_guests'        => '1',
				'mhmrentiva_edit_special_notes' => '',
			),
			$fields
		);

		BookingEditMetaBox::save_booking_details($booking_id);
		BookingMeta::save_meta($booking_id, get_post($booking_id));
	}

	private function busy(string $pickup, string $dropoff): bool
	{
		$range = Util::parse_datetimes($pickup, '10:00', $dropoff, '10:00');

		return Util::has_overlap($this->vehicle_id, $range['start_ts'], $range['end_ts']);
	}

	public function test_locked_overlap_can_exclude_the_booking_being_edited(): void
	{
		$booking_id = $this->booking('confirmed', '2027-03-10', '2027-03-13');
		$range      = Util::parse_datetimes('2027-03-10', '10:00', '2027-03-13', '10:00');

		$this->assertTrue(Util::has_overlap_locked($this->vehicle_id, $range['start_ts'], $range['end_ts']));
		$this->assertFalse(
			Util::has_overlap_locked($this->vehicle_id, $range['start_ts'], $range['end_ts'], $booking_id),
			'A booking must not conflict with itself.'
		);
	}

	public function test_moving_a_booking_to_free_dates_moves_its_occupancy_too(): void
	{
		$booking_id = $this->booking('confirmed', '2027-03-20', '2027-03-22');

		$this->admin_save(
			$booking_id,
			array(
				'mhmrentiva_edit_pickup_date'  => '2027-03-25',
				'mhmrentiva_edit_dropoff_date' => '2027-03-27',
			)
		);

		$this->assertSame('2027-03-25', get_post_meta($booking_id, '_mhmrentiva_pickup_date', true));
		$this->assertTrue($this->busy('2027-03-25', '2027-03-27'), 'The new dates must be occupied.');
		$this->assertFalse($this->busy('2027-03-20', '2027-03-22'), 'The old dates must be released.');
	}

	public function test_moving_a_booking_onto_an_occupied_range_is_refused(): void
	{
		$this->booking('confirmed', '2027-03-10', '2027-03-13');
		$moved = $this->booking('confirmed', '2027-03-20', '2027-03-22');

		$this->admin_save(
			$moved,
			array(
				'mhmrentiva_edit_pickup_date'  => '2027-03-11',
				'mhmrentiva_edit_dropoff_date' => '2027-03-14',
			)
		);

		$this->assertSame('2027-03-20', get_post_meta($moved, '_mhmrentiva_pickup_date', true), 'Neither handler may write the refused dates.');
		$this->assertSame('2027-03-22', get_post_meta($moved, '_mhmrentiva_dropoff_date', true));
		$this->assertTrue($this->busy('2027-03-20', '2027-03-22'), 'The booking keeps its original occupancy.');
		$this->assertFalse(BookingMeta::edit_availability_allowed($moved));

		$location = 'http://example.org/wp-admin/post.php?post=' . $moved . '&action=edit&message=1';
		$this->assertStringNotContainsString(
			'message=',
			apply_filters('redirect_post_location', $location, $moved),
			'Core must not also say "Booking updated." for a refused edit.'
		);
		$other = 'http://example.org/wp-admin/post.php?post=1&action=edit&message=1';
		$this->assertSame($other, apply_filters('redirect_post_location', $other, 1), 'Other posts keep their message.');
	}

	public function test_reconfirming_a_cancelled_booking_whose_dates_were_sold_is_refused(): void
	{
		$this->booking('confirmed', '2027-03-10', '2027-03-13');
		$cancelled = $this->booking('cancelled', '2027-03-11', '2027-03-14');

		$this->admin_save($cancelled, array( 'mhmrentiva_edit_status' => 'confirmed' ));

		$this->assertSame('cancelled', get_post_meta($cancelled, '_mhmrentiva_status', true));
	}

	public function test_reconfirming_a_cancelled_booking_on_free_dates_is_allowed(): void
	{
		$cancelled = $this->booking('cancelled', '2027-03-11', '2027-03-14');

		$this->admin_save($cancelled, array( 'mhmrentiva_edit_status' => 'confirmed' ));

		$this->assertSame('confirmed', get_post_meta($cancelled, '_mhmrentiva_status', true));
	}

	public function test_an_unrelated_edit_on_an_already_conflicting_booking_still_saves(): void
	{
		// Two confirmed bookings that overlap already (legacy data). Editing a
		// note must not lock the operator out of the booking.
		$this->booking('confirmed', '2027-03-10', '2027-03-13');
		$legacy = $this->booking('confirmed', '2027-03-11', '2027-03-14');

		$this->admin_save($legacy, array( 'mhmrentiva_edit_special_notes' => 'called the customer' ));

		$this->assertSame('called the customer', get_post_meta($legacy, '_mhmrentiva_special_notes', true));
		$this->assertTrue(BookingMeta::edit_availability_allowed($legacy));
	}
}
