<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

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
