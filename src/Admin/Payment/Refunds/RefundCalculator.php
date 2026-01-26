<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Refund Calculator - Central Refund Calculation Class
 *
 * Centralizes common refund calculation logic for all payment gateways
 */
final class RefundCalculator {

	/**
	 * Calculates refundable amount
	 */
	public static function calculateRemainingAmount( int $bookingId ): int {
		$paidAmount     = (int) get_post_meta( $bookingId, '_mhm_payment_amount', true );
		$refundedAmount = (int) get_post_meta( $bookingId, '_mhm_refunded_amount', true );

		return max( 0, $paidAmount - $refundedAmount );
	}

	/**
	 * Gets paid amount
	 */
	public static function getPaidAmount( int $bookingId ): int {
		return (int) get_post_meta( $bookingId, '_mhm_payment_amount', true );
	}

	/**
	 * Gets previously refunded amount
	 */
	public static function getRefundedAmount( int $bookingId ): int {
		return (int) get_post_meta( $bookingId, '_mhm_refunded_amount', true );
	}

	/**
	 * Validates if refund amount is valid
	 */
	public static function validateRefundAmount( int $bookingId, int $amountKurus ): array {
		$paidAmount      = self::getPaidAmount( $bookingId );
		$refundedAmount  = self::getRefundedAmount( $bookingId );
		$remainingAmount = self::calculateRemainingAmount( $bookingId );

		if ( $paidAmount <= 0 ) {
			return array(
				'valid'     => false,
				'message'   => __( 'Paid amount not found', 'mhm-rentiva' ),
				'paid'      => $paidAmount,
				'refunded'  => $refundedAmount,
				'remaining' => $remainingAmount,
			);
		}

		if ( $remainingAmount <= 0 ) {
			return array(
				'valid'     => false,
				'message'   => __( 'No amount left to refund', 'mhm-rentiva' ),
				'paid'      => $paidAmount,
				'refunded'  => $refundedAmount,
				'remaining' => $remainingAmount,
			);
		}

		if ( $amountKurus <= 0 ) {
			return array(
				'valid'     => false,
				'message'   => __( 'Invalid refund amount', 'mhm-rentiva' ),
				'paid'      => $paidAmount,
				'refunded'  => $refundedAmount,
				'remaining' => $remainingAmount,
			);
		}

		if ( $amountKurus > $remainingAmount ) {
			return array(
				'valid'     => false,
				'message'   => __( 'Refund amount exceeds remaining balance', 'mhm-rentiva' ),
				'paid'      => $paidAmount,
				'refunded'  => $refundedAmount,
				'remaining' => $remainingAmount,
			);
		}

		return array(
			'valid'     => true,
			'amount'    => $amountKurus,
			'paid'      => $paidAmount,
			'refunded'  => $refundedAmount,
			'remaining' => $remainingAmount,
		);
	}

	/**
	 * Updates refund amount
	 */
	public static function updateRefundedAmount( int $bookingId, int $refundAmount ): bool {
		$currentRefunded   = self::getRefundedAmount( $bookingId );
		$newRefundedAmount = $currentRefunded + $refundAmount;

		return update_post_meta( $bookingId, '_mhm_refunded_amount', $newRefundedAmount );
	}

	/**
	 * Checks if fully refunded
	 */
	public static function isFullyRefunded( int $bookingId ): bool {
		$paidAmount     = self::getPaidAmount( $bookingId );
		$refundedAmount = self::getRefundedAmount( $bookingId );

		return $paidAmount > 0 && $refundedAmount >= $paidAmount;
	}

	/**
	 * Checks if partially refunded
	 */
	public static function isPartiallyRefunded( int $bookingId ): bool {
		$paidAmount     = self::getPaidAmount( $bookingId );
		$refundedAmount = self::getRefundedAmount( $bookingId );

		return $paidAmount > 0 && $refundedAmount > 0 && $refundedAmount < $paidAmount;
	}

	/**
	 * Gets refund status summary
	 */
	public static function getRefundSummary( int $bookingId ): array {
		$paidAmount      = self::getPaidAmount( $bookingId );
		$refundedAmount  = self::getRefundedAmount( $bookingId );
		$remainingAmount = self::calculateRemainingAmount( $bookingId );

		return array(
			'paid'                  => $paidAmount,
			'refunded'              => $refundedAmount,
			'remaining'             => $remainingAmount,
			'is_fully_refunded'     => self::isFullyRefunded( $bookingId ),
			'is_partially_refunded' => self::isPartiallyRefunded( $bookingId ),
			'refund_percentage'     => $paidAmount > 0 ? round( ( $refundedAmount / $paidAmount ) * 100, 2 ) : 0,
		);
	}
}
