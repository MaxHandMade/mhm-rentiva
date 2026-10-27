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
	 * @return array{kind: string, money_received: bool, refunded_minor: int, remaining: float, currency: string}
	 */
	public static function for_booking(int $booking_id, string $status): array
	{
		$state          = PaymentState::forBooking($booking_id);
		$money_received = $state->paid() > 0 || array() !== $state->orders();
		$remaining      = (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true);

		if ($state->refunded() > 0) {
			$kind = self::REFUNDED;
		} elseif ($money_received) {
			$kind = $remaining > 0 ? self::DEPOSIT : self::PAID;
		} elseif (! in_array($status, array( Status::CANCELLED, Status::REFUNDED ), true)) {
			$kind = self::AWAITING;
		} else {
			$kind = self::NONE;
		}

		return array(
			'kind'           => $kind,
			'money_received' => $money_received,
			'refunded_minor' => $state->refunded(),
			'remaining'      => $remaining,
			'currency'       => $state->currency(),
		);
	}
}
