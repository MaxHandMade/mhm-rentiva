<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsCalendar;
use WP_UnitTestCase;

/**
 * Aşama 2 PR A: the shared month calendar -- grid built from plain data
 * (week start, leap year, day states) and the partial that prints it.
 */
final class RvsCalendarTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option('start_of_week');
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $calendar
	 * @return array<int, array<string, mixed>> day number => cell
	 */
	private static function days(array $calendar): array
	{
		$out = array();
		foreach ($calendar['weeks'] as $week) {
			foreach ($week as $cell) {
				if (null !== $cell) {
					$out[ $cell['day'] ] = $cell;
				}
			}
		}
		return $out;
	}

	public function test_month_grid_respects_week_start_and_leap_year(): void
	{
		update_option('start_of_week', 1);
		$cal = RvsCalendar::month(2028, 2, array(), '2028-01-10');

		// 1 February 2028 is a Tuesday: one empty cell before it on a Monday-first grid.
		$this->assertNull($cal['weeks'][0][0]);
		$this->assertSame(1, $cal['weeks'][0][1]['day']);
		$this->assertCount(29, self::days($cal));
		foreach ($cal['weeks'] as $week) {
			$this->assertCount(7, $week);
		}

		update_option('start_of_week', 0);
		$cal = RvsCalendar::month(2028, 2, array(), '2028-01-10');
		$this->assertSame(wp_date('l', strtotime('2028-02-06 12:00:00')), $cal['weekdays'][0]['long']);
		// Sunday-first: Sun, Mon empty, Tuesday the 1st in the third cell.
		$this->assertNull($cal['weeks'][0][1]);
		$this->assertSame(1, $cal['weeks'][0][2]['day']);
	}

	public function test_states_past_today_booked_available(): void
	{
		$days = self::days(RvsCalendar::month(2027, 7, array( '2027-07-20', '2027-07-21' ), '2027-07-15'));

		$this->assertSame('past', $days[14]['state']);
		$this->assertSame('available', $days[15]['state']);
		$this->assertTrue($days[15]['today']);
		$this->assertFalse($days[16]['today']);
		$this->assertSame('booked', $days[20]['state']);
		$this->assertSame('available', $days[22]['state']);
		$this->assertSame('2027-07-22', $days[22]['date']);
	}

	public function test_selected_range_marks_inclusive_days(): void
	{
		$days = self::days(RvsCalendar::month(2027, 7, array(), '2027-07-01', array( '2027-07-16', '2027-07-18' )));

		foreach (array( 16, 17, 18 ) as $d) {
			$this->assertSame('selected', $days[ $d ]['state'], (string) $d);
		}
		$this->assertSame('available', $days[19]['state']);
		$this->assertSame('available', $days[15]['state']);

		$single = self::days(RvsCalendar::month(2027, 7, array(), '2027-07-01', array( '2027-07-16' )));
		$this->assertSame('selected', $single[16]['state']);
		$this->assertSame('available', $single[17]['state']);
	}

	public function test_booked_wins_over_selected(): void
	{
		$days = self::days(RvsCalendar::month(2027, 7, array( '2027-07-17' ), '2027-07-01', array( '2027-07-16', '2027-07-18' )));

		$this->assertSame('booked', $days[17]['state']);
		$this->assertSame('selected', $days[16]['state']);
	}

	public function test_label_carries_date_and_state_text(): void
	{
		$days = self::days(RvsCalendar::month(2027, 7, array( '2027-07-20' ), '2027-07-01'));

		$this->assertStringContainsString(wp_date((string) get_option('date_format'), strtotime('2027-07-20 12:00:00')), $days[20]['label']);
		$this->assertStringContainsString('Booked', $days[20]['label']);
		$this->assertStringContainsString('Available', $days[21]['label']);
	}

	public function test_ids_are_unique_per_call(): void
	{
		$this->assertNotSame(RvsCalendar::month(2027, 7, array(), '2027-07-01')['id'], RvsCalendar::month(2027, 7, array(), '2027-07-01')['id']);
	}

	public function test_partial_markup(): void
	{
		$cal  = RvsCalendar::month(2027, 7, array( '2027-07-20' ), '2027-07-15');
		$html = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);

		$this->assertStringContainsString('id="' . $cal['id'] . '-title"', $html);
		$this->assertStringContainsString('aria-labelledby="' . $cal['id'] . '-title"', $html);
		$this->assertSame(7, substr_count($html, '<th scope="col"'));
		// The visible number stays readable; the state is spoken text, not an
		// attribute a `cell` may not carry (Fable PR A I2).
		$this->assertStringNotContainsString('aria-hidden', $html);
		$this->assertStringNotContainsString('aria-disabled', $html);
		$this->assertMatchesRegularExpression('/data-date="2027-07-20"[^>]*><span class="rvs-cal__num">20<\/span><span class="rvs-sr-only">, Booked<\/span>/', $html);
		$this->assertMatchesRegularExpression('/data-date="2027-07-14"[^>]*>.*?<span class="rvs-sr-only">, Past<\/span>/s', $html);
		$this->assertStringContainsString('is-today', $html);
		$this->assertStringContainsString('data-rvs-calendar', $html);
	}

	public function test_day_label_keeps_its_date_east_of_utc_plus_12(): void
	{
		update_option('timezone_string', 'Pacific/Auckland');
		$days = self::days(RvsCalendar::month(2027, 7, array(), '2027-07-01'));
		delete_option('timezone_string');

		$expected = wp_date((string) get_option('date_format'), gmmktime(12, 0, 0, 7, 20, 2027), new \DateTimeZone('UTC'));
		$this->assertStringStartsWith($expected, $days[20]['label']);
	}
}
