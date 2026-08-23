<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (!defined('ABSPATH')) {
    exit;
}



// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cancellation workflows validate booking/payment state through controlled direct queries.



use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking Cancellation Handler
 *
 * Handles booking cancellations with deadline validation,
 * availability management, email notifications, and refunds.
 *
 * @since 4.0.0
 */
final class CancellationHandler {

	/**
	 * Booking id => the operator's refund-failed e-mail has already been
	 * sent SUCCESSFULLY once for this booking, this request.
	 *
	 * Fix round 1, F6 (Task 14b review): settle_refund()'s PENDING-not-
	 * recorded refusal (item 11) and cancel_booking()'s own post-commit
	 * recovery block both decide to send
	 * NotificationHelper::send_refund_failed_email() on the identical
	 * `refundable() > 0` condition, and both can fire in the SAME
	 * cancel_booking() call: the refusal returns a value rather than
	 * throwing (fix round 2, G1), so execution continues normally into
	 * `do_action( 'mhmrentiva_booking_cancelled', ... )`, and if a LATER
	 * listener on that hook throws for an unrelated reason, the recovery
	 * block runs too -- sending the operator the identical e-mail a
	 * second time. Request-local by design, not persisted: the risk this
	 * closes is two sends inside one PHP execution, not deduplication
	 * across separate requests (a genuinely later, independent failure on
	 * the same booking should still be able to notify).
	 *
	 * @var array<int, bool>
	 */
	private static array $refund_failed_email_sent = array();

	/**
	 * Cancel a booking
	 *
	 * @param int    $booking_id Booking ID
	 * @param int    $user_id User ID who is cancelling (0 for admin)
	 * @param string $reason Cancellation reason (optional)
	 * @param bool   $force Force cancellation even if deadline passed (admin only)
	 * @param bool   $system Attribution only: true for cron/automation callers, which
	 *                       have no current user. It buys nothing at the money step --
	 *                       since K6 no unattended path moves money at all, an
	 *                       unattributed 0 actor is refused by MoneyAuthorization
	 *                       regardless of this flag (spec §5.4). Kept on the signature
	 *                       so a caller can still say who it is without that
	 *                       declaration becoming a bypass.
	 *
	 * @return array{cancelled: bool, problems: array<int, string>}|\WP_Error
	 *               An array on success -- 'cancelled' is always true past
	 *               COMMIT, and 'problems' lists process_refund()'s own
	 *               refusal (a string describing a refusal it could not
	 *               even start -- fix round 2, G1) plus any post-commit
	 *               \Throwable messages, empty when nothing went wrong
	 *               after COMMIT. WP_Error only for a failure BEFORE
	 *               COMMIT (validation, the deadline, permission, or a
	 *               rolled-back write).
	 *
	 *               Task 14b item 12: a 'refund' key used to sit beside
	 *               'problems' carrying the exact same value process_refund()
	 *               returned (null = nothing to surface, a string = a
	 *               refusal, ALREADY copied into 'problems' too) -- reversed
	 *               polarity from what its name suggests (a non-null string
	 *               means the refund did NOT start, not that one did), and
	 *               measured to have zero readers on this unpublished branch.
	 *               Fix round 1, F9: the original measurement covered
	 *               `src/ tests/ templates/ assets/` in THIS repo only;
	 *               re-measured against the local Pro checkout
	 *               (`plugins/mhm-rentiva-pro/`) for this fix round --
	 *               `grep -rn "['refund']"` over its whole tree returns
	 *               nothing, and Pro has no caller of
	 *               `CancellationHandler::cancel_booking()` at all, so it
	 *               cannot be reading this return value's 'refund' key
	 *               either way. Removed before anything could depend on it;
	 *               $refund is still used internally to build 'problems'.
	 */
	public static function cancel_booking( int $booking_id, int $user_id = 0, string $reason = '', bool $force = false, bool $system = false ) {
		global $wpdb;

		// Validate booking exists
		$booking = get_post( $booking_id );
		if ( ! $booking || $booking->post_type !== 'mhmrentiva_booking' ) {
			return new \WP_Error(
				'invalid_booking',
				__( 'Invalid booking ID.', 'mhm-rentiva' )
			);
		}

		// Get current status
		$current_status = Status::get( $booking_id );

		// Check if booking can be cancelled
		if ( Status::CANCELLED === $current_status ) {
			return new \WP_Error(
				'already_cancelled',
				__( 'This booking is already cancelled.', 'mhm-rentiva' )
			);
		}

		if ( Status::COMPLETED === $current_status ) {
			return new \WP_Error(
				'booking_completed',
				__( 'Cannot cancel a completed booking.', 'mhm-rentiva' )
			);
		}

		if ( Status::REFUNDED === $current_status ) {
			return new \WP_Error(
				'booking_refunded',
				__( 'Cannot cancel a refunded booking.', 'mhm-rentiva' )
			);
		}

		// Check cancellation deadline (skip for admin with force flag)
		if ( ! $force ) {
			$deadline_check = self::check_cancellation_deadline( $booking_id );
			if ( is_wp_error( $deadline_check ) ) {
				return $deadline_check;
			}
		}

		// Check user permission (user can only cancel their own bookings).
		// Tied to the same predicate the money step uses (spec §5): both
		// questions -- may this actor cancel, may this actor move money --
		// resolve to "is this actor the booking's customer, or an admin",
		// and asking current_user_can() here (the request, not the argument)
		// let an ambient administrator push through a cancellation attributed
		// to someone else. $user_id === 0 (a declared system caller) skips
		// this check entirely, same as before -- the money step's own hard
		// floor is what refuses an unattributed actor.
		if ( $user_id > 0 && ! \MHMRentiva\Admin\Payment\Core\MoneyAuthorization::mayMoveMoney( $booking_id, $user_id, 'cancel' ) ) {
			return new \WP_Error(
				'permission_denied',
				__( 'You do not have permission to cancel this booking.', 'mhm-rentiva' )
			);
		}

		// Begin transaction-like operation
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Save cancellation metadata BEFORE status update so email hooks can read it
			$cancellation_data = array(
				'cancelled_at'        => current_time( 'mysql' ),
				'cancelled_by'        => $user_id > 0 ? $user_id : 'admin',
				'cancellation_reason' => $reason,
			);

			update_post_meta( $booking_id, '_mhmrentiva_cancellation_data', $cancellation_data );

			// Update booking status to cancelled (fires mhmrentiva_booking_status_changed)
			$status_updated = Status::update_status( $booking_id, Status::CANCELLED, $user_id );

			if ( ! $status_updated ) {
				throw new \Exception( __( 'Failed to update booking status.', 'mhm-rentiva' ) );
			}

			// Free up vehicle availability. Best-effort on purpose: this is
			// bookkeeping, and refusing to cancel a booking because its vehicle
			// meta is incomplete fails in the wrong direction -- the operator
			// asked for a cancellation, and the worst case of a skipped release
			// is that some dates stay blocked. Measured: dev bookings 9471 and
			// 9474 carry no pickup date, so throwing here turned the operator's
			// cancel button into "Cancellation failed: Invalid booking data for
			// availability update." Logged rather than swallowed.
			$availability_freed = self::free_vehicle_availability( $booking_id );
			if ( is_wp_error( $availability_freed ) ) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::add(
					array(
						'gateway'    => 'cancellation',
						'action'     => 'availability_release',
						'status'     => 'error',
						'booking_id' => $booking_id,
						'message'    => sprintf(
							/* translators: %s placeholder. */
							__( 'Vehicle availability was not released for this cancellation: %s', 'mhm-rentiva' ),
							$availability_freed->get_error_message()
						),
					)
				);
			}

