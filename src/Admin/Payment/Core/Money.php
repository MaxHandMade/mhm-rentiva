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
	 */
	public static function toMinor(float|string $major): int
	{
		return (int) round( (float) $major * ( 10 ** self::decimals() ) );
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
