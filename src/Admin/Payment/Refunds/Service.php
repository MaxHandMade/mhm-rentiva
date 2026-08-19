<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger as Logger;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Service {

	public static function process( int $bookingId, int $amountKurus, string $reason = '' ): array {
		$validation = RefundValidator::validatePartialRefund( $bookingId, $amountKurus );

		if ( ! $validation['valid'] ) {
			return array(
				'mhmrentiva_refund'     => '0',
				'mhmrentiva_refund_msg' => $validation['message'],
			);
		}

		return self::finish( $bookingId, self::runOperation( $bookingId, $validation['amount'], $reason ), $reason );
	}

	public static function processFullRefund( int $bookingId, string $reason = '' ): array {
		$validation = RefundValidator::validateFullRefund( $bookingId );

		if ( ! $validation['valid'] ) {
			return array(
				'mhmrentiva_refund'     => '0',
				'mhmrentiva_refund_msg' => $validation['message'],
			);
		}

		return self::finish( $bookingId, self::runOperation( $bookingId, $validation['amount'], $reason ), $reason );
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
	 * @return array{ok: bool, refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string}
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
				'ok'       => true,
				'refunded' => $amountKurus,
				'mode'     => RefundValidator::MODE_MANUAL,
				'txn_ids'  => array( 'manual_' . wp_generate_uuid4() ),
				'channel'  => $channel,
				'message'  => '',
			);
		}

		$outstanding = $amountKurus;
		$refunded    = 0;
		$txnIds      = array();
		$allAuto     = true;

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
				// a partial failure and the operator retries the rest.
				return array(
					'ok'       => false,
					'refunded' => $refunded,
					'mode'     => $allAuto ? RefundValidator::MODE_AUTO : RefundValidator::MODE_MANUAL,
					'txn_ids'  => $txnIds,
					'channel'  => $channel,
					'message'  => $refund->get_error_message() ?: __( 'Failed to create WooCommerce refund', 'mhm-rentiva' ),
				);
			}

			$txnIds[]     = (string) $refund->get_id();
			$refunded    += $leg;
			$outstanding -= $leg;
		}

		if ( 0 === $refunded ) {
			return array(
				'ok'       => false,
				'refunded' => 0,
				'mode'     => RefundValidator::MODE_MANUAL,
				'txn_ids'  => array(),
				'channel'  => $channel,
				'message'  => __( 'No amount left to refund', 'mhm-rentiva' ),
			);
		}

		return array(
			'ok'       => $outstanding <= 0,
			'refunded' => $refunded,
			'mode'     => $allAuto ? RefundValidator::MODE_AUTO : RefundValidator::MODE_MANUAL,
			'txn_ids'  => $txnIds,
			'channel'  => $channel,
			'message'  => $outstanding <= 0 ? '' : __( 'Refund could not be completed in full', 'mhm-rentiva' ),
		);
	}

	/**
	 * Close the operation: log it, record it, tell the customer once.
	 *
	 * Tasks 7-10 of the slice-3 plan build this out. It exists from Task 6 so
	 * the two entry points have one exit, not two.
	 *
	 * @param array{ok: bool, refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string} $operation
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
			}

			return array(
				'mhmrentiva_refund'     => '0',
				'mhmrentiva_refund_msg' => $message,
			);
		}

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

		return array(
			'mhmrentiva_refund'     => '1',
			'mhmrentiva_refund_msg' => '',
		);
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
