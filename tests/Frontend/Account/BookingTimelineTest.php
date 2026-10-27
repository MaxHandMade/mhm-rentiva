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
}
