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
	 * A stored booking's breakdown, same shape as from_quote() (R8).
	 *
	 * Read from the booking record, never re-priced: the daily price and the
	 * weekend difference are not stored, so the rental is one "Rental, N days"
	 * row (total minus the add-ons). Add-on rows are the stored
	 * `_mhmrentiva_addon_details` lines at the price the booking was made
	 * with (a later price change or a deleted add-on does not touch them);
	 * only a booking from before that record existed is priced again from its
	 * selected add-ons. No tax row (K7).
	 *
	 * The deposit row says "Deposit paid" once money arrived (any paid order
	 * or offline payment), "Deposit" before. Due at pickup is the stored
	 * remaining amount.
	 *
	 * @return array{rows: list<array{kind: string, label: string, amount: float, html: string}>, total: array{amount: float, html: string, suffix: string}, deposit: ?array{amount: float, html: string, label: string}, on_pickup: ?array{amount: float, html: string}}
	 */
	public static function from_booking(int $booking_id): array
	{
		$total = (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true);

		$details = get_post_meta($booking_id, '_mhmrentiva_addon_details', true);
		$lines   = self::stored_addon_lines($booking_id);
		$stored  = get_post_meta($booking_id, '_mhmrentiva_addon_total', true);
		// The stored add-on total goes with the stored lines; re-priced lines sum.
		$addons  = is_array($details) && array() !== $details && is_numeric($stored)
			? (float) $stored
			: (float) array_sum(array_column($lines, 'amount'));
		$days    = max(1, (int) get_post_meta($booking_id, '_mhmrentiva_rental_days', true));
		$rentals = max(0.0, $total - $addons);

		/* translators: %d: number of rental days. */
		$rows = array( self::row('rental', sprintf(_n('Rental, %d day', 'Rental, %d days', $days, 'mhm-rentiva'), $days), $rentals) );
		foreach ($lines as $line) {
			$rows[] = self::row('addon', $line['label'], $line['amount']);
		}

		$deposit = (float) get_post_meta($booking_id, '_mhmrentiva_deposit_amount', true);
		$split   = 'deposit' === (string) get_post_meta($booking_id, '_mhmrentiva_payment_type', true)
			&& $deposit > 0
			&& $deposit < $total;

		$remaining = (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true);
		$display   = PriceDisplay::for_cart($total);

		return array(
			'rows'      => $rows,
			'total'     => array(
				'amount' => $total,
				'html'   => $display['html'],
				'suffix' => $display['suffix'],
			),
			'deposit'   => $split
				? self::money($deposit) + array(
					'label' => self::money_received($booking_id) ? _x('Deposit paid', 'payment box label', 'mhm-rentiva') : __('Deposit', 'mhm-rentiva'),
				)
				: null,
			'on_pickup' => $remaining > 0 && ! self::is_closed($booking_id) ? self::money($remaining) : null,
		);
	}

	/**
	 * Is the booking over without a pickup to pay at (cancelled, refunded or
	 * no-show)? Then "due at pickup" must not be printed.
	 */
	private static function is_closed(int $booking_id): bool
	{
		return in_array(\MHMRentiva\Admin\Booking\Core\Status::get($booking_id), array( 'cancelled', 'refunded', 'no_show' ), true);
	}

	/**
	 * Did money arrive for this booking, through any channel? A paid order
	 * counts on its own: paid() reads 0 for a mixed-currency pair.
	 */
	public static function money_received(int $booking_id): bool
	{
		$state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking($booking_id);

		return $state->paid() > 0 || array() !== $state->orders();
	}

	/**
	 * The booking's add-on lines as stored; priced again only for a booking
	 * that has selected add-ons but no stored lines (an older booking).
	 *
	 * @return list<array{label: string, amount: float}>
	 */
	private static function stored_addon_lines(int $booking_id): array
	{
		$details = get_post_meta($booking_id, '_mhmrentiva_addon_details', true);

		if (! is_array($details) || array() === $details) {
			$selection = \MHMRentiva\Admin\Addons\AddonSelection::for_booking($booking_id);
			if (array() === $selection->ids()) {
				return array();
			}
			$details = \MHMRentiva\Admin\Addons\AddonManager::build_addon_details(
				$selection->ids(),
				array(
					'rental_days'      => max(1, (int) get_post_meta($booking_id, '_mhmrentiva_rental_days', true)),
					'addon_quantities' => $selection->quantities(),
				)
			);
		}

		$lines = array();
		foreach ($details as $row) {
			if (! is_array($row)) {
				continue;
			}
			$lines[] = array(
				'label'  => \MHMRentiva\Admin\Addons\AddonManager::line_label($row),
				'amount' => (float) ( $row['line_total'] ?? $row['price'] ?? 0 ),
			);
		}

		return $lines;
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
