<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsCalendar;
use MHMRentiva\Helpers\Html;
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
		delete_option('time_format');
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

	public function test_non_interactive_output_is_byte_identical_to_the_vehicle_page_golden(): void
	{
		update_option('start_of_week', 1);
		$cal       = RvsCalendar::month(2027, 7, array( '2027-07-20', '2027-07-21' ), '2027-07-15', array( '2027-07-24', '2027-07-26' ));
		$cal['id'] = 'rvs-cal-golden';
		$html      = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);

		// Captured from the partial before the options array existed (PR E task 2).
		$this->assertSame(file_get_contents(dirname(__DIR__, 2) . '/fixtures/golden/rvs-calendar-month-vehicle-page.html'), $html);
		$this->assertStringNotContainsString('<button', $html);
		$this->assertStringNotContainsString('is-range', $html);
		$this->assertStringNotContainsString('is-interactive', $html);
	}

	public function test_week_start_option_overrides_the_site_setting(): void
	{
		update_option('start_of_week', 1);
		$cal = RvsCalendar::month(2028, 2, array(), '2028-01-10', array(), array( 'week_start' => 0 ));
		$this->assertSame('Sunday', $cal['weekdays'][0]['long']);
		$this->assertNull($cal['weeks'][0][1]);
		$this->assertSame(1, $cal['weeks'][0][2]['day']);

		$site = RvsCalendar::month(2028, 2, array(), '2028-01-10', array(), array( 'week_start' => null ));
		$this->assertSame('Monday', $site['weekdays'][0]['long']);
	}

	public function test_interactive_days_are_buttons_and_past_booked_are_not(): void
	{
		$cal  = RvsCalendar::month(2027, 7, array( '2027-07-20' ), '2027-07-15', array( '2027-07-24', '2027-07-26' ), array( 'interactive' => true ));
		$html = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);

		$this->assertStringContainsString('<button type="button" class="rvs-cal__btn" data-date="2027-07-22"', $html);
		$this->assertMatchesRegularExpression('/<button type="button" class="rvs-cal__btn" data-date="2027-07-22" aria-label="[^"]*22[^"]*Available"/', $html);
		$this->assertMatchesRegularExpression('/data-date="2027-07-25" aria-label="[^"]*Selected"/', $html);
		$this->assertStringNotContainsString('data-date="2027-07-14" aria-label', $html);
		$this->assertStringNotContainsString('data-date="2027-07-20" aria-label', $html);
		$this->assertStringContainsString('rvs-cal is-interactive', $html);
		// Buttons: 15..31 minus the one booked day = 16 available/selected days.
		$this->assertSame(16, substr_count($html, '<button type="button"'));
	}

	public function test_interactive_range_classes(): void
	{
		$cal  = RvsCalendar::month(2027, 7, array( '2027-07-20' ), '2027-07-15', array( '2027-07-24', '2027-07-26' ), array( 'interactive' => true ));
		$html = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);

		$this->assertMatchesRegularExpression('/class="rvs-cal__day is-selected is-range-start" data-date="2027-07-24"/', $html);
		$this->assertMatchesRegularExpression('/class="rvs-cal__day is-selected" data-date="2027-07-25"/', $html);
		$this->assertMatchesRegularExpression('/class="rvs-cal__day is-selected is-range-end" data-date="2027-07-26"/', $html);
		// A booked day stays booked, with no button and no range class.
		$this->assertMatchesRegularExpression('/class="rvs-cal__day is-booked" data-date="2027-07-20"/', $html);

		$one  = RvsCalendar::month(2027, 7, array(), '2027-07-15', array( '2027-07-24' ), array( 'interactive' => true ));
		$html = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $one ), true);
		$this->assertMatchesRegularExpression('/class="rvs-cal__day is-selected is-range-start is-range-end" data-date="2027-07-24"/', $html);
	}

	public function test_interactive_markup_survives_allowed_markup(): void
	{
		$cal      = RvsCalendar::month(2027, 7, array(), '2027-07-15', array(), array( 'interactive' => true ));
		$raw      = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);
		$filtered = wp_kses($raw, Html::allowed_markup());

		$this->assertStringContainsString('<button type="button" class="rvs-cal__btn" data-date="2027-07-22" aria-label="', $filtered);
		$this->assertSame(substr_count($raw, '<button'), substr_count($filtered, '<button'));
		$this->assertSame(substr_count($raw, 'aria-label='), substr_count($filtered, 'aria-label='));
		$this->assertSame(substr_count($raw, 'data-date='), substr_count($filtered, 'data-date='));
	}

	/**
	 * Ç6 (approved 2026-11-11): the day-state map of
	 * BookingConflicts::calendar_day_states() -- partial days carry their time,
	 * closed days their own state; the old list of booked dates still works.
	 *
	 * @return array<string, array{state: string, from?: string, until?: string}>
	 */
	private static function states(): array
	{
		update_option('time_format', 'H:i');

		return array(
			'2027-07-19' => array( 'state' => 'partial', 'until' => '09:00' ),
			'2027-07-20' => array( 'state' => 'booked' ),
			'2027-07-21' => array( 'state' => 'partial', 'from' => '11:30' ),
			'2027-07-27' => array( 'state' => 'closed' ),
		);
	}

	public function test_state_map_gives_partial_and_closed_days(): void
	{
		$days = self::days(RvsCalendar::month(2027, 7, self::states(), '2027-07-15'));

		$this->assertSame('partial', $days[19]['state']);
		$this->assertSame('', $days[19]['partial_from']);
		$this->assertSame('09:00', $days[19]['partial_until']);
		$this->assertSame('booked', $days[20]['state']);
		$this->assertSame('partial', $days[21]['state']);
		$this->assertSame('11:30', $days[21]['partial_from']);
		$this->assertSame('closed', $days[27]['state']);
		$this->assertSame('available', $days[22]['state']);
		$this->assertStringEndsWith(', Available from 11:30', $days[21]['label']);
		$this->assertStringEndsWith(', Available until 09:00', $days[19]['label']);
		$this->assertStringEndsWith(', Closed', $days[27]['label']);
	}

	public function test_partial_days_are_buttons_closed_days_are_not(): void
	{
		$cal  = RvsCalendar::month(2027, 7, self::states(), '2027-07-15', array(), array( 'interactive' => true ));
		$html = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);

		$this->assertMatchesRegularExpression('/class="rvs-cal__day is-partial is-partial-from" data-date="2027-07-21"[^>]*><button type="button" class="rvs-cal__btn" data-date="2027-07-21" aria-label="[^"]*Available from 11:30"/', $html);
		$this->assertMatchesRegularExpression('/class="rvs-cal__day is-partial is-partial-until" data-date="2027-07-19"[^>]*><button/', $html);
		$this->assertMatchesRegularExpression('/class="rvs-cal__day is-closed" data-date="2027-07-27"><span class="rvs-cal__num">27<\/span><span class="rvs-cal__time" aria-hidden="true">/', $html);
		$this->assertStringNotContainsString('data-date="2027-07-27" aria-label', $html);
		// 15..31 = 17 days, minus booked 20 and closed 27.
		$this->assertSame(15, substr_count($html, '<button type="button"'));
	}

	public function test_partial_time_is_printed_full_and_short(): void
	{
		$cal  = RvsCalendar::month(2027, 7, self::states(), '2027-07-15');
		$html = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);

		$this->assertStringContainsString('<span class="rvs-cal__time" aria-hidden="true">11:30 →</span><span class="rvs-cal__time-short" aria-hidden="true">11:30 →</span>', $html);
		$this->assertStringContainsString('<span class="rvs-cal__time" aria-hidden="true">→ 09:00</span><span class="rvs-cal__time-short" aria-hidden="true">→ 09</span>', $html);
		$this->assertMatchesRegularExpression('/data-date="2027-07-19"[^>]*>.*?<span class="rvs-sr-only">, Available until 09:00<\/span>/s', $html);
	}

	public function test_a_selected_partial_day_keeps_its_time(): void
	{
		$cal  = RvsCalendar::month(2027, 7, self::states(), '2027-07-15', array( '2027-07-21', '2027-07-24' ), array( 'interactive' => true ));
		$days = self::days($cal);
		$html = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);

		$this->assertSame('selected', $days[21]['state']);
		$this->assertSame('11:30', $days[21]['partial_from']);
		$this->assertMatchesRegularExpression('/class="rvs-cal__day is-selected is-partial-from is-range-start" data-date="2027-07-21"/', $html);
	}

	public function test_a_twelve_hour_site_keeps_the_full_time_in_the_short_label(): void
	{
		self::states();
		update_option('time_format', 'g:i a');
		$cal  = RvsCalendar::month(2027, 7, array( '2027-07-19' => array( 'state' => 'partial', 'until' => '09:00' ) ), '2027-07-15');
		$html = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);

		$this->assertStringContainsString('<span class="rvs-cal__time-short" aria-hidden="true">→ 9:00 am</span>', $html);
	}

	public function test_partial_markup_survives_allowed_markup(): void
	{
		$cal      = RvsCalendar::month(2027, 7, self::states(), '2027-07-15', array(), array( 'interactive' => true ));
		$raw      = (string) Templates::render('partials/rvs/calendar-month', array( 'calendar' => $cal ), true);
		$filtered = wp_kses($raw, Html::allowed_markup());

		$this->assertSame(substr_count($raw, 'rvs-cal__time'), substr_count($filtered, 'rvs-cal__time'));
		$this->assertSame(substr_count($raw, 'aria-hidden="true"'), substr_count($filtered, 'aria-hidden="true"'));
	}

	public function test_week_start_is_normalised_into_0_to_6(): void
	{
		$seven = RvsCalendar::month(2028, 2, array(), '2028-01-10', array(), array( 'week_start' => 7 ));
		$this->assertSame('Sunday', $seven['weekdays'][0]['long']);

		$minus = RvsCalendar::month(2028, 2, array(), '2028-01-10', array(), array( 'week_start' => -1 ));
		$this->assertSame('Saturday', $minus['weekdays'][0]['long']);
		foreach ($minus['weeks'] as $week) {
			$this->assertCount(7, $week);
		}
	}
}
