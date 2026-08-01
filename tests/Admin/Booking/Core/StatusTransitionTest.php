<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Core;

use MHMRentiva\Admin\Booking\Core\Status;
use WP_UnitTestCase;

/**
 * Regression test: Status::can_transition() must allow completed → in_progress
 * for early-completion correction (e.g., cron mistakenly auto-completed a
 * booking whose return time has not yet arrived).
 *
 * Bug context: AutoComplete cron marked booking #3810 "completed" at gece 00:00
 * on the dropoff day, even though dropoff_time was 16:00. To restore correct
 * state via data-cleanup script, we need a sanctioned transition path that
 * fires the audit hook (mhmrentiva_booking_status_changed) — direct
 * update_post_meta bypass would skip the audit trail.
 */
final class StatusTransitionTest extends WP_UnitTestCase
{
	/**
	 * RED with current code: completed → in_progress must be allowed
	 * (currently completed only transitions to refunded).
	 */
	public function test_transition_completed_to_in_progress_allowed_for_correction(): void
	{
		$this->assertTrue(
			Status::can_transition(Status::COMPLETED, Status::IN_PROGRESS),
			'Status::can_transition(completed → in_progress) must be allowed for early-completion correction.'
		);
	}

	/**
	 * Audit chain: update_status() must fire the status_changed action
	 * with correct old/new arguments when correcting completed → in_progress.
	 */
	public function test_update_status_completed_to_in_progress_fires_status_change_action(): void
	{
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($booking_id, '_mhmrentiva_status', Status::COMPLETED);

		$captured = array();
		add_action(
			'mhmrentiva_booking_status_changed',
			static function ($id, $old, $new) use (&$captured) {
				$captured[] = array( $id, $old, $new );
			},
			10,
			3
		);

		$result = Status::update_status($booking_id, Status::IN_PROGRESS, 0);

		$this->assertTrue($result, 'update_status must succeed for completed → in_progress.');
		$this->assertSame(
			Status::IN_PROGRESS,
			get_post_meta($booking_id, '_mhmrentiva_status', true),
			'Meta must reflect new status.'
		);
		$this->assertCount(1, $captured, 'status_changed action must fire exactly once.');
		$this->assertSame(
			array( $booking_id, Status::COMPLETED, Status::IN_PROGRESS ),
			$captured[0],
			'status_changed action must receive (booking_id, completed, in_progress).'
		);
	}

	/**
	 * Regression: existing transitions stay intact.
	 * completed → refunded must still be allowed.
	 * completed → cancelled must still NOT be allowed.
	 */
	public function test_existing_completed_transitions_unchanged(): void
	{
		$this->assertTrue(
			Status::can_transition(Status::COMPLETED, Status::REFUNDED),
			'Existing transition completed → refunded must still be allowed.'
		);
		$this->assertFalse(
			Status::can_transition(Status::COMPLETED, Status::CANCELLED),
			'completed → cancelled must remain disallowed.'
		);
	}
}
