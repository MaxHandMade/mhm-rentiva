<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

if (! defined('ABSPATH')) {
	exit;
}



use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use WP_Query;
use Exception;



final class AutoCancel {



	public const EVENT    = 'mhmrentiva_auto_cancel_event';
	public const SCHEDULE = 'mhmrentiva_5min'; // Changed to 5min to match DatabaseInitialization

	/**
	 * Payment statuses that disqualify a booking from the past-pickup sweep.
	 *
	 * This one stays a DENYLIST, deliberately, and the asymmetry with the
	 * booking-status allowlist next to it is the point. The difference is not
	 * that one key is more exposed than the other -- both are plain post meta
	 * and nothing in this plugin filters either. The difference is that one
	 * key has an enumerated set to derive from and this one does not.
	 * `_mhmrentiva_status` has `Status::allowed()` and a transition matrix, so
	 * an allowlist can be computed and will follow the matrix on its own (see
	 * selectable_status_values() below). `_mhmrentiva_payment_status` has no
	 * enumeration at all: no constant lists its values, no filter publishes
	 * them, and the writers scattered through src/ spell five between them
	 * (`paid`, `pending`, `refunded`, `partially_refunded`, `cancelled`) while
	 * the queries already read two the writers never produce -- `completed` in
	 * the list below, and `pending_payment` in sweep #1. Any allowlist here
	 * could only be hand-maintained, and a hand-maintained allowlist on a
	 * destructive sweep fails the wrong way: a value nobody remembered to add
	 * disqualifies the booking forever and the sweep quietly shrinks. A
	 * hand-maintained denylist fails the other way -- an unnamed value is
	 * swept, which for the question this clause asks ("was this booking never
	 * paid?") is the sweep doing its job. Spec v3 §7.2.3 narrows this key by
	 * naming an addition (`partially_refunded`), not by inverting the compare.
	 *
	 * `partially_refunded` is that addition: the operator refunded part of the
	 * money and deliberately kept the rest as a cancellation fee, which is a
	 * settled outcome, not "unpaid". Reading it as unpaid sent this sweep
	 * after a booking somebody had already dealt with by hand.
	 */
	private const SETTLED_PAYMENT_STATUSES = array( 'paid', 'completed', 'refunded', 'cancelled', 'partially_refunded' );

	public static function register(): void
	{
		// Add custom schedules - register immediately so it's available when wp_schedule_event is called
		// The filter is lazy-loaded, so it will be applied when wp_get_schedules() is called
		// Use priority 1 to ensure it's registered before other plugins
		add_filter('cron_schedules', array( self::class, 'schedules' ), 1);

		// Also ensure it's registered on plugins_loaded if not already
		if (! did_action('plugins_loaded')) {
			add_action(
				'plugins_loaded',
				function () {
					add_filter('cron_schedules', array( self::class, 'schedules' ), 1);
				},
				1
			);
		}

		// Schedule event if not scheduled
		add_action('init', array( self::class, 'maybe_schedule' ), 100);

		// Hook runner
		add_action(self::EVENT, array( self::class, 'run' ));
	}

	public static function schedules(array $schedules): array
	{
		if (! isset($schedules['mhmrentiva_5min'])) {
			$schedules['mhmrentiva_5min'] = array(
				'interval' => 300, // 5 min
				'display'  => __('Every 5 Minutes (Rentiva)', 'mhm-rentiva'),
			);
		}

		if (! isset($schedules['mhmrentiva_15min'])) {
			$schedules['mhmrentiva_15min'] = array(
				'interval' => 900, // 15 min
				'display'  => __('Every 15 Minutes (Rentiva)', 'mhm-rentiva'),
			);
		}

		return $schedules;
	}

	public static function maybe_schedule(): void
	{
		// Ensure schedule filter is applied before checking schedules
		add_filter('cron_schedules', array( self::class, 'schedules' ), 1);

		// Get schedules (this will trigger the filter)
		$schedules = wp_get_schedules();

		if (! isset($schedules[ self::SCHEDULE ])) {
			// Task 14b item 3: promoted from warning() to error() -- this
			// runs on every 'init' (priority 100), so if it ever fires for
			// real, the auto-cancel deadline sweep and the auto-complete
			// sweep never get scheduled at all, on every page load, with no
			// operator-visible sign why. warning() is dropped under the
			// default mhmrentiva_log_level of 'error'; the failure this
			// guards is worth more than that default.
			AdvancedLogger::error_linked(
				sprintf(
					/* translators: 1: the custom cron schedule slug that is missing, 2: the schedule slugs that ARE registered. */
					__( 'Custom schedule not found (schedule: %1$s, available: %2$s)', 'mhm-rentiva' ),
					self::SCHEDULE,
					implode( ', ', array_keys( $schedules ) )
				),
				0,
				array(
					'schedule'  => self::SCHEDULE,
					'available' => array_keys($schedules),
				)
			);
			return;
		}

		// If already scheduled, check if it's using the correct schedule
		$next_scheduled = wp_next_scheduled(self::EVENT);
		if ($next_scheduled) {
			// FIX: Check for timezone issue (UTC vs Local)
			// If the event is scheduled far in the future (> 10 mins), it's likely using Local Time (UTC+3)
			// WP-Cron runs on UTC, so this would delay execution by 3 hours.
			if ($next_scheduled > ( time() + 600 )) {
				// Task 14b item 3: left at warning() deliberately -- this is
				// a detected-and-corrected drift, not a failure (the very
				// next lines unschedule and let the code below reschedule
				// correctly), so dropping it under the default log level is
				// the right amount of silence for a self-healing branch.
				AdvancedLogger::warning('Timezone mismatch detected (Event > 10min in future). Unscheduling to fix.');
				wp_unschedule_event($next_scheduled, self::EVENT);
				$next_scheduled = false;
			} else {
				$current_schedule = wp_get_schedule(self::EVENT);
				// If schedule is wrong, unschedule and reschedule
				if ($current_schedule !== self::SCHEDULE) {
					wp_unschedule_event($next_scheduled, self::EVENT);
					$next_scheduled = false; // Force reschedule
				} else {
					// Verify the schedule is still valid
					$verify_schedule = wp_get_schedule(self::EVENT);
					if ($verify_schedule === self::SCHEDULE) {
						return; // Already scheduled correctly
					}
					// Schedule is invalid, unschedule it
					wp_unschedule_event($next_scheduled, self::EVENT);
					$next_scheduled = false;
				}
			}
		}

		// Double-check schedule exists before scheduling
		// Force filter application by calling wp_get_schedules() multiple times
		$schedules = wp_get_schedules();
		if (! isset($schedules[ self::SCHEDULE ])) {
			AdvancedLogger::error('Schedule not available when attempting to schedule event', array(
				'schedule'  => self::SCHEDULE,
				'available' => array_keys($schedules),
			));
			return;
		}

		// Verify schedule details
		$schedule_info = $schedules[ self::SCHEDULE ];
		if (! isset($schedule_info['interval']) || $schedule_info['interval'] !== 300) {
			AdvancedLogger::error('Schedule has incorrect interval', array(
				'schedule' => self::SCHEDULE,
				'interval' => $schedule_info['interval'] ?? 'missing',
			));
			return;
		}

		// Use direct cron array manipulation to avoid WordPress's schedule validation
		// This bypasses the invalid_schedule error that occurs when wp_schedule_event()
		// checks the schedule before the filter is applied
		self::direct_schedule_event();
	}

