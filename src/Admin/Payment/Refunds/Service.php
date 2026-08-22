<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Emails\Notifications\RefundNotifications;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger as Logger;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\MoneyAuthorization;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Core\RefundStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Service {

	/**
	 * Bookings whose refund operation is currently running, keyed by id.
	 *
	 * Per booking, not global: a cron pass can refund several bookings inside
	 * one request, and a single boolean would silence every booking after the
	 * first. Always released in a finally block -- a flag left standing after
	 * an exception makes every later refund in the request go out with no
	 * e-mail at all, which is the failure mode nobody notices.
	 *
	 * @var array<int, true>
	 */
	private static array $inFlight = array();

	/**
	 * Is a Service-driven refund operation running for this booking right now?
	 *
	 * WooCommerceBridge::handle_order_refunded() asks this to decide whether it
	 * owns the customer e-mail. When the answer is yes, Service sends one
	 * e-mail at the end of the whole operation; when it is no -- an admin
	 * refunding from WooCommerce's own order screen -- the hook sends it.
	 */
	public static function isRefundInFlight( int $bookingId ): bool {
		return isset( self::$inFlight[ $bookingId ] );
	}

	public static function process( int $bookingId, int $amountKurus, string $reason, int $actorId ): array {
		// First statement, on purpose (spec §5): every caller of this method
		// inherits the gate whether it remembers to ask or not. Before this
		// task the question lived at each call site instead, and one of them
		// -- Actions::refund_booking() -- never asked at all.
		//
		// Always asks as 'service', regardless of who is calling -- even when
		// the caller is CancellationHandler::process_refund(), which already
		// asked the SAME actor one layer up as 'refund' (or cancel_booking()'s
		// own outer gate, as 'cancel'). That is a deliberate double-ask, not a
		// redundant one: by the time this ask runs, the outer ask has already
		// let settle_refund() take RefundLock and write the 'pending' status.
		// A future surface-aware filter that allows the outer ask and refuses
		// this one would refuse here AFTER that lock and write already
		// happened -- and the caller-side code records that as
		// 'reason' => 'validator_refused', a label that would then describe a
		// permission refusal, not a validator one (fix round 1, F6). Threading
		// the caller's own surface through this ask was judged out of this
		// task's scope; a surface-aware filter has to account for the
		// two-ask sequence itself.
		if ( ! MoneyAuthorization::mayMoveMoney( $bookingId, $actorId, 'service' ) ) {
			self::logRefusedMoneyMove( $bookingId );

			return array(
				'mhmrentiva_refund'     => '0',
				'mhmrentiva_refund_msg' => __( 'You do not have permission to move money on this booking.', 'mhm-rentiva' ),
			);
		}

		return self::withLock(
			$bookingId,
			static function () use ( $bookingId, $amountKurus, $reason ): array {
				// The flag goes up before validation, not after. Validation resolves a
				// PaymentState and touches WooCommerce objects, so it is part of the
				// operation this booking owns; a throw there has to unwind the flag
				// exactly like a throw in the refund loop. Raising it after validation
				// leaves a window the finally block does not cover.
				self::$inFlight[ $bookingId ] = true;

				try {
					$validation = RefundValidator::validatePartialRefund( $bookingId, $amountKurus );

					if ( ! $validation['valid'] ) {
						return array(
							'mhmrentiva_refund'     => '0',
							'mhmrentiva_refund_msg' => $validation['message'],
						);
					}

					$operation = self::runOperation( $bookingId, $validation['amount'], $reason );
				} finally {
					unset( self::$inFlight[ $bookingId ] );
				}

				return self::finish( $bookingId, $operation, $reason );
			}
		);
	}

	public static function processFullRefund( int $bookingId, string $reason, int $actorId ): array {
		// See process() above -- the same gate, the same reason it is the
		// first statement, the same 'service' surface, and the same
		// two-ask-sequence note.
		if ( ! MoneyAuthorization::mayMoveMoney( $bookingId, $actorId, 'service' ) ) {
			self::logRefusedMoneyMove( $bookingId );

			return array(
				'mhmrentiva_refund'     => '0',
				'mhmrentiva_refund_msg' => __( 'You do not have permission to move money on this booking.', 'mhm-rentiva' ),
			);
		}

		return self::withLock(
			$bookingId,
			static function () use ( $bookingId, $reason ): array {
				self::$inFlight[ $bookingId ] = true;

				try {
					$validation = RefundValidator::validateFullRefund( $bookingId );

					if ( ! $validation['valid'] ) {
						return array(
							'mhmrentiva_refund'     => '0',
							'mhmrentiva_refund_msg' => $validation['message'],
						);
					}

					$operation = self::runOperation( $bookingId, $validation['amount'], $reason );
				} finally {
					unset( self::$inFlight[ $bookingId ] );
				}

				return self::finish( $bookingId, $operation, $reason );
			}
		);
	}

	/**
	 * A refused money-move attempt is exactly the event an operator needs
	 * after the fact -- this task's whole point is that a caller (like
	 * Actions::refund_booking()) can now reach this refusal having never
	 * asked the question itself, so nothing else in the tree is guaranteed
	 * to have logged the attempt (fix round 1, F2). Mirrors the shape
	 * CancellationHandler::process_refund()'s own refusal branch already
	 * uses, one layer up.
	 */
	private static function logRefusedMoneyMove( int $bookingId ): void {
		Logger::add(
			array(
				'gateway'    => 'refund_service',
				'action'     => 'refund',
				'status'     => 'error',
				'booking_id' => $bookingId,
				'message'    => __( 'Refund not attempted: this request was not attributed to a user allowed to move money.', 'mhm-rentiva' ),
			)
		);
	}

	/**
	 * Serialise the money step per booking.
	 *
	 * The in-flight flag above and this lock answer different questions and
	 * both stay: $inFlight decides who owns the customer e-mail inside ONE
	 * request, the lock decides whether a SECOND request may run at all.
	 *
	 * @param callable(): array{mhmrentiva_refund: string, mhmrentiva_refund_msg: string} $operation
	 * @return array{mhmrentiva_refund: string, mhmrentiva_refund_msg: string}
	 */
	private static function withLock( int $bookingId, callable $operation ): array {
		if ( ! RefundLock::acquire( $bookingId ) ) {
			return array(
				'mhmrentiva_refund'     => '0',
				'mhmrentiva_refund_msg' => __( 'Another refund is already running for this booking. Please try again in a moment.', 'mhm-rentiva' ),
			);
		}

		// Waiting for the lock is only half of it: $operation() (the validator,
		// via PaymentState::forBooking()) is about to read booking meta that a
		// concurrent request may just have written and committed. WordPress's
		// request-local post_meta cache does not know that -- it can still be
		// serving a snapshot taken before this acquire() succeeded. Without
		// this, the critical section decides on data that predates the lock it
		// just waited for. Same fix, same reasoning, as
		// RemainingPaymentHandler::resolve_remaining_order(): "Serialisation
		// without freshness is not mutual exclusion."
		wp_cache_delete( $bookingId, 'post_meta' );

		// finish() below writes a terminal status through RefundStatus::transition(),
		// and the matrix only reaches completed/manual_pending/partial_failure/failed
		// FROM pending -- never directly from the empty string. CancellationHandler
		// already writes pending before it ever calls in here, so for that caller
		// this is a same-value no-op (transition() reports "nothing changed" and
		// skips the hook). For a caller that reaches this class directly --
		// DepositManagementAjax, Actions.php, a future integration -- this is the
		// only place that establishes the precondition finish() now depends on;
		// without it, a direct caller's terminal write would be silently refused
		// by the matrix and the booking would carry no record at all of what its
		// own refund operation just did.
		RefundStatus::transition( $bookingId, RefundStatus::PENDING, array( 'surface' => 'refunds_service' ) );

		try {
			return $operation();
		} finally {
			RefundLock::release( $bookingId );
		}
	}

	/**
	 * Refund an amount across the booking's paid orders, original first.
	 *
	 * Each order is refunded by at most its own remaining balance, so the
	 * operation never asks WooCommerce for more than one order can give back
	 * (wc_create_refund() rejects that outright, WC 11.0.1 :584-586). The
	 * refund_payment flag is decided per order rather than per booking: a
	 * deposit paid by card and a remainder paid by transfer are two different
	 * answers, and collapsing them to "manual" would record a refund for the
	 * card without moving the money.
	 *
	 * auto_refunded and manual_refunded (both minor units, summing to
	 * 'refunded') carry the same per-order split forward: 'mode' alone
	 * collapses a mixed operation to a single word, which is exactly the
	 * H-2 messaging defect -- an operator told "transfer the amount above
	 * manually" for the WHOLE total when only part of it never touched a
	 * gateway. RefundNotifications::notify() reads these two to tell the
	 * two amounts apart.
	 *
	 * @return array{ok: bool, refunded: int, auto_refunded: int, manual_refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string, order_refunds: array<int, string>}
	 */
	private static function runOperation( int $bookingId, int $amountKurus, string $reason ): array {
		$state   = PaymentState::forBooking( $bookingId );
		$orders  = $state->orders();
		$channel = array() === $orders
			? RefundValidator::CHANNEL_OFFLINE
			: RefundValidator::CHANNEL_WOOCOMMERCE;

		if ( RefundValidator::CHANNEL_OFFLINE === $channel ) {
			// Nothing to call: there is no gateway behind offline money. The
			// refund is a bookkeeping record and Task 8 writes it.
			return array(
				'ok'              => true,
				'refunded'        => $amountKurus,
				'auto_refunded'   => 0,
				'manual_refunded' => $amountKurus,
				'mode'            => RefundValidator::MODE_MANUAL,
				'txn_ids'         => array( 'manual_' . wp_generate_uuid4() ),
				'channel'         => $channel,
				'message'         => '',
				'order_refunds'   => array(),
			);
		}

		$outstanding    = $amountKurus;
		$refunded       = 0;
		$autoRefunded   = 0;
		$manualRefunded = 0;
		$txnIds         = array();
		$orderRefunds   = array();
		$allAuto        = true;

		foreach ( $orders as $orderId ) {
			if ( $outstanding <= 0 ) {
				break;
			}

			$order = wc_get_order( $orderId );

			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			$available = Money::toMinor( $order->get_remaining_refund_amount() );

			if ( $available <= 0 ) {
				continue;
			}

			$leg  = min( $outstanding, $available );
			$mode = RefundValidator::modeForOrder( $order );

			if ( RefundValidator::MODE_AUTO !== $mode ) {
				$allAuto = false;
			}

			$refund = wc_create_refund(
				array(
					'order_id'       => $orderId,
					'amount'         => Money::toMajor( $leg ),
					'reason'         => '' !== $reason ? $reason : __( 'Refund processed from Rentiva panel', 'mhm-rentiva' ),
					'refund_payment' => RefundValidator::MODE_AUTO === $mode,
				)
			);

			if ( is_wp_error( $refund ) ) {
				// The flow stops here. Refunds already made are NOT rolled back
				// -- WooCommerce has no such operation -- so the caller records
				// a partial failure and the operator retries the rest. The
				// subtotals reflect only the legs that actually succeeded
				// before this one failed, same as 'refunded' does.
				return array(
					'ok'              => false,
					'refunded'        => $refunded,
					'auto_refunded'   => $autoRefunded,
					'manual_refunded' => $manualRefunded,
					'mode'            => $allAuto ? RefundValidator::MODE_AUTO : RefundValidator::MODE_MANUAL,
					'txn_ids'         => $txnIds,
					// Correction #5: only a leg that ACTUALLY moved money votes
					// here -- the leg that just failed never reached $autoRefunded
					// / $manualRefunded, so this reflects the legs that succeeded
					// before it, not what was attempted.
					'channel'         => ( $autoRefunded > 0 && $manualRefunded > 0 ) ? RefundValidator::CHANNEL_MIXED : $channel,
					'message'         => $refund->get_error_message() ?: __( 'Failed to create WooCommerce refund', 'mhm-rentiva' ),
					'order_refunds'   => $orderRefunds,
				);
			}

			if ( RefundValidator::MODE_AUTO === $mode ) {
				$autoRefunded += $leg;
			} else {
				$manualRefunded += $leg;
			}

			$txnIds[]                 = (string) $refund->get_id();
			$orderRefunds[ $orderId ] = (string) $refund->get_id();
			$refunded                += $leg;
			$outstanding             -= $leg;
		}

		if ( 0 === $refunded ) {
			return array(
				'ok'              => false,
				'refunded'        => 0,
				'auto_refunded'   => 0,
				'manual_refunded' => 0,
				'mode'            => RefundValidator::MODE_MANUAL,
				'txn_ids'         => array(),
				'channel'         => $channel,
				'message'         => __( 'No amount left to refund', 'mhm-rentiva' ),
				'order_refunds'   => array(),
			);
		}

		return array(
			'ok'              => $outstanding <= 0,
			'refunded'        => $refunded,
			'auto_refunded'   => $autoRefunded,
			'manual_refunded' => $manualRefunded,
			'mode'            => $allAuto ? RefundValidator::MODE_AUTO : RefundValidator::MODE_MANUAL,
			'txn_ids'         => $txnIds,
			// Both legs genuinely moved money, one auto, one manual: neither
			// CHANNEL_WOOCOMMERCE nor CHANNEL_OFFLINE alone tells a listener
			// that. The offline ledger at CHANNEL_OFFLINE === channel below
			// (finish()) is untouched by this value -- 'mixed' never equals
			// CHANNEL_OFFLINE, so a mixed operation cannot fall into that
			// branch and double-count money PaymentState already sees through
			// the WooCommerce orders themselves.
			'channel'         => ( $autoRefunded > 0 && $manualRefunded > 0 ) ? RefundValidator::CHANNEL_MIXED : $channel,
			'message'         => $outstanding <= 0 ? '' : __( 'Refund could not be completed in full', 'mhm-rentiva' ),
			'order_refunds'   => $orderRefunds,
		);
	}

	/**
	 * Close the operation: log it, record it, tell the customer once.
	 *
	 * Built out across Tasks 6-9 of the slice-3 plan. It exists from Task 6
	 * so the two entry points have one exit, not two.
	 *
	 * @param array{ok: bool, refunded: int, auto_refunded: int, manual_refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string, order_refunds: array<int, string>} $operation
	 */
	private static function finish( int $bookingId, array $operation, string $reason ): array {
		Logger::add(
			array(
				'gateway'      => $operation['channel'],
				'action'       => 'refund',
				'status'       => $operation['ok'] ? 'success' : 'error',
				'booking_id'   => $bookingId,
				'amount_kurus' => $operation['refunded'],
				'message'      => $operation['ok']
					? __( 'Refund successful', 'mhm-rentiva' )
					: $operation['message'],
				'context'      => array(
					'mode'    => $operation['mode'],
					'txn_ids' => $operation['txn_ids'],
				),
			)
		);

		if ( ! $operation['ok'] ) {
			$message = $operation['message'];

			if ( $operation['refunded'] > 0 ) {
				// A retry cannot over-refund -- the validator recomputes a
				// shrunken refundable() against what already moved -- so this
				// is not a money defect. But "refund failed" alone, after a
				// real leg already succeeded, hides that money already left
				// the account; the operator needs both facts in one place.
				$message = sprintf(
					/* translators: 1: the amount already refunded before the failure, 2: the underlying error that stopped the rest */
					__( '%1$s was already refunded before this failed: %2$s', 'mhm-rentiva' ),
					CurrencyHelper::format_price(
						(float) Money::toMajor( $operation['refunded'] ),
						Money::decimals(),
						PaymentState::forBooking( $bookingId )->currency()
					),
					$operation['message']
				);

				// The operation still speaks exactly once, even when it fails
				// partway through: a leg already succeeded, real money already
				// left the account, and the customer must hear about THAT
				// amount -- not zero, and not the amount that was requested.
				// Returning here without this call was the worse defect:
				// "exactly one mail" read as license to send none, on the
				// money path, in the failure case least likely to be
				// exercised before a real customer hit it.
				self::announce( $bookingId, $operation );
			}

			// partial_failure and failed are different facts: the first means
			// money has already left and the operator must finish the job by
			// hand, the second means nothing moved. Collapsing them would hide
			// a real transfer behind the word "failed".
			$refundStatus = $operation['refunded'] > 0 ? RefundStatus::PARTIAL_FAILURE : RefundStatus::FAILED;

			RefundStatus::transition(
				$bookingId,
				$refundStatus,
				array( 'channel' => $operation['channel'] )
			);

			self::announceCompletion(
				$bookingId,
				$operation + array(
					'refund_status' => $refundStatus,
					'currency'      => self::resolveCurrency( $bookingId ),
				)
			);

			return array(
				'mhmrentiva_refund'     => '0',
				'mhmrentiva_refund_msg' => $message,
			);
		}

		// 'completed' is a claim that the money has already gone back. Only the
		// gateway path can make it: a manual refund is a bookkeeping record and
		// the operator still has to transfer the money by hand
		// (wp-knowledge/official/woocommerce/wc-refunds.md, fact 2). Spec §5.3
		// step 9 names this value; N-05 (step 7) is what will show it.
		$refundStatus = RefundValidator::MODE_MANUAL === $operation['mode'] ? RefundStatus::MANUAL_PENDING : RefundStatus::COMPLETED;

		RefundStatus::transition(
			$bookingId,
			$refundStatus,
			array( 'channel' => $operation['channel'] )
		);

		if ( RefundValidator::CHANNEL_OFFLINE === $operation['channel'] ) {
			// The one place in the plugin that adds rather than sets. Offline
			// money has no WC_Order_Refund behind it, so no hook fired and this
			// meta is the entire record of what has been given back. Every
			// other write of this key -- the WooCommerce channel's -- is
			// absolute and derived from PaymentState (Task 8).
			$previous = max( 0, (int) get_post_meta( $bookingId, '_mhmrentiva_refunded_amount', true ) );

			update_post_meta( $bookingId, '_mhmrentiva_refunded_amount', $previous + $operation['refunded'] );

			$state = PaymentState::forBooking( $bookingId );

			update_post_meta(
				$bookingId,
				'_mhmrentiva_payment_status',
				$state->isFullyRefunded() ? 'refunded' : 'partially_refunded'
			);

			foreach ( $operation['txn_ids'] as $txnId ) {
				add_post_meta( $bookingId, '_mhmrentiva_refund_txn_id', $txnId );
			}
		}

		self::announce( $bookingId, $operation );

		self::announceCompletion(
			$bookingId,
			$operation + array(
				'refund_status' => $refundStatus,
				'currency'      => self::resolveCurrency( $bookingId ),
			)
		);

		return array(
			'mhmrentiva_refund'     => '1',
			'mhmrentiva_refund_msg' => '',
		);
	}

	/**
	 * Fire mhmrentiva_refund_completed, isolated from the operation's own outcome.
	 *
	 * Before this branch, the hook had zero listeners; nothing could reach this
	 * throw. Now a broken third-party listener must not undo work finish() has
	 * already committed -- the terminal status is written, the log row exists,
	 * and the customer mail (if any) has already gone out by the time this
	 * fires.
	 *
	 * The trigger is the money, not the status. runOperation() decides
	 * refund_payment per order, so a card deposit can genuinely come back
	 * while a bank-transfer remainder waits on a human -- an operation whose
	 * overall status is manual_pending but in which gateway money really did
	 * return. Binding this to a terminal-status label hid that from anything
	 * tracking money. finish() calls this from two places (its failure branch
	 * and its success branch); this guard is what both of them share, so a
	 * plain 'failed' operation (nothing moved) does not announce, while a
	 * 'partial_failure' operation that moved gateway money before the failing
	 * leg still does.
	 *
	 * @param array{ok: bool, refunded: int, auto_refunded: int, manual_refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string, order_refunds: array<int, string>, refund_status: string, currency: string} $operation
	 */
	private static function announceCompletion( int $bookingId, array $operation ): void {
		if ( ( $operation['auto_refunded'] ?? 0 ) <= 0 ) {
			return;
		}

		try {
			do_action( 'mhmrentiva_refund_completed', $bookingId, $operation );
		} catch ( \Throwable $e ) {
			Logger::add(
				array(
					'gateway'    => $operation['channel'],
					'action'     => 'refund_completed_hook',
					'status'     => 'error',
					'booking_id' => $bookingId,
					'message'    => __( 'A mhmrentiva_refund_completed listener failed:', 'mhm-rentiva' ) . ' ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * The currency a refund event should carry: PaymentState's own reading
	 * when it has one, the store's configured currency otherwise.
	 *
	 * Extracted so every caller asks this exact question once. Before this,
	 * announce() carried the fallback inline and announceCompletion()'s
	 * payload would have needed its own copy -- the same question with two
	 * chances to drift apart.
	 */
	private static function resolveCurrency( int $bookingId ): string {
		$currency = PaymentState::forBooking( $bookingId )->currency();

		return '' !== $currency
			? $currency
			: (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_currency', 'USD' );
	}

	/**
	 * The operation's single customer + admin e-mail.
	 *
	 * One per operation, not one per WooCommerce refund object: a deposit
	 * booking refunded in full creates two refunds and fired the hook twice,
	 * so the customer received two mails for one event. handle_order_refunded()
	 * now defers to isRefundInFlight() and this method is the only sender while
	 * an operation is running.
	 *
	 * WooCommerce's own "Refunded order" customer e-mail is deliberately NOT
	 * suppressed: it fires for every manual refund made from the WooCommerce
	 * order screen too, and silencing a core customer mail is a larger change
	 * than this slice carries. The mode-specific sentence lives in ours.
	 *
	 * @param array{ok: bool, refunded: int, auto_refunded: int, manual_refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string, order_refunds: array<int, string>} $operation
	 */
	private static function announce( int $bookingId, array $operation ): void {
		try {
			$state = PaymentState::forBooking( $bookingId );

			RefundNotifications::notify(
				$bookingId,
				$operation['refunded'],
				self::resolveCurrency( $bookingId ),
				$state->isFullyRefunded() ? 'refunded' : 'partially_refunded',
				'',
				$operation['mode'],
				// H-2 (fable-audit.md): 'mode' alone collapses a mixed
				// operation (a deposit paid by card, a remainder paid by
				// transfer) to a single word, and the old message named the
				// OPERATION TOTAL regardless -- an operator told to
				// hand-transfer money the gateway already returned. These two
				// subtotals let notify() tell the two amounts apart; they are
				// 0/0 for every pure-mode operation runOperation() already
				// produced, which keeps those sentences byte-for-byte
				// unchanged.
				$operation['auto_refunded'] ?? 0,
				$operation['manual_refunded'] ?? 0
			);
		} catch ( \Throwable $e ) {
			Logger::add(
				array(
					// Without 'gateway', AdvancedLogger::add() (:585-588) returns 0
					// and writes nothing -- this catch block exists specifically to
					// record that notify() failed, so a log call that is itself
					// silently discarded reproduces the exact silent failure it was
					// written to prevent. $operation['channel'] is in scope and is
					// what every other Logger::add() call in this file passes.
					'gateway'    => $operation['channel'],
					'action'     => 'refund_notification',
					'status'     => 'error',
					'booking_id' => $bookingId,
					'message'    => __( 'Refund notification could not be sent:', 'mhm-rentiva' ) . ' ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Checks refund status
	 */
	public static function isRefundSuccessful( array $result ): bool {
		return $result['ok'] === true && ! empty( $result['id'] );
	}

	/**
	 * Gets refund ID
	 */
	public static function getRefundId( array $result ): string {
		return $result['id'] ?? '';
	}

	/**
	 * Gets refund amount
	 */
	public static function getRefundAmount( array $result ): int {
		return $result['amount'] ?? 0;
	}
}
