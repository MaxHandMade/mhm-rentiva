<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Rvs;

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Booking\Pricing\RentalQuote;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The front-end standard's price breakdown, as plain data.
 *
 * Built from the same RentalQuote the booking charges, shown through
 * WooCommerce's cart display (PriceDisplay::for_cart()). There is no tax row:
 * WooCommerce owns tax and its price suffix sits under the total.
 * templates/partials/rvs/breakdown.php only prints the result.
 */
final class RvsBreakdown {

	/**
	 * @param array<int, array{label: string, amount: float}> $addon_lines  One row per add-on; when given, replaces the single add-on row.
	 * @param float                                           $deposit_rate The deposit percentage, for the row's label ("Deposit (10%)").
	 * @return array{rows: list<array{kind: string, label: string, amount: float, html: string}>, total: array{amount: float, html: string, suffix: string}, deposit: ?array{amount: float, html: string, label: string}, on_pickup: ?array{amount: float, html: string}}
	 */
	public static function from_quote(RentalQuote $quote, float $deposit = 0.0, array $addon_lines = array(), float $deposit_rate = 0.0): array
	{
		$days     = $quote->days;
		$standard = $quote->rental_amount - $quote->weekend_surcharge;

		// A flat "rate x days" label is only true without seasonal multipliers.
		if (abs($standard - ( $quote->daily_price * $days )) < 0.005) {
			$label = sprintf(
				/* translators: 1: daily price, 2: number of rental days. */
				_n('%1$s × %2$d day', '%1$s × %2$d days', $days, 'mhm-rentiva'),
				wp_strip_all_tags(PriceDisplay::for_cart($quote->daily_price)['html']),
				$days
			);
		} else {
			/* translators: %d: number of rental days. */
			$label = sprintf(_n('Rental, %d day', 'Rental, %d days', $days, 'mhm-rentiva'), $days);
		}

		$rows = array( self::row('rental', $label, $standard) );
		if ($quote->weekend_surcharge > 0) {
			$rows[] = self::row('weekend', __('Weekend difference', 'mhm-rentiva'), $quote->weekend_surcharge);
		}
		if (array() !== $addon_lines) {
			foreach ($addon_lines as $line) {
				$rows[] = self::row('addon', (string) $line['label'], (float) $line['amount']);
			}
		} elseif ($quote->addon_total > 0) {
			$rows[] = self::row('addon', __('Add-on services', 'mhm-rentiva'), $quote->addon_total);
		}

		$total = PriceDisplay::for_cart($quote->full_amount);
		$split = $deposit > 0 && $deposit < $quote->full_amount;

		return array(
			'rows'      => $rows,
			'total'     => array(
				'amount' => $quote->full_amount,
				'html'   => $total['html'],
				'suffix' => $total['suffix'],
			),
			// The deposit is named, not promised as "due now": the customer still
			// chooses deposit or full payment at checkout.
			'deposit'   => $split
				? self::money($deposit) + array(
					'label' => $deposit_rate > 0
						/* translators: %s: deposit percentage, e.g. "10%". */
						? sprintf(__('Deposit (%s)', 'mhm-rentiva'), \MHMRentiva\Admin\Core\CurrencyHelper::format_percentage($deposit_rate))
						: __('Deposit', 'mhm-rentiva'),
				)
				: null,
			'on_pickup' => $split ? self::money($quote->full_amount - $deposit) : null,
		);
	}

	/**
	 * @return array{kind: string, label: string, amount: float, html: string}
	 */
	private static function row(string $kind, string $label, float $amount): array
	{
		return array(
			'kind'   => $kind,
			'label'  => $label,
			'amount' => $amount,
			'html'   => PriceDisplay::for_cart($amount)['html'],
		);
	}

	/**
	 * @return array{amount: float, html: string}
	 */
	private static function money(float $amount): array
	{
		return array(
			'amount' => $amount,
			'html'   => PriceDisplay::for_cart($amount)['html'],
		);
	}
}
