<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

use MHMRentiva\Admin\Core\CurrencyHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Major <-> minor unit conversion, in one place.
 *
 * Split out of PaymentState for 6.1.0 before the fixed-100 sweep, not after:
 * the sweep binds seventeen call points to these three methods, and a
 * converter that lives on PaymentState would make a log line, an e-mail and a
 * CSV column depend on the class that answers "where is this booking's money".
 * They only need the scale.
 *
 * @since 6.1.0
 */
final class Money {

	/**
	 * The store's decimal precision.
	 *
	 * Delegated, not reimplemented: CurrencyHelper::get_price_decimals() is the
	 * plugin's canonical answer to this question and is the stricter of the two
	 * -- it checks woocommerce_is_active() as well as function_exists(), and
	 * clamps a negative setting to 0. A second implementation here would let
	 * the plugin answer "how many decimals" two different ways.
	 */
	public static function decimals(): int
	{
		return CurrencyHelper::get_price_decimals();
	}

	/**
	 * Major units (what WooCommerce and the booking meta store) -> minor units.
	 *
	 * `round()` is not optional: (int) ( 19.99 * 100 ) is 1998, because the
	 * float is 1998.9999999999998.
	 *
	 * A string that PHP's own is_numeric() rejects is routed through
	 * CurrencyHelper::to_amount() rather than a bare (float) cast. This
	 * method's signature is float|string and public, so nothing stops a
	 * future caller from handing it raw meta or a request value instead of
	 * the machine-format values today's seven call sites use -- and a bare
	 * cast reads a locale string such as "1.500,00" as 1.5, a silent 1000x
	 * error (see to_amount()'s docblock). is_numeric("1.500,00") is false
	 * (the comma is not valid in a PHP numeric literal), so that shape is
	 * exactly what this guard catches.
	 *
	 * The guard is deliberately narrower than "every string goes through
	 * to_amount()": to_amount()'s own docblock states its grouping heuristic
	 * -- a lone separator followed by exactly three digits reads as
	 * thousands, not a decimal fraction -- assumes money is stored with 0 or
	 * 2 decimals. Money supports a 3-decimal store (KWD) via decimals(), and
	 * routing every string through to_amount() misreads its own machine
	 * format there: is_numeric("19.990") is true, and a 3-decimal store's
	 * "19.990" (nineteen point nine nine zero) would be read as the grouped
	 * integer 19990 -- the same class of silent error this fix exists to
	 * remove, just pointed the other way. Every is_numeric() string,
	 * including that one, is machine format already and is left on the
	 * direct-cast path unchanged; see
	 * MoneyScaleTest::test_to_minor_honours_a_three_decimal_store().
	 */
	public static function toMinor(float|string $major): int
	{
		$value = ( is_string($major) && ! is_numeric($major) )
			? CurrencyHelper::to_amount($major)
			: (float) $major;

		return (int) round( $value * ( 10 ** self::decimals() ) );
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
