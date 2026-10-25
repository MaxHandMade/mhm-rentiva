<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Booking\Pricing\RentalQuote;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsBreakdown;
use WP_UnitTestCase;

/**
 * Aşama 2 PR A: the shared price breakdown -- rows from a RentalQuote, the
 * deposit / due-at-pickup split, and the partial.
 */
final class RvsBreakdownTest extends WP_UnitTestCase
{
	/**
	 * 3 days at 1000; `$surcharge` is the weekend difference (one weekend day when > 0).
	 */
	private static function quote(float $surcharge = 150.0, float $addons = 200.0, ?float $standard_sum = null): RentalQuote
	{
		$standard_sum = $standard_sum ?? 3000.0;
		$lines        = array();
		for ($i = 0; $i < 3; $i++) {
			$lines[] = array(
				'date'          => '2027-07-1' . ( 5 + $i ),
				'standard'      => $standard_sum / 3,
				'weekend_extra' => 2 === $i ? $surcharge : 0.0,
			);
		}
		return new RentalQuote(3, $lines, 1000.0, 1.0, $surcharge > 0 ? 1.15 : 1.0, $surcharge > 0 ? 1 : 0, $surcharge, $standard_sum + $surcharge, $addons, $standard_sum + $surcharge + $addons);
	}

	public function test_rows_from_quote(): void
	{
		$b = RvsBreakdown::from_quote(self::quote());

		$this->assertSame(array( 'rental', 'weekend', 'addon' ), array_column($b['rows'], 'kind'));
		$this->assertSame(array( 3000.0, 150.0, 200.0 ), array_column($b['rows'], 'amount'));
		$this->assertSame(3350.0, $b['total']['amount']);
		$this->assertStringContainsString('3 days', $b['rows'][0]['label']);
		$this->assertStringNotContainsString('<', $b['rows'][0]['label']);
	}

	public function test_zero_rows_are_not_drawn(): void
	{
		$b = RvsBreakdown::from_quote(self::quote(0.0, 0.0));

		$this->assertSame(array( 'rental' ), array_column($b['rows'], 'kind'));
		$this->assertNull($b['deposit']);
		$this->assertNull($b['on_pickup']);
	}

	public function test_deposit_and_pickup_split(): void
	{
		$b = RvsBreakdown::from_quote(self::quote(), 500.0);
		$this->assertSame(500.0, $b['deposit']['amount']);
		$this->assertSame(2850.0, $b['on_pickup']['amount']);

		$full = RvsBreakdown::from_quote(self::quote(), 3350.0);
		$this->assertNull($full['deposit']);
		$this->assertNull($full['on_pickup']);
	}

	public function test_addon_lines_replace_single_row(): void
	{
		$b = RvsBreakdown::from_quote(
			self::quote(),
			0.0,
			array(
				array(
					'label'  => 'Child seat',
					'amount' => 120.0,
				),
				array(
					'label'  => 'GPS',
					'amount' => 80.0,
				),
			)
		);

		$addons = array_values(array_filter($b['rows'], static fn($r) => 'addon' === $r['kind']));
		$this->assertSame(array( 'Child seat', 'GPS' ), array_column($addons, 'label'));
		$this->assertSame(array( 120.0, 80.0 ), array_column($addons, 'amount'));
	}

	public function test_seasonal_rental_row_does_not_claim_a_flat_daily_rate(): void
	{
		// Seasonal multipliers: the three days sum to 3300, not 3 x 1000.
		$b = RvsBreakdown::from_quote(self::quote(0.0, 0.0, 3300.0));

		$this->assertSame(3300.0, $b['rows'][0]['amount']);
		$this->assertStringNotContainsString('×', $b['rows'][0]['label']);
		$this->assertStringContainsString('3 days', $b['rows'][0]['label']);
	}

	public function test_amount_html_matches_price_display(): void
	{
		$b = RvsBreakdown::from_quote(self::quote(), 500.0);

		foreach ($b['rows'] as $row) {
			$this->assertSame(PriceDisplay::for_cart($row['amount'])['html'], $row['html']);
		}
		$this->assertSame(PriceDisplay::for_cart(3350.0)['html'], $b['total']['html']);
		$this->assertSame(PriceDisplay::for_cart(3350.0)['suffix'], $b['total']['suffix']);
	}

	public function test_partial_collapsible_and_suffix(): void
	{
		$b    = RvsBreakdown::from_quote(self::quote(), 500.0);
		$html = (string) Templates::render('partials/rvs/breakdown', array( 'breakdown' => $b, 'collapsible' => true ), true);

		$this->assertMatchesRegularExpression('/<details[^>]*>.*<dl class="rvs-breakdown__rows">.*<\/details>/s', $html);
		$this->assertMatchesRegularExpression('/<\/details>.*rvs-breakdown__total/s', $html);
		$this->assertStringContainsString('rvs-breakdown__deposit', $html);
		$this->assertStringContainsString('rvs-breakdown__pickup', $html);

		$b['total']['suffix'] = '';
		$plain                = (string) Templates::render('partials/rvs/breakdown', array( 'breakdown' => $b ), true);
		$this->assertStringNotContainsString('rvs-breakdown__suffix', $plain);
		$this->assertStringNotContainsString('<details', $plain);

		$b['total']['suffix'] = 'incl. VAT';
		$this->assertStringContainsString('rvs-breakdown__suffix', (string) Templates::render('partials/rvs/breakdown', array( 'breakdown' => $b ), true));
	}
}
