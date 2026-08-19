<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Payment\Core\PaymentState;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RefundValidator {

	/**
	 * The gateway can send the money back on its own.
	 */
	public const MODE_AUTO = 'auto';

	/**
	 * A human moves the money; the refund record is bookkeeping only.
	 */
	public const MODE_MANUAL = 'manual';

	/**
	 * The booking's money sits in at least one paid WooCommerce order.
	 */
	public const CHANNEL_WOOCOMMERCE = 'woocommerce';

	/**
	 * No paid WooCommerce order: cash, transfer, or a manually entered booking.
	 */
	public const CHANNEL_OFFLINE = 'offline';

	/**
	 * Validates booking for refund
	 */
	public static function validateBooking( int $bookingId ): array {
		if ( $bookingId <= 0 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Invalid booking ID', 'mhm-rentiva' ),
			);
		}

		$post = get_post( $bookingId );
		if ( ! $post || $post->post_type !== 'mhmrentiva_booking' ) {
			return array(
				'valid'   => false,
				'message' => __( 'Invalid booking type', 'mhm-rentiva' ),
			);
		}

		return array(
			'valid'   => true,
			'booking' => $post,
		);
	}

	/**
	 * Checks payment status
	 */
	public static function validatePaymentStatus( int $bookingId ): array {
		$paymentStatus = (string) get_post_meta( $bookingId, '_mhmrentiva_payment_status', true );

		if ( empty( $paymentStatus ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Payment status not found', 'mhm-rentiva' ),
			);
		}

		if ( $paymentStatus === 'pending' ) {
			return array(
				'valid'   => false,
				'message' => __( 'Pending payments cannot be refunded', 'mhm-rentiva' ),
			);
		}

		if ( $paymentStatus === 'failed' ) {
			return array(
				'valid'   => false,
				'message' => __( 'Failed payments cannot be refunded', 'mhm-rentiva' ),
			);
		}

		if ( $paymentStatus === 'refunded' ) {
			return array(
				'valid'   => false,
				'message' => __( 'Already fully refunded', 'mhm-rentiva' ),
			);
		}

		return array(
			'valid'  => true,
			'status' => $paymentStatus,
		);
	}

	/**
	 * Can this order's gateway send the money back by itself?
	 *
	 * This replaced a rejection, not another rejection. The old code refused
	 * the refund unless the order was still WooCommerce-editable -- true only
	 * for pending/on-hold/auto-draft, a question about the Edit Order screen,
	 * not about refundability. Measured on the dev site 2026-08-19, that gate
	 * passed only orders with no money in them.
	 *
	 * The canonical pair for this question is supports('refunds') plus
	 * can_refund_order() (wp-knowledge/official/woocommerce/wc-refunds.md).
	 * wc_get_payment_gateway_by_order() returns false for a method no active
	 * gateway claims -- offline transfers, deleted plugins, legacy orders --
	 * and false lands on MODE_MANUAL, which is the fail-safe direction: a
	 * manual refund records the debt without pretending money moved.
	 */
	public static function modeForOrder( \WC_Order $order ): string {
		if ( ! function_exists( 'wc_get_payment_gateway_by_order' ) ) {
			return self::MODE_MANUAL;
		}

		$gateway = wc_get_payment_gateway_by_order( $order );

		if ( ! $gateway instanceof \WC_Payment_Gateway ) {
			return self::MODE_MANUAL;
		}

		return ( $gateway->supports( 'refunds' ) && $gateway->can_refund_order( $order ) )
			? self::MODE_AUTO
			: self::MODE_MANUAL;
	}

	/**
	 * Validate a refund of the whole remaining balance.
	 *
	 * The old signature passed 0 to validateAmount() as a "means full" sentinel
	 * and that validator refused 0 as an invalid amount, so this method could
	 * never return valid. There is no sentinel now: the amount IS the balance
	 * PaymentState reports.
	 */
	public static function validateFullRefund( int $bookingId ): array {
		$state = PaymentState::forBooking( $bookingId );

		return self::decide( $bookingId, $state, $state->refundable() );
	}

	/**
	 * Validate a refund of a specific amount, in minor units.
	 */
	public static function validatePartialRefund( int $bookingId, int $amountKurus ): array {
		return self::decide( $bookingId, PaymentState::forBooking( $bookingId ), $amountKurus );
	}

	/**
	 * The part both entry points share.
	 *
	 * Order matters and is not arbitrary. The booking and payment-status checks
	 * come first: every state they reject also produces refundable() === 0, so
	 * they narrow nothing, but they say WHY in a sentence an operator can act
	 * on. Checking the amount first would answer "refund amount exceeds
	 * remaining balance" for a booking id that does not exist.
	 *
	 * Then the balance, then the request. "There is nothing left to give back"
	 * and "you asked for the wrong number" are different problems and the first
	 * one is the operator's actual situation.
	 *
	 * @param int $requested Minor units. The whole balance for a full refund.
	 */
	private static function decide( int $bookingId, PaymentState $state, int $requested ): array {
		$bookingValidation = self::validateBooking( $bookingId );
		if ( ! $bookingValidation['valid'] ) {
			return $bookingValidation;
		}

		$statusValidation = self::validatePaymentStatus( $bookingId );
		if ( ! $statusValidation['valid'] ) {
			return $statusValidation;
		}

		$refundable = $state->refundable();

		if ( $refundable <= 0 ) {
			return array(
				'valid'   => false,
				'message' => __( 'No amount left to refund', 'mhm-rentiva' ),
			);
		}

		if ( $requested <= 0 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Invalid refund amount', 'mhm-rentiva' ),
			);
		}

		if ( $requested > $refundable ) {
			return array(
				'valid'   => false,
				'message' => __( 'Refund amount exceeds remaining balance', 'mhm-rentiva' ),
			);
		}

		return array(
			'valid'      => true,
			'booking_id' => $bookingId,
			'channel'    => array() === $state->orders()
				? self::CHANNEL_OFFLINE
				: self::CHANNEL_WOOCOMMERCE,
			'amount'     => $requested,
			'state'      => $state,
		);
	}
}
