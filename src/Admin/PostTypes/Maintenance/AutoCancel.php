<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

if (! defined('ABSPATH')) {
	exit;
}



// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use WP_Query;
use Exception;



final class AutoCancel {



	public const EVENT    = 'mhmrentiva_auto_cancel_event';
	public const SCHEDULE = 'mhmrentiva_5min'; // Changed to 5min to match DatabaseInitialization

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
			AdvancedLogger::warning('Custom schedule not found', array(
				'schedule'  => self::SCHEDULE,
				'available' => array_keys($schedules),
			));
			return;
		}

		// If already scheduled, check if it's using the correct schedule
		$next_scheduled = wp_next_scheduled(self::EVENT);
		if ($next_scheduled) {
			// FIX: Check for timezone issue (UTC vs Local)
			// If the event is scheduled far in the future (> 10 mins), it's likely using Local Time (UTC+3)
			// WP-Cron runs on UTC, so this would delay execution by 3 hours.
			if ($next_scheduled > ( time() + 600 )) {
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
					// partially_refunded added: the operator deliberately kept
					// a cancellation fee, which is a settled outcome, not
					// "unpaid". Reading it as unpaid sent this sweep after a
					// booking somebody had already dealt with by hand.
					'value'   => array( 'paid', 'completed', 'refunded', 'cancelled', 'partially_refunded' ),
					'compare' => 'NOT IN',
				),
				array(
					'key'     => '_mhmrentiva_status',
					// completed added: Status::can_transition() gives COMPLETED
					// only REFUNDED and IN_PROGRESS as exits, so there is no
					// CANCELLED edge to take. Including it produced a booking
					// that was selected and refused on every single tick, and
					// the refusal logs at warning level, which the default
					// mhmrentiva_log_level of 'error' drops -- a silent
					// infinite loop. The remaining selectable statuses (draft,
					// pending_payment, pending, confirmed, in_progress,
					// no_show) all do have a CANCELLED edge.
					'value'   => array( 'cancelled', 'refunded', 'completed' ),
					'compare' => 'NOT IN',
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
	 * NOT EXISTS is the first half because a `NOT IN` / `!=` clause on its own
	 * joins on the key and therefore drops every booking that has never been
	 * through a refund flow at all -- which is nearly all of them.
	 *
	 * The key type is int|string because a meta_query clause group is exactly
	 * that shape: a string-keyed `relation` alongside numerically indexed
	 * sub-clauses.
	 *
	 * @return array<int|string, array<string, string>|string>
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
				'value'   => RefundStatus::NEEDS_REVIEW,
				'compare' => '!=',
			),
		);
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
							$notified = class_exists('\MHMRentiva\Helpers\NotificationHelper')
								&& \MHMRentiva\Helpers\NotificationHelper::send_refund_needs_review_email($bid);

							if (! $notified) {
								// error() + add(), same pair and same reasons
								// as the lock-refusal branch below; see the
								// comments there.
								AdvancedLogger::error(
									"Refund review notification failed for booking #$bid (surface: auto_cancel): the booking is parked in needs_review but no one was told",
									array(
										'booking_id' => $bid,
										'surface'    => 'auto_cancel',
										'reason'     => 'notification_failed',
									),
									AdvancedLogger::CATEGORY_SYSTEM
								);

								AdvancedLogger::add(
									array(
										'gateway'    => 'auto_cancel',
										'action'     => 'refund_review',
										'status'     => 'error',
										'booking_id' => $bid,
										'message'    => __('This booking was parked for refund review, but the notification e-mail could not be sent -- no one has been told that it is holding paid money.', 'mhm-rentiva'),
									)
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
					// sibling Status::update_status() refusal below is no
					// counter-example: it calls warning(), which the default
					// log level drops (see the next paragraph), so on a
					// stock install that branch is just as silent. It is
					// tracked as a class in Task 14 rather than fixed here.
					//
					// error(), not warning(): should_skip_log() compares
					// against mhmrentiva_log_level, which defaults to 'error'
					// on every install, so a warning-level audit trace is
					// dropped before it is written -- measured in Task 2.
					AdvancedLogger::error(
						"Refund lock refused for booking #$bid (surface: auto_cancel): the booking holds paid money and was not parked for review",
						array(
							'booking_id' => $bid,
							'surface'    => 'auto_cancel',
							'reason'     => 'lock_refused',
						),
						AdvancedLogger::CATEGORY_SYSTEM
					);

					// error()'s context array is JSON-encoded into the post
					// body; only log()'s own booking_id argument writes
					// _mhmrentiva_log_booking_id, which is the meta the admin
					// Logs list table's Booking column reads (LogColumns.php:98).
					// payment() and booking() pass it too, but both pin the
					// entry to LEVEL_INFO (:355, :370), which the default
					// 'error' log level drops -- so add() with a non-success
					// status is the only public entry point that writes the
					// booking link at a level that survives. The pair, not
					// error() alone, is what keeps this entry traceable to its
					// booking. Same pairing the sibling lock-refusal branch
					// uses (CancellationHandler.php:525-535).
					AdvancedLogger::add(
						array(
							'gateway'    => 'auto_cancel',
							'action'     => 'refund_review',
							'status'     => 'error',
							'booking_id' => $bid,
							// The branch cannot tell the two apart -- acquire()
							// also returns false for a row it refuses to steal
							// (RefundLock.php:198-221) -- so the message names
							// both possibilities instead of asserting one.
							'message'    => __('Auto-cancel left a paid order alone but could not park the booking for review: its refund lock could not be taken. Another refund operation may still be running, or a lock left behind by one that did not finish is waiting to expire.', 'mhm-rentiva'),
						)
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
				AdvancedLogger::warning(
					"Auto-cancel refused for booking #$bid: no transition from " . Status::get($bid),
					array( 'booking_id' => $bid ),
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
			if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning(
					'Auto-cancel skipped a booking',
					array(
						'booking_id' => $bid,
						'error'      => $e->getMessage(),
					),
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
	 * Nullable, because wc_get_order() answers `false` for an id that no
	 * longer resolves and a WC_Order_Refund (which is not a WC_Order) for a
	 * refund id; callers narrow to null in both cases rather than let a
	 * missing order read as a paid one.
	 */
	private static function is_paid( ?\WC_Order $order ): bool {
		return null !== $order && (bool) $order->get_date_paid();
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
	 * Usage:
	 *   wp eval 'echo MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel::sync_orphan_wc_orders();'
	 *
	 * @return array{checked: int, cancelled: int, skipped: int}
	 */
	public static function sync_orphan_wc_orders(): array
	{
		$checked   = 0;
		$cancelled = 0;
		$skipped   = 0;

		if (! function_exists('wc_get_order')) {
			return compact('checked', 'cancelled', 'skipped');
		}

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
			foreach (array_unique($candidate_ids) as $oid) {
				$order = call_user_func('\wc_get_order', $oid);
				if (! $order) {
					continue;
				}
				// K6, restated here because this body never passes through
				// cancel_booking_with_orders() and so never meets its guard.
				// The has_status() gate below is not one: `processing` is the
				// status of a typical PAID order, so without this line an
				// operator running the backfill by hand could cancel an order
				// somebody had paid for. Measured, not hypothetical.
				//
				// Narrowed to ?WC_Order rather than passed straight through:
				// wc_get_order() can hand back a WC_Order_Refund, which is not
				// a WC_Order, and letting it reach has_status() unchanged is
				// this line's pre-existing behaviour, not something to alter
				// while closing a money hole.
				if (self::is_paid($order instanceof \WC_Order ? $order : null)) {
					continue;
				}
				if (! $order->has_status(array( 'pending', 'on-hold', 'failed', 'processing' ))) {
					continue;
				}
				$order->update_status('cancelled', __('Booking auto-cancelled — orphan WC order backfill.', 'mhm-rentiva'));
				++$cancelled;
				$booking_touched = true;
			}

			if (! $booking_touched) {
				++$skipped;
			}
		}

		if (class_exists(AdvancedLogger::class)) {
			AdvancedLogger::info(
				'Orphan WC order backfill completed.',
				compact('checked', 'cancelled', 'skipped'),
				'system'
			);
		}

		return compact('checked', 'cancelled', 'skipped');
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
