<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Pricing;

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use WP_UnitTestCase;

/**
 * The calendar, the vehicle page and the booking form insert a price with
 * jQuery's .html(), so `wc_price` filter output has to be clean where the price
 * is made: these are the shared producer's two entry points.
 */
final class PriceDisplayFiltersMarkupTest extends WP_UnitTestCase
{
	private function hostile_price(): \Closure
	{
		$filter = static fn( string $html ): string => $html . '<img src=x onerror="alert(1)"><script>alert(2)</script>';
		add_filter('wc_price', $filter);

		return $filter;
	}

	public function test_a_hostile_price_filter_is_stripped_in_both_contexts(): void
	{
		$filter = $this->hostile_price();
		try {
			foreach (array( PriceDisplay::for_shop(1000.0), PriceDisplay::for_cart(1000.0) ) as $price) {
				$this->assertStringNotContainsString('onerror', $price['html']);
				$this->assertStringNotContainsString('<script', $price['html']);
				$this->assertStringContainsString('<bdi>', $price['html'], 'The price itself survives the filter.');
			}
		} finally {
			remove_filter('wc_price', $filter);
		}
	}

	public function test_a_normal_price_and_its_suffix_are_kept(): void
	{
		update_option('woocommerce_price_display_suffix', 'incl. test VAT');
		update_option('woocommerce_calc_taxes', 'yes');

		$price = PriceDisplay::for_shop(1000.0);

		$this->assertStringContainsString('<bdi>', $price['html']);
		$this->assertStringContainsString('woocommerce-Price-amount', $price['html']);
		$this->assertStringContainsString('woocommerce-Price-currencySymbol', $price['html']);
		$this->assertSame(html_entity_decode(wp_strip_all_tags(wc_price($price['amount']))), html_entity_decode(wp_strip_all_tags($price['html'])));
		$this->assertStringContainsString('incl. test VAT', $price['suffix']);
	}
}
