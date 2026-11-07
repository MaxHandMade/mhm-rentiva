<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Core\BookingPricingInputs;
use MHMRentiva\Admin\Booking\Meta\BookingEditCancellation;
use MHMRentiva\Admin\Booking\Meta\BookingEditDates;
use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingEditNotice;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * Function scan H4: a return before the pickup was saved. The client check
 * was bound to ids that do not exist and the server had none; the day count
 * took the absolute difference, so 1 May -> 27 April read as 4 days.
 *
 * Now both savers refuse the dates (and only the dates): every other field of
 * the Update is saved, the operator is told.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditDates
 */
final class BookingEditDatesTest extends WP_UnitTestCase
{
	private int $vehicle;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		BookingEditCancellation::reset();
		BookingPricingInputs::reset();
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	private function booking(string $pickup = '2099-05-01', string $dropoff = '2099-05-04'): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		foreach (array(
			'_mhmrentiva_status'       => 'confirmed',
			'_mhmrentiva_vehicle_id'   => (string) $this->vehicle,
			'_mhmrentiva_pickup_date'  => $pickup,
			'_mhmrentiva_start_time'   => '10:00',
			'_mhmrentiva_dropoff_date' => $dropoff,
			'_mhmrentiva_end_time'     => '10:00',
			'_mhmrentiva_rental_days'  => '3',
		) as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return $id;
	}

	/** @param array<string, string> $fields */
	private function update(int $id, array $fields): void
	{
		add_action('save_post', array( BookingEditMetaBox::class, 'save_booking_details' ), 5);
		add_action('save_post', array( BookingMeta::class, 'save_meta' ), 10, 2);
		$_POST = $fields + array(
			'mhmrentiva_booking_edit_meta_nonce' => wp_create_nonce('mhmrentiva_booking_edit_action'),
			'_wpnonce'                           => wp_create_nonce('update-post_' . $id),
			'mhmrentiva_edit_vehicle_id'         => (string) $this->vehicle,
			'mhmrentiva_edit_pickup_date'        => '2099-05-01',
			'mhmrentiva_edit_pickup_time'        => '10:00',
			'mhmrentiva_edit_dropoff_date'       => '2099-05-04',
			'mhmrentiva_edit_dropoff_time'       => '10:00',
			'mhmrentiva_edit_status'             => 'confirmed',
		);
		wp_update_post(array( 'ID' => $id, 'post_title' => 'Updated' ));
		$_POST = array();
		wp_cache_delete($id, 'post_meta');
	}

	public function test_the_rule_is_the_canonical_one_for_a_changed_range(): void
	{
		$id = $this->booking();
		$this->assertTrue(BookingEditDates::is_refused($id, '2099-05-04', '10:00', '2099-05-01', '10:00'), 'Reversed.');
		$this->assertTrue(BookingEditDates::is_refused($id, '2099-06-01', '10:00', '2099-06-01', '10:00'), 'An equal moment is no rental (Util::parse_datetimes).');
		$this->assertTrue(BookingEditDates::is_refused($id, '2099-05-01', '10:00', '2099-05-01', '09:00'), 'Same day, earlier return.');
		$this->assertFalse(BookingEditDates::is_refused($id, '2099-05-01', '10:00', '2099-05-01', '18:00'), 'Same day, later return.');
		$this->assertTrue(BookingEditDates::is_refused($id, '2099-05-02', '', '2099-05-02', ''), 'Empty times are 10:00 each: equal.');
		$this->assertFalse(BookingEditDates::is_refused($id, '', '', '2099-05-02', ''), 'Nothing to compare.');
	}

	public function test_an_unchanged_equal_range_is_left_alone(): void
	{
		$id = $this->booking('2099-05-01', '2099-05-01');

		$this->assertFalse(BookingEditDates::is_refused($id, '2099-05-01', '10:00', '2099-05-01', '10:00'), 'A transfer may carry it; its other fields must save.');
	}

	/**
	 * Codex code audit 3b-2, r2: moving both ends to one moment was saved while
	 * pricing, the day count and the occupancy timestamps refused it -- the
	 * screen showed the new dates, occupancy used the old ones.
	 */
	public function test_moving_both_ends_to_one_moment_is_refused_and_nothing_drifts(): void
	{
		$id = $this->booking();
		update_post_meta($id, '_mhmrentiva_total_price', '2550');
		update_post_meta($id, '_mhmrentiva_start_ts', '4081831200');
		update_post_meta($id, '_mhmrentiva_end_ts', '4082090400');

		$this->update($id, array(
			'mhmrentiva_edit_pickup_date'  => '2099-06-01',
			'mhmrentiva_edit_dropoff_date' => '2099-06-01',
		));

		$this->assertSame('2099-05-01', get_post_meta($id, '_mhmrentiva_pickup_date', true));
		$this->assertSame('2099-05-04', get_post_meta($id, '_mhmrentiva_dropoff_date', true));
		$this->assertSame('2550', (string) get_post_meta($id, '_mhmrentiva_total_price', true));
		$this->assertSame('3', (string) get_post_meta($id, '_mhmrentiva_rental_days', true));
		$this->assertStringContainsString('The return date and time must be after the pickup.', BookingEditNotice::peek($id));
	}

