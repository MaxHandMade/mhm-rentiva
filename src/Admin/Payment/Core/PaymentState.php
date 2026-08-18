<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The single read surface for "where is this booking's money".
 *
 * Written for 6.1.0 to replace _mhmrentiva_payment_amount, a meta key with
 * zero writers in production code and sixteen read points across Lite and
 * Pro -- every one of them reading 0, which is why refunds were refused with
 * "Paid amount not found" and why Pro's reports showed a zero paid column.
 *
 * Two rules hold this class together:
 *
 * 1. The refund base is never derived. It is read from WooCommerce's own
 *    get_remaining_refund_amount(). paid() is a reporting figure and cannot
 *    move money on its own -- so a coupon or a hand-edited order total skews
 *    a report, never a refund.
 * 2. Amounts are derived, statuses are stored. Booking lists filter on
 *    payment_status with meta_query and a derived value cannot be queried in
 *    SQL; no query anywhere filters on an amount.
 *
 * @since 6.1.0
 */
final class PaymentState {

	/**
	 * @param int[] $order_ids Paid WooCommerce orders, in payment order.
	 */
	private function __construct(
		private readonly array $order_ids,
	) {
	}

	/**
	 * Resolve one consistent snapshot of a booking's payment state.
	 *
	 * Named forBooking() rather than the spec's for(): `for` is a reserved
	 * word and, while PHP 7+ accepts it as a method name, it reads badly at
	 * every call site.
	 */
	public static function forBooking(int $booking_id): self
	{
		return new self(self::resolvePaidOrders($booking_id));
	}

	/**
	 * The orders whose money actually arrived, original first.
	 *
	 * `resolve_wc_order_id()` knows four legacy keys but not the
	 * remaining-payment order, which is why the refund subsystem never saw the
	 * second half of a deposit booking's money.
	 *
	 * @return int[]
	 */
	private static function resolvePaidOrders(int $booking_id): array
	{
		if (! function_exists('wc_get_order')) {
			return array();
		}

		$candidates = array(
			BookingQueryHelper::resolve_wc_order_id($booking_id),
			(int) get_post_meta($booking_id, '_mhmrentiva_remaining_order_id', true),
		);

		$paid = array();

		foreach ($candidates as $order_id) {
			if ($order_id <= 0 || in_array($order_id, $paid, true)) {
				continue;
			}

			$order = wc_get_order($order_id);

			// get_date_paid() rather than is_paid(): is_paid() is status-based
			// and a fully refunded order sits in `refunded`, which would drop
			// it from the set and take its refund history with it.
			if ($order instanceof \WC_Order && $order->get_date_paid() !== null) {
				$paid[] = $order_id;
			}
		}

		return $paid;
	}

	/**
	 * @return int[]
	 */
	public function orders(): array
	{
		return $this->order_ids;
	}

	/**
	 * The store's decimal precision, or 2 when WooCommerce is absent.
	 *
	 * `wc_get_price_decimals()` does not exist without WooCommerce; calling
	 * it unguarded is a fatal error, not a fallback.
	 */
	public static function decimals(): int
	{
		return function_exists('wc_get_price_decimals')
			? (int) wc_get_price_decimals()
			: 2;
	}

	/**
	 * Major units (what WooCommerce and the booking meta store) -> minor units.
	 *
	 * `round()` is not optional: (int) ( 19.99 * 100 ) is 1998, because the
	 * float is 1998.9999999999998.
	 */
	public static function toMinor(float|string $major): int
	{
		return (int) round( (float) $major * ( 10 ** self::decimals() ));
	}

	/**
	 * Minor units -> a major-unit string WooCommerce accepts.
	 */
	public static function toMajor(int $minor): string
	{
		$decimals = self::decimals();
		$major    = $minor / ( 10 ** $decimals );

		return function_exists('wc_format_decimal')
			? (string) wc_format_decimal($major, $decimals)
			: number_format($major, $decimals, '.', '');
	}
}
