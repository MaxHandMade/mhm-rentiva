<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\Core\PaymentState;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Where a booking's payment stands -- the one truth the booking detail's
 * payment line and the dashboard card both read (PR D, Task 3 fix round 1).
 *
 * Money received means a paid amount or a paid order; the booking's status
 * does not decide it (a pending_payment booking with a paid order is paid).
 * "Refunded" is the whole payment returned, or a refund on a closed booking; a
 * partial refund on a live booking leaves the deposit or paid line standing,
 * and the payment box lists what was refunded.
 *
 * @since 6.4.0
 */
final class AccountPaymentSummary {

	public const REFUNDED = 'refunded';
	public const DEPOSIT  = 'deposit';
	public const PAID     = 'paid';
	public const AWAITING = 'awaiting';
	public const NONE     = 'none';

	/**
	 * @return array{kind: string, money_received: bool, paid_minor: int, order_ids: list<int>, refunded_minor: int, remaining: float, currency: string}
	 */
	public static function for_booking(int $booking_id, string $status, ?PaymentState $state = null): array
	{
		$state          = $state ?? PaymentState::forBooking($booking_id);
		$money_received = $state->paid() > 0 || array() !== $state->orders();
		$remaining      = (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true);
		$closed         = in_array($status, array( Status::CANCELLED, Status::REFUNDED ), true);

		if ($state->refunded() > 0 && ( $closed || $state->refunded() >= $state->paid() )) {
			$kind = self::REFUNDED;
		} elseif ($money_received) {
			$kind = $remaining > 0 ? self::DEPOSIT : self::PAID;
		} elseif (! $closed) {
			$kind = self::AWAITING;
		} else {
			$kind = self::NONE;
		}

		return array(
			'kind'           => $kind,
			'money_received' => $money_received,
			'paid_minor'     => $state->paid(),
			'order_ids'      => array_map('intval', $state->orders()),
			'refunded_minor' => $state->refunded(),
			'remaining'      => $remaining,
			'currency'       => $state->currency(),
		);
	}

	/**
	 * The payment-history date of a booking: the latest moment one of its orders
	 * was paid, else when the booking was created. 0 when neither is recorded.
	 *
	 * @param list<int> $order_ids
	 */
	public static function row_date_ts(int $booking_id, array $order_ids): int
	{
		$paid_at = 0;
		if (function_exists('wc_get_order')) {
			foreach ($order_ids as $order_id) {
				$order = wc_get_order($order_id);
				$date  = $order instanceof \WC_Order ? $order->get_date_paid() : null;
				if (null !== $date) {
					$paid_at = max($paid_at, $date->getTimestamp());
				}
			}
		}

		return $paid_at > 0 ? $paid_at : (int) get_post_timestamp($booking_id);
	}
}
