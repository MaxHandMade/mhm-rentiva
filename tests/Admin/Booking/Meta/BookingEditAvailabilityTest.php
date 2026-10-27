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
		$user_id = get_current_user_id();
		delete_transient('mhmrentiva_booking_notice_' . $user_id);
		delete_transient('mhmrentiva_admin_blocked_day_notice_' . $user_id);
		if ($user_id > 0) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user($user_id);
		}
		// Locker's START TRANSACTION already committed the framework's transaction,
		// and with autocommit still off the deletes above would sit in a new
		// implicit one that parent::tearDown()'s ROLLBACK undoes. Commit them.
		$GLOBALS['wpdb']->query('COMMIT');
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

	/**
	 * Rows of a vehicle's availability cache in the options table. Read from the
	 * database, not get_transient(): Cache::invalidateVehicle() deletes rows, and
	 * within one PHP request get_transient() can still answer from the object cache.
	 */
	private function cached_rows(int $vehicle_id): int
	{
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'%' . $wpdb->esc_like('mhmrentiva_avail_' . $vehicle_id . '_') . '%'
			)
		);
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

	public function test_moving_a_booking_to_a_vehicle_that_is_busy_on_its_dates_is_refused(): void
	{
		$other_vehicle = $this->track(
			self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_vehicle',
					'post_status' => 'publish',
				)
			)
		);
		update_post_meta($other_vehicle, '_mhmrentiva_price_per_day', '1000');
		$taken = $this->booking('confirmed', '2027-03-10', '2027-03-13');
		update_post_meta($taken, '_mhmrentiva_vehicle_id', $other_vehicle);

		$moved = $this->booking('confirmed', '2027-03-10', '2027-03-13');

		$this->admin_save($moved, array( 'mhmrentiva_edit_vehicle_id' => (string) $other_vehicle ));

		$this->assertSame((string) $this->vehicle_id, (string) get_post_meta($moved, '_mhmrentiva_vehicle_id', true));
	}

	public function test_moving_a_rental_booking_onto_a_transfer_only_vehicle_is_refused(): void
	{
		$transfer_only = $this->track(
			self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_vehicle',
					'post_status' => 'publish',
				)
			)
		);
		update_post_meta($transfer_only, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($transfer_only, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_SERVICE_TYPE, 'transfer');

		$moved = $this->booking('confirmed', '2027-03-10', '2027-03-13');
		$this->admin_save($moved, array( 'mhmrentiva_edit_vehicle_id' => (string) $transfer_only ));

		$this->assertSame((string) $this->vehicle_id, (string) get_post_meta($moved, '_mhmrentiva_vehicle_id', true));
		$this->assertSame('service_unavailable', get_transient('mhmrentiva_booking_notice_' . get_current_user_id()));

		// A transfer booking may move to it: the service gate is for rentals.
		BookingMeta::reset_edit_availability_verdicts();
		$transfer = $this->booking('confirmed', '2027-04-10', '2027-04-11');
		update_post_meta($transfer, '_mhmrentiva_transfer_origin_id', 5);
		$this->admin_save($transfer, array( 'mhmrentiva_edit_vehicle_id' => (string) $transfer_only ));

		$this->assertSame((string) $transfer_only, (string) get_post_meta($transfer, '_mhmrentiva_vehicle_id', true));
	}

	public function test_an_existing_booking_on_a_transfer_only_vehicle_can_still_be_edited(): void
	{
		// Bookings made before the gate existed must not be locked out of a date fix.
		update_post_meta($this->vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_SERVICE_TYPE, 'transfer');
		$booking_id = $this->booking('confirmed', '2027-05-10', '2027-05-12');

		$this->admin_save(
			$booking_id,
			array(
				'mhmrentiva_edit_pickup_date'  => '2027-05-11',
				'mhmrentiva_edit_dropoff_date' => '2027-05-13',
			)
		);

		$this->assertSame('2027-05-11', get_post_meta($booking_id, '_mhmrentiva_pickup_date', true));
	}

	public function test_a_saved_move_drops_cached_availability_of_both_vehicles(): void
	{
		// check_availability() returns a cached "unavailable" without rechecking,
		// so a move must clear what was cached about the vehicle it left too.
		$other_vehicle = $this->track(
			self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_vehicle',
					'post_status' => 'publish',
				)
			)
		);
		$booking_id = $this->booking('confirmed', '2027-03-20', '2027-03-22');
		set_transient('mhmrentiva_avail_' . $this->vehicle_id . '_probe', 'unavailable', HOUR_IN_SECONDS);
		set_transient('mhmrentiva_avail_' . $other_vehicle . '_probe', 'unavailable', HOUR_IN_SECONDS);

		$this->admin_save($booking_id, array( 'mhmrentiva_edit_vehicle_id' => (string) $other_vehicle ));

		$this->assertSame((string) $other_vehicle, (string) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true));
		$this->assertSame(0, $this->cached_rows($this->vehicle_id), 'The vehicle the booking left must not keep a cached answer.');
		$this->assertSame(0, $this->cached_rows($other_vehicle));
	}

	public function test_a_saved_date_move_leaves_no_cached_availability(): void
	{
		// Regression fence: on a same-vehicle date move the guard already clears
		// the vehicle's cache before its check, and the handlers clear it again
		// after writing.
		$booking_id = $this->booking('confirmed', '2027-03-20', '2027-03-22');
		set_transient('mhmrentiva_avail_' . $this->vehicle_id . '_probe', 'unavailable', HOUR_IN_SECONDS);

		$this->admin_save(
			$booking_id,
			array(
				'mhmrentiva_edit_pickup_date'  => '2027-03-25',
				'mhmrentiva_edit_dropoff_date' => '2027-03-27',
			)
		);

		$this->assertSame(0, $this->cached_rows($this->vehicle_id));
	}

	public function test_confirming_a_pending_booking_whose_payment_window_lapsed_is_checked(): void
	{
		// The overlap query ignores a pending booking past its payment deadline, so
		// its dates could be sold while it waited. Confirming it later must not be
		// waved through as "nothing changed".
		$lapsed = $this->booking('pending_payment', '2027-03-11', '2027-03-14');
		update_post_meta($lapsed, '_mhmrentiva_payment_deadline', gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS));
		$this->booking('confirmed', '2027-03-10', '2027-03-13');

		$this->admin_save($lapsed, array( 'mhmrentiva_edit_status' => 'confirmed' ));

		$this->assertSame('pending_payment', get_post_meta($lapsed, '_mhmrentiva_status', true));
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function broken_count_queries(): array
	{
		return array(
			'SQL error'                       => array( 'SELECT broken FROM', false ),
			// wpdb::query() returns false before recording any error.
			'query never runs'                => array( '', false ),
			// ...and before flush(), so get_var() would read the PREVIOUS
			// query's result: here a '0' left by the statement just before.
			'query never runs, stale result' => array( '', true ),
		);
	}

	/**
	 * @dataProvider broken_count_queries
	 */
	public function test_an_availability_query_that_cannot_answer_refuses_the_edit(string $replacement, bool $stale_zero): void
	{
		$booking_id = $this->booking('confirmed', '2027-03-20', '2027-03-22');

		// Replace only the guard's locked COUNT query; optionally make the query
		// that runs just before it leave a '0' in wpdb's last result.
		$break = static function (string $query) use ($replacement, $stale_zero): string {
			if ($stale_zero && str_starts_with($query, 'SET time_zone')) {
				return "SELECT '0'";
			}
			return ( str_contains($query, 'SELECT COUNT(*)') && str_contains($query, 'p.ID <>') ) ? $replacement : $query;
		};
		add_filter('query', $break);
		$suppress = $GLOBALS['wpdb']->suppress_errors(true);

		try {
			$this->admin_save(
				$booking_id,
				array(
					'mhmrentiva_edit_pickup_date'  => '2027-03-25',
					'mhmrentiva_edit_dropoff_date' => '2027-03-27',
				)
			);
		} finally {
			$GLOBALS['wpdb']->suppress_errors($suppress);
			remove_filter('query', $break);
		}

		$this->assertSame('2027-03-20', get_post_meta($booking_id, '_mhmrentiva_pickup_date', true), 'An unverifiable edit must not be saved.');
		$this->assertSame('availability_unverified', get_transient('mhmrentiva_booking_notice_' . get_current_user_id()));
	}

	private function blocked_day_notice(): mixed
	{
		return get_transient('mhmrentiva_admin_blocked_day_notice_' . get_current_user_id());
	}

	public function test_edit_existing_booking_spanning_later_blocked_day_is_not_locked(): void
	{
		// Booked first, the day closed afterwards (maintenance, or "apply
		// to all"). Extending the return by two hours must still save.
		$booking_id = $this->booking('confirmed', '2027-03-10', '2027-03-13');
		update_post_meta($this->vehicle_id, '_mhmrentiva_blocked_dates', '["2027-03-11"]');

		$this->admin_save($booking_id, array( 'mhmrentiva_edit_dropoff_time' => '12:00' ));

		$this->assertTrue(BookingMeta::edit_availability_allowed($booking_id));
		$this->assertSame('12:00', get_post_meta($booking_id, '_mhmrentiva_end_time', true));
		$this->assertSame(array( 'booking_id' => $booking_id, 'days' => array( '2027-03-11' ) ), $this->blocked_day_notice(), 'Saved, and the operator is told.');
	}

	public function test_admin_edit_onto_a_blocked_day_saves_and_warns(): void
	{
		update_post_meta($this->vehicle_id, '_mhmrentiva_blocked_dates', '["2027-04-02"]');
		$booking_id = $this->booking('confirmed', '2027-03-20', '2027-03-22');

		$this->admin_save(
			$booking_id,
			array(
				'mhmrentiva_edit_pickup_date'  => '2027-04-01',
				'mhmrentiva_edit_dropoff_date' => '2027-04-03',
			)
		);

		$this->assertSame('2027-04-01', get_post_meta($booking_id, '_mhmrentiva_pickup_date', true));
		$this->assertSame(array( 'booking_id' => $booking_id, 'days' => array( '2027-04-02' ) ), $this->blocked_day_notice());
		$this->assertFalse(get_transient('mhmrentiva_booking_notice_' . get_current_user_id()), 'No refusal notice.');
	}

	/**
	 * The notice says "the booking was saved anyway", so it is queued by the
	 * write, not by the check: a check whose edit is never written leaves
	 * nothing behind.
	 */
	public function test_blocked_day_notice_waits_for_the_write(): void
	{
		update_post_meta($this->vehicle_id, '_mhmrentiva_blocked_dates', '["2027-04-02"]');
		$booking_id = $this->booking('confirmed', '2027-03-20', '2027-03-22');

		$allowed = BookingMeta::guard_edit_availability($booking_id, $this->vehicle_id, '2027-04-01', '10:00', '2027-04-03', '10:00', 'confirmed');

		$this->assertTrue($allowed);
		$this->assertFalse($this->blocked_day_notice(), 'Checked, not written: no notice yet.');

		$this->admin_save(
			$booking_id,
			array(
				'mhmrentiva_edit_pickup_date'  => '2027-04-01',
				'mhmrentiva_edit_dropoff_date' => '2027-04-03',
			)
		);

		$this->assertSame(array( 'booking_id' => $booking_id, 'days' => array( '2027-04-02' ) ), $this->blocked_day_notice(), 'Written: now the operator is told.');
	}

	public function test_admin_edit_unverified_when_query_fails(): void
	{
		// The operator's check counts bookings only, but a check that could not
		// run still refuses: a blocked day must not turn "unknown" into "free".
		update_post_meta($this->vehicle_id, '_mhmrentiva_blocked_dates', '["2027-03-26"]');
		$booking_id = $this->booking('confirmed', '2027-03-20', '2027-03-22');

		$break = static function (string $query): string {
			return ( str_contains($query, 'SELECT COUNT(*)') && str_contains($query, 'p.ID <>') ) ? '' : $query;
		};
		add_filter('query', $break);

		try {
			$this->admin_save(
				$booking_id,
				array(
					'mhmrentiva_edit_pickup_date'  => '2027-03-25',
					'mhmrentiva_edit_dropoff_date' => '2027-03-27',
				)
			);
		} finally {
			remove_filter('query', $break);
		}

		$this->assertSame('2027-03-20', get_post_meta($booking_id, '_mhmrentiva_pickup_date', true));
		$this->assertSame('availability_unverified', get_transient('mhmrentiva_booking_notice_' . get_current_user_id()));
		$this->assertFalse($this->blocked_day_notice(), 'Nothing was saved, so nothing was "saved anyway".');
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
