<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The booking detail's "Progress" list (PR D, Task 4; R9).
 *
 * Drawn only from recorded facts: the post date, paid orders and their
 * WooCommerce paid date, the structured status-change entries of the
 * booking history (`status_to` + `at`), the cancellation record and the
 * planned pickup and return. A step that happened but has no recorded time
 * is drawn without one -- a time is never invented. The history's sentences
 * and staff notes (`note`, `customer_contact` ...) are never read.
 *
 * For a payment confirmed by hand (a bank transfer) WooCommerce's paid date
 * is when staff confirmed it, so the step says "Payment received", not when
 * the money reached the bank.
 *
 * @since 6.4.0
 */
final class BookingTimeline {

	private const CONFIRMED_OR_LATER = array( Status::CONFIRMED, Status::IN_PROGRESS, Status::COMPLETED );

	/**
	 * @return list<array{key: string, label: string, detail: ?string, at: ?int, done: bool}>
	 */
	public static function for_booking(int $booking_id): array
	{
		$status  = Status::get($booking_id);
		$changes = self::status_changes($booking_id);
		$steps   = array();

		$created = get_post_time('U', true, $booking_id);
		$steps[] = self::step('received', __('Booking received', 'mhm-rentiva'), null, false !== $created ? (int) $created : null, true);

		foreach (self::payment_steps($booking_id) as $payment) {
			$steps[] = $payment;
		}

		$cancelled = in_array($status, array( Status::CANCELLED, Status::REFUNDED ), true);

		// Confirmation: drawn when the booking is (or was) confirmed, or while
		// it is still on its way there.
		$confirmed_at = $changes[ Status::CONFIRMED ] ?? null;
		if (! $cancelled || null !== $confirmed_at) {
			$steps[] = self::step(
				'confirmed',
				__('Confirmed', 'mhm-rentiva'),
				null,
				$confirmed_at,
				in_array($status, self::CONFIRMED_OR_LATER, true) || ( $cancelled && null !== $confirmed_at )
			);
		}

		if ($cancelled) {
			$steps[] = self::step('cancelled', __('Cancelled', 'mhm-rentiva'), null, $changes[ Status::CANCELLED ] ?? self::cancelled_at($booking_id), true);

			$state = PaymentState::forBooking($booking_id);
			if ($state->refunded() > 0) {
				$steps[] = self::step(
					'refunded',
					/* translators: %s: refunded amount. */
					sprintf(__('Refunded · %s', 'mhm-rentiva'), self::amount_text($state->refunded())),
					null,
					self::local_mysql_ts( (string) get_post_meta($booking_id, '_mhmrentiva_refund_completed_at', true)),
					true
				);
			}

			return $steps;
		}

		$planned = self::planned($booking_id);
		$steps[] = self::step('pickup', __('Pickup', 'mhm-rentiva'), null, $planned['pickup'], in_array($status, array( Status::IN_PROGRESS, Status::COMPLETED ), true));
		$steps[] = self::step('return', __('Return', 'mhm-rentiva'), null, $planned['return'], Status::COMPLETED === $status);

		if (Status::COMPLETED === $status) {
			$steps[] = self::step('completed', __('Completed', 'mhm-rentiva'), null, $changes[ Status::COMPLETED ] ?? null, true);
		}

		return $steps;
	}

	/**
	 * Planned pickup and return instants (Unix timestamps), null when the
	 * date is missing. Pickup time: CancellationHandler::pickup_time_for()
	 * (R10). Return time: `_mhmrentiva_end_time` -> `_mhmrentiva_dropoff_time`
	 * -> `_mhmrentiva_booking_dropoff_time`, else the pickup time (a rental
	 * returns at the hour it started, as the booking card shows it).
	 *
	 * @return array{pickup: ?int, return: ?int}
	 */
	public static function planned(int $booking_id): array
	{
		$pickup_time = CancellationHandler::pickup_time_for($booking_id);

		$return_time = $pickup_time;
		foreach (array( '_mhmrentiva_end_time', '_mhmrentiva_dropoff_time', '_mhmrentiva_booking_dropoff_time' ) as $key) {
			$value = trim( (string) get_post_meta($booking_id, $key, true));
			if (1 === preg_match('/^(\d{1,2}):(\d{2})/', $value, $m) && (int) $m[1] < 24 && (int) $m[2] < 60) {
				$return_time = sprintf('%02d:%s', (int) $m[1], $m[2]);
				break;
			}
		}

		$return_day = '';
		foreach (array( '_mhmrentiva_dropoff_date', '_mhmrentiva_booking_dropoff_date', '_mhmrentiva_end_date' ) as $key) {
			$value = trim( (string) get_post_meta($booking_id, $key, true));
			if (AccountBookingsView::is_day($value)) {
				$return_day = substr($value, 0, 10);
				break;
			}
		}

		$pickup_day = substr(trim( (string) get_post_meta($booking_id, '_mhmrentiva_pickup_date', true)), 0, 10);

		return array(
			'pickup' => AccountBookingsView::is_day($pickup_day) ? SiteTime::instant($pickup_day, $pickup_time) : null,
			'return' => '' !== $return_day ? SiteTime::instant($return_day, $return_time) : null,
		);
	}