			// Clear cache
			if ( class_exists( 'MHMRentiva\Admin\Booking\Helpers\Cache' ) ) {
				$vehicle_id = (int) get_post_meta( $booking_id, '_mhmrentiva_vehicle_id', true );
				if ( $vehicle_id > 0 ) {
					Cache::invalidateVehicle( $vehicle_id );
				}
			}

			// Commit changes
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			// \Throwable, not \Exception (Task 14b item 10): Status::update_status()
			// above fires the public mhmrentiva_booking_status_changed action
			// BEFORE COMMIT, and a third-party listener on it -- or a plain
			// TypeError -- can throw an \Error, which catch(\Exception) never
			// sees. Before this fix an \Error here skipped ROLLBACK entirely
			// and fatalled the request with the transaction left open; this is
			// the same defect class 14a closed one phase later, on the
			// post-commit try below.
			// Rollback on error
			$wpdb->query( 'ROLLBACK' );

			// Fix round 1, F4 (Task 14b review): widening this catch to
			// \Throwable (item 10) pulled two consequences into a task
			// about visibility that were not addressed at the time. (a)
			// This branch wrote NOTHING to the Logs -- the only branch
			// this task touched that skipped error_linked(). (b)
			// $e->getMessage() below is echoed verbatim to whichever
			// caller asked, and on a customer-reachable endpoint
			// (AccountController::ajax_cancel_booking()) that was only
			// ever safe because it was always a plugin-authored
			// \Exception message. An \Error/\TypeError message is
			// ENGINE-generated and can carry absolute server paths --
			// before catch(\Throwable) existed, such an \Error fatalled to
			// a generic WordPress critical-error page instead of reaching
			// this return at all, so echoing it verbatim to the customer
			// is a NEW disclosure this fix introduced, not a pre-existing
			// one. Logged here regardless of type (the full message is
			// safe in the admin-only Logs screen); returned to the caller
			// only when it is plugin-authored.
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
				sprintf(
					/* translators: %s: the full pre-commit throwable message. */
					__( 'Pre-commit failure cancelling booking: %s', 'mhm-rentiva' ),
					$e->getMessage()
				),
				$booking_id,
				array( 'error' => $e->getMessage() ),
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
			);

			return new \WP_Error(
				'cancellation_failed',
				$e instanceof \Exception
					? sprintf(
						/* translators: %s: a plugin-authored exception message, safe to show the caller. */
						__( 'Cancellation failed: %s', 'mhm-rentiva' ),
						$e->getMessage()
					)
					: __( 'Cancellation failed due to an unexpected error. The site administrator has been notified.', 'mhm-rentiva' )
			);
		}

		// Past this line nothing may be rolled back: the booking IS
		// cancelled and the customer has been told. A failure in any of the
		// three steps below is "cancelled, with problems", never
		// "cancellation failed" -- ROLLBACK on an already-committed
		// transaction does nothing.
		//
		// Deliberately OUTSIDE the try/catch above (fix round 1, F1): every
		// post-commit statement, INCLUDING this block's own recovery path a
		// few lines down, must sit outside the rollback-protected try. An
		// \Exception raised while HANDLING a post-commit problem is
		// genuinely reachable -- RefundStatus::transition() fires the
		// public mhmrentiva_refund_status_changed action, wp_mail() runs
		// phpmailer_init listeners and only catches PHPMailer's own
		// exception type, AdvancedLogger::add() inserts a post and fires
		// save_post -- and before this fix such an \Exception was caught by
		// the ROLLBACK handler above and reported as "Cancellation failed"
		// about a booking that was already committed cancelled, while an
		// \Error there was uncaught entirely. Both are exactly the outcomes
		// this task exists to remove, so nothing post-COMMIT may sit inside
		// that try any more.
		$problems = array();
		$refund   = null;

		try {
			// Send cancellation email (after commit to ensure data consistency)
			self::send_cancellation_email( $booking_id, $reason );

			// Process refund if payment was made. $system is deliberately NOT
			// carried past this point: it is attribution information about
			// cancel_booking() itself, not a bypass the money step accepts --
			// see process_refund() and MoneyAuthorization.
			$refund = self::process_refund( $booking_id, $user_id, $reason );

			// Fix round 2, G1: a non-null return is a refusal
			// process_refund() could not even start (correction #7) --
			// recorded here as a VALUE, precisely so it does NOT abort this
			// try the way fix round 1's throw did. Aborting here skipped
			// do_action() below entirely, even though the cancellation had
			// already committed and other listeners (Pro's
			// VendorCancellationDateBlocker::maybe_block_dates() among them)
			// still need to run regardless of what happened to the refund.
			if ( null !== $refund ) {
				$problems[] = $refund;
			}

			// Trigger action for other plugins/integrations
			do_action( 'mhmrentiva_booking_cancelled', $booking_id, $user_id, $reason );
		} catch ( \Throwable $e ) {
			// \Throwable, not \Exception: catching only \Exception is the
			// defect this block exists to remove (see above).
			$problems[] = $e->getMessage();

			// The recovery itself gets its own try/catch (fix round 1, F1):
			// none of RefundLock::acquire(), RefundStatus::transition(),
			// the logging calls, or send_refund_failed_email() may be
			// allowed to escape uncaught -- there is no outer catch left to
			// (mis)handle them any more, on purpose, so a throwable from in
			// here must be caught right here or it fatals the request just
			// like the original defect this task removes.
			try {
				if ( \MHMRentiva\Admin\Payment\Core\RefundLock::acquire( $booking_id ) ) {
					try {
						$failed_recorded = \MHMRentiva\Admin\Payment\Core\RefundStatus::transition(
							$booking_id,
							\MHMRentiva\Admin\Payment\Core\RefundStatus::FAILED,
							array( 'reason' => 'post_commit_throwable' )
						);

						if ( ! $failed_recorded ) {
							// Matrix refused (fix round 1, F4) -- most
							// commonly because refund_status is still ''
							// (process_refund() never reached settle_refund()
							// at all, e.g. no money owed): matrix('')'s only
							// edges are to PENDING/NEEDS_REVIEW, never FAILED,
							// so this is an EXPECTED refusal on that shape,
							// not a bug -- but it must still be logged rather
							// than silently discarded, the same reasoning as
							// settle_refund()'s own PENDING-refusal guard
							// below.
							// Task 14b item 1: was error() + add() -- one call
							// now does both (booking-linked, LEVEL_ERROR).
							\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
								sprintf(
									/* translators: %s: the booking's current refund_status, which has no outgoing edge to FAILED. */
									__( "Post-commit FAILED status could not be recorded: current refund_status is '%s'. A post-commit cancellation problem occurred, but the refund status could not be updated to reflect it.", 'mhm-rentiva' ),
									\MHMRentiva\Admin\Payment\Core\RefundStatus::get( $booking_id )
								),
								$booking_id,
								array( 'reason' => 'post_commit_failed_not_recorded' ),
								\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
							);
						}
					} finally {
						\MHMRentiva\Admin\Payment\Core\RefundLock::release( $booking_id );
					}
				} else {
					// Lock refusal, previously silent (fix round 1, F4).
					// Task 14b item 1: was error() + add(); merged.
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
						__( 'Refund lock refused while recording a post-commit failure. A post-commit cancellation problem occurred, but the refund lock could not be acquired to record it.', 'mhm-rentiva' ),
						$booking_id,
						array( 'reason' => 'lock_refused' ),
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
					);
				}

				// Task 14b item 1: was a standalone error() with no
				// booking_id (see the removed comment this method used to
				// carry about error() dropping the link); error_linked()
				// keeps this entry traceable like its siblings above.
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
					sprintf(
						/* translators: %s: the message thrown by the post-commit throwable this recovery is handling. */
						__( 'Post-commit failure cancelling booking: %s', 'mhm-rentiva' ),
						$e->getMessage()
					),
					$booking_id,
					array( 'error' => $e->getMessage() ),
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
				);

				// Tam başarısızlıkta operatör e-postası (plan Step 5),
				// gated on there actually being money at stake (fix round 1,
				// F5): an unrelated post-commit throwable on a booking with
				// nothing owed must not tell the operator a refund is
				// missing when none was ever due.
				//
				// Correction #5 (Task 15): refundable() is zeroed for a
				// mixed-currency booking, which is precisely the case where
				// money IS owed and a human IS needed -- without the
				// isMixedCurrency() disjunct this gate would silently drop
				// the operator e-mail for exactly the booking this slice
				// exists to stop closing silently.
				$recovery_state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $booking_id );

				if ( $recovery_state->refundable() > 0 || $recovery_state->isMixedCurrency() ) {
					// Fix round 1, F6: routed through send_refund_failed_email_once()
					// rather than the helper directly -- this branch and
					// settle_refund()'s PENDING-not-recorded refusal (item 11)
					// both send this same e-mail on this same condition, and
					// both can fire in one cancel_booking() call.
					if ( ! self::send_refund_failed_email_once( $booking_id ) ) {
						// Task 14b item 1: was error() + add(), same pair and
						// same reasons as every other "the notification
						// itself may silently fail" branch in this codebase
						// (AutoCancel.php's send_refund_needs_review_email()
						// caller): a dropped bool here is a post-commit
						// problem nobody was told about.
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
							__( 'Refund-failed notification could not be sent. A post-commit cancellation problem occurred, but the operator notification e-mail could not be sent -- no one has been told.', 'mhm-rentiva' ),
							$booking_id,
							array( 'reason' => 'notification_failed' ),
							\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
						);
					}
				}
			} catch ( \Throwable $e2 ) {
				// The recovery itself failed (fix round 1, F1) -- recorded
				// like any other post-commit problem, never a WP_Error,
				// never left to propagate.
				$problems[] = $e2->getMessage();
			}
		}

		return array(
			'cancelled' => true,
			'problems'  => $problems,
		);
	}

	/**
	 * Check if booking can be cancelled based on deadline
	 *
	 * @param int $booking_id Booking ID
	 * @return true|\WP_Error True if can cancel, WP_Error if deadline passed
	 */
	public static function check_cancellation_deadline( int $booking_id ) {
		// Get cancellation deadline setting (in hours)
		$deadline_hours = (int) SettingsCore::get( 'mhmrentiva_booking_cancellation_deadline_hours', 24 );

		// Get booking pickup date/time
		$pickup_date = get_post_meta( $booking_id, '_mhmrentiva_pickup_date', true );
		$pickup_time = get_post_meta( $booking_id, '_mhmrentiva_pickup_time', true );

		if ( empty( $pickup_date ) ) {
			return new \WP_Error(
				'invalid_booking_data',
				__( 'Booking pickup date is missing.', 'mhm-rentiva' )
			);
		}

		// Use default time if not set
		if ( empty( $pickup_time ) ) {
			$pickup_time = '10:00';
		}

		try {
			// Create pickup datetime
			$timezone        = wp_timezone();
			$pickup_datetime = \DateTime::createFromFormat( 'Y-m-d H:i', $pickup_date . ' ' . $pickup_time, $timezone );

			if ( ! $pickup_datetime ) {
				return new \WP_Error(
					'invalid_datetime',
					__( 'Invalid pickup date/time format.', 'mhm-rentiva' )
				);
			}

			// Calculate cancellation deadline
			$cancellation_deadline = clone $pickup_datetime;
			$cancellation_deadline->modify( '-' . $deadline_hours . ' hours' );

			// Get current time
			$current_time = new \DateTime( 'now', $timezone );

			// Check if deadline has passed
			if ( $current_time > $cancellation_deadline ) {
				$hours_until_pickup = round( ( $pickup_datetime->getTimestamp() - $current_time->getTimestamp() ) / 3600, 1 );

				return new \WP_Error(
					'deadline_passed',
					sprintf(
						/* translators: 1: required hours before pickup, 2: hours remaining until pickup. */
						__( 'Cancellation deadline has passed. Bookings must be cancelled at least %1$d hours before pickup. Only %2$.1f hours remaining.', 'mhm-rentiva' ),
						$deadline_hours,
						max( 0, $hours_until_pickup )
					)
				);
			}

			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'deadline_check_failed',
				__( 'Failed to check cancellation deadline.', 'mhm-rentiva' )
			);
		}
	}

	/**
	 * Free vehicle availability after cancellation
	 *
	 * @param int $booking_id Booking ID
	 * @return true|\WP_Error True on success, WP_Error on failure
	 */
	private static function free_vehicle_availability( int $booking_id ) {
		$vehicle_id   = (int) get_post_meta( $booking_id, '_mhmrentiva_vehicle_id', true );
		$pickup_date  = get_post_meta( $booking_id, '_mhmrentiva_pickup_date', true );
		$dropoff_date = get_post_meta( $booking_id, '_mhmrentiva_dropoff_date', true );

		if ( $vehicle_id === 0 || empty( $pickup_date ) || empty( $dropoff_date ) ) {
			return new \WP_Error(
				'invalid_booking_data',
				__( 'Invalid booking data for availability update.', 'mhm-rentiva' )
			);
		}

		// Get blocked dates
		$blocked_dates = get_post_meta( $vehicle_id, '_mhmrentiva_booking_blocked_dates', true );
		if ( ! is_array( $blocked_dates ) ) {
			$blocked_dates = array();
		}

		// Generate date range for this booking
		$start    = new \DateTime( $pickup_date );
		$end      = new \DateTime( $dropoff_date );
		$interval = new \DateInterval( 'P1D' );
		$period   = new \DatePeriod( $start, $interval, $end );

		// Remove dates from blocked list
		foreach ( $period as $date ) {
			$date_str = $date->format( 'Y-m-d' );
			$key      = array_search( $date_str, $blocked_dates );
			if ( $key !== false ) {
				unset( $blocked_dates[ $key ] );
			}
		}

		// Update vehicle meta
		update_post_meta( $vehicle_id, '_mhmrentiva_booking_blocked_dates', array_values( $blocked_dates ) );

		return true;
	}

	/**
	 * Send cancellation email
	 *
	 * @param int    $booking_id Booking ID
	 * @param string $reason Cancellation reason
	 * @return bool True if email sent, false otherwise
	 */
	private static function send_cancellation_email( int $booking_id, string $reason = '' ): bool {
		// Check if cancellation emails are enabled
		if ( ! SettingsCore::get( 'mhmrentiva_booking_send_confirmation_emails', '1' ) ) {
			return false;
		}

		// Get customer email
		$customer_email = get_post_meta( $booking_id, '_mhmrentiva_customer_email', true );
		if ( empty( $customer_email ) ) {
			return false;
		}

		// Get booking details
		$vehicle_id    = (int) get_post_meta( $booking_id, '_mhmrentiva_vehicle_id', true );
		$vehicle_name  = get_the_title( $vehicle_id );
		$pickup_date   = get_post_meta( $booking_id, '_mhmrentiva_pickup_date', true );
		$dropoff_date  = get_post_meta( $booking_id, '_mhmrentiva_dropoff_date', true );
		$customer_name = get_post_meta( $booking_id, '_mhmrentiva_customer_name', true );

		// Email subject
		$subject = sprintf(
			/* translators: %s placeholder. */
			__( 'Booking Cancellation Confirmation - %s', 'mhm-rentiva' ),
			get_bloginfo( 'name' )
		);

		// Email template
		$template_path = MHMRENTIVA_PLUGIN_DIR . 'templates/emails/booking-cancelled.html.php';

		// Fallback to simple HTML if template doesn't exist
		if ( file_exists( $template_path ) ) {
			ob_start();
			include $template_path;
			$message = ob_get_clean();
		} else {
			$message = self::get_default_cancellation_email_content(
				$customer_name,
				$booking_id,
				$vehicle_name,
				$pickup_date,
				$dropoff_date,
				$reason
			);
		}

		// Email headers
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		// Send email
		$sent = wp_mail( $customer_email, $subject, $message, $headers );

		// Send copy to admin if admin notifications are enabled
		$admin_notifications = SettingsCore::get( 'mhmrentiva_booking_admin_notifications', '1' );
		if ( '1' === $admin_notifications ) {
			$admin_email   = get_option( 'admin_email' );
			$admin_subject = sprintf(
				/* translators: %d placeholder. */
				__( '[ADMIN] Booking Cancelled - #%d', 'mhm-rentiva' ),
				$booking_id
			);
			wp_mail( $admin_email, $admin_subject, $message, $headers );
		}

		return $sent;
	}

	/**
	 * Get default cancellation email content
	 *
	 * @param string $customer_name Customer name
	 * @param int    $booking_id Booking ID
	 * @param string $vehicle_name Vehicle name
	 * @param string $pickup_date Pickup date
	 * @param string $dropoff_date Dropoff date
	 * @param string $reason Cancellation reason
	 * @return string Email HTML content
	 */
	private static function get_default_cancellation_email_content(
		string $customer_name,
		int $booking_id,
		string $vehicle_name,
		string $pickup_date,
		string $dropoff_date,
		string $reason
	): string {
		$html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$html .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;">';
		$html .= '<h2 style="color: #dc3545;">' . esc_html__( 'Booking Cancelled', 'mhm-rentiva' ) . '</h2>';
		/* translators: %s placeholder. */
		$html .= '<p>' . sprintf( esc_html__( 'Hello %s,', 'mhm-rentiva' ), esc_html( $customer_name ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Your booking has been cancelled successfully.', 'mhm-rentiva' ) . '</p>';

		$html .= '<div style="background: white; padding: 15px; margin: 20px 0; border-left: 4px solid #dc3545;">';
		$html .= '<p><strong>' . esc_html__( 'Booking Details:', 'mhm-rentiva' ) . '</strong></p>';
		$html .= '<p><strong>' . esc_html__( 'Booking ID:', 'mhm-rentiva' ) . '</strong> #' . esc_html( $booking_id ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Vehicle:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $vehicle_name ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Pickup Date:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $pickup_date ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Dropoff Date:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $dropoff_date ) . '</p>';

		if ( ! empty( $reason ) ) {
			$html .= '<p><strong>' . esc_html__( 'Reason:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $reason ) . '</p>';
		}
		$html .= '</div>';

		$html .= '<p>' . esc_html__( 'If a refund applies to this booking, you will receive a separate refund notice with the amount and how it will reach you.', 'mhm-rentiva' ) . '</p>';
		$html .= '<p>' . esc_html__( 'Thank you for choosing our service.', 'mhm-rentiva' ) . '</p>';
		$html .= '<p style="margin-top: 30px; font-size: 12px; color: #666;">' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		$html .= '</div></body></html>';

		return $html;
	}

	/**
	 * Sends the operator's refund-failed notification at most ONCE per
	 * booking per request (fix round 1, F6). Wraps
	 * NotificationHelper::send_refund_failed_email() so the two independent
	 * branches that both decide to send it (settle_refund()'s
	 * PENDING-not-recorded refusal, and cancel_booking()'s own post-commit
	 * recovery block) cannot both succeed and land two identical e-mails
	 * for one cancellation -- see $refund_failed_email_sent's own docblock.
	 *
	 * Marks "sent" only on an actual successful send, deliberately: if the
	 * first attempt fails (a bad admin_email, a mail transport hiccup), a
	 * later independent branch retrying is a second CHANCE, not a second
	 * DELIVERED e-mail -- wp_mail() succeeding twice for one cancellation
	 * is the concrete thing this method makes impossible, not a second
	 * attempt after the first one never went anywhere.
	 *
	 * Considered moving the send itself outside settle_refund()'s
	 * RefundLock instead (the lock is released via `finally` before
	 * settle_refund() returns to process_refund(), so the caller already
	 * runs unlocked): rejected for this fix round because settle_refund()
	 * returns a bare string for TWO different refusals (this one, item 11's
	 * PENDING-not-recorded; and item 13's FAILED-not-recorded, which must
	 * NOT send this e-mail), and telling them apart from process_refund()
	 * would mean widening the return contract -- a larger change than a
	 * duplicate-send bug calls for. The dedupe flag removes the double-send
	 * outright; the lock is still held a little longer on this one rare
	 * refusal path, not the common success path, which is accepted here
	 * rather than fixed.
	 */
	private static function send_refund_failed_email_once( int $booking_id ): bool {
		if ( ! empty( self::$refund_failed_email_sent[ $booking_id ] ) ) {
			return true;
		}

		$sent = \MHMRentiva\Helpers\NotificationHelper::send_refund_failed_email( $booking_id );

		if ( $sent ) {
			self::$refund_failed_email_sent[ $booking_id ] = true;
		}

		return $sent;
	}

	/**
	 * The money step of a cancellation (spec §5.3).
	 *
	 * @param int    $booking_id Booking ID
	 * @param int    $user_id    The actor. There is no $system leg any more --
	 *                           since K6 no unattended path moves money at
	 *                           all, so an unattributed 0 actor is refused
	 *                           here regardless of how cancel_booking() was
	 *                           called (see MoneyAuthorization).
	 * @param string $reason     Cancellation reason, carried into the refund record
	 * @return string|null Null when nothing about the refund step itself
	 *                      needs to be surfaced to the caller (a refund
	 *                      actually completed, none was owed, or a
	 *                      pre-existing failure was already logged by
	 *                      settle_refund() through its own channel). A
	 *                      string when settle_refund() could not even
	 *                      record that a refund attempt started (fix round
	 *                      2, G1) -- returned, not thrown, so cancel_booking()
	 *                      can add it to 'problems' AND still go on to fire
	 *                      mhmrentiva_booking_cancelled, which a throw here
	 *                      used to skip.
	 */
	private static function process_refund( int $booking_id, int $user_id, string $reason = '' ): ?string {
		if ( ! \MHMRentiva\Admin\Payment\Core\MoneyAuthorization::mayMoveMoney( $booking_id, $user_id, 'refund' ) ) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::add(
				array(
					'gateway'    => 'cancellation',
					'action'     => 'refund',
					'status'     => 'error',
					'booking_id' => $booking_id,
					'message'    => __( 'Refund not attempted: the cancellation was not attributed to a user allowed to move money.', 'mhm-rentiva' ),
				)
			);

			return null;
		}

		$payment_status = (string) get_post_meta( $booking_id, '_mhmrentiva_payment_status', true );

		// The entry condition is the balance, not the status string. 'paid' is
		// kept as an OR rather than replaced: a booking whose meta claims a
		// payment no channel can see is a legacy shape, and narrowing the
		// extension point would take mhmrentiva_process_refund away from
		// integrators who fire on exactly that claim. It closes as
		// not_required below instead.
		//
		// T15-R1: isMixedCurrency() is ALSO an OR here, deliberately. paid()
		// is zeroed for a mixed-currency booking (Task 15), so without this
		// disjunct a mixed booking whose payment_status meta is not
		// 'paid'/'partially_refunded' would never reach settle_refund() at
		// all -- and settle_refund()'s own mixed-currency guard, which is
		// what routes the booking to needs_review instead of a silent
		// not_required close, would never run.
		$payment_state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $booking_id );

		$has_money = $payment_state->paid() > 0
			|| $payment_state->isMixedCurrency()
			|| in_array( $payment_status, array( 'paid', 'partially_refunded' ), true );

		if ( ! $has_money ) {
			return null;
		}

		$payment_gateway = (string) get_post_meta( $booking_id, '_mhmrentiva_payment_gateway', true );

		// Not part of the status machine -- audit metadata about the request
		// itself, not a state RefundStatus's matrix governs -- so it is
		// written here and does not need the lock settle_refund() is about to
		// take for the actual status write.
		update_post_meta( $booking_id, '_mhmrentiva_refund_requested_at', current_time( 'mysql' ) );
		update_post_meta( $booking_id, '_mhmrentiva_refund_requested_by', $user_id );

		// cancel_booking() carries no "surface" concept today (no such
		// parameter exists on it or on this method), so a literal names the
		// one caller of settle_refund() rather than growing a parameter chain
		// this slice does not otherwise need.
		return self::settle_refund( $booking_id, $reason, $user_id, $payment_gateway, 'cancellation_handler' );
	}

	/**
	 * Steps 4-9 of the spec §5.3 sequence, plus (as of the single-writer slice)
	 * the pending write and the integrator hook, both moved inside the lock.
	 *
	 * RefundStatus::transition() refuses to write without the lock held, so
	 * the pending marker has to be set AFTER acquire(), not before it --
	 * writing it earlier (as this method used to, via process_refund()) meant
	 * it silently never landed, and every later transition then looked like it
	 * started from the empty string.
	 *
	 * Two different resolutions of PaymentState appear below, deliberately:
	 * the first, before the hook, is a stub for Task 15's mixed-currency guard,
	 * which needs to see the booking's currency shape before pending is even
	 * written. The second, after the hook, is what THIS method's own decision
	 * uses -- an integrator that made its own refund in mhmrentiva_process_refund
	 * has already lowered the balance, and deciding from the pre-hook snapshot
	 * would refund the same money twice.
	 *
	 * A balance of zero is not one fact but two, and they are recorded
	 * differently: money was taken and is no longer refundable through us
	 * (completed_externally -- including a refund made before this
	 * cancellation), or no money was ever taken (not_required). Collapsing them
	 * would hide a real transfer from the audit trail.
	 *
	 * @return string|null Null when nothing about the refund step needs
	 *                      surfacing to the caller: money moved, none was
	 *                      owed, or a failure is already visible through
	 *                      refund_status itself (the three surfaces that
	 *                      call this class check `RefundStatus::FAILED ===
	 *                      RefundStatus::get(...)` themselves). A string on
	 *                      every other early return: the PENDING transition
	 *                      itself could not be recorded (fix round 1, F2),
	 *                      this booking's payment is split across
	 *                      currencies (Task 15), the FAILED transition could
	 *                      not be recorded because the matrix genuinely
	 *                      refused it for another reason (Task 14b item 13),
	 *                      or finish() had already written partial_failure
	 *                      for this exact failure (whole-branch review, F3
	 *                      -- partial_failure has no matrix edge to failed,
	 *                      but IS a real failure the surfaces' `FAILED ===`
	 *                      check cannot see on its own). All of these return
	 *                      the refusal message as a VALUE rather than
	 *                      throwing it (fix round 2, G1) -- a throw here
	 *                      used to unwind past process_refund()'s return in
	 *                      cancel_booking(), skipping the public
	 *                      mhmrentiva_booking_cancelled action entirely (a
	 *                      live Pro consumer:
	 *                      VendorCancellationDateBlocker::maybe_block_dates())
	 *                      even though the cancellation itself had already
	 *                      committed and needed that hook to run regardless
	 *                      of what happened to the refund.
	 */
	private static function settle_refund( int $booking_id, string $reason, int $user_id, string $payment_gateway, string $surface ): ?string {
		if ( ! \MHMRentiva\Admin\Payment\Core\RefundLock::acquire( $booking_id ) ) {
			// The lock refusal branch: no lock, no operation, and no status
			// write either. RefundStatus::transition() refuses to write
			// without the lock held (its own isHeld() guard) -- a request
			// that never held it has no standing to describe the booking's
			// refund state; only the actual holder does. Reaching FAILED
			// from here would mean two writers can disagree about this
			// booking's status, exactly the class this slice exists to
			// remove. _mhmrentiva_refund_status is left untouched: '' if
			// this is the booking's first attempt, or whatever terminal
			// value the actual holder already wrote if it finished first.
			//
			// The refusal is not silent even though the meta stays
			// untouched: logged here, with the actor, so a stale/orphaned
			// lock does not produce a cancellation whose refund was refused
			// and which nothing records.
			//
			// error(), not warning(): AdvancedLogger::should_skip_log()
			// compares against mhmrentiva_log_level, which defaults to
			// 'error' on every install (LogsSettings.php, SettingsSanitizer.php)
			// -- a 'warning'-level call is silently dropped under that
			// default, which is exactly the "nothing records it" failure
			// this call exists to prevent. Verified empirically: the
			// intended assertion in CancellationInitiatesRefundTest found no
			// log post until this was switched from warning() to error().
			// Task 14b item 1: was error() + add(), merged into one call.
			// Task 14b item 2: the message no longer asserts a cause
			// RefundLock::acquire() cannot actually tell apart -- a lock
			// refusal means either a genuinely running refund OR a lock a
			// dead request left behind that has not hit RefundLock's own
			// TTL yet (up to 5 minutes), and "another refund is already
			// running" stated the first as fact regardless of which it was.
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
				sprintf(
					/* translators: 1: booking id, 2: the user id attempting the refund. */
					__( "Refund lock refused for booking #%1\$d (actor #%2\$d): another refund attempt holds this booking's lock -- refund not attempted.", 'mhm-rentiva' ),
					$booking_id,
					$user_id
				),
				$booking_id,
				array( 'reason' => 'lock_refused' ),
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
			);

			return null;
		}

		try {
			// Freshness first (RefundStatus::transition does this too, but the
			// mixed-currency read below is not a transition).
			wp_cache_delete( $booking_id, 'post_meta' );

			$state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $booking_id );

			// Spec v3 §6/2: BEFORE pending and before every zero-balance
			// branch, because refundable() returns the same 0 for "mixed
			// currency" and "nothing to refund" -- and closing a mixed-currency
			// booking as not_required is exactly the silent close this guard
			// exists to prevent. Task 15 fills this in.
			if ( $state->isMixedCurrency() ) {
				// Before pending, deliberately: the matrix has no
				// pending -> needs_review edge, and adding one would grow the
				// machine for a case that never starts an operation at all.
				// '' -> needs_review is already an edge, so this lands
				// cleanly -- verified against RefundStatus::matrix() (Task
				// 15 correction #6): this call sits inside RefundLock's own
				// try block, after acquire() above already succeeded, so
				// RefundStatus::transition()'s isHeld() guard is satisfied.
				//
				// Mirrors AutoCancel::cancel_booking_with_orders()'s own
				// park branch (Task 14b's sibling pattern): the notification
				// is only attempted once the transition itself is recorded,
				// and its bool is never discarded -- a dropped notification
				// here would leave a mixed-currency booking parked in
				// needs_review with no one told it is holding money across
				// more than one currency.
				$mixed_currency_recorded = \MHMRentiva\Admin\Payment\Core\RefundStatus::transition(
					$booking_id,
					\MHMRentiva\Admin\Payment\Core\RefundStatus::NEEDS_REVIEW,
					array(
						'reason'  => 'mixed_currency',
						'surface' => $surface,
					)
				);

				if ( $mixed_currency_recorded ) {
					// Review fix round 1, F1: NOT send_refund_needs_review_email().
					// That helper's wording is AutoCancel's own -- "left it
					// alone, did not cancel the booking" and "AutoCancel
					// found" are both FALSE on this path (the booking IS
					// cancelled; auto-cancel was never involved) -- and it
					// prints Money::toMajor($state->paid()), which is
					// exactly the meaningless-if-summed figure
					// isMixedCurrency() exists to warn against.
					// send_refund_mixed_currency_review_email() tells this
					// path's own true story and prints no amount at all.
					if ( ! \MHMRentiva\Helpers\NotificationHelper::send_refund_mixed_currency_review_email( $booking_id ) ) {
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
							sprintf(
								/* translators: %d: booking id. */
								__( 'Mixed-currency refund review notification could not be sent for booking #%d: the booking is parked in needs_review, but no one has been told it is holding money across more than one currency.', 'mhm-rentiva' ),
								$booking_id
							),
							$booking_id,
							array(
								'surface' => $surface,
								'reason'  => 'notification_failed',
							),
							\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
						);
					}
				} else {
					// Review fix round 1, F2: this used to return silently,
					// exactly the defect this slice has already fixed twice
					// elsewhere. The matrix refusing NEEDS_REVIEW means this
					// booking's CURRENT refund_status has no outgoing edge to
					// it from here -- e.g. a concurrent request already moved
					// it to pending/failed/a terminal value, or this is a
					// second cancellation attempt on a booking already
					// parked. Mirrors AutoCancel::park_paid_booking_for_review()'s
					// own matrix-refusal log (AutoCancel.php:1003-1026) and
					// this same method's PENDING-not-recorded branch twenty
					// lines below -- neither of those is allowed to discard
					// a refusal, so this one may not either.
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
						sprintf(
							/* translators: 1: booking id, 2: the booking's current refund_status, which has no outgoing edge to needs_review. */
							__( "Mixed-currency NEEDS_REVIEW could not be recorded for booking #%1\$d: current refund_status is '%2\$s'.", 'mhm-rentiva' ),
							$booking_id,
							\MHMRentiva\Admin\Payment\Core\RefundStatus::get( $booking_id )
						),
						$booking_id,
						array(
							'surface' => $surface,
							'reason'  => 'mixed_currency_not_recorded',
						),
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
					);
				}

				// settle_refund()'s contract is ?string, not bool, as of Task
				// 14a -- null means "nothing to surface", a non-empty string
				// is a problem cancel_booking() appends to 'problems'. Review
				// fix round 1 measured that none of the three AJAX surfaces
				// actually display this text (each emits its own fixed
				// sentence once 'problems' is non-empty) -- the BEHAVIOURAL
				// half still matters (a non-null return flips which sentence
				// a surface shows), so this still returns a real string
				// rather than a bare false the caller cannot act on, even
				// though the string itself has no display path today.
				return __( "Refund not attempted: this booking's payment is split across more than one currency and cannot be totalled automatically. It has been flagged for manual review.", 'mhm-rentiva' );
			}

			$pending_recorded = \MHMRentiva\Admin\Payment\Core\RefundStatus::transition( $booking_id, \MHMRentiva\Admin\Payment\Core\RefundStatus::PENDING, array( 'surface' => $surface ) );

			if ( ! $pending_recorded ) {
				// The matrix refused: this booking's CURRENT refund_status
				// has no outgoing edge to PENDING -- e.g. a concurrent
				// review_dismiss() already closed it as not_required, or
				// another request already finished the whole refund and
				// reached a terminal status of its own. Before this guard,
				// the return value was discarded and this method pressed on
				// into the money step regardless: money would move while
				// _mhmrentiva_refund_status stayed at whatever terminal
				// value it already held, silently disagreeing with what
				// just happened. If nothing recorded that a refund attempt
				// even started, the money step has no terminal status left
				// to land in, so it must not run at all -- the single-writer
				// discipline this class exists for (RefundStatus's own
				// docblock).
				// Task 14b item 1: was error() + add(), merged into one call.
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
					sprintf(
						/* translators: 1: the surface that attempted this refund, 2: the booking's current refund_status, which has no outgoing edge to PENDING. */
						__( "Refund PENDING could not be recorded (surface: %1\$s): current refund_status is '%2\$s'. Refund not attempted: this booking's refund status changed before the refund could start.", 'mhm-rentiva' ),
						$surface,
						\MHMRentiva\Admin\Payment\Core\RefundStatus::get( $booking_id )
					),
					$booking_id,
					array(
						'surface' => $surface,
						'reason'  => 'pending_not_recorded',
					),
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
				);

				// Task 14b item 11: this refusal used to be a throw (fix
				// round 1), which landed in cancel_booking()'s post-commit
				// recovery block and sent the operator this same
				// send_refund_failed_email() whenever refundable() > 0. Fix
				// round 2 turned it into a returned value instead (G1),
				// precisely so it stops skipping
				// do_action('mhmrentiva_booking_cancelled', ...) -- but that
				// also means this refusal no longer reaches that recovery
				// block at all, so the operator e-mail it used to send
				// silently stopped firing: only the customer, reading
				// 'problems' below, ever learned money was still owed on a
				// terminal refund_status. Sent here directly instead, on the
				// same condition the old recovery block used. $state is
				// still the fresh pre-hook snapshot read a few lines above --
				// nothing between there and here can have changed it, since
				// this branch returns before the hook ever fires.
				//
				// Fix round 1, F6: because this branch RETURNS rather than
				// throws, execution still reaches do_action() below and, if
				// a LATER listener on it throws, cancel_booking()'s own
				// post-commit recovery block -- which checks this identical
				// refundable() > 0 condition -- would send the SAME e-mail
				// again. send_refund_failed_email_once() makes that
				// impossible rather than merely unlikely.
				if ( $state->refundable() > 0 ) {
					if ( ! self::send_refund_failed_email_once( $booking_id ) ) {
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
							__( 'Refund-failed notification could not be sent for a refused PENDING transition -- no one has been told.', 'mhm-rentiva' ),
							$booking_id,
							array(
								'surface' => $surface,
								'reason'  => 'notification_failed',
							),
							\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
						);
					}
				}

				// Fix round 2, G1: RETURNED as a value, not thrown. Fix round
				// 1 threw here, which reported the refusal but also unwound
				// past process_refund()'s own return inside cancel_booking()'s
				// try -- skipping do_action( 'mhmrentiva_booking_cancelled', ... )
				// entirely, even though the cancellation itself had already
				// committed and other listeners (Pro's
				// VendorCancellationDateBlocker::maybe_block_dates() among
				// them) still need to run regardless of what happened to the
				// refund. Returning lets the caller decide what to do with
				// the refusal -- add it to 'problems' -- without aborting
				// anything that comes after it. finally below still
				// releases the lock.
				return __( "Refund not attempted: this booking's refund status changed before the refund could start.", 'mhm-rentiva' );
			}

			// Spec v3 §4.2: the integrator hook moves INSIDE the lock. Its
			// stated purpose -- a refund the listener makes is visible to the
			// decision below -- is satisfied equally here, and outside the lock
			// two concurrent requests could run two listeners at once while the
			// mutex taken afterwards serialised nothing.
			try {
				do_action( 'mhmrentiva_process_refund', $booking_id, $payment_gateway, $user_id );
			} catch ( \Throwable $e ) {
				// Task 14b item 1: was error() + add() -- error() alone used
				// to drop this entry's booking linkage (AdvancedLogger::error()
				// takes no booking_id and never passes one to log(), which
				// only writes _mhmrentiva_log_booking_id when
				// $args['booking_id'] is set, the meta the admin Logs list
				// table's Booking column reads), and add() was kept
				// alongside it purely to supply that link. One call now
				// does both.
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
					sprintf(
						/* translators: %s: the message thrown by a mhmrentiva_process_refund listener. */
						__( 'A mhmrentiva_process_refund listener failed: %s', 'mhm-rentiva' ),
						$e->getMessage()
					),
					$booking_id,
					array( 'error' => $e->getMessage() ),
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
				);
			}

			// Resolved again, deliberately: $state above is the pre-hook
			// snapshot Task 15's mixed-currency guard needs. This decision
			// must not reuse it -- see the method docblock.
			wp_cache_delete( $booking_id, 'post_meta' );
			$state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $booking_id );

			if ( $state->refundable() <= 0 ) {
				$externally_refunded = $state->paid() > 0;

				\MHMRentiva\Admin\Payment\Core\RefundStatus::transition(
					$booking_id,
					$externally_refunded ? \MHMRentiva\Admin\Payment\Core\RefundStatus::COMPLETED_EXTERNALLY : \MHMRentiva\Admin\Payment\Core\RefundStatus::NOT_REQUIRED,
					array( 'surface' => $surface )
				);

				return null;
			}

			$result  = \MHMRentiva\Admin\Payment\Refunds\Service::processFullRefund( $booking_id, $reason, $user_id );
			$success = '1' === ( $result['mhmrentiva_refund'] ?? '0' );

			// processFullRefund() returns early -- before finish() -- when
			// RefundValidator refuses the request (empty/pending/failed/refunded
			// payment status). That path never writes a terminal status and
			// never logs, so a refusal here would otherwise leave the 'pending'
			// marker standing forever with no trace of why. RefundStatus's own
			// matrix is the guard now, in place of the read-back this used to
			// do by hand: 'failed' is reachable only from 'pending', so a
			// finish() that already wrote a terminal status (completed/failed/
			// partial_failure) cannot be overwritten by this call, and
			// transition()'s return value says whether this call was the one
			// that actually recorded something.
			if ( ! $success ) {
				$recorded = \MHMRentiva\Admin\Payment\Core\RefundStatus::transition( $booking_id, \MHMRentiva\Admin\Payment\Core\RefundStatus::FAILED, array( 'reason' => 'validator_refused' ) );

				if ( $recorded ) {
					// Fix round 1, F8: was add() -- the only remaining place
					// in this if/else pair still logging through the
					// deprecated API while its own `else` sibling (item 13,
					// added in the same fix round) already uses
					// error_linked(). Converged onto the same call so one
					// if/else does not log through two different
					// mechanisms, one of them deprecated.
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
						$result['mhmrentiva_refund_msg'] ?? '',
						$booking_id,
						array( 'reason' => 'validator_refused' ),
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
					);
				} else {
					// Whole-branch review, F3: transition() to FAILED returns
					// false for TWO unrelated reasons, and this branch used
					// to treat them as one. Reason one is the genuine race
					// item 13 was written for: something else moved
					// refund_status to an unrelated terminal value between
					// the PENDING write above and this point. Reason two is
					// not a race at all -- Service::finish() (inside
					// processFullRefund(), a few lines above, in this SAME
					// request) already wrote this exact gateway failure as
					// FAILED or PARTIAL_FAILURE itself, so this attempt to
					// ALSO write FAILED was always going to be refused:
					// either $from === $to (finish() wrote FAILED, refunded
					// === 0) or partial_failure's only matrix edge leads back
					// to pending, never to failed (finish() wrote
					// PARTIAL_FAILURE, refunded > 0). Logging "could not be
					// recorded" and returning "status changed before the
					// failure could be recorded" for reason two is false on
					// its face -- for the $from === $to case it is also
					// self-contradicting, naming 'failed' as both the status
					// that could not be recorded and the value already
					// standing.
					$post_finish_status = \MHMRentiva\Admin\Payment\Core\RefundStatus::get( $booking_id );

					if ( \MHMRentiva\Admin\Payment\Core\RefundStatus::FAILED === $post_finish_status ) {
						// Nothing is wrong, and nothing new needs recording:
						// finish() already wrote FAILED for this exact
						// failure. All three surfaces read refund_status
						// themselves (`RefundStatus::FAILED ===
						// RefundStatus::get(...)`, DepositManagementAjax.php
						// :303/:684, AccountController.php:1157) alongside
						// 'problems', so they already see this failure
						// without help from a 'problems' entry here -- same
						// as the $recorded === true branch above, which
						// relies on the same fact.
						return null;
					}

					if ( \MHMRentiva\Admin\Payment\Core\RefundStatus::PARTIAL_FAILURE === $post_finish_status ) {
						// Also nothing wrong -- but unlike plain FAILED,
						// partial_failure is NOT what the three surfaces'
						// `FAILED ===` check looks for, so this case DOES
						// still need a 'problems' entry, or the fact that
						// only part of the money went back reaches nobody.
						// $result['mhmrentiva_refund_msg'] already carries
						// finish()'s own true accounting of what happened
						// (the "%1$s was already refunded before this
						// failed: %2$s" message, Service.php's finish()) --
						// reused rather than composing a second, competing
						// description of the same event.
						return $result['mhmrentiva_refund_msg']
							?? __( 'Refund partially failed; the rest did not go back.', 'mhm-rentiva' );
					}

					// The real race item 13 was written for: the matrix
					// refused the FAILED write for a reason OTHER than
					// finish() having already recorded this exact failure --
					// e.g. a mhmrentiva_process_refund listener moved
					// refund_status to an unrelated terminal value (without
					// actually resolving the balance) between the PENDING
					// write above and this point. Before item 13, this
					// branch did nothing at all: no log, and $success's own
					// failure never reached 'problems' either -- so all
					// three AJAX surfaces kept reading "cancelled
					// successfully" while the money never moved and nothing
					// anywhere said a refund had even been attempted here.
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
						sprintf(
							/* translators: %s: the booking's current refund_status, which has no outgoing edge to FAILED. */
							__( "Refund FAILED status could not be recorded: current refund_status is '%s'.", 'mhm-rentiva' ),
							$post_finish_status
						),
						$booking_id,
						array( 'reason' => 'failed_not_recorded' ),
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
					);

					return __( "Refund failed, but this booking's refund status changed before the failure could be recorded.", 'mhm-rentiva' );
				}
			}

			// Fix round 2, G1: $success already drove the FAILED transition
			// and its log entry above -- both existed before this task and
			// are untouched. A validator refusal here is read back from
			// _mhmrentiva_refund_status by every consumer that cares (the
			// AJAX surfaces' FAILED check, F6), so it does not also need a
			// 'problems' entry; only the PENDING-not-recorded refusal above,
			// and item 13's own $recorded === false branch just above, do.
			return null;
		} finally {
			\MHMRentiva\Admin\Payment\Core\RefundLock::release( $booking_id );
		}
	}

	/**
	 * Check if user can cancel booking
	 *
	 * @param int $booking_id Booking ID
	 * @param int $user_id User ID (0 for current user)
	 * @return bool True if can cancel, false otherwise
	 */
	/**
	 * The account a booking belongs to.
	 *
	 * Both call sites used to read `_mhmrentiva_customer_id` directly. Nothing in
	 * either edition has ever written that key: every writer uses
	 * `_mhmrentiva_customer_user_id`, and so does every other reader
	 * (AccountController::can_access_receipt(), the receipt handlers,
	 * RemainingPaymentHandler). The read returned '' -> 0, matched no real user,
	 * and customer self-cancellation was refused for everyone -- a feature that
	 * had never worked rather than a hole, since it failed closed.
	 *
	 * That key is NOT read as a fallback. Ownership is an authorization answer,
	 * and reading it from a shape no writer produces hands ownership to anything
	 * that can put the key there by another route -- an import, a sibling plugin,
	 * a hand-edited row -- with no writer of ours to audit against. The usual
	 * argument for a legacy fallback is existing data, and there is none:
	 * measured on 2026-08-16 the key had zero writers in the tree, no migration
	 * step, and zero rows in the database.
	 *
	 * The Faz 2 sweep reached this conclusion independently and pinned it in
	 * CancellationOwnershipTest::test_the_gate_reads_the_key_that_is_actually_written().
	 */
	private static function resolve_booking_customer_id( int $booking_id ): int {
		return (int) get_post_meta( $booking_id, '_mhmrentiva_customer_user_id', true );
	}

	public static function user_can_cancel( int $booking_id, int $user_id = 0 ): bool {
		if ( $user_id === 0 ) {
			$user_id = get_current_user_id();
		}

		// Admins can always cancel. Asks the $user_id argument, not the
		// ambient request (fix round 1, F3) -- this was the same defect
		// class MoneyAuthorization exists to remove one method below,
		// reached from the UI-gate side instead of the money side. The
		// single production caller (templates/account/booking-detail.php)
		// never passes an explicit actor, so $user_id is always the current
		// user there and this changes nothing for it; it matters the moment
		// any caller asks on another actor's behalf.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Check if user owns the booking.
		//
		// Ownership has to be an identity, not the absence of one: a booking
		// with no owner meta resolves to 0 and a logged-out visitor is also 0,
		// so a bare `!==` let two zeros stand in for a match and the ownership
		// question was never asked. Both sides must name a real user first.
		$booking_customer_id = self::resolve_booking_customer_id( $booking_id );
		if ( $booking_customer_id <= 0 || $user_id <= 0 || $booking_customer_id !== $user_id ) {
			return false;
		}

		// Check status
		$current_status = Status::get( $booking_id );
		if ( in_array( $current_status, array( Status::CANCELLED, Status::COMPLETED, Status::REFUNDED ), true ) ) {
			return false;
		}

		// Check deadline
		$deadline_check = self::check_cancellation_deadline( $booking_id );

		return ! is_wp_error( $deadline_check );
	}

	/**
	 * Get cancellation deadline info for display
	 *
	 * @param int $booking_id Booking ID
	 * @return array{can_cancel: bool, deadline: string, hours_remaining: float, message: string}
	 */
	public static function get_cancellation_info( int $booking_id ): array {
		$deadline_hours = (int) SettingsCore::get( 'mhmrentiva_booking_cancellation_deadline_hours', 24 );

		$pickup_date = get_post_meta( $booking_id, '_mhmrentiva_pickup_date', true );
		$pickup_time = get_post_meta( $booking_id, '_mhmrentiva_pickup_time', true );

		if ( empty( $pickup_date ) ) {
			return array(
				'can_cancel'      => false,
				'deadline'        => '',
				'hours_remaining' => 0,
				'message'         => __( 'Invalid booking data.', 'mhm-rentiva' ),
			);
		}

		if ( empty( $pickup_time ) ) {
			$pickup_time = '10:00';
		}

		try {
			$timezone        = wp_timezone();
			$pickup_datetime = \DateTime::createFromFormat( 'Y-m-d H:i', $pickup_date . ' ' . $pickup_time, $timezone );

			if ( ! $pickup_datetime ) {
				return array(
					'can_cancel'      => false,
					'deadline'        => '',
					'hours_remaining' => 0,
					'message'         => __( 'Invalid pickup date/time format.', 'mhm-rentiva' ),
				);
			}

			$cancellation_deadline = clone $pickup_datetime;
			$cancellation_deadline->modify( '-' . $deadline_hours . ' hours' );

			$current_time    = new \DateTime( 'now', $timezone );
			$hours_remaining = ( $cancellation_deadline->getTimestamp() - $current_time->getTimestamp() ) / 3600;

			$can_cancel = $hours_remaining > 0;

			if ( $can_cancel ) {
				$message = sprintf(
					/* translators: 1: %s; 2: %d. */
					__( 'You can cancel until %1$s (%2$d hours before pickup)', 'mhm-rentiva' ),
					$cancellation_deadline->format( 'Y-m-d H:i' ),
					$deadline_hours
				);
			} else {
				$message = sprintf(
					/* translators: %d placeholder. */
					__( 'Cancellation deadline has passed. Bookings must be cancelled at least %d hours before pickup.', 'mhm-rentiva' ),
					$deadline_hours
				);
			}

			return array(
				'can_cancel'      => $can_cancel,
				'deadline'        => $cancellation_deadline->format( 'Y-m-d H:i' ),
				'hours_remaining' => round( $hours_remaining, 1 ),
				'message'         => $message,
			);
		} catch ( \Exception $e ) {
			return array(
				'can_cancel'      => false,
				'deadline'        => '',
				'hours_remaining' => 0,
				'message'         => __( 'Error calculating cancellation deadline.', 'mhm-rentiva' ),
			);
		}
	}
}