	public static function run(): void
	{
		// Read from unified settings array
		$enabled = (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_booking_auto_cancel_enabled', '0') === '1';

		if (! $enabled) {
			return;
		}

		// Use Booking Management setting: payment deadline minutes
		$minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_booking_payment_deadline_minutes', 30); // Default 30 min
		if ($minutes < 5) {
			$minutes = 5; // Minimum 5 minutes
		}

		// Reasonable batch limit
		$limit = 50;

		// 1. Get current UTC timestamp
		$current_utc_ts = time();

		// 2. Subtract deadline minutes (Minutes -> Seconds)
		$deadline_ts = $current_utc_ts - ( $minutes * 60 );

		// 3. Convert to MySQL format (Local time)
		// wp_date will automatically apply the site's timezone setting to the standard UTC timestamp
		$deadline_str = wp_date('Y-m-d H:i:s', $deadline_ts);

		// Find unpaid bookings created before the time limit
		$q = new WP_Query(
			array(
				'post_type'      => 'mhmrentiva_booking',
				'post_status'    => 'any', // Check all statuses to be safe, filter later
				'fields'         => 'ids',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'column'    => 'post_date',
						'before'    => $deadline_str, // "Older than this local time"
						'inclusive' => true,
					),
				),
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_mhmrentiva_payment_status',
						'value'   => array( 'pending', 'pending_payment' ),
						'compare' => 'IN',
					),
					array(
						'key'     => '_mhmrentiva_status',
						'value'   => array( 'pending', 'pending_payment' ), // Also check booking status
						'compare' => 'IN',
					),
					self::not_parked_for_review(),
				),
			)
		);

		if (! $q->have_posts()) {
			return;
		}

		foreach ($q->posts as $bid) {
			self::cancel_booking_with_orders(
				(int) $bid,
				'Payment deadline expired (' . $minutes . ' minutes)'
			);
		}
		wp_reset_postdata();

		// Second sweep: bookings whose pickup_date is already in the past but
		// were never paid. These escape the deadline-based sweep when the
		// payment_deadline_minutes setting was changed, when meta keys were
		// missing on legacy bookings, or when cron was offline at the time.
		self::sweep_past_pickup_unpaid();
	}

	/**
	 * Sweep bookings whose pickup_date is in the past but payment_status is
	 * still pending. Idempotent — re-running is safe.
	 *
	 * @param int $limit Maximum bookings to process per run.
	 * @return int Number of bookings cancelled in this sweep.
	 */
	private static function sweep_past_pickup_unpaid( int $limit = 50 ): int {
		$today = wp_date( 'Y-m-d' );
		$q     = new WP_Query(array(
			'post_type'      => 'mhmrentiva_booking',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_mhmrentiva_pickup_date',
					'value'   => $today,
					'compare' => '<',
					'type'    => 'DATE',
				),
				array(
					'key'     => '_mhmrentiva_payment_status',
					// Denylist on purpose; see SETTLED_PAYMENT_STATUSES for why
					// this key and the one below take opposite compares.
					'value'   => self::SETTLED_PAYMENT_STATUSES,
					'compare' => 'NOT IN',
				),
				array(
					'key'     => '_mhmrentiva_status',
					// Allowlist, derived rather than written out;
					// selectable_status_values() carries the reasoning and is
					// the only place the set is decided.
					'value'   => self::selectable_status_values(),
					'compare' => 'IN',
				),
				self::not_parked_for_review(),
			),
		));

		$count = 0;
		foreach ( $q->posts as $bid ) {
			self::cancel_booking_with_orders( (int) $bid, 'Pickup date passed without payment' );
			++$count;
		}
		wp_reset_postdata();
		return $count;
	}

	/**
	 * The booking statuses the past-pickup sweep is allowed to select.
	 *
	 * Derived from the transition matrix, never written out. This sweep's
	 * only outcome is `Status::update_status( $bid, CANCELLED )`, so the set
	 * of statuses worth selecting is exactly the set with a CANCELLED edge,
	 * and `Status::can_transition()` already decides that.
	 *
	 * The reason to derive it is that the hand-written form drifted once and
	 * nothing stopped it drifting again. Two generations of that form, both
	 * measured. The first was `NOT IN ('cancelled','refunded')`, and it was
	 * the drifted one: it did not name `completed`, so a completed booking was
	 * selected and refused on every tick, and the refusal logs at warning
	 * level, which the default `mhmrentiva_log_level` of 'error' drops -- a
	 * silent loop. The second added `completed` and was correct, and it is the
	 * one this method replaces; the drift is not what it is being replaced
	 * for. What it is being replaced for is what getting there cost: somebody
	 * had to notice, by hand, and the corrected set then had to be spelled out
	 * a second time in a comment beside the query. Deriving it means an edge
	 * cannot be added to or taken out of the matrix without this query
	 * following.
	 *
	 * An ALLOWLIST, per spec v3 §7.2.3 ("booking durumu seçimi CANCELLED
	 * kenarı olanlarla sınırlanır"). The consequence is deliberate: a booking
	 * whose `_mhmrentiva_status` holds a value this plugin does not recognise
	 * is no longer swept. A denylist read every unknown value as "safe to
	 * cancel", and the damage was not hypothetical -- `Status::get()` coerces
	 * an unrecognised value to PENDING, PENDING has a CANCELLED edge, so the
	 * sweep cancelled the booking and overwrote the one record of the state
	 * nobody could read. A destructive unattended job does not get to act on
	 * a booking it cannot describe. The write end of that hole is a separate,
	 * tracked defect (`ajax_create_booking` stores the requested status with
	 * no allowlist of its own); this clause is the read end.
	 *
	 * "Cannot describe" is the whole of it, and it is narrower than "is not in
	 * this list": an EMPTY value is one the plugin describes perfectly well.
	 * selectable_status_values() is where that distinction is applied -- this
	 * method stays the matrix question and nothing else.
	 *
	 * @return array<int, string>
	 */
	private static function cancellable_statuses(): array {
		return array_values(
			array_filter(
				Status::allowed(),
				static fn ( string $status ): bool => Status::can_transition( $status, Status::CANCELLED )
			)
		);
	}

	/**
	 * The `_mhmrentiva_status` meta VALUES the past-pickup sweep may select on.
	 *
	 * Its sibling cancellable_statuses() answers a question about the matrix:
	 * which statuses have a CANCELLED edge. This one answers a question about
	 * storage: which stored values resolve to one of those statuses. They are
	 * not the same set, because `''` is a value this key really holds and it
	 * is not a status at all; it is the absence of one.
	 *
	 * Every canonical reader in this plugin resolves that absence to PENDING.
	 * `Status::get()` returns PENDING for anything outside `Status::allowed()`
	 * (Status.php:39), `DashboardService::get_status_breakdown()` buckets it
	 * with `COALESCE(NULLIF(pm.meta_value, ''), 'pending')` and its comment
	 * warns about exactly this trap, and `BookingColumns` filters on the same
	 * priority. Dropping `''` here would make this the one place in the plugin
	 * that reads the key differently from the rest of it, and it would leave
	 * such a booking with no sweep at all: sweep #1's own `_mhmrentiva_status`
	 * clause is `IN ('pending','pending_payment')`, which does not match an
	 * empty value either.
	 *
	 * So the empty value is included exactly when the status it resolves to is
	 * included: take PENDING's CANCELLED edge out of the matrix and this drops
	 * out with it, no edit needed. The one thing not derived is WHICH status
	 * the fallback lands on -- `Status::get()` names PENDING in a literal and
	 * offers no accessor for it, so this restates the constant rather than
	 * inventing an accessor for one caller. `DashboardService` restates the
	 * same fallback in its COALESCE for the same reason; a third shape would
	 * be the worse answer.
	 *
	 * A flat IN list rather than an OR of two clauses, and that was measured
	 * on WP 7.1: both shapes produce the same five LEFT JOINs (core reuses the
	 * alias for same-key siblings under OR), but the OR shape restates
	 * `mt2.meta_key = '_mhmrentiva_status'` inside the OR group for no gain.
	 * One predicate is the smaller thing to read and the smaller thing to get
	 * wrong.
	 *
	 * @return array<int, string>
	 */
	private static function selectable_status_values(): array {
		$values = self::cancellable_statuses();

		// Status::get()'s fallback for an unrecognised or empty stored value.
		if ( in_array( Status::PENDING, $values, true ) ) {
			$values[] = '';
		}

		return $values;
	}

	/**
	 * The meta_query clause both sweeps use to skip bookings a human already
	 * owns.
	 *
	 * A park writes only `_mhmrentiva_refund_status`; the booking status and
	 * payment status stay exactly as they were, which is precisely what keeps
	 * both sweep queries selecting the booking on every following tick while
	 * somebody works on it (spec v3 §7.2.2). The transition matrix already
	 * makes the second visit harmless -- `needs_review -> needs_review`
	 * returns false, so no notification and no event -- but harmless is not
	 * the same as absent: a re-selection that cannot take the booking's refund
	 * lock logs an error every five minutes about a booking that is in exactly
	 * the state it should be in.
	 *
	 * NOT EXISTS is the first half of the OR, and the `!=` on its own would
	 * not do. Core builds a `!=` clause with an INNER JOIN whose ON condition
	 * is the post id and nothing else (class-wp-meta-query.php:609-611); the
	 * meta_key restriction is emitted into WHERE instead (:669-671), which
	 * lands it inside this group's OR. A booking that has never been through
	 * a refund flow therefore has no `_mhmrentiva_refund_status` row for that
	 * WHERE half to match, and the clause drops it -- which is nearly every
	 * booking, measured.
	 *
	 * The NOT EXISTS half also does something less obvious, worth naming
	 * before somebody "simplifies" it away: a single NOT EXISTS clause
	 * anywhere in a meta_query makes core rewrite EVERY INNER JOIN in that
	 * query to a LEFT JOIN (:374-378, core's own comment: "Otherwise posts
	 * with no metadata will be excluded"). Both sweeps still pin meta_key AND
	 * meta_value in WHERE for their other clauses, so a missing row still
	 * fails them and the selection is unchanged -- but the query being run is
	 * not the one the array on the page looks like.
	 *
	 * Core line anchors are against the RUNNING install, WordPress 7.1 in the
	 * dev container. The wp/ tree at the root of the dev stack is an older
	 * copy and these lines sit elsewhere in it.
	 *
	 * The key type is int|string because a meta_query clause group is exactly
	 * that shape: a string-keyed `relation` alongside numerically indexed
	 * sub-clauses.
	 *
	 * Task 12 correction #3 (slice 5) widened the second half from `!=
	 * needs_review` to `NOT IN (parked_refund_statuses())`. Measured: a
	 * booking this sweep excludes because it is `needs_review` can be closed
	 * by an operator to `not_required` (Task 12's own review_dismiss()) and
	 * fall right back into an ordinary unpaid, past-deadline selection on the
	 * very next tick -- `not_required` is a plain string to this clause, not
	 * a status the matrix still has an edge for. What happened next, also
	 * measured: cancel_booking_with_orders() finds the paid order again and
	 * tries `RefundStatus::transition( ..., NEEDS_REVIEW )`, but the matrix
	 * has no key for a terminal `$from` (matrix() above), so transition()
	 * returns false and the whole `if ( $moved )` block -- notification, log,
	 * event -- is skipped in total silence. The operator's decision and the
	 * sweep disagree forever, and nothing records either half.
	 *
	 * The fix is the same shape as cancellable_statuses(): stop excluding one
	 * literal and start excluding every status a human or a flow could only
	 * have reached by already closing the money question --
	 * RefundStatus::terminalStates(), which is derived from matrix() rather
	 * than written out here, so an edge added to or removed from the matrix
	 * cannot silently stop matching this clause. `needs_review` itself is
	 * added back in explicitly: it is NOT terminal (it has an edge, to
	 * PENDING and to NOT_REQUIRED), so terminalStates() alone would not cover
	 * the original case this clause exists for.
	 *
	 * The declared cost, stated once and not hidden: a booking whose
	 * refund_status is terminal, unpaid, and past its pickup date or payment
	 * deadline is no longer auto-cancelled by either sweep. A terminal
	 * refund_status only exists once a human or a flow has already closed
	 * that booking's money question -- and Task 12 hands the operator the
	 * cancel button for exactly this shape (review_cancel_and_refund()), so
	 * the booking is not stranded, only no longer unattended. See
	 * AutoCancelSweepSelectionTest for the negative control that pins this
	 * set and proves it is not larger than the four terminal states plus
	 * needs_review.
	 *
	 * @return array<int|string, string|array<string, string|array<int, string>>>
	 */
	private static function not_parked_for_review(): array {
		return array(
			'relation' => 'OR',
			array(
				'key'     => RefundStatus::META_KEY,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => RefundStatus::META_KEY,
				'value'   => self::parked_refund_statuses(),
				'compare' => 'NOT IN',
			),
		);
	}

	/**
	 * `_mhmrentiva_refund_status` values a human already owns: the one
	 * awaiting review, and every state that has no exit left at all.
	 *
	 * NEEDS_REVIEW is not itself terminal (matrix() gives it an edge to
	 * PENDING and to NOT_REQUIRED), so it is named here explicitly rather
	 * than folded into RefundStatus::terminalStates(); the terminal set is
	 * added, not enumerated, for the reasons on not_parked_for_review()
	 * above.
	 *
	 * @return array<int, string>
	 */
	private static function parked_refund_statuses(): array {
		return array_merge( array( RefundStatus::NEEDS_REVIEW ), RefundStatus::terminalStates() );
	}

	/**
	 * Centralised cancellation for a single booking: updates booking meta,
	 * sends notification email, cancels both linked WC orders (deposit +
	 * remaining), clears availability cache and fires the
	 * `mhmrentiva_booking_auto_cancelled` action.
	 *
	 * Used by both cron sweeps and backfill helpers so the cancellation
	 * side-effects stay identical.
	 *
	 * @param int    $bid    Booking post ID.
	 * @param string $reason Human-readable reason persisted on the booking.
	 */
	private static function cancel_booking_with_orders( int $bid, string $reason ): void {
		try {
			// Lookup chain mirrors ReportRepository / RemainingPaymentHandler —
			// historical key drift left several aliases in production data.
			$wc_orders_to_cancel = array_filter(array(
				\MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id($bid),
				(int) get_post_meta($bid, '_mhmrentiva_remaining_order_id', true),
			));

			// K6: no unattended path moves money. A paid order inside the cancel
			// set is either a data inconsistency or a real refund obligation, and
			// neither may be settled by a sweep whose trigger can itself be a bug
			// (the on-hold chain was exactly that). Park it for a human instead.
			if (self::has_paid_order($wc_orders_to_cancel)) {
				if (RefundLock::acquire($bid)) {
					try {
						if (RefundStatus::transition($bid, RefundStatus::NEEDS_REVIEW, array( 'surface' => 'auto_cancel' ))) {
							// The bool is load-bearing: the notification
							// returns false without throwing when admin_email
							// does not validate (NotificationHelper.php:64-66)
							// and when wp_mail() fails, and src/ registers no
							// wp_mail_failed listener. Discarding it parks the
							// booking, drops the e-mail and records nothing --
							// and this failure hides better than the lock
							// refusal below, because the durable half (the
							// status write, its event) all succeeded.
							$helper_exists = class_exists('\MHMRentiva\Helpers\NotificationHelper');
							$notified      = $helper_exists
								&& \MHMRentiva\Helpers\NotificationHelper::send_refund_needs_review_email($bid);

							if (! $notified) {
								// Task 14b item 1: was error() + add(), same
								// pair and same reasons as the lock-refusal
								// branch below; one call now covers both.
								//
								// Task 14b item 7: the reason used to read
								// 'notification_failed' even when $helper_exists
								// was false -- the same operator-visible
								// silence, but a different, misstated cause.
								// Practically unreachable (NotificationHelper
								// ships in the Lite manifest), kept honest
								// anyway since it is the exact class item 2
								// exists for.
								AdvancedLogger::error_linked(
									sprintf(
										/* translators: 1: booking id, 2: the surface (auto_cancel) that parked this booking for review. */
										__( 'Refund review notification failed for booking #%1$d (surface: %2$s): the booking is parked in needs_review, but the notification e-mail could not be sent -- no one has been told that it is holding paid money.', 'mhm-rentiva' ),
										$bid,
										'auto_cancel'
									),
									$bid,
									array(
										'surface' => 'auto_cancel',
										'reason'  => $helper_exists ? 'notification_failed' : 'helper_missing',
									),
									AdvancedLogger::CATEGORY_SYSTEM
								);
							}
						}
					} finally {
						RefundLock::release($bid);
					}
				} else {
					// Without this the whole park-and-notify block is skipped
					// in silence: no status, no email, no trace. A lock left
					// behind by a request that died blocks both the
					// notification and the cancellation until its TTL expires
					// and tells the operator nothing (ruling T2-R3). The
					// sibling Status::update_status() refusal below used to
					// be a counter-example -- it called warning(), which the
					// default log level drops -- until Task 14b item 3
					// promoted it too, below.
					//
					// Task 14b item 1: was error() + add(); merged into one
					// call. The message still names both possibilities
					// rather than asserting one (item 2's own fix elsewhere
					// in this slice): acquire() returns false identically
					// for a row genuinely still held and one it refuses to
					// steal because it has not hit RefundLock's TTL yet
					// (RefundLock.php:198-221).
					AdvancedLogger::error_linked(
						sprintf(
							/* translators: 1: booking id, 2: the surface (auto_cancel) that could not park this booking for review. */
							__( 'Refund lock refused for booking #%1$d (surface: %2$s): the booking holds paid money and could not park the booking for review -- its refund lock could not be taken. Another refund operation may still be running, or a lock left behind by one that did not finish is waiting to expire.', 'mhm-rentiva' ),
							$bid,
							'auto_cancel'
						),
						$bid,
						array(
							'surface' => 'auto_cancel',
							'reason'  => 'lock_refused',
						),
						AdvancedLogger::CATEGORY_SYSTEM
					);
				}

				return;
			}

			if (! Status::update_status($bid, Status::CANCELLED, 0)) {
				// The transition matrix refused (e.g. a COMPLETED booking has no
				// CANCELLED edge). Writing the meta directly would have made the
				// booking "cancelled" without the transition check and without
				// mhmrentiva_booking_status_changed, so the rest of the plugin
				// would never learn about it.
				//
				// Task 14b item 3: promoted from warning() to error(). Both
				// sweeps' own queries are built to exclude anything without a
				// CANCELLED edge (selectable_status_values()), so this branch
				// firing at all means a booking was selected AND refused --
				// exactly the "silent infinite loop" AutoCancelSweepSelectionTest
				// documents: at the previous warning() level, that loop left
				// no trace on a stock install, tick after tick.
				AdvancedLogger::error_linked(
					sprintf(
						/* translators: %s: the booking's current status, which has no outgoing edge to CANCELLED. */
						__( 'Auto-cancel refused for booking: no transition from %s', 'mhm-rentiva' ),
						Status::get($bid)
					),
					$bid,
					array(),
					'system'
				);

				return;
			}

			$new_status = Status::CANCELLED;
			update_post_meta($bid, '_mhmrentiva_payment_status', 'cancelled');
			update_post_meta($bid, '_mhmrentiva_auto_cancelled', current_time('timestamp'));
			update_post_meta($bid, '_mhmrentiva_auto_cancelled_reason', $reason);

			if (class_exists('\MHMRentiva\Helpers\NotificationHelper')) {
				\MHMRentiva\Helpers\NotificationHelper::send_auto_cancel_email($bid);
			}

			if ($wc_orders_to_cancel && function_exists('wc_get_order')) {
				foreach (array_unique($wc_orders_to_cancel) as $oid) {
					$order = call_user_func('\wc_get_order', $oid);
					if ($order && $order->has_status(array( 'pending', 'on-hold', 'failed', 'processing' ))) {
						$order->update_status('cancelled', __('Reservation time expired.', 'mhm-rentiva'));
					}
				}
			}

			$vehicle_id = (int) get_post_meta($bid, '_mhmrentiva_vehicle_id', true);
			if ($vehicle_id && class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
				\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
			}

			if (class_exists(AdvancedLogger::class)) {
				AdvancedLogger::info(
					"Booking #$bid auto-cancelled.",
					array(
						'booking_id' => $bid,
						'reason'     => $reason,
					),
					'system'
				);
			}

			do_action('mhmrentiva_booking_auto_cancelled', $bid, $new_status);
		} catch (\Throwable $e) {
			// Routed to the plugin's own logger instead of the site's PHP error
			// log, so the record is visible in the admin Logs screen and a
			// distributed plugin does not write to a file the owner never opted into.
			//
			// Task 14b item 3: promoted from warning() to error(). A
			// per-booking exception here means the sweep silently never
			// cancelled a booking it selected -- the same shape as
			// AutoComplete.php's sibling catch (a separate file, same
			// pattern) -- and warning() is exactly the level a stock
			// install drops.
			if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
					sprintf(
						/* translators: %s: the throwable message that interrupted this booking's auto-cancel. */
						__( 'Auto-cancel skipped a booking: %s', 'mhm-rentiva' ),
						$e->getMessage()
					),
					$bid,
					array( 'error' => $e->getMessage() ),
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
				);
			}
		}
	}

	/**
	 * Has money already changed hands on this one order?
	 *
	 * Checked via get_date_paid() rather than has_status(): a status check
	 * would miss an order sitting in `on-hold` or `refunded` after having
	 * been paid once — both are still "money changed hands", which is the
	 * fact this predicate exists to protect. It would also read `processing`
	 * as safe to cancel, which is the status of a typical PAID order.
	 *
	 * One predicate, one home: sync_orphan_wc_orders() asks the same question
	 * about an order object it is already holding, and has_paid_order() asks
	 * it about a set of ids. Two copies of the get_date_paid() rule is the
	 * defect class this slice exists to remove, so both callers come here.
	 *
	 * Strictly `WC_Order`, and both callers step past anything else with a
	 * `continue` before they reach here. That is not defensive style, it is
	 * the only shape this question can be asked in. `wc_get_order()` has two
	 * other return shapes -- `false` for an id that no longer resolves, and
	 * `WC_Order_Refund` for a refund id -- and `WC_Order_Refund extends
	 * WC_Abstract_Order`, NOT `WC_Order` (WooCommerce 11.0.1,
	 * includes/class-wc-order-refund.php:17), so handing either one to this
	 * parameter is a TypeError.
	 *
	 * Widening the parameter to swallow them would be worse than the crash.
	 * This is a money gate, and its PERMISSIVE answer is `false`: "there is
	 * no order here" would come back as "nobody has paid", which is precisely
	 * the green light to cancel. A caller holding something that is not a
	 * WC_Order has not asked this question and must not be handed an answer
	 * to it.
	 */
	private static function is_paid( \WC_Order $order ): bool {
		return (bool) $order->get_date_paid();
	}

	/**
	 * Does any candidate order already carry a payment?
	 *
	 * @param array<int, int> $order_ids
	 */
	private static function has_paid_order( array $order_ids ): bool {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		foreach ( array_unique( $order_ids ) as $oid ) {
			$order = wc_get_order( $oid );

			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			if ( self::is_paid( $order ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * One-time backfill: cancel WC orders left in pending/on-hold for bookings
	 * that were already auto-cancelled (or whose status is `cancelled`) but
	 * whose WC order was never synced. Handles the historical key-drift bug
	 * where AutoCancel only checked the wrong meta key.
	 *
	 * Idempotent — re-running is safe; only touches orders still in pending
	 * statuses.
	 *
	 * Task 16 minor 3 (fix round 1, C1): a booking whose refund_status is
	 * already needs_review or terminal is still SELECTED and still walked --
	 * its `checked` and `cancelled` counts are unaffected -- because an
	 * unpaid candidate order (e.g. a remaining-payment order that was never
	 * paid, left behind after the booking's OWN refund_status already closed
	 * through a different order entirely) still needs the same
	 * pending/on-hold/failed cleanup this method exists for. Only the
	 * RE-PARK decision below is skipped for such a booking: before this fix,
	 * every re-run of this backfill against an already-parked/terminal
	 * booking called park_paid_booking_for_review() again, which found the
	 * matrix has no NEEDS_REVIEW -> NEEDS_REVIEW self-edge and logged an
	 * ERROR every single time -- a stable steady state that read as a fresh
	 * failure forever. That booking is now counted as `$skipped` (nothing
	 * new to do about paid money a human already owns) instead of
	 * re-attempting the park and erroring. An earlier version of this fix
	 * excluded such bookings from the query entirely, which also hid their
	 * unpaid candidate orders from the cancel loop -- corrected; see
	 * AutoCancelSweepSelectionTest's sync_orphan_wc_orders tests for both
	 * halves pinned separately.
	 *
	 * Usage:
	 *   wp eval 'echo MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel::sync_orphan_wc_orders();'
	 *
	 * Task 14b item 5: a booking with AT LEAST ONE paid candidate order --
	 * not only when every candidate is paid; fix round 1 F3 closed exactly
	 * the mixed deposit-plus-remaining pair, one paid and one not -- is no
	 * longer just $skipped -- it is parked in needs_review and a human is
	 * notified, the same K6 guard cancel_booking_with_orders() uses, via
	 * park_paid_booking_for_review(). `$skipped` now covers three shapes:
	 * the genuinely nothing-to-do cases (no candidate order ids at all, or
	 * every candidate already in a settled status this backfill has no
	 * reason to touch); a paid-order booking whose park attempt was refused
	 * (RefundLock held, or the status matrix refused NEEDS_REVIEW for a
	 * reason other than already owning it); and, since Task 16 minor 3, a
	 * paid-order booking whose refund_status already reads needs_review or
	 * terminal, where re-parking is deliberately not attempted. All three
	 * are indistinguishable in this one counter.
	 *
	 * @return array{checked: int, cancelled: int, skipped: int, parked: int}
	 */
	public static function sync_orphan_wc_orders(): array
	{
		$checked   = 0;
		$cancelled = 0;
		$skipped   = 0;
		$parked    = 0;

		if (! function_exists('wc_get_order')) {
			return compact('checked', 'cancelled', 'skipped', 'parked');
		}

		// Fix round 1, C1: this query deliberately does NOT exclude a booking
		// whose refund_status is already needs_review or terminal (an earlier
		// version of this fix ANDed in not_parked_for_review() here, which
		// also hid the booking from the order-cancelling loop below -- a
		// candidate order still sitting in pending/on-hold/failed, e.g. a
		// never-paid remaining-payment order left behind after the deposit's
		// OWN refund already closed the booking's refund_status, must still
		// be reachable and cancelled by this backfill. Only the re-park
		// decision further down is guarded against an already-owned
		// refund_status; selection is not).
		$q = new \WP_Query(array(
			'post_type'      => 'mhmrentiva_booking',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_mhmrentiva_status',
					'value'   => 'cancelled',
					'compare' => '=',
				),
				array(
					'key'     => '_mhmrentiva_auto_cancelled',
					'compare' => 'EXISTS',
				),
			),
		));

		foreach ($q->posts as $bid) {
			++$checked;
			$bid = (int) $bid;

			$candidate_ids = array_filter(array(
				\MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id($bid),
				(int) get_post_meta($bid, '_mhmrentiva_remaining_order_id', true),
			));

			if (! $candidate_ids) {
				++$skipped;
				continue;
			}

			// Each booking can have BOTH a deposit order and a remaining order;
			// check & cancel them independently so a deposit already cancelled
			// in a previous pass doesn't cause the remaining order to be missed.
			$booking_touched = false;
			// Task 14b item 5: tracked separately from $booking_touched so a
			// paid candidate can be told apart from "nothing here needed
			// touching at all" (an already-cancelled/refunded/completed
			// order, or an id that no longer resolves) -- only the paid case
			// is the K6 money question this park exists for.
			$has_paid_order = false;
			foreach (array_unique($candidate_ids) as $oid) {
				$order = call_user_func('\wc_get_order', $oid);
				// One statement where there used to be a falsy check here and
				// a second, inline instanceof narrowing further down, at the
				// is_paid() call.
				// wc_get_order() answers `false` for an unresolvable id and a
				// WC_Order_Refund for a refund id, and this loop may act on
				// neither -- WC_Order_Refund does not even define
				// update_status() (measured, WooCommerce 11.0.1), so reaching
				// the cancel call with one would be fatal. Behaviour is
				// unchanged: a refund object already fell through to the
				// has_status() gate below and was skipped there, its status
				// being `completed`. It is now skipped before any money
				// question is asked about it, which is the difference between
				// a gate and a coincidence.
				if (! $order instanceof \WC_Order) {
					continue;
				}
				// K6, restated here because this body never passes through
				// cancel_booking_with_orders() and so never meets its guard.
				// The has_status() gate below is not one: `processing` is the
				// status of a typical PAID order, so without this line an
				// operator running the backfill by hand could cancel an order
				// somebody had paid for. Measured, not hypothetical.
				if (self::is_paid($order)) {
					$has_paid_order = true;
					continue;
				}
				if (! $order->has_status(array( 'pending', 'on-hold', 'failed', 'processing' ))) {
					continue;
				}
				$order->update_status('cancelled', __('Booking auto-cancelled — orphan WC order backfill.', 'mhm-rentiva'));
				++$cancelled;
				$booking_touched = true;
			}

			// Fix round 1, F3: checked independently of $booking_touched.
			// Before this fix, a booking with ONE paid and ONE unpaid
			// candidate order (a deposit-plus-remaining pair, the exact
			// shape the comment above this loop names) was never parked
			// and left no trace at all: the unpaid sibling's cancellation
			// set $booking_touched = true, so the `if (! $booking_touched)`
			// guard skipped both branches below entirely, and the booking
			// was counted only as an order-level $cancelled success --
			// reading as a clean outcome while a paid order sat untouched
			// with nobody told. Measured: this booking's OWN
			// `_mhmrentiva_status` is already 'cancelled' or carries
			// `_mhmrentiva_auto_cancelled` (the query's own selection
			// criterion, above), so parking it in NEEDS_REVIEW here
			// describes the still-open refund question about the paid
			// sibling -- it does not conflict with, or reopen, the
			// cancellation the unpaid sibling's own order status already
			// reflects.
			if ($has_paid_order) {
				// Task 16 minor 3 (fix round 1, C1): a booking whose
				// refund_status is already needs_review or terminal is a
				// human's or a closed flow's own decision -- re-attempting
				// park_paid_booking_for_review() here would only re-trip
				// RefundStatus::transition()'s missing NEEDS_REVIEW ->
				// NEEDS_REVIEW self-edge and log an ERROR on every single
				// re-run of this backfill against the same booking, forever.
				// Guards ONLY the park attempt, not selection above: the
				// order-cancelling arm two blocks up already ran for this
				// booking's OTHER candidate orders regardless of this check.
				if (in_array(RefundStatus::get($bid), self::parked_refund_statuses(), true)) {
					++$skipped;
				} elseif (self::park_paid_booking_for_review($bid, 'sync_orphan_wc_orders')) {
					// Task 14b item 5 (T5-R4): this used to fall straight into
					// $skipped with nothing an operator could see -- K6 was
					// upheld (no money moved) but the asymmetry with
					// cancel_booking_with_orders()'s own paid-order guard,
					// which parks and notifies, was exactly this task's own
					// subject. Applying the sibling's pattern here too.
					//
					// Fix round 1, F2: only counted as $parked when the
					// booking was ACTUALLY parked. park_paid_booking_for_review()
					// used to be void and this incremented unconditionally,
					// so a lock refusal or a matrix refusal both still
					// counted as "parked" -- a summary stating something the
					// method could not know, the exact defect class this
					// task exists to remove, reintroduced inside its own fix.
					++$parked;
				} else {
					++$skipped;
				}
			} elseif (! $booking_touched) {
				++$skipped;
			}
		}

		if (class_exists(AdvancedLogger::class)) {
			AdvancedLogger::info(
				'Orphan WC order backfill completed.',
				compact('checked', 'cancelled', 'skipped', 'parked'),
				'system'
			);
		}

		return compact('checked', 'cancelled', 'skipped', 'parked');
	}

	/**
	 * Parks a booking for manual review because a sweep found paid money it
	 * may not touch on its own (K6) -- mirrors cancel_booking_with_orders()'s
	 * own guard for the exact same reason (see its docblock, above). Task
	 * 14b item 5: sync_orphan_wc_orders() used to fall straight into its
	 * $skipped counter on exactly this path, with no status change, no
	 * notification, and no log -- the asymmetry with the cron sweep this
	 * method now closes.
	 *
	 * Deliberately its own method rather than a refactor of
	 * cancel_booking_with_orders()'s inline block: that block is exercised by
	 * this slice's own K6 tests (AutoCancelLeavesPaidMoneyAloneTest) and this
	 * task's scope is visibility, not restructuring code that already works.
	 *
	 * @param int    $bid     Booking post ID.
	 * @param string $surface Recorded on the transition and used in the
	 *                        notification-failure log, so an operator reading
	 *                        either can tell which caller parked the booking.
	 * @return bool True only when this call actually recorded NEEDS_REVIEW
	 *              -- fix round 1, F2. The caller uses this, not "did we
	 *              attempt it", to decide whether to count the booking as
	 *              parked: a lock refusal or a matrix refusal both mean
	 *              nothing was parked, and a $parked counter that could not
	 *              tell the difference would itself be the "states a cause
	 *              it cannot know" defect this whole task exists to remove.
	 */
	private static function park_paid_booking_for_review( int $bid, string $surface ): bool {
		if (! RefundLock::acquire($bid)) {
			AdvancedLogger::error_linked(
				sprintf(
					/* translators: %s: the surface (e.g. sync_orphan_wc_orders) that found paid money it could not park for review. */
					__( 'Refund lock refused (surface: %s): the booking holds paid money and was not parked for review.', 'mhm-rentiva' ),
					$surface
				),
				$bid,
				array(
					'surface' => $surface,
					'reason'  => 'lock_refused',
				),
				AdvancedLogger::CATEGORY_SYSTEM
			);
			return false;
		}

		try {
			if (! RefundStatus::transition($bid, RefundStatus::NEEDS_REVIEW, array( 'surface' => $surface ))) {
				// Fix round 1, F2: this used to return silently. The matrix
				// refusing NEEDS_REVIEW means this booking's refund_status is
				// already something the matrix has no edge from to it here --
				// e.g. a concurrent pass, or an operator action, already
				// moved it -- so this call did not park anything, and before
				// this fix nothing said so: the caller had no way to avoid
				// counting it as parked, exactly the false summary this
				// task exists to remove.
				AdvancedLogger::error_linked(
					sprintf(
						/* translators: 1: the surface, 2: the booking's current refund_status, which has no outgoing edge to NEEDS_REVIEW. */
						__( "Refund NEEDS_REVIEW could not be recorded (surface: %1\$s): current refund_status is '%2\$s'.", 'mhm-rentiva' ),
						$surface,
						RefundStatus::get($bid)
					),
					$bid,
					array(
						'surface' => $surface,
						'reason'  => 'needs_review_not_recorded',
					),
					AdvancedLogger::CATEGORY_SYSTEM
				);
				return false;
			}

			$helper_exists = class_exists('\MHMRentiva\Helpers\NotificationHelper');
			$notified      = $helper_exists
				&& \MHMRentiva\Helpers\NotificationHelper::send_refund_needs_review_email($bid);

			if (! $notified) {
				// Task 14b item 7: the reason is not the same fact in both
				// branches -- the helper class being entirely absent
				// (helper_missing) is a different problem than the helper
				// running and failing (notification_failed), even though the
				// operator sees the same silence either way.
				AdvancedLogger::error_linked(
					sprintf(
						/* translators: %s: the surface that parked this booking for review. */
						__( 'Refund review notification failed (surface: %s): the booking is parked in needs_review but no one was told.', 'mhm-rentiva' ),
						$surface
					),
					$bid,
					array(
						'surface' => $surface,
						'reason'  => $helper_exists ? 'notification_failed' : 'helper_missing',
					),
					AdvancedLogger::CATEGORY_SYSTEM
				);
			}

			// The transition itself succeeded -- the booking IS parked --
			// regardless of whether the notification also succeeded; that
			// failure is recorded separately above, not conflated with
			// whether parking happened at all.
			return true;
		} finally {
			RefundLock::release($bid);
		}
	}

	/**
	 * One-time backfill: cancel bookings whose pickup date is in the past but
	 * payment was never received. Catches bookings created before the
	 * deadline-based sweep existed, or while it was disabled / mis-configured.
	 *
	 * Idempotent — bookings already in cancelled/completed/refunded/active
	 * statuses are skipped.
	 *
	 * Usage:
	 *   wp eval 'echo wp_json_encode(MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel::sync_stale_past_bookings());'
	 *
	 * @return array{cancelled: int}
	 */
	public static function sync_stale_past_bookings(): array {
		$cancelled = self::sweep_past_pickup_unpaid( 200 );
		return array( 'cancelled' => $cancelled );
	}

	/**
	 * Direct schedule event - bypasses wp_schedule_event's schedule validation
	 * This method directly manipulates the cron array to avoid the invalid_schedule error
	 */
	private static function direct_schedule_event(): void
	{
		// Ensure schedule filter is applied
		add_filter('cron_schedules', array( self::class, 'schedules' ), 1);
		$schedules = wp_get_schedules();

		if (! isset($schedules[ self::SCHEDULE ])) {
			AdvancedLogger::error('Cannot schedule - schedule not available', array( 'schedule' => self::SCHEDULE ));
			return;
		}

		// Get cron array
		$cron = _get_cron_array();
		if ($cron === false) {
			$cron = array();
		}

		// Remove any existing events for this hook
		foreach ($cron as $timestamp => $cronhooks) {
			if (isset($cronhooks[ self::EVENT ])) {
				unset($cron[ $timestamp ][ self::EVENT ]);
				// Clean up empty timestamps
				if (empty($cron[ $timestamp ])) {
					unset($cron[ $timestamp ]);
				}
			}
		}

		// Calculate next run time (5 minutes from now)
		$next_run = time() + 300;

		// Add to cron array with proper structure
		$cron[ $next_run ][ self::EVENT ][ md5(serialize(array())) ] = array(
			'schedule' => self::SCHEDULE,
			'args'     => array(),
		);

		// Sort by timestamp
		ksort($cron);

		// Save cron array
		_set_cron_array($cron);

		// Verify it was scheduled
		$verify_next     = wp_next_scheduled(self::EVENT);
		$verify_schedule = wp_get_schedule(self::EVENT);

		if ($verify_next && $verify_schedule === self::SCHEDULE) {
			AdvancedLogger::info('Successfully scheduled recurring event', array(
				'schedule' => self::SCHEDULE,
				'next_run' => wp_date('Y-m-d H:i:s', $verify_next),
			));
		} else {
			AdvancedLogger::error('Direct schedule failed', array(
				'next'     => $verify_next ? wp_date('Y-m-d H:i:s', $verify_next) : 'none',
				'schedule' => $verify_schedule ?: 'none',
			));
		}
	}
}
