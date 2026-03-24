<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\PostTypes\Logs\Logger;
use MHMRentiva\Admin\Emails\Notifications\RefundNotifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Service {

	/**
	 * Processes refund
	 */
	public static function process( int $bookingId, int $amountKurus, string $reason = '' ): array {
		// Validate
		$validation = RefundValidator::validatePartialRefund( $bookingId, $amountKurus );
		if ( ! $validation['valid'] ) {
			return array(
				'mhm_refund'     => '0',
				'mhm_refund_msg' => $validation['message'],
			);
		}

		$gateway = $validation['gateway'];
		$amount  = $validation['amount'];

		// Process refund based on gateway
		$result = self::processGatewayRefund( $bookingId, $gateway, $amount, $reason );

		if ( ! $result['ok'] ) {
			Logger::add(
				array(
					'gateway'      => $gateway,
					'action'       => 'refund',
					'status'       => 'error',
					'booking_id'   => $bookingId,
					'amount_kurus' => $amount,
					'message'      => $result['message'] ?? __( 'Refund failed', 'mhm-rentiva' ),
				)
			);

			return array(
				'mhm_refund'     => '0',
				'mhm_refund_msg' => $result['message'] ?? __( 'Refund failed', 'mhm-rentiva' ),
			);
		}

		// Update booking meta
		self::updateBookingMeta( $bookingId, $amount, $result );

		// Send email notification
		self::sendRefundNotification( $bookingId, $amount, $reason );

		Logger::add(
			array(
				'gateway'      => $gateway,
				'action'       => 'refund',
				'status'       => 'success',
				'booking_id'   => $bookingId,
				'amount_kurus' => $amount,
				'message'      => __( 'Refund successful', 'mhm-rentiva' ),
				'context'      => array(
					'refund_id' => $result['id'] ?? '',
					'gateway'   => $gateway,
				),
			)
		);

		return array(
			'mhm_refund'     => '1',
			'mhm_refund_msg' => '',
		);
	}

	/**
	 * Processes full refund
	 */
	public static function processFullRefund( int $bookingId, string $reason = '' ): array {
		// Validate
		$validation = RefundValidator::validateFullRefund( $bookingId );
		if ( ! $validation['valid'] ) {
			return array(
				'mhm_refund'     => '0',
				'mhm_refund_msg' => $validation['message'],
			);
		}

		$gateway = $validation['gateway'];
		$amount  = $validation['amount'];

		// Process full refund based on gateway
		$result = self::processGatewayFullRefund( $bookingId, $gateway, $reason );

		if ( ! $result['ok'] ) {
			Logger::add(
				array(
					'gateway'      => $gateway,
					'action'       => 'full_refund',
					'status'       => 'error',
					'booking_id'   => $bookingId,
					'amount_kurus' => $amount,
					'message'      => $result['message'] ?? __( 'Full refund failed', 'mhm-rentiva' ),
				)
			);

			return array(
				'mhm_refund'     => '0',
				'mhm_refund_msg' => $result['message'] ?? __( 'Full refund failed', 'mhm-rentiva' ),
			);
		}

		// Update booking meta
		self::updateBookingMeta( $bookingId, $amount, $result );

		// Send email notification
		self::sendRefundNotification( $bookingId, $amount, $reason );

		Logger::add(
			array(
				'gateway'      => $gateway,
				'action'       => 'full_refund',
				'status'       => 'success',
				'booking_id'   => $bookingId,
				'amount_kurus' => $amount,
				'message'      => __( 'Full refund successful', 'mhm-rentiva' ),
				'context'      => array(
					'refund_id' => $result['id'] ?? '',
					'gateway'   => $gateway,
				),
			)
		);

		return array(
			'mhm_refund'     => '1',
			'mhm_refund_msg' => '',
		);
	}

	/**
	 * Processes refund based on gateway
	 * ⭐ Now supports both 'offline' and 'woocommerce' gateways
	 */
	private static function processGatewayRefund( int $bookingId, string $gateway, int $amount, string $reason ): array {
		if ( $gateway === 'offline' ) {
			return array(
				'ok'      => true,
				'id'      => 'manual_' . uniqid(),
				'amount'  => $amount,
				'message' => __( 'Manual refund recorded', 'mhm-rentiva' ),
			);
		}

		if ( $gateway === 'woocommerce' ) {
			// ⭐ For WooCommerce, refund should be processed through WooCommerce UI
			// This method is called when admin manually processes refund from Rentiva panel
			// We'll create a WooCommerce refund programmatically

			$order_id = (int) get_post_meta( $bookingId, '_mhm_woocommerce_order_id', true );
			if ( empty( $order_id ) ) {
				// Try alternative meta keys
				$order_id = (int) get_post_meta( $bookingId, '_mhm_wc_order_id', true );
				if ( empty( $order_id ) ) {
					$order_id = (int) get_post_meta( $bookingId, '_mhm_order_id', true );
				}
			}

			if ( empty( $order_id ) || ! class_exists( 'WooCommerce' ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'WooCommerce order not found for this booking', 'mhm-rentiva' ),
				);
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array(
					'ok'      => false,
					'message' => __( 'WooCommerce order not found', 'mhm-rentiva' ),
				);
			}

			// Convert amount from kurus to order currency amount
			$refund_amount = $amount / 100.0;

			// Check if refund amount is valid
			$max_refund = $order->get_total() - $order->get_total_refunded();
			if ( $refund_amount > $max_refund ) {
				return array(
					'ok'      => false,
					'message' => sprintf(
						/* translators: %s: maximum refund amount */
						__( 'Refund amount exceeds maximum refundable amount (%s)', 'mhm-rentiva' ),
						wc_price( $max_refund, array( 'currency' => $order->get_currency() ) )
					),
				);
			}

			// Create WooCommerce refund
			$refund = wc_create_refund(
				array(
					'order_id'       => $order_id,
					'amount'         => $refund_amount,
					'reason'         => $reason ?: __( 'Refund processed from Rentiva panel', 'mhm-rentiva' ),
					'refund_payment' => true, // Process refund through payment gateway
				)
			);

			if ( is_wp_error( $refund ) ) {
				return array(
					'ok'      => false,
					'message' => $refund->get_error_message() ?: __( 'Failed to create WooCommerce refund', 'mhm-rentiva' ),
				);
			}

			return array(
				'ok'      => true,
				'id'      => (string) $refund->get_id(),
				'amount'  => $amount,
				'message' => __( 'WooCommerce refund processed successfully', 'mhm-rentiva' ),
			);
		}

		return array(
			'ok'      => false,
			'message' => __( 'Unsupported payment gateway', 'mhm-rentiva' ),
		);
	}

	/**
	 * Processes full refund based on gateway
	 * ⭐ Now supports both 'offline' and 'woocommerce' gateways
	 */
	private static function processGatewayFullRefund( int $bookingId, string $gateway, string $reason ): array {
		if ( $gateway === 'offline' ) {
			return array(
				'ok'      => true,
				'id'      => 'manual_' . uniqid(),
				'message' => __( 'Manual full refund recorded', 'mhm-rentiva' ),
			);
		}

		if ( $gateway === 'woocommerce' ) {
			// ⭐ For WooCommerce, process full refund through WooCommerce

			$order_id = (int) get_post_meta( $bookingId, '_mhm_woocommerce_order_id', true );
			if ( empty( $order_id ) ) {
				// Try alternative meta keys
				$order_id = (int) get_post_meta( $bookingId, '_mhm_wc_order_id', true );
				if ( empty( $order_id ) ) {
					$order_id = (int) get_post_meta( $bookingId, '_mhm_order_id', true );
				}
			}

			if ( empty( $order_id ) || ! class_exists( 'WooCommerce' ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'WooCommerce order not found for this booking', 'mhm-rentiva' ),
				);
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array(
					'ok'      => false,
					'message' => __( 'WooCommerce order not found', 'mhm-rentiva' ),
				);
			}

			// Get remaining refundable amount
			$refund_amount = $order->get_total() - $order->get_total_refunded();

			if ( $refund_amount <= 0 ) {
				return array(
					'ok'      => false,
					'message' => __( 'Order is already fully refunded', 'mhm-rentiva' ),
				);
			}

			// Create WooCommerce full refund
			$refund = wc_create_refund(
				array(
					'order_id'       => $order_id,
					'amount'         => $refund_amount,
					'reason'         => $reason ?: __( 'Full refund processed from Rentiva panel', 'mhm-rentiva' ),
					'refund_payment' => true, // Process refund through payment gateway
				)
			);

			if ( is_wp_error( $refund ) ) {
				return array(
					'ok'      => false,
					'message' => $refund->get_error_message() ?: __( 'Failed to create WooCommerce full refund', 'mhm-rentiva' ),
				);
			}

			// Convert to kurus for return value
			$amount_kurus = (int) round( $refund_amount * 100 );

			return array(
				'ok'      => true,
				'id'      => (string) $refund->get_id(),
				'amount'  => $amount_kurus,
				'message' => __( 'WooCommerce full refund processed successfully', 'mhm-rentiva' ),
			);
		}

		return array(
			'ok'      => false,
			'message' => __( 'Unsupported payment gateway', 'mhm-rentiva' ),
		);
	}

	/**
	 * Updates booking meta
	 */
	private static function updateBookingMeta( int $bookingId, int $amount, array $result ): void {
		$refundedAmount    = (int) get_post_meta( $bookingId, '_mhm_refunded_amount', true );
		$newRefundedAmount = $refundedAmount + $amount;

		update_post_meta( $bookingId, '_mhm_refunded_amount', $newRefundedAmount );

		$paidAmount       = (int) get_post_meta( $bookingId, '_mhm_payment_amount', true );
		$newPaymentStatus = $newRefundedAmount >= $paidAmount ? 'refunded' : 'partially_refunded';

		update_post_meta( $bookingId, '_mhm_payment_status', $newPaymentStatus );

		// Save refund transaction ID
		if ( ! empty( $result['id'] ) ) {
			add_post_meta( $bookingId, '_mhm_refund_txn_id', (string) $result['id'] );
		}
	}

	/**
	 * Sends refund notification
	 */
	private static function sendRefundNotification( int $bookingId, int $amount, string $reason ): void {
		try {
			// ⭐ Get currency dynamically - prioritize WooCommerce, then booking meta, then plugin settings
			$currency = (string) get_post_meta( $bookingId, '_mhm_payment_currency', true );

			if ( empty( $currency ) ) {
				// Try to get from WooCommerce order
				$order_id = (int) get_post_meta( $bookingId, '_mhm_woocommerce_order_id', true );
				if ( empty( $order_id ) ) {
					$order_id = (int) get_post_meta( $bookingId, '_mhm_wc_order_id', true );
				}
				if ( empty( $order_id ) ) {
					$order_id = (int) get_post_meta( $bookingId, '_mhm_order_id', true );
				}

				if ( $order_id && class_exists( 'WooCommerce' ) ) {
					$order = wc_get_order( $order_id );
					if ( $order ) {
						$currency = $order->get_currency();
					}
				}
			}

			// Fallback to WooCommerce currency or plugin settings
			if ( empty( $currency ) ) {
				if ( function_exists( 'get_woocommerce_currency' ) ) {
					$currency = get_woocommerce_currency();
				} else {
					$currency = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_currency', 'USD' );
				}
			}

			$refundedAmount = (int) get_post_meta( $bookingId, '_mhm_refunded_amount', true );
			$paidAmount     = (int) get_post_meta( $bookingId, '_mhm_payment_amount', true );
			$paymentStatus  = $refundedAmount >= $paidAmount ? 'refunded' : 'partially_refunded';

			RefundNotifications::notify( $bookingId, $amount, $currency, $paymentStatus, $reason );
		} catch ( \Throwable $e ) {
			// Email error is not critical, log it
			Logger::add(
				array(
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