	/**
	 * The latest recorded instant of each status reached, from the
	 * structured entries only (an entry without `status_to` is a sentence:
	 * not read).
	 *
	 * @return array<string, ?int>
	 */
	private static function status_changes(int $booking_id): array
	{
		$changes = array();
		foreach (BookingMeta::history_sorted($booking_id) as $entry) {
			if ('status_change' !== ( $entry['type'] ?? '' ) || empty($entry['status_to'])) {
				continue;
			}
			$changes[ (string) $entry['status_to'] ] = isset($entry['at']) ? (int) $entry['at'] : null;
		}

		return $changes;
	}

	/**
	 * One step per paid WooCommerce order (deposit / full payment / balance),
	 * or one undated step for money proven paid offline.
	 *
	 * @return list<array{key: string, label: string, detail: ?string, at: ?int, done: bool}>
	 */
	private static function payment_steps(int $booking_id): array
	{
		$state        = PaymentState::forBooking($booking_id);
		$remaining_id = (int) get_post_meta($booking_id, '_mhmrentiva_remaining_order_id', true);
		$deposit_type = 'deposit' === (string) get_post_meta($booking_id, '_mhmrentiva_payment_type', true);
		$steps        = array();
		$first        = true;

		foreach ($state->orders() as $order_id) {
			$order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
			if (! $order instanceof \WC_Order) {
				continue;
			}

			$amount = self::amount_text(Money::toMinor($order->get_total()));
			if ($remaining_id > 0 && $order_id === $remaining_id) {
				/* translators: %s: amount paid. */
				$label = sprintf(__('Balance paid · %s', 'mhm-rentiva'), $amount);
				$key   = 'balance_paid';
			} elseif ($first && $deposit_type) {
				/* translators: %s: amount paid. */
				$label = sprintf(__('Deposit paid · %s', 'mhm-rentiva'), $amount);
				$key   = 'deposit_paid';
			} else {
				/* translators: %s: amount paid. */
				$label = sprintf(__('Payment received · %s', 'mhm-rentiva'), $amount);
				$key   = 'payment_received';
			}

			$paid_at = $order->get_date_paid();
			$method  = trim( (string) $order->get_payment_method_title());
			$steps[] = self::step($key, $label, '' !== $method ? $method : null, null !== $paid_at ? $paid_at->getTimestamp() : null, true);
			$first   = false;
		}

		// Offline money (no paid order): its arrival time is not recorded.
		if (array() === $state->orders() && $state->paid() > 0) {
			$remaining = (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true);
			$amount    = self::amount_text($state->paid());
			$steps[]   = $deposit_type && $remaining > 0
				/* translators: %s: amount paid. */
				? self::step('deposit_paid', sprintf(__('Deposit paid · %s', 'mhm-rentiva'), $amount), null, null, true)
				/* translators: %s: amount paid. */
				: self::step('payment_received', sprintf(__('Payment received · %s', 'mhm-rentiva'), $amount), null, null, true);
		}

		return $steps;
	}

	/**
	 * Minor units as plain display text (PriceDisplay, plan r3 y9).
	 */
	private static function amount_text(int $minor): string
	{
		return wp_strip_all_tags(PriceDisplay::for_cart( (float) Money::toMajor($minor))['html']);
	}

	/**
	 * `cancelled_at` of the cancellation record (site-local `Y-m-d H:i:s`).
	 */
	private static function cancelled_at(int $booking_id): ?int
	{
		$data = get_post_meta($booking_id, '_mhmrentiva_cancellation_data', true);

		return is_array($data) ? self::local_mysql_ts( (string) ( $data['cancelled_at'] ?? '' )) : null;
	}

	/**
	 * A site-local `Y-m-d H:i:s` (current_time('mysql')) as a timestamp.
	 */
	private static function local_mysql_ts(string $value): ?int
	{
		$value = trim($value);
		if (1 !== preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2})?)/', $value, $m)) {
			return null;
		}

		return SiteTime::instant($m[1], $m[2]);
	}

	/**
	 * @return array{key: string, label: string, detail: ?string, at: ?int, done: bool}
	 */
	private static function step(string $key, string $label, ?string $detail, ?int $at, bool $done): array
	{
		return array(
			'key'    => $key,
			'label'  => $label,
			'detail' => $detail,
			'at'     => $at,
			'done'   => $done,
		);
	}
}
