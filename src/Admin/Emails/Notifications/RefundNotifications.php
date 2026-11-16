<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Utilities\SiteLocaleString;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Payment\Refunds\RefundValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RefundNotifications {

	/**
	 * The refund mail's mode once the operator confirmed the hand transfer:
	 * the money has been sent, not "will be".
	 */
	public const MODE_TRANSFERRED = 'transferred';

	public static function register(): void {
		// nothing to hook right now; kept for consistency
	}

	/**
	 * Tell the customer a refund paid back by hand has been transferred.
	 *
	 * The refund mail at cancellation said the amount "will be transferred
	 * to you manually"; DepositManagementAjax::close_manual_refund() calls
	 * this once the operator confirmed the transfer, so the customer learns
	 * the money left. The amount is what was refunded without the gateway
	 * (hand_refunded_kurus()). The customer only: the operator is the one who
	 * confirmed it.
	 *
	 * @param string $reference The transfer reference the operator typed, or ''.
	 */
	public static function notify_transferred( int $booking_id, string $reference ): void {
		$state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $booking_id );

		$currency = $state->currency() ?: \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_code();

		SiteLocaleString::in_site_locale(
			static function () use ( $booking_id, $state, $currency, $reference ): void {
				self::compose_and_send(
					$booking_id,
					self::hand_refunded_kurus( $state ),
					$currency,
					$state->isFullyRefunded() ? 'refunded' : 'partially_refunded',
					'',
					self::MODE_TRANSFERRED,
					0,
					0,
					$reference
				);
			}
		);
	}

	/**
	 * What was refunded on this booking without the gateway, in minor units.
	 *
	 * A WooCommerce refund the gateway accepted is marked on the refund
	 * itself (WC_Order_Refund::get_refunded_payment()); every other refund on
	 * the booking's orders was money to hand over. A booking with no order
	 * (offline channel) has nothing but hand refunds.
	 */
	private static function hand_refunded_kurus( \MHMRentiva\Admin\Payment\Core\PaymentState $state ): int {
		$orders = $state->orders();

		if ( array() === $orders ) {
			return $state->refunded();
		}

		$total = 0;

		foreach ( $orders as $order_id ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			foreach ( $order->get_refunds() as $refund ) {
				if ( ! $refund->get_refunded_payment() ) {
					$total += \MHMRentiva\Admin\Payment\Core\Money::toMinor( (float) $refund->get_amount() );
				}
			}
		}

		return $total;
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
		// The amount wording and the mode sentences are translated below,
		// before Mailer renders; they belong to the e-mail, so to the site
		// locale, not to the operator who pressed "refund".
		SiteLocaleString::in_site_locale(
			static function () use ( $booking_id, $amount_kurus, $currency, $newPayStatus, $reason, $mode, $auto_refunded_kurus, $manual_refunded_kurus ): void {
				self::compose_and_send( $booking_id, $amount_kurus, $currency, $newPayStatus, $reason, $mode, $auto_refunded_kurus, $manual_refunded_kurus, '' );
			}
		);
	}

	/**
	 * The body of notify(), run in the site locale.
	 */
	private static function compose_and_send(
		int $booking_id,
		int $amount_kurus,
		string $currency,
		string $newPayStatus,
		string $reason,
		string $mode,
		int $auto_refunded_kurus,
		int $manual_refunded_kurus,
		string $reference
	): void {
		// The address every OTHER consumer resolves through: customer_email ->
		// booking_customer_email -> contact_email. Reading contact_email alone
		// meant the notice had no recipient on 27 of 28 dev bookings, and
		// `if ( $email )` below made that silent.
		$customer = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo( $booking_id );
		$email    = (string) ( $customer['email'] ?? '' );
		$name     = trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) );
		$admin    = \MHMRentiva\Admin\Settings\Groups\EmailSettings::admin_recipient( 'refund' );

		// The refund carries its own currency code, which may differ from the store
		// default; the placement still comes from the one house rule. The old
		// WooCommerce-inactive branch read `mhmrentiva_currency_position` as a
		// top-level option — a key the plugin never writes there, since its settings
		// live inside the `mhmrentiva_settings` array — so it always resolved to
		// `right_space`.
		// The `?: null` below, here and at the two mixed-mode calls: format_price()'s
		// third argument is nullable precisely so "this booking never stored a
		// currency" defers to the store's own. Naming a fixed 'TRY' here printed
		// lira totals in refund mail sent from shops that sell in something else.
		$amountHuman = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
			(float) \MHMRentiva\Admin\Payment\Core\Money::toMajor( $amount_kurus ),
			\MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
			$currency ?: null
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
		// A confirmed transfer is not a classification: it is what happened.
		if ( self::MODE_TRANSFERRED !== $mode ) {
			$mode = (string) apply_filters( 'mhmrentiva_refund_notification_mode', $mode, $booking_id );
		}

		// A mixed operation needs both legs named; either subtotal being 0
		// means this was a pure-mode operation (or a caller that never passed
		// the split at all), so the two branches below stay byte-for-byte
		// what they always were -- no translator sees a diff for those.
		$isMixedMode = $auto_refunded_kurus > 0 && $manual_refunded_kurus > 0;

		if ( self::MODE_TRANSFERRED === $mode ) {
			$modeText = '' !== $reference
				? sprintf(
					/* translators: 1: the refunded amount, 2: the bank transfer or receipt reference */
					__( '%1$s has been transferred to you. Transfer reference: %2$s', 'mhm-rentiva' ),
					$amountHuman,
					$reference
				)
				: sprintf(
					/* translators: %s: the refunded amount */
					__( '%s has been transferred to you.', 'mhm-rentiva' ),
					$amountHuman
				);

			// No admin mail goes out for this mode (below).
			$adminModeText = '';
		} elseif ( $isMixedMode ) {
			$autoHuman   = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
				(float) \MHMRentiva\Admin\Payment\Core\Money::toMajor( $auto_refunded_kurus ),
				\MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
				$currency ?: null
			);
			$manualHuman = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
				(float) \MHMRentiva\Admin\Payment\Core\Money::toMajor( $manual_refunded_kurus ),
				\MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
				$currency ?: null
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
				'id'         => (int) $booking_id,
				'order_id'   => $wc_order_id ?: (int) $booking_id,
				'display_id' => mhmrentiva_get_display_id( (int) $booking_id ),
				'title'    => get_the_title( $booking_id ),
				'status'   => (string) get_post_meta( $booking_id, '_mhmrentiva_status', true ),
				'payment'  => array(
					'status'   => $newPayStatus,
					'amount'   => \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $booking_id )->paid(),
					'currency' => (string) get_post_meta( $booking_id, '_mhmrentiva_payment_currency', true )
						?: \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_code(),
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
		if ( $admin && self::MODE_TRANSFERRED !== $mode ) {
			Mailer::send( 'refund_admin', $admin, $context );
		}
	}
}
