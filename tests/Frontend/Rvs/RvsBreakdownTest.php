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

	/**
	 * Owner decision (PR B review): the deposit row names the deposit, not
	 * "due now" -- the customer still chooses deposit or full payment at
	 * checkout.
	 */
	public function test_deposit_row_names_the_rate_not_a_due_now_promise(): void
	{
		$b    = RvsBreakdown::from_quote(self::quote(), 335.0, array(), 10.0);
		$html = (string) Templates::render('partials/rvs/breakdown', array( 'breakdown' => $b ), true);

		$this->assertSame('Deposit (10%)', $b['deposit']['label']);
		$this->assertStringContainsString('Deposit (10%)', $html);
		$this->assertStringNotContainsString('Due now', $html);
		$this->assertSame('Deposit', RvsBreakdown::from_quote(self::quote(), 335.0)['deposit']['label']);
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

	/**
	 * A stored booking: 3 days, total 2 915, add-on lines as the booking
	 * stored them (PR D Task 4; R8, M2, Y3).
	 */
	private function stored_booking(array $details, array $meta = array()): int
	{
		$id   = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		$meta += array(
			'_mhmrentiva_total_price'      => '2915.00',
			'_mhmrentiva_rental_days'      => 3,
			'_mhmrentiva_payment_type'     => 'deposit',
			'_mhmrentiva_deposit_amount'   => '292.00',
			'_mhmrentiva_remaining_amount' => '2623.00',
			'_mhmrentiva_addon_details'    => $details,
			'_mhmrentiva_addon_total'      => (float) array_sum(array_column($details, 'line_total')),
		);
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return $id;
	}

	private static function line(string $title, float $total, int $quantity = 1, int $addon_id = 999999): array
	{
		return array(
			'id'           => $addon_id,
			'title'        => $title,
			'price'        => $total / $quantity,
			'pricing_type' => 'fixed',
			'multiplier'   => 1,
			'quantity'     => $quantity,
			'line_total'   => $total,
		);
	}

	public function test_from_booking_rows_without_invented_daily_price(): void
	{
		$b = RvsBreakdown::from_booking($this->stored_booking(array( self::line('Tam sigorta', 25.0), self::line('Çocuk koltuğu', 60.0, 2) )));

		$this->assertSame(array( 'rental', 'addon', 'addon' ), array_column($b['rows'], 'kind'));
		$this->assertSame(array( 'Rental, 3 days', 'Tam sigorta', 'Çocuk koltuğu × 2' ), array_column($b['rows'], 'label'));
		$this->assertSame(array( 2830.0, 25.0, 60.0 ), array_column($b['rows'], 'amount'), 'Rental = total - add-ons; no daily rate, no weekend row.');
		$this->assertStringNotContainsString('×', $b['rows'][0]['label']);
		$this->assertSame(2915.0, $b['total']['amount']);
		$this->assertSame(2623.0, $b['on_pickup']['amount']);
		$this->assertSame(292.0, $b['deposit']['amount']);
		$this->assertSame('Deposit', $b['deposit']['label'], 'No money arrived: not "paid".');
		foreach ($b['rows'] as $row) {
			$this->assertSame(PriceDisplay::for_cart($row['amount'])['html'], $row['html']);
		}
	}

	public function test_due_at_pickup_is_not_printed_for_a_closed_booking(): void
	{
		foreach (array( 'cancelled', 'refunded', 'no_show' ) as $status) {
			$id = $this->stored_booking(array(), array( '_mhmrentiva_status' => $status ));
			$this->assertNull(RvsBreakdown::from_booking($id)['on_pickup'], $status);
		}

		$open = $this->stored_booking(array(), array( '_mhmrentiva_status' => 'confirmed' ));
		$this->assertSame(2623.0, RvsBreakdown::from_booking($open)['on_pickup']['amount']);
	}

	public function test_deposit_label_says_paid_once_money_arrived(): void
	{
		$id = $this->stored_booking(array(), array( '_mhmrentiva_payment_status' => 'paid' ));

		$this->assertSame('Deposit paid', RvsBreakdown::from_booking($id)['deposit']['label']);
	}

	public function test_from_booking_shape_equals_from_quote_shape(): void
	{
		$booking = RvsBreakdown::from_booking($this->stored_booking(array( self::line('GPS', 25.0) )));
		$quote   = RvsBreakdown::from_quote(self::quote(), 500.0);

		$this->assertSame(array_keys($quote), array_keys($booking));
		$this->assertSame(array_keys($quote['total']), array_keys($booking['total']));
		$this->assertSame(array_keys($quote['deposit']), array_keys($booking['deposit']));
		$this->assertSame(array_keys($quote['on_pickup']), array_keys($booking['on_pickup']));
		$this->assertSame(array_keys($quote['rows'][0]), array_keys($booking['rows'][0]));
	}

	public function test_addon_price_changed_shows_booked_amount(): void
	{
		$addon_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon', 'post_title' => 'GPS', 'post_status' => 'publish' ));
		update_post_meta($addon_id, 'mhmrentiva_addon_price', '999');
		$id = $this->stored_booking(array( self::line('GPS', 25.0, 1, $addon_id) ), array( '_mhmrentiva_selected_addons' => array( $addon_id ) ));

		$rows = RvsBreakdown::from_booking($id)['rows'];

		$this->assertSame(25.0, $rows[1]['amount'], 'The price the booking was made with.');
	}

	public function test_deleted_addon_line_kept(): void
	{
		// The add-on post no longer exists; its stored line still shows.
		$id = $this->stored_booking(array( self::line('Retired add-on', 40.0, 1, 987654) ));

		$rows = RvsBreakdown::from_booking($id)['rows'];

		$this->assertSame(array( 'Rental, 3 days', 'Retired add-on' ), array_column($rows, 'label'));
		$this->assertSame(2875.0, $rows[0]['amount']);
	}

	public function test_old_booking_without_stored_lines_is_priced_from_its_selection(): void
	{
		$addon_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon', 'post_title' => 'Child seat', 'post_status' => 'publish' ));
		update_post_meta($addon_id, 'mhmrentiva_addon_price', '20');
		$id = $this->stored_booking(array(), array( '_mhmrentiva_selected_addons' => array( $addon_id ) ));
		delete_post_meta($id, '_mhmrentiva_addon_details');
		delete_post_meta($id, '_mhmrentiva_addon_total');

		$rows = RvsBreakdown::from_booking($id)['rows'];

		$this->assertCount(2, $rows);
		$this->assertSame('Child seat', $rows[1]['label']);
		$this->assertSame(2915.0 - $rows[1]['amount'], $rows[0]['amount']);
	}
}