	public function test_an_unchanged_equal_booking_saves_its_notes_without_a_warning(): void
	{
		$id = $this->booking('2099-05-01', '2099-05-01');

		$this->update($id, array(
			'mhmrentiva_edit_pickup_date'   => '2099-05-01',
			'mhmrentiva_edit_dropoff_date'  => '2099-05-01',
			'mhmrentiva_edit_special_notes' => 'transfer note',
		));

		$this->assertSame('transfer note', get_post_meta($id, '_mhmrentiva_special_notes', true));
		$this->assertSame('', BookingEditNotice::peek($id));
	}

	public function test_a_reversed_range_is_not_saved_and_the_operator_is_told(): void
	{
		$id = $this->booking();

		$this->update($id, array(
			'mhmrentiva_edit_pickup_date'   => '2099-05-04',
			'mhmrentiva_edit_dropoff_date'  => '2099-05-01',
			'mhmrentiva_edit_special_notes' => 'kept',
		));

		$this->assertSame('2099-05-01', get_post_meta($id, '_mhmrentiva_pickup_date', true));
		$this->assertSame('2099-05-04', get_post_meta($id, '_mhmrentiva_dropoff_date', true));
		$this->assertSame('3', (string) get_post_meta($id, '_mhmrentiva_rental_days', true));
		$this->assertSame('kept', get_post_meta($id, '_mhmrentiva_special_notes', true), 'The other fields of the Update are saved.');
		$this->assertStringContainsString('The return date and time must be after the pickup. The dates were not changed.', BookingEditNotice::peek($id));
	}

	public function test_same_day_with_a_later_return_time_is_saved(): void
	{
		$id = $this->booking();

		$this->update($id, array(
			'mhmrentiva_edit_pickup_date'  => '2099-05-02',
			'mhmrentiva_edit_dropoff_date' => '2099-05-02',
			'mhmrentiva_edit_dropoff_time' => '18:00',
		));

		$this->assertSame('2099-05-02', get_post_meta($id, '_mhmrentiva_dropoff_date', true));
		$this->assertSame('18:00', get_post_meta($id, '_mhmrentiva_end_time', true));
		$this->assertSame('', BookingEditNotice::peek($id));
	}

	public function test_a_booking_in_the_past_still_saves_a_notes_only_change(): void
	{
		$id = $this->booking('2020-05-01', '2020-05-04');

		$this->update($id, array(
			'mhmrentiva_edit_pickup_date'   => '2020-05-01',
			'mhmrentiva_edit_dropoff_date'  => '2020-05-04',
			'mhmrentiva_edit_special_notes' => 'returned late',
		));

		$this->assertSame('returned late', get_post_meta($id, '_mhmrentiva_special_notes', true));
		$this->assertSame('', BookingEditNotice::peek($id));
	}

	/** Codex code audit 3b-2, Minor 8: BookingMeta's saver alone still tells the operator. */
	public function test_the_priority_10_saver_alone_tells_the_operator(): void
	{
		$id = $this->booking();
		add_action('save_post', array( BookingMeta::class, 'save_meta' ), 10, 2);
		$_POST = array(
			'mhmrentiva_booking_meta_main_nonce' => wp_create_nonce('mhmrentiva_booking_meta_action'),
			'mhmrentiva_edit_vehicle_id'         => (string) $this->vehicle,
			'mhmrentiva_edit_pickup_date'        => '2099-05-04',
			'mhmrentiva_edit_pickup_time'        => '10:00',
			'mhmrentiva_edit_dropoff_date'       => '2099-05-01',
			'mhmrentiva_edit_dropoff_time'       => '10:00',
		);
		wp_update_post(array( 'ID' => $id, 'post_title' => 'Updated' ));
		$_POST = array();
		wp_cache_delete($id, 'post_meta');

		$this->assertSame('2099-05-01', get_post_meta($id, '_mhmrentiva_pickup_date', true));
		$this->assertStringContainsString('The return date and time must be after the pickup.', BookingEditNotice::peek($id));
	}

	/** Codex r3 Minor 1 / Fable M-D: the form's own reading is what is compared. */
	public function test_a_booking_with_zero_times_is_not_seen_as_changed(): void
	{
		$id = $this->booking('2099-05-01', '2099-05-01');
		update_post_meta($id, '_mhmrentiva_start_time', '0');
		update_post_meta($id, '_mhmrentiva_end_time', '0');

		$this->assertFalse(BookingEditDates::is_refused($id, '2099-05-01', '', '2099-05-01', ''));
	}

