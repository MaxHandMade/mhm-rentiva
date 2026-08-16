<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Emails\Core\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RefundNotifications {


	public static function register(): void {
		// nothing to hook right now; kept for consistency
	}

	public static function notify( int $booking_id, int $amount_kurus, string $currency, string $newPayStatus, string $reason = '' ): void {
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
			$amount_kurus / 100,
			\MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
			$currency ?: 'TRY'
		);
		$statusText  = $newPayStatus === 'refunded' ? __( 'full refund', 'mhm-rentiva' ) : __( 'partial refund', 'mhm-rentiva' );

		$wc_order_id = (int) get_post_meta( $booking_id, '_mhmrentiva_woocommerce_order_id', true );

		$context = array(
			'booking'  => array(
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
			'amount'   => $amountHuman,
			'status'   => $statusText,
			'reason'   => (string) $reason,
			'customer' => array(
				'email' => $email,
				'name'  => $name,
			),
			'site'     => array(
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
