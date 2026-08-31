<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Actions;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Tests\Support\SiteClock;
use WP_Ajax_UnitTestCase;

/**
 * Independent review finding M-01: settling the remaining payment could mark a
 * booking completed hours before the car comes back.
 *
 * The status choice read only `_mhmrentiva_dropoff_date` and compared
 * strtotime($date) < time(). strtotime() on a bare date lands on midnight, so a
 * booking due back today at 18:00 already looks finished at 09:00 when the
 * customer settles the balance. `_mhmrentiva_dropoff_time` was never consulted,
 * even though the booking stores it and the rest of the plugin uses it.
 *
 * Completed is not cosmetic: availability, dashboards, reporting and status
 * hooks all read it.
 *
 * @covers \MHMRentiva\Admin\Booking\Actions\DepositManagementAjax::process_remaining_payment
 */
final class RemainingPaymentCompletesOnDropoffTimeTest extends WP_Ajax_UnitTestCase
{
	use SiteClock;

	private int $admin_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->admin_id = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		DepositManagementAjax::register();
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	private function make_booking(string $dropoff_date, string $dropoff_time): int
	{
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));

		update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($booking_id, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($booking_id, '_mhmrentiva_payment_status', 'partially_paid');
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', 500);
		update_post_meta($booking_id, '_mhmrentiva_total_price', 1000);
		update_post_meta($booking_id, '_mhmrentiva_dropoff_date', $dropoff_date);
		update_post_meta($booking_id, '_mhmrentiva_dropoff_time', $dropoff_time);

		return $booking_id;
	}

	private function settle(int $booking_id): void
	{
		wp_set_current_user($this->admin_id);

		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
			'booking_id' => $booking_id,
		);

		try {
			$this->_handleAjax('mhmrentiva_process_remaining_payment');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// wp_send_json_* terminates.
		}
	}

	/**
	 * RED before the fix: dropoff is later today, but the date-only comparison
	 * puts it in the past and the booking is marked completed.
	 */
	public function test_booking_due_back_later_today_is_not_completed_yet(): void
	{
		// The fixture is "later today", so the clock has to be somewhere the
		// arithmetic stays inside today. It used to be whatever hour the suite
		// happened to run at, and the test skipped itself after 18:00 -- real in
		// the morning, decorative at night, a coin flip in CI.
		$this->pin_site_hour(9);
		$later_today = ( new \DateTimeImmutable('now', wp_timezone()) )->modify('+6 hours');

		$booking_id = $this->make_booking($later_today->format('Y-m-d'), $later_today->format('H:i'));

		$this->settle($booking_id);

		$this->assertSame(
			'confirmed',
			Status::get($booking_id),
			'A car still out until later today must not be marked completed when the balance is paid.'
		);
	}

	/**
	 * Negative control: a genuinely finished rental must still complete, so the
	 * fix cannot be "never complete".
	 */
	public function test_booking_already_returned_is_completed(): void
	{
		$yesterday  = ( new \DateTimeImmutable('now', wp_timezone()) )->modify('-1 day');
		$booking_id = $this->make_booking($yesterday->format('Y-m-d'), '10:00');

		$this->settle($booking_id);

		$this->assertSame(
			'completed',
			Status::get($booking_id),
			'A rental whose drop-off has passed must be completed when the balance is settled.'
		);
	}

	/**
	 * A booking with no drop-off time recorded must fall back to the old
	 * date-level behaviour rather than erroring or silently completing.
	 */
	public function test_missing_dropoff_time_falls_back_to_date(): void
	{
		$yesterday  = ( new \DateTimeImmutable('now', wp_timezone()) )->modify('-1 day');
		$booking_id = $this->make_booking($yesterday->format('Y-m-d'), '');

		$this->settle($booking_id);

		$this->assertSame('completed', Status::get($booking_id));
	}
}
