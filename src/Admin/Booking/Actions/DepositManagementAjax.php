<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Actions;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox;
use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\MoneyAuthorization;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Admin\Emails\Core\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deposit-management write endpoints (payment approval, remaining-payment
 * processing/link, cancellation, refund, manual refund closure).
 *
 * Every handler opens with a line-local `check_ajax_referer( ..., false )`
 * that is REDUNDANT with authorize_booking_action() immediately below it.
 * That is deliberate: this file used to contain its own nonce and capability
 * checks inline, and the Faz 2 Task 7 guard extraction moved both one file
 * away -- leaving five registered `wp_ajax_*` money endpoints in which
 * grepping for a nonce check finds nothing (a sixth, close_manual_refund(),
 * joined later under the same pattern). The authoritative check (and the
 * failure response) still belongs to BookingActionGuard; these lines only
 * make the protection visible where the endpoint is.
 */
final class DepositManagementAjax {

	/**
	 * Single entry guard for every deposit action.
	 *
	 * Delegates to BookingActionGuard::authorize() (Faz 2 Task 7 extraction)
	 * under this class's own nonce action -- byte-for-byte the same four
	 * checks (nonce, id, post_type, edit_post on the resolved booking) this
	 * method used to run inline. See BookingActionGuard's docblock for why
	 * edit_post on the resolved booking, not a blanket edit_posts check, is
	 * the capability that belongs here: the handlers used to check the
	 * blanket capability and then act on whichever booking_id arrived, so
	 * any role with edit_posts (a contributor, for instance) could approve
	 * payments, cancel bookings or issue refunds on bookings belonging to
	 * anyone.
	 */
	private static function authorize_booking_action(): int {
		$booking_id = BookingActionGuard::authorize( 'mhmrentiva_deposit_management_action' );
		if ( ! $booking_id ) {
			return 0;
		}

		// Redundant by design: keeps the capability check greppable in this
		// file (WP.org review lens). BookingActionGuard::authorize() already
		// ran this exact current_user_can('edit_post', ...) check against
		// this exact booking id one line above -- nothing runs in between
		// that could change the outcome, so this branch can never reject a
		// caller the guard just approved. Message matches the guard's own
		// rejection payload so behaviour is identical either way.
		if ( ! current_user_can( 'edit_post', $booking_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return 0;
		}

		return $booking_id;
	}

	public static function register(): void {
		add_action( 'wp_ajax_mhmrentiva_process_remaining_payment', array( self::class, 'process_remaining_payment' ) );
		add_action( 'wp_ajax_mhmrentiva_send_remaining_payment_link', array( self::class, 'send_remaining_payment_link' ) );
		add_action( 'wp_ajax_mhmrentiva_approve_payment', array( self::class, 'approve_payment' ) );
		add_action( 'wp_ajax_mhmrentiva_deposit_cancel_booking', array( self::class, 'cancel_booking' ) );
		add_action( 'wp_ajax_mhmrentiva_deposit_process_refund', array( self::class, 'process_refund' ) );
		add_action( 'wp_ajax_mhmrentiva_close_manual_refund', array( self::class, 'close_manual_refund' ) );
	}

	public static function process_remaining_payment(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		// Deposit system check
		$payment_type = get_post_meta( $booking_id, '_mhmrentiva_payment_type', true );
		if ( $payment_type !== 'deposit' ) {
			wp_send_json_error( array( 'message' => __( 'This booking does not use deposit system.', 'mhm-rentiva' ) ) );
			return;
		}

		$remaining_amount = floatval( get_post_meta( $booking_id, '_mhmrentiva_remaining_amount', true ) );
		if ( $remaining_amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'No remaining amount found.', 'mhm-rentiva' ) ) );
			return;
		}

		// Reset remaining amount
		update_post_meta( $booking_id, '_mhmrentiva_remaining_amount', 0 );

		// Update payment status
		update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'paid' );

		// If the rental period has actually ended, mark as completed; otherwise
		// confirmed. Uses the drop-off time, not just the date -- see
		// Util::rental_has_ended().
		$target_status = \MHMRentiva\Admin\Booking\Helpers\Util::rental_has_ended( $booking_id ) ? 'completed' : 'confirmed';
		Status::update_status( $booking_id, $target_status, get_current_user_id() );

		// Add log
		self::add_booking_log(
			$booking_id,
			'remaining_payment_processed',
			array(
				'amount'       => $remaining_amount,
				'processed_by' => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Remaining amount processed successfully.', 'mhm-rentiva' ),
			)
		);
	}

	public static function send_remaining_payment_link(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		// Deposit system check (same guard as process_remaining_payment())
		$payment_type = get_post_meta( $booking_id, '_mhmrentiva_payment_type', true );
		if ( $payment_type !== 'deposit' ) {
			wp_send_json_error( array( 'message' => __( 'This booking does not use deposit system.', 'mhm-rentiva' ) ) );
			return;
		}

		$remaining_amount = floatval( get_post_meta( $booking_id, '_mhmrentiva_remaining_amount', true ) );
		if ( $remaining_amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'No remaining amount found.', 'mhm-rentiva' ) ) );
			return;
		}

		$order = RemainingPaymentHandler::get_or_create_remaining_order( $booking_id );
		if ( is_wp_error( $order ) ) {
			wp_send_json_error( array( 'message' => $order->get_error_message() ) );
			return;
		}

		$payment_url = $order->get_checkout_payment_url();

		Mailer::sendBookingEmail(
			'remaining_payment_link_customer',
			$booking_id,
			'customer',
			array(
				'payment' => array( 'url' => $payment_url ),
			)
		);

		// Add log
		self::add_booking_log(
			$booking_id,
			'remaining_payment_link_sent',
			array(
				'order_id' => $order->get_id(),
				'sent_by'  => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message'     => __( 'Payment link generated and emailed to the customer.', 'mhm-rentiva' ),
				'payment_url' => $payment_url,
			)
		);
	}

	/**
	 * Whether a remaining-payment link can be offered for this booking.
	 *
	 * Delegates to RemainingPaymentHandler::is_hybrid_booking() -- the exact
	 * question get_or_create_remaining_order() answers before it refuses.
	 * Asked here so the admin screen does not render a control whose only
	 * outcome is an error message. One predicate, two callers: if this drifts
	 * from the handler's guard, the button comes back.
	 */
	public static function can_send_remaining_payment_link( int $booking_id ): bool {
		return ! RemainingPaymentHandler::is_hybrid_booking( $booking_id );
	}

	public static function approve_payment(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		$payment_status = get_post_meta( $booking_id, '_mhmrentiva_payment_status', true );
		if ( ! in_array( $payment_status, array( 'pending', 'unpaid', 'pending_verification', '' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This booking is not awaiting payment.', 'mhm-rentiva' ) ) );
			return;
		}

		// Update payment status to confirmed
		update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'paid' );

		// Update booking status to confirmed
		Status::update_status( $booking_id, 'confirmed', get_current_user_id() );

		// Add log
		self::add_booking_log(
			$booking_id,
			'payment_approved',
			array(
				'approved_by' => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Payment confirmed successfully.', 'mhm-rentiva' ),
			)
		);
	}

	public static function cancel_booking(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		$current_status = Status::get( $booking_id );
		if ( ! in_array( $current_status, array( 'pending', 'confirmed' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This booking cannot be cancelled.', 'mhm-rentiva' ) ) );
			return;
		}

		// One entry point for both cancellation surfaces (spec §5.3, decision
		// 4). $force is true because the deadline in CancellationHandler is the
		// CUSTOMER's cancellation policy; the operator's own button must not
		// inherit it -- this screen never applied it before.
		$result = CancellationHandler::cancel_booking( $booking_id, get_current_user_id(), '', true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return;
		}

		// Add log
		self::add_booking_log(
			$booking_id,
			'booking_cancelled',
			array(
				'cancelled_by' => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Booking cancelled successfully.', 'mhm-rentiva' ),
			)
		);
	}

	public static function process_refund(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		// The same predicate the deposit screen gates its button on and the
		// refund box gates its link on. Three copies of this question used to
		// live in three files and disagree; see
		// BookingDepositMetaBox::can_refund_from_deposit_screen().
		if ( ! BookingDepositMetaBox::can_refund_from_deposit_screen( $booking_id ) ) {
			// An empty balance is the one failing condition the operator can
			// read a meaning into, so it gets said out loud rather than hidden
			// behind the generic refusal.
			$message = PaymentState::forBooking( $booking_id )->refundable() > 0
				? __( 'Refund cannot be processed for this booking.', 'mhm-rentiva' )
				: __( 'This booking has no refundable balance left.', 'mhm-rentiva' );

			wp_send_json_error( array( 'message' => $message ) );
			return;
		}

		// The refund amount IS the refundable balance. It used to be computed
		// from _mhmrentiva_deposit_amount, which a full-payment booking stores
		// as 0 -- so every such booking was told "Refund not processed due to
		// cancellation policy" and refunded nothing, however much money was
		// genuinely still refundable.
		//
		// 🔴 This is an int in MINOR units (kuruş), unlike the major-unit float
		// it replaced. Service::process() wants minor units, so there is no
		// Money::toMinor() at the call site any more; the display path is the
		// one that now needs Money::toMajor().
		$refund_amount = PaymentState::forBooking( $booking_id )->refundable();

		// Cancellation policy check. Unchanged in intent: past the deadline
		// nothing is owed back, otherwise the whole amount is. Only the meaning
		// of "the whole amount" moved, from the deposit to the balance.
		$cancellation_deadline = get_post_meta( $booking_id, '_mhmrentiva_cancellation_deadline', true );

		if ( $cancellation_deadline && time() > strtotime( $cancellation_deadline . ' UTC' ) ) {
			$refund_amount = 0;
		}

		// Policy says nothing is owed back: nothing to attempt, nothing to
		// change. The gate above already proved refundable() > 0, so an expired
		// deadline is the only way to reach this -- which is why the message
		// can name the policy honestly. It could not before: a zero deposit
		// arrived here too and got the same sentence on bookings that had no
		// cancellation deadline at all.
		if ( $refund_amount <= 0 ) {
			self::add_booking_log(
				$booking_id,
				'refund_skipped',
				array(
					'reason'       => 'cancellation_policy',
					'processed_by' => get_current_user_id(),
				)
			);

			wp_send_json_success(
				array(
					'message' => __( 'Refund not processed due to cancellation policy.', 'mhm-rentiva' ),
				)
			);
			return;
		}

		// Hand the actual refund to the refund service.
		//
		// Until 6.0.4 this method wrote _mhmrentiva_payment_status = refunded and
		// answered "Refund completed successfully" WITHOUT ever calling a gateway:
		// no wc_create_refund(), no API call, nothing checking that money moved.
		// The booking said refunded, the operator saw success, and the customer was
		// never paid back.
		//
		// Payment\Refunds\Service does it properly -- validates, calls the gateway,
		// inspects the result and only writes meta when the refund actually
		// happened -- and had no caller anywhere in either edition. Rather than
		// grow a second refund implementation, the live path now goes through it.
		//
		// It works in kuruş (minor units), which is what every consumer of
		// _mhmrentiva_refunded_amount already assumes (BookingRefundMetaBox,
		// RefundCalculator); the float this method used to write was itself a unit
		// mismatch with those readers. $refund_amount is already in those units.
		$result = \MHMRentiva\Admin\Payment\Refunds\Service::process(
			$booking_id,
			$refund_amount,
			__( 'Refund issued from the deposit management screen.', 'mhm-rentiva' ),
			get_current_user_id()
		);

		// The log's `refund_amount` key is shared with WooCommerceBridge, which
		// writes it in major units; keep the unit of the key rather than
		// silently rescaling a stored audit record.
		$refund_amount_major = (float) Money::toMajor( $refund_amount );

		if ( '1' !== ( $result['mhmrentiva_refund'] ?? '0' ) ) {
			$message = (string) ( $result['mhmrentiva_refund_msg'] ?? '' );

			self::add_booking_log(
				$booking_id,
				'refund_failed',
				array(
					'refund_amount' => $refund_amount_major,
					'processed_by'  => get_current_user_id(),
					'reason'        => $message,
				)
			);

			wp_send_json_error(
				array(
					'message' => '' !== $message ? $message : __( 'Refund failed.', 'mhm-rentiva' ),
				)
			);
			return;
		}

		// The service has written payment status, refunded amount and the gateway
		// transaction id. These two are this screen's own audit trail.
		update_post_meta( $booking_id, '_mhmrentiva_refund_date', gmdate( 'Y-m-d H:i:s' ) );
		update_post_meta( $booking_id, '_mhmrentiva_refund_processed_by', get_current_user_id() );

		self::add_booking_log(
			$booking_id,
			'refund_processed',
			array(
				'refund_amount' => $refund_amount_major,
				'processed_by'  => get_current_user_id(),
			)
		);

		wp_send_json_success(
			array(
				/* translators: %s placeholder. */
				'message' => sprintf( __( 'Refund completed successfully. Refund amount: %s', 'mhm-rentiva' ), self::format_price( $refund_amount_major ) ),
			)
		);
	}

	/**
	 * Reference strings are free text from an operator; capped so a pasted
	 * essay does not land verbatim in a WC order note and in post meta.
	 * 191, not a round number: the sibling-safe bound under utf8mb4 (the
	 * historical MySQL index-byte-length ceiling other varchar columns in
	 * this stack are sized to), used here purely as a sane text cap, not
	 * because this value is indexed.
	 */
	private const REFERENCE_MAX_LENGTH = 191;

	/**
	 * Attest that a manual_pending refund's money was handed over.
	 *
	 * Moves no money and computes nothing: RefundStatus::transition() already
	 * refuses every edge except manual_pending -> completed_manually, so a
	 * second click, or a click on a booking this task never reached, is
	 * turned away by the transition matrix itself rather than by a check
	 * duplicated here. The booking-level audit trio (_by/_at/_reference) is
	 * written unconditionally -- it is the entire record of who is vouching
	 * for this transfer. The WC order note is written only when a
	 * WooCommerce order actually backs the money: the offline channel this
	 * endpoint exists for is precisely the case where no such order exists,
	 * so requiring one would make the channel impossible to close.
	 *
	 * Every terminating wp_send_json_*() call is OUTSIDE the try/finally
	 * below, on purpose (fix round 1, C1). wp_send_json_*() calls wp_die(),
	 * which is a hard exit in production -- PHP does not run a finally block
	 * across an exit -- so a version of this method that called
	 * wp_send_json_success()/wp_send_json_error() from INSIDE the try left
	 * RefundLock::release() unreached on every real request, leaking the
	 * lock for RefundLock::TTL_SECONDS. WP_Ajax_UnitTestCase could not catch
	 * this: it intercepts wp_die() by THROWING (WPAjaxDieContinueException
	 * extends \Exception), and PHP's finally DOES run while an exception
	 * unwinds the stack -- so the bug was invisible to every test that goes
	 * through _handleAjax(). $moved is decided and acted on entirely inside
	 * the try (RefundStatus::transition()'s own isHeld() guard is satisfied
	 * the whole time the lock is held), and the lock is released before
	 * either response is sent.
	 */
	public static function close_manual_refund(): void {
		// Line-local nonce check, redundant by design -- see the class
		// docblock. authorize_booking_action() below is authoritative.
		check_ajax_referer( 'mhmrentiva_deposit_management_action', 'nonce', false );

		$booking_id = self::authorize_booking_action();
		if ( ! $booking_id ) {
			return;
		}

		$actor = get_current_user_id();

		// Moving no money, but attesting that money moved -- the same bar.
		if ( ! MoneyAuthorization::mayMoveMoney( $booking_id, $actor, 'manual_close' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return;
		}

		$req       = VerifiedRequest::from( $_POST );
		$reference = mb_substr( $req->text( 'reference' ), 0, self::REFERENCE_MAX_LENGTH );

		if ( ! RefundLock::acquire( $booking_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Another refund is already running for this booking. Please try again in a moment.', 'mhm-rentiva' ) ) );
			return;
		}

		$moved = false;

		try {
			$moved = RefundStatus::transition(
				$booking_id,
				RefundStatus::COMPLETED_MANUALLY,
				array(
					'surface'  => 'manual_close',
					'actor_id' => $actor,
				)
			);

			if ( $moved ) {
				// The audit record is booking-level and mandatory. The WC
				// order note is conditional: the offline channel exists
				// precisely because there is no WooCommerce order behind the
				// money, so requiring one would make this channel impossible
				// to close.
				update_post_meta( $booking_id, '_mhmrentiva_refund_completed_by', $actor );
				update_post_meta( $booking_id, '_mhmrentiva_refund_completed_at', current_time( 'mysql' ) );
				update_post_meta( $booking_id, '_mhmrentiva_refund_completed_reference', $reference );

				$order_id = BookingQueryHelper::resolve_wc_order_id( $booking_id );
				$order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

				if ( $order instanceof \WC_Order ) {
					$order->add_order_note(
						sprintf(
							/* translators: %s: operator-supplied payment reference */
							__( 'Hand transfer of the refund confirmed. Reference: %s', 'mhm-rentiva' ),
							$reference
						)
					);
				}

				self::add_booking_log(
					$booking_id,
					'manual_refund_closed',
					array(
						'reference'    => $reference,
						'processed_by' => $actor,
					)
				);
			}
		} finally {
			RefundLock::release( $booking_id );
		}

		if ( ! $moved ) {
			wp_send_json_error( array( 'message' => __( 'This refund is not awaiting a hand transfer.', 'mhm-rentiva' ) ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'Hand transfer recorded.', 'mhm-rentiva' ) ) );
	}

	private static function add_booking_log( int $booking_id, string $action, array $data = array() ): void {
		$logs_meta = get_post_meta( $booking_id, '_mhmrentiva_booking_logs', true );
		$logs      = is_array( $logs_meta ) ? $logs_meta : array();

		$logs[] = array(
			'action'    => $action,
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
			'data'      => $data,
		);

		update_post_meta( $booking_id, '_mhmrentiva_booking_logs', $logs );
	}

	private static function format_price( float $price ): string {
		// Canonical currency formatting (WC-aware symbol/position/separators).
		// Reading mhmrentiva_currency_position here pinned this to `right_space`
		// whenever that option was unset, which is its normal state.
		return \MHMRentiva\Admin\Core\CurrencyHelper::format_price( $price, 2 );
	}
}
