<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Rvs;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * One month of the front-end standard's calendar, as plain data.
 *
 * No queries: the caller passes the booked dates (and the selected range, if
 * any); templates/partials/rvs/calendar-month.php only prints the result. The
 * vehicle page's availability block and the availability calendar shortcode
 * share it, so a day looks and reads the same in both.
 */
final class RvsCalendar {

	/**
	 * @param array<int, string> $booked   Booked days, `Y-m-d`.
	 * @param string             $today    Today, `Y-m-d` (the caller's clock, so tests and the site agree).
	 * @param array<int, string> $selected Start and end, `Y-m-d`; one value selects one day.
	 * @return array{id: string, year: int, month: int, label: string, weekdays: list<array{short: string, long: string}>, weeks: list<list<array{date: string, day: int, state: string, today: bool, status: string, label: string}|null>>}
	 */
	public static function month(int $year, int $month, array $booked, string $today, array $selected = array()): array
	{
		global $wp_locale;

		// Dates are calendar days, not moments: format them in UTC so a site east of
		// UTC+12 does not label the 20th as the 21st.
		$utc        = new \DateTimeZone('UTC');
		$week_start = (int) get_option('start_of_week', 1);
		$first      = gmmktime(12, 0, 0, $month, 1, $year);
		$days       = (int) gmdate('t', $first);
		$lead       = ( (int) gmdate('w', $first) - $week_start + 7 ) % 7;

		$booked = array_flip(array_map('strval', $booked));
		$start  = isset($selected[0]) ? (string) $selected[0] : '';
		$end    = isset($selected[1]) ? (string) $selected[1] : $start;

		$weekdays = array();
		for ($i = 0; $i < 7; $i++) {
			$index      = ( $week_start + $i ) % 7;
			$long       = (string) $wp_locale->get_weekday($index);
			$weekdays[] = array(
				'short' => (string) $wp_locale->get_weekday_abbrev($long),
				'long'  => $long,
			);
		}

		$cells = array_fill(0, $lead, null);
		for ($day = 1; $day <= $days; $day++) {
			$date = sprintf('%04d-%02d-%02d', $year, $month, $day);
			if (isset($booked[ $date ])) {
				$state = 'booked';
			} elseif ($date < $today) {
				$state = 'past';
			} elseif ('' !== $start && $date >= $start && $date <= $end) {
				$state = 'selected';
			} else {
				$state = 'available';
			}
			$cells[] = array(
				'date'   => $date,
				'day'    => $day,
				'state'  => $state,
				'today'  => $date === $today,
				'status' => self::state_text($state),
				'label'  => sprintf(
					/* translators: 1: a date, 2: its availability (Available, Booked, Selected or Past). */
					__('%1$s, %2$s', 'mhm-rentiva'),
					wp_date( (string) get_option('date_format'), gmmktime(12, 0, 0, $month, $day, $year), $utc),
					self::state_text($state)
				),
			);
		}
		$trail = ( 7 - count($cells) % 7 ) % 7;
		$cells = array_merge($cells, array_fill(0, $trail, null));

		return array(
			'id'       => wp_unique_id('rvs-cal-'),
			'year'     => $year,
			'month'    => $month,
			'label'    => (string) wp_date('F Y', $first, $utc),
			'weekdays' => $weekdays,
			'weeks'    => array_chunk($cells, 7),
		);
	}

	private static function state_text(string $state): string
	{
		switch ($state) {
			case 'booked':
				return __('Booked', 'mhm-rentiva');
			case 'past':
				return __('Past', 'mhm-rentiva');
			case 'selected':
				return __('Selected', 'mhm-rentiva');
			default:
				return __('Available', 'mhm-rentiva');
		}
	}
}
