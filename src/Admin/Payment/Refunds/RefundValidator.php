<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds;

if (!defined('ABSPATH')) {
    exit;
}

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
	 * Validates payment gateway
	 * ⭐ Now supports both 'offline' and 'woocommerce' payment methods
	 */
	public static function validateGateway( string $gateway ): array {
		$supportedGateways = array( 'offline', 'woocommerce' );

		if ( ! in_array( $gateway, $supportedGateways, true ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Unsupported payment method for refund', 'mhm-rentiva' ),
			);
		}

		return array(
			'valid'   => true,
			'gateway' => $gateway,
		);
	}

	/**
	 * Validates refund amount
	 */
	public static function validateAmount( int $bookingId, int $amountKurus ): array {
		return RefundCalculator::validateRefundAmount( $bookingId, $amountKurus );
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
	 * Performs full refund validation
	 */
	public static function validateFullRefund( int $bookingId ): array {
		// Booking validation
		$bookingValidation = self::validateBooking( $bookingId );
		if ( ! $bookingValidation['valid'] ) {
			return $bookingValidation;
		}

		// Payment status validation
		$statusValidation = self::validatePaymentStatus( $bookingId );
		if ( ! $statusValidation['valid'] ) {
			return $statusValidation;
		}

		// Gateway validation
		$gateway           = (string) get_post_meta( $bookingId, '_mhmrentiva_payment_gateway', true );
		$gatewayValidation = self::validateGateway( $gateway );
		if ( ! $gatewayValidation['valid'] ) {
			return $gatewayValidation;
		}

		// Amount validation (for full refund)
		$amountValidation = self::validateAmount( $bookingId, 0 ); // 0 = tam iade
		if ( ! $amountValidation['valid'] ) {
			return $amountValidation;
		}

		return array(
			'valid'      => true,
			'booking_id' => $bookingId,
			'gateway'    => $gateway,
			'amount'     => $amountValidation['remaining'],
		);
	}

	/**
	 * Performs partial refund validation
	 */
	public static function validatePartialRefund( int $bookingId, int $amountKurus ): array {
		// Booking validation
		$bookingValidation = self::validateBooking( $bookingId );
		if ( ! $bookingValidation['valid'] ) {
			return $bookingValidation;
		}

		// Payment status validation
		$statusValidation = self::validatePaymentStatus( $bookingId );
		if ( ! $statusValidation['valid'] ) {
			return $statusValidation;
		}

		// Gateway validation
		$gateway           = (string) get_post_meta( $bookingId, '_mhmrentiva_payment_gateway', true );
		$gatewayValidation = self::validateGateway( $gateway );
		if ( ! $gatewayValidation['valid'] ) {
			return $gatewayValidation;
		}

		// Amount validation
		$amountValidation = self::validateAmount( $bookingId, $amountKurus );
		if ( ! $amountValidation['valid'] ) {
			return $amountValidation;
		}

		return array(
			'valid'      => true,
			'booking_id' => $bookingId,
			'gateway'    => $gateway,
			'amount'     => $amountKurus,
		);
	}
}
