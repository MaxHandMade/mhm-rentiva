<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\Util;
use WP_UnitTestCase;

/**
 * The vehicle total is not days x daily price: Util::total_price() charges
 * `mhmrentiva_vehicle_weekend_multiplier` (default 1.2) for every Saturday and
 * Sunday in the range. That is a deliberate product feature, and it is
 * correct -- but nothing in the plugin ever SAYS so. The manual booking screen
 * prints "Daily Price: 2800" next to "Vehicle Total: 6720" for a two-day
 * booking, and an operator who works out 2 x 2800 = 5600 has no way to
 * account for the other 1120. The customer-facing form has the same silence.
 *
 * price_breakdown() exists so both surfaces can show the surcharge instead of
 * hiding it, and so the figure they show is computed once, in the same place
 * the money is computed -- not re-derived in a template or in JavaScript,
 * which is how two numbers for one concept start to disagree.
 *
 * The parity test below is the important one: it anchors the breakdown to the
 * value total_price() produced BEFORE this change, so a breakdown that is
 * internally consistent but wrong cannot pass.
 */
final class WeekendPricingBreakdownTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', 2800);
	}

	/**
	 * 10 Oct 2026 is a Saturday, 11 Oct a Sunday: both days surcharged.
	 * 2800 * 1.2 * 2 = 6720, i.e. 1120 above the 5600 an operator expects.
	 */
	public function test_breakdown_reports_both_weekend_days_and_the_surcharge(): void
	{
		$breakdown = Util::price_breakdown($this->vehicle_id, 2, strtotime('2026-10-10 10:00:00 UTC'));

		$this->assertSame(2, $breakdown['weekend_days'], 'Saturday and Sunday must both be counted.');
		$this->assertSame(0, $breakdown['weekday_days']);
		$this->assertEqualsWithDelta(2800.0, $breakdown['base_price_per_day'], 0.001);
		$this->assertEqualsWithDelta(1120.0, $breakdown['weekend_surcharge'], 0.001, 'The surcharge is what the screen fails to explain.');
		$this->assertEqualsWithDelta(6720.0, $breakdown['total'], 0.001);
	}

	/**
	 * 20 Sep 2026 is a Sunday, 21 Sep a Monday: one surcharged day, one not.
	 * A single flat multiplier over the whole range would give the wrong
	 * answer here, which is why this case is separate.
	 */
	public function test_breakdown_counts_only_the_weekend_days_in_a_mixed_range(): void
	{
		$breakdown = Util::price_breakdown($this->vehicle_id, 2, strtotime('2026-09-20 10:00:00 UTC'));

		$this->assertSame(1, $breakdown['weekend_days']);
		$this->assertSame(1, $breakdown['weekday_days']);
		$this->assertEqualsWithDelta(560.0, $breakdown['weekend_surcharge'], 0.001);
		$this->assertEqualsWithDelta(6160.0, $breakdown['total'], 0.001);
	}

	public function test_breakdown_reports_no_surcharge_for_a_weekday_only_range(): void
	{
		// 22 Sep 2026 is a Tuesday; 22-24 covers Tue and Wed.
		$breakdown = Util::price_breakdown($this->vehicle_id, 2, strtotime('2026-09-22 10:00:00 UTC'));

		$this->assertSame(0, $breakdown['weekend_days']);
		$this->assertEqualsWithDelta(0.0, $breakdown['weekend_surcharge'], 0.001);
		$this->assertEqualsWithDelta(5600.0, $breakdown['total'], 0.001, 'A weekday-only rental is exactly days x daily price.');
	}

	/**
	 * 🔴 The anchor. Whatever the breakdown reports, its total must be the
	 * number the money path already produced -- otherwise the screen and the
	 * charge disagree, which is worse than the silence being fixed here.
	 *
	 * @dataProvider ranges
	 */
	public function test_breakdown_total_equals_total_price(string $start, int $days): void
	{
		$start_ts = (int) strtotime($start);

		$this->assertEqualsWithDelta(
			Util::total_price($this->vehicle_id, $days, $start_ts),
			Util::price_breakdown($this->vehicle_id, $days, $start_ts)['total'],
			0.001,
			'The breakdown must never disagree with the amount actually charged.'
		);
	}

	/**
	 * @return array<string, array{0: string, 1: int}>
	 */
	public static function ranges(): array
	{
		return array(
			'weekend pair'   => array( '2026-10-10 10:00:00 UTC', 2 ),
			'mixed range'    => array( '2026-09-20 10:00:00 UTC', 2 ),
			'weekday only'   => array( '2026-09-22 10:00:00 UTC', 2 ),
			'full week'      => array( '2026-09-14 10:00:00 UTC', 7 ),
			'single sunday'  => array( '2026-09-20 10:00:00 UTC', 1 ),
		);
	}

	/**
	 * Without a start timestamp the money path cannot know which days fall on
	 * a weekend and charges the flat rate. The breakdown must say the same
	 * thing rather than inventing a surcharge.
	 */
	public function test_breakdown_reports_no_surcharge_without_a_start_timestamp(): void
	{
		$breakdown = Util::price_breakdown($this->vehicle_id, 2, 0);

		$this->assertSame(0, $breakdown['weekend_days']);
		$this->assertEqualsWithDelta(0.0, $breakdown['weekend_surcharge'], 0.001);
		$this->assertEqualsWithDelta(
			Util::total_price($this->vehicle_id, 2, 0),
			$breakdown['total'],
			0.001
		);
	}
}
