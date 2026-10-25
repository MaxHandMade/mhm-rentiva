<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Pricing;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The price of one rental, as RentalPricing::quote() computed it.
 *
 * Amounts are in the store's price-entry convention: tax-inclusive when
 * WooCommerce is set to "prices entered with tax", tax-exclusive otherwise.
 * There is deliberately no tax field -- WooCommerce computes tax at checkout
 * and displays it through wc_get_price_to_display().
 */
final class RentalQuote {

	/**
	 * @param array<int, array{date: string, standard: float, weekend_extra: float}> $day_lines
	 */
	public function __construct(
		public readonly int $days,
		public readonly array $day_lines,
		public readonly float $daily_price,
		public readonly float $base_multiplier,
		public readonly float $weekend_multiplier,
		public readonly int $weekend_days,
		public readonly float $weekend_surcharge,
		public readonly float $rental_amount,
		public readonly float $addon_total,
		public readonly float $full_amount
	) {
	}
}
