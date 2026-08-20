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

	/**
	 * @param int $auto_refunded_kurus   Fable audit H-2: Service::runOperation()
	 *                                   decides refund_payment PER ORDER -- a
	 *                                   deposit paid by card and a remainder
	 *                                   paid by transfer are two different
	 *                                   answers -- but $mode alone collapses a
	 *                                   mixed operation to one word, and the
	 *                                   old messaging named the OPERATION
	 *                                   TOTAL regardless of which part the
	 *                                   gateway actually touched. These two
	 *                                   subtotals let the mixed-mode sentence
	 *                                   name both amounts. Minor units.
	 * @param int $manual_refunded_kurus See $auto_refunded_kurus. Both default
	 *                                   to 0, so every existing caller
	 *                                   (WooCommerceBridge's single-order
	 *                                   refund, this class's own tests) is
	 *                                   unaffected and produces the exact
	 *                                   pure-mode sentence it always did.
	 */
	public static function notify(
		int $booking_id,
		int $amount_kurus,
		string $currency,
		string $newPayStatus,
		string $reason = '',
		string $mode = RefundValidator::MODE_AUTO,
		int $auto_refunded_kurus = 0,
		int $manual_refunded_kurus = 0
	): void {
		// The address every OTHER consumer resolves through: customer_email ->
		// booking_customer_email -> contact_email. Reading contact_email alone
		// meant the notice had no recipient on 27 of 28 dev bookings, and
		// `if ( $email )` below made that silent.
		$customer = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo( $booking_id );
		$email    = (string) ( $customer['email'] ?? '' );
		$name     = trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) );
		$admin    = get_option( 'admin_email' );

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

		// A mixed operation needs both legs named; either subtotal being 0
		// means this was a pure-mode operation (or a caller that never passed
		// the split at all), so the two branches below stay byte-for-byte
		// what they always were -- no translator sees a diff for those.
		$isMixedMode = $auto_refunded_kurus > 0 && $manual_refunded_kurus > 0;

		if ( $isMixedMode ) {
			$autoHuman   = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
				(float) \MHMRentiva\Admin\Payment\Core\Money::toMajor( $auto_refunded_kurus ),
				\MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
				$currency ?: 'TRY'
			);
			$manualHuman = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
				(float) \MHMRentiva\Admin\Payment\Core\Money::toMajor( $manual_refunded_kurus ),
				\MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
				$currency ?: 'TRY'
			);

			$modeText = sprintf(
				/* translators: 1: the amount already returned automatically to the customer's original payment method, 2: the amount that will still be transferred to the customer by hand */
				__( '%1$s was returned to your original payment method automatically; the remaining %2$s will be transferred to you manually.', 'mhm-rentiva' ),
				$autoHuman,
				$manualHuman
			);

			// The operator's copy of the same fact, phrased as an action item:
			// the gateway leg is already done, ONLY the manual leg is still
			// owed by hand -- naming the total here is exactly the defect
			// this branch exists to close (an operator who followed it would
			// over-refund the customer by the gateway leg).
			$adminModeText = sprintf(
				/* translators: 1: the amount the payment gateway already returned automatically, 2: the amount that must still be transferred to the customer manually */
				__( 'The payment gateway already returned %1$s of this refund automatically; only the remaining %2$s must be transferred to the customer manually.', 'mhm-rentiva' ),
				$autoHuman,
				$manualHuman
			);
		} else {
			$modeText = RefundValidator::MODE_MANUAL === $mode
				? __( 'The refund will be transferred to you manually; it will not appear on your original payment method automatically.', 'mhm-rentiva' )
				: __( 'The refund has been sent back to your original payment method.', 'mhm-rentiva' );

			// The operator's copy of the same fact, phrased as an action item
			// rather than a promise to the customer: a manual-mode refund did not
			// touch the gateway, so unlike the customer sentence, this is the
			// admin's cue that the transfer still has to be made by hand.
			$adminModeText = RefundValidator::MODE_MANUAL === $mode
				? __( 'The payment gateway could not process this refund automatically; the amount above must be transferred to the customer manually.', 'mhm-rentiva' )
				: __( 'The payment gateway processed this refund automatically; no manual transfer is required.', 'mhm-rentiva' );
		}

		$wc_order_id = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id( (int) $booking_id );

		$context = array(
			'booking'         => array(
				'id'       => (int) $booking_id,
				'order_id' => $wc_order_id ?: (int) $booking_id,
				'title'    => get_the_title( $booking_id ),
				'status'   => (string) get_post_meta( $booking_id, '_mhmrentiva_status', true ),
				'payment'  => array(
					'status'   => $newPayStatus,
					'amount'   => \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $booking_id )->paid(),
					'currency' => (string) get_post_meta( $booking_id, '_mhmrentiva_payment_currency', true ) ?: 'TRY',
				),
			),
			'amount'          => $amountHuman,
			'status'          => $statusText,
			'mode'            => $mode,
			'mode_text'       => $modeText,
			'admin_mode_text' => $adminModeText,
			'reason'          => (string) $reason,
			'customer'        => array(
				'email' => $email,
				'name'  => $name,
			),
			'site'            => array(
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url( '/' ),
			),
		);
		if ( $email ) {
			Mailer::send( 'refund_customer', $email, $context );
		} else {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::add(
				array(
					'gateway'    => 'refund',
					'action'     => 'refund_notification',
					'status'     => 'error',
					'booking_id' => $booking_id,
					'message'    => __( 'Refund notice not sent: no customer address on this booking.', 'mhm-rentiva' ),
				)
			);
		}
		if ( $admin ) {
			Mailer::send( 'refund_admin', $admin, $context );
		}
	}
}
