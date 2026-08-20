<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Payment\Refunds\RefundValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RefundNotifications {


	public static function register(): void {
		// nothing to hook right now; kept for consistency
	}

	public static function notify(
		int $booking_id,
		int $amount_kurus,
		string $currency,
		string $newPayStatus,
		string $reason = '',
		string $mode = RefundValidator::MODE_AUTO
	): void {
		$email = (string) get_post_meta( $booking_id, '_mhmrentiva_contact_email', true );
		$name  = (string) get_post_meta( $booking_id, '_mhmrentiva_contact_name', true );
		$admin = get_option( 'admin_email' );

		// The refund carries its own currency code, which may differ from the store
		// default; the placement still comes from the one house rule. The old
		// WooCommerce-inactive branch read `mhmrentiva_currency_position` as a
		// top-level option — a key the plugin never writes there, since its settings
		// live inside the `mhmrentiva_settings` array — so it always resolved to
		// `right_space`.
		$amountHuman = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
			(float) \MHMRentiva\Admin\Payment\Core\Money::toMajor( $amount_kurus ),
			\MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
			$currency ?: 'TRY'
		);
		$statusText  = $newPayStatus === 'refunded' ? __( 'full refund', 'mhm-rentiva' ) : __( 'partial refund', 'mhm-rentiva' );

		/**
		 * The refund mode as the customer will be told it.
		 *
		 * 'auto' means the gateway sent the money back and it will appear on
		 * the original payment method; 'manual' means a person has to move it,
		 * so promising an automatic return would be false. The filter exists so
		 * an integrator can override the classification for a gateway we cannot
		 * interrogate -- and so the slice-3 tests can observe it.
		 */
		$mode = (string) apply_filters( 'mhmrentiva_refund_notification_mode', $mode, $booking_id );

		$modeText = RefundValidator::MODE_MANUAL === $mode
			? __( 'The refund will be transferred to you manually; it will not appear on your original payment method automatically.', 'mhm-rentiva' )
			: __( 'The refund has been sent back to your original payment method.', 'mhm-rentiva' );

		$wc_order_id = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id( (int) $booking_id );

		$context = array(
			'booking'   => array(
				'id'       => (int) $booking_id,
				'order_id' => $wc_order_id ?: (int) $booking_id,
				'title'    => get_the_title( $booking_id ),
				'status'   => (string) get_post_meta( $booking_id, '_mhmrentiva_status', true ),
				'payment'  => array(
					'status'   => $newPayStatus,
					'amount'   => (int) get_post_meta( $booking_id, '_mhmrentiva_payment_amount', true ),
					'currency' => (string) get_post_meta( $booking_id, '_mhmrentiva_payment_currency', true ) ?: 'TRY',
				),
			),
			'amount'    => $amountHuman,
			'status'    => $statusText,
			'mode'      => $mode,
			'mode_text' => $modeText,
			'reason'    => (string) $reason,
			'customer'  => array(
				'email' => $email,
				'name'  => $name,
			),
			'site'      => array(
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url( '/' ),
			),
		);
		if ( $email ) {
			Mailer::send( 'refund_customer', $email, $context );
		}
		if ( $admin ) {
			Mailer::send( 'refund_admin', $admin, $context );
		}
	}
}
