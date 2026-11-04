<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Frontend\Account\BookingTimeline;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR D Task 4 (R9, M9, y6): the booking detail's progress is drawn from
 * recorded facts only, and the history keeps every entry in time order.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\BookingTimeline
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::add_history_note
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::history_sorted
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::auto_add_status_change_note
 */
final class BookingTimelineTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	public function setUp(): void
	{
		parent::setUp();
		update_option('timezone_string', 'Europe/Istanbul');
	}

	public function tearDown(): void
	{
		update_option('timezone_string', '');
		parent::tearDown();
	}

	private function booking(string $status, array $meta = array()): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$meta += array(
			'_mhmrentiva_status'           => $status,
			'_mhmrentiva_payment_type'     => 'deposit',
			'_mhmrentiva_payment_status'   => 'pending',
			'_mhmrentiva_total_price'      => '2915.00',
			'_mhmrentiva_deposit_amount'   => '292.00',
			'_mhmrentiva_remaining_amount' => '2623.00',
			'_mhmrentiva_pickup_date'      => '2031-10-21',
			'_mhmrentiva_start_time'       => '10:00',
			'_mhmrentiva_dropoff_date'     => '2031-10-24',
			'_mhmrentiva_end_time'         => '10:00',
		);
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return $id;
	}

	/**
	 * @param list<array<string, mixed>> $steps
	 * @return array<string, array<string, mixed>>
	 */
	private static function by_key(array $steps): array
	{
		return array_column($steps, null, 'key');
	}

	public function test_deposit_then_confirmed_then_future_pickup(): void
	{
		$this->require_woocommerce();
		$id    = $this->booking('confirmed');
		$order = $this->create_paid_order_for_booking($id, '292');
		BookingMeta::auto_add_status_change_note($id, 'pending', 'confirmed');

		$steps = BookingTimeline::for_booking($id);
		$this->assertSame(array( 'received', 'deposit_paid', 'confirmed', 'pickup', 'return' ), array_column($steps, 'key'));

		$s = self::by_key($steps);
		$this->assertTrue($s['received']['done']);
		$this->assertSame( (int) get_post_time('U', true, $id), $s['received']['at']);

		$this->assertTrue($s['deposit_paid']['done']);
		$this->assertStringStartsWith('Deposit paid · ', $s['deposit_paid']['label']);
		$this->assertStringContainsString('292', $s['deposit_paid']['label']);
		$this->assertSame($order->get_date_paid()->getTimestamp(), $s['deposit_paid']['at']);

		$this->assertTrue($s['confirmed']['done']);
		$this->assertIsInt($s['confirmed']['at']);
		$this->assertEqualsWithDelta(time(), $s['confirmed']['at'], 5);

		$this->assertFalse($s['pickup']['done']);
		$this->assertSame(SiteTime::instant('2031-10-21', '10:00'), $s['pickup']['at']);
		$this->assertFalse($s['return']['done']);
		$this->assertSame(SiteTime::instant('2031-10-24', '10:00'), $s['return']['at']);
	}

	public function test_unknown_confirm_time_is_dateless_not_invented(): void
	{
		$id = $this->booking('confirmed');
		// A pre-6.4 entry: the sentence only, no status_to / at.
		update_post_meta(
			$id,
			'_mhmrentiva_booking_history',
			array(
				1760000000 => array(
					'note'      => 'Status changed from Pending to Confirmed',
					'type'      => 'status_change',
					'user_id'   => 1,
					'timestamp' => 1760000000,
				),
			)
		);

		$s = self::by_key(BookingTimeline::for_booking($id));

		$this->assertTrue($s['confirmed']['done']);
		$this->assertNull($s['confirmed']['at'], 'The sentence is not parsed for a time.');
	}

	public function test_staff_notes_never_shown(): void
	{
		$id = $this->booking('confirmed');
		BookingMeta::add_history_note($id, 'Secret staff note', 'note');
		BookingMeta::add_history_note($id, 'Called the customer about the deposit', 'customer_contact');

		$text = wp_json_encode(BookingTimeline::for_booking($id));

		$this->assertStringNotContainsString('Secret staff note', (string) $text);
		$this->assertStringNotContainsString('Called the customer', (string) $text);
	}

	public function test_payment_updates_never_shown(): void
	{
		$id = $this->booking('confirmed');
		BookingMeta::add_history_note(
			$id,
			'Payment: Pending → Paid',
			'payment_update',
			null,
			array( 'payment_from' => 'pending', 'payment_to' => 'paid' )
		);

		$timeline = BookingTimeline::for_booking($id);
		$text     = wp_json_encode($timeline);

		$this->assertStringNotContainsString('Payment: Pending', (string) $text);
		// The confirmed step comes from the booking status, not from this row.
		$this->assertNull(self::by_key($timeline)['confirmed']['at']);
	}

	public function test_cancelled_with_refund_branch(): void
	{
		// Deposit paid offline, then cancelled and refunded by hand.
		$id = $this->booking(
			'cancelled',
			array(
				'_mhmrentiva_payment_status'     => 'refunded',
				'_mhmrentiva_refunded_amount'    => 29200,
				'_mhmrentiva_refund_completed_at' => '2031-10-15 12:30:00',
				'_mhmrentiva_cancellation_data'  => array( 'cancelled_at' => '2031-10-15 11:00:00' ),
			)
		);

		$steps = BookingTimeline::for_booking($id);
		$this->assertSame(array( 'received', 'deposit_paid', 'cancelled', 'refunded' ), array_column($steps, 'key'), 'No pickup or return on a cancelled booking; no confirmation that never happened.');

		$s = self::by_key($steps);
		$this->assertNull($s['deposit_paid']['at'], 'Offline money has no recorded arrival time.');
		$this->assertSame(SiteTime::instant('2031-10-15', '11:00:00'), $s['cancelled']['at']);
		$this->assertStringStartsWith('Refunded · ', $s['refunded']['label']);
		$this->assertStringContainsString('292', $s['refunded']['label']);
		$this->assertSame(SiteTime::instant('2031-10-15', '12:30:00'), $s['refunded']['at']);
	}

	public function test_pending_payment_has_no_done_payment_step(): void
	{
		$steps = BookingTimeline::for_booking($this->booking('pending_payment'));

		$keys = array_column($steps, 'key');
		$this->assertNotContains('deposit_paid', $keys);
		$this->assertNotContains('payment_received', $keys);
		$s = self::by_key($steps);
		$this->assertFalse($s['confirmed']['done']);
		$this->assertNull($s['confirmed']['at']);
	}

	public function test_two_changes_same_second_both_kept(): void
	{
		$id = $this->booking('in_progress');
		BookingMeta::auto_add_status_change_note($id, 'pending', 'confirmed');
		BookingMeta::auto_add_status_change_note($id, 'confirmed', 'in_progress');

		$history = BookingMeta::history_sorted($id);
		$this->assertCount(2, $history);
		$this->assertSame(array( 'confirmed', 'in_progress' ), array_column($history, 'status_to'));
		$this->assertSame(array( 'pending', 'confirmed' ), array_column($history, 'status_from'));
		$this->assertSame('status_change', $history[0]['type']);
		$this->assertArrayHasKey('note', $history[0], 'The old fields stay.');
		$this->assertArrayHasKey('timestamp', $history[0]);
	}

	public function test_legacy_and_new_entries_sort_by_instant_on_an_offset_site(): void
	{
		// Europe/Istanbul is UTC+3. A legacy entry stored the local clock.
		$id   = $this->booking('confirmed');
		$utc  = 1900000000;
		$off  = 3 * HOUR_IN_SECONDS;
		$rows = array(
			// Legacy, happened at $utc + 120 (stored as local clock reading).
			( $utc + 120 + $off ) . ''    => array(
				'note'      => 'later legacy',
				'type'      => 'note',
				'user_id'   => 1,
				'timestamp' => $utc + 120 + $off,
			),
			// New, happened at $utc + 60; its local timestamp key sorts AFTER the
			// legacy key above when read as a number.
			( $utc + 60 + $off ) . '-abc' => array(
				'note'      => 'new',
				'type'      => 'status_change',
				'user_id'   => 1,
				'timestamp' => $utc + 60 + $off,
				'at'        => $utc + 60,
				'status_to' => 'confirmed',
			),
			// Legacy, happened at $utc.
			( $utc + $off ) . ''          => array(
				'note'      => 'early legacy',
				'type'      => 'note',
				'user_id'   => 1,
				'timestamp' => $utc + $off,
			),
		);
		update_post_meta($id, '_mhmrentiva_booking_history', $rows);

		$sorted = BookingMeta::history_sorted($id);

		$this->assertSame(array( 'early legacy', 'new', 'later legacy' ), array_column($sorted, 'note'));
		$this->assertSame(array( $utc, $utc + 60, $utc + 120 ), array_column($sorted, 'sort_ts'));
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	private function sorted_in_berlin(array $rows): array
	{
		update_option('timezone_string', 'Europe/Berlin');
		update_option('gmt_offset', '');
		$id = $this->booking('confirmed');
		update_post_meta($id, '_mhmrentiva_booking_history', $rows);

		return BookingMeta::history_sorted($id);
	}

	/** The wall clock a legacy entry stored for a UTC instant, read as if UTC. */
	private static function legacy_timestamp(int $utc): int
	{
		$local = ( new \DateTimeImmutable('@' . $utc) )->setTimezone(new \DateTimeZone('Europe/Berlin'));

		return (int) strtotime($local->format('Y-m-d H:i:s') . ' UTC');
	}

	public function test_legacy_entry_before_a_new_one_on_the_autumn_change_day(): void
	{
		// 2026-10-25 in Berlin: 03:00 CEST falls back to 02:00 CET at 01:00 UTC.
		$old = (int) strtotime('2026-10-25 00:30:00 UTC');
		$new = (int) strtotime('2026-10-25 01:00:00 UTC');

		$sorted = $this->sorted_in_berlin(
			array(
				'new'    => array( 'note' => 'new', 'timestamp' => self::legacy_timestamp($new), 'at' => $new ),
				'legacy' => array( 'note' => 'old', 'timestamp' => self::legacy_timestamp($old) ),
			)
		);

		$this->assertSame(array( 'old', 'new' ), array_column($sorted, 'note'));
		$this->assertSame(array( $old, $new ), array_column($sorted, 'sort_ts'));
	}

	public function test_legacy_entry_after_the_autumn_change_is_placed_exactly(): void
	{
		$after = (int) strtotime('2026-10-25 02:30:00 UTC');

		$sorted = $this->sorted_in_berlin(array( array( 'note' => 'after', 'timestamp' => self::legacy_timestamp($after) ) ));

		$this->assertSame($after, $sorted[0]['sort_ts']);
	}

	public function test_legacy_entries_around_the_spring_gap_are_placed_exactly(): void
	{
		// 2026-03-29 in Berlin: 02:00 CET jumps to 03:00 CEST at 01:00 UTC.
		$before = (int) strtotime('2026-03-29 00:30:00 UTC');
		$after  = (int) strtotime('2026-03-29 01:30:00 UTC');

		$sorted = $this->sorted_in_berlin(
			array(
				array( 'note' => 'after', 'timestamp' => self::legacy_timestamp($after) ),
				array( 'note' => 'before', 'timestamp' => self::legacy_timestamp($before) ),
			)
		);

		$this->assertSame(array( $before, $after ), array_column($sorted, 'sort_ts'));
	}

	public function test_no_show_is_a_closed_outcome_not_upcoming_steps(): void
	{
		$id = $this->booking('no_show');
		BookingMeta::auto_add_status_change_note($id, 'pending', 'confirmed');
		BookingMeta::auto_add_status_change_note($id, 'confirmed', 'no_show');

		$steps = BookingTimeline::for_booking($id);

		$this->assertSame(array( 'received', 'confirmed', 'no_show' ), array_column($steps, 'key'), 'No pickup or return drawn as a step still ahead.');
		foreach ($steps as $step) {
			$this->assertTrue($step['done'], $step['key'] . ' must not read as upcoming.');
		}
		$this->assertSame(\MHMRentiva\Admin\Booking\Core\Status::get_label('no_show'), $steps[2]['label']);
		$this->assertIsInt($steps[2]['at']);

		// Never confirmed on record: no confirmation step at all.
		$keys = array_column(BookingTimeline::for_booking($this->booking('no_show')), 'key');
		$this->assertSame(array( 'received', 'no_show' ), $keys);
	}

	public function test_moment_text_matches_the_vehicle_card_format(): void
	{
		update_option('date_format', 'j F Y');
		update_option('time_format', 'H:i');
		$ts = SiteTime::instant('2031-10-21', '10:00');

		$this->assertSame('21 October 2031 · 10:00', BookingTimeline::moment_text($ts));
		$this->assertSame('', BookingTimeline::moment_text(null));

		$id = $this->booking('confirmed');
		wp_set_current_user( (int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$html = \MHMRentiva\Admin\Frontend\Account\AccountRenderer::render_booking_detail($id, true);
		$this->assertStringContainsString('<strong>21 October 2031 · 10:00</strong>', $html, 'Vehicle card.');
		$this->assertStringContainsString('<span class="rvs-timeline__meta">21 October 2031 · 10:00</span>', $html, 'Progress uses the same format.');
	}
}