	public function test_a_booking_with_only_the_older_time_keys_is_not_seen_as_changed(): void
	{
		$id = $this->booking('2099-05-01', '2099-05-01');
		delete_post_meta($id, '_mhmrentiva_start_time');
		delete_post_meta($id, '_mhmrentiva_end_time');
		update_post_meta($id, '_mhmrentiva_pickup_time', '14:00');
		update_post_meta($id, '_mhmrentiva_dropoff_time', '14:00');

		$this->assertSame(array( '2099-05-01', '14:00', '2099-05-01', '14:00' ), BookingEditDates::stored($id));
		$this->assertFalse(BookingEditDates::is_refused($id, '2099-05-01', '14:00', '2099-05-01', '14:00'));
	}

	public function test_the_browser_gets_the_filtered_default_times(): void
	{
		add_filter('mhmrentiva_default_pickup_time', static fn() => '08:00');
		add_filter('mhmrentiva_default_dropoff_time', static fn() => '18:00');

		$this->assertSame(array( 'pickup' => '08:00', 'dropoff' => '18:00' ), BookingEditDates::default_times());
		$this->assertFalse(BookingEditDates::is_refused($this->booking(), '2099-06-01', '', '2099-06-01', ''), 'Server and browser agree: 08:00 -> 18:00 is a rental.');
	}

	/** Codex r4: the baseline and the defaults are printed before the script, as data. */
	public function test_the_edit_screen_prints_the_drawn_range_before_the_script(): void
	{
		global $post, $post_type;
		$id        = $this->booking();
		$post      = get_post($id);
		$post_type = 'mhmrentiva_booking';

		BookingEditMetaBox::enqueue_scripts('post.php');
		$before = implode("\n", (array) wp_scripts()->get_data('mhm-rentiva-booking-edit-meta', 'before'));

		$this->assertStringContainsString('window.mhmBookingEditDates', $before);
		$this->assertStringContainsString('"pickup_date":"2099-05-01"', $before);
		$this->assertStringContainsString('"defaultTimes"', $before);
		$post      = null;
		$post_type = null; // Not reset by the test library (Fable M-H).
	}

	/** Codex r5: a stored time the time input cannot hold is '' -- as the browser posts it. */
	public function test_a_stored_time_the_input_cannot_hold_is_read_as_empty(): void
	{
		foreach (array( '9:00', '24:00' ) as $bad) {
			$id = $this->booking('2099-06-01', '2099-06-01');
			update_post_meta($id, '_mhmrentiva_start_time', $bad);
			update_post_meta($id, '_mhmrentiva_end_time', $bad);

			$this->assertSame(array( '2099-06-01', '', '2099-06-01', '' ), BookingEditDates::stored($id), $bad);
			$this->assertFalse(BookingEditDates::is_refused($id, '2099-06-01', '', '2099-06-01', ''), $bad . ': untouched, so not refused.');
		}
	}

	public function test_a_stored_date_the_input_cannot_hold_is_read_as_empty(): void
	{
		$id = $this->booking('2099-13-01', '2099-02-30');

		$stored = BookingEditDates::stored($id);
		$this->assertSame('', $stored[0]);
		$this->assertSame('', $stored[2]);
	}

	/**
	 * Codex r6: a refused change keeps the meta exactly -- the savers write the
	 * RAW stored range back, not the input-shaped one (which reads '9:00' as '').
	 */
	public function test_a_refused_range_keeps_a_raw_stored_time_and_its_timestamp(): void
	{
		$id = $this->booking('2099-06-01', '2099-06-03');
		update_post_meta($id, '_mhmrentiva_start_time', '9:00');
		update_post_meta($id, '_mhmrentiva_end_time', '18:00');
		update_post_meta($id, '_mhmrentiva_start_ts', '4084318800');

		$this->update($id, array(
			'mhmrentiva_edit_pickup_date'  => '2099-06-05',
			'mhmrentiva_edit_pickup_time'  => '10:00',
			'mhmrentiva_edit_dropoff_date' => '2099-06-01',
			'mhmrentiva_edit_dropoff_time' => '10:00',
		));

		$this->assertSame('9:00', get_post_meta($id, '_mhmrentiva_start_time', true));
		$this->assertSame('2099-06-01', get_post_meta($id, '_mhmrentiva_pickup_date', true));
		$this->assertSame('4084318800', (string) get_post_meta($id, '_mhmrentiva_start_ts', true));
	}

	/** Codex r6 Minors: \z not $, and years of four digits or more. */
	public function test_input_shapes_follow_the_html_contract(): void
	{
		$id = $this->booking();
		update_post_meta($id, '_mhmrentiva_pickup_date', "2099-06-01\n");
		update_post_meta($id, '_mhmrentiva_start_time', "10:00\n");
		update_post_meta($id, '_mhmrentiva_dropoff_date', '10000-06-01');
		update_post_meta($id, '_mhmrentiva_end_time', '10:00:30');

		$this->assertSame(array( '', '', '10000-06-01', '10:00:30' ), BookingEditDates::stored($id));
		$this->assertSame("2099-06-01\n", BookingEditDates::raw($id)[0], 'raw() is the database value.');
	}
}
