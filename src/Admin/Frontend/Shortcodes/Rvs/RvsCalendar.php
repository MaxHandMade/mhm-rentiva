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
	 * @param array<int|string, mixed> $booked Booked days as a list of `Y-m-d`, or the day-state map of
	 *        BookingConflicts::calendar_day_states() (`Y-m-d` => {state: booked|closed|partial, from?, until?}).
	 * @param string             $today    Today, `Y-m-d` (the caller's clock, so tests and the site agree).
	 * @param array<int, string> $selected Start and end, `Y-m-d`; one value selects one day.
	 * @param array{week_start?: int|null, interactive?: bool} $options `week_start` 0-6 (default: the site's
	 *        `start_of_week`); `interactive` makes available and selected days buttons and marks the range ends.
	 * @return array{id: string, year: int, month: int, label: string, interactive: bool, weekdays: list<array{short: string, long: string}>, weeks: list<list<array{date: string, day: int, state: string, today: bool, status: string, label: string, range: string, partial_from: string, partial_until: string, time: string, time_short: string}|null>>}
	 */
	public static function month(int $year, int $month, array $booked, string $today, array $selected = array(), array $options = array()): array
	{
		global $wp_locale;

		// Dates are calendar days, not moments: format them in UTC so a site east of
		// UTC+12 does not label the 20th as the 21st.
		$utc         = new \DateTimeZone('UTC');
		$week_start  = isset($options['week_start']) ? ( (int) $options['week_start'] % 7 + 7 ) % 7 : (int) get_option('start_of_week', 1);
		$interactive = ! empty($options['interactive']);
		$first       = gmmktime(12, 0, 0, $month, 1, $year);
		$days        = (int) gmdate('t', $first);
		$lead        = ( (int) gmdate('w', $first) - $week_start + 7 ) % 7;

		$states = self::normalise_states($booked);
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
			$date  = sprintf('%04d-%02d-%02d', $year, $month, $day);
			$taken = $states[ $date ] ?? null;
			$from  = '';
			$until = '';
			if (null !== $taken && 'partial' !== $taken['state']) {
				$state = $taken['state'];
			} elseif ($date < $today) {
				$state = 'past';
			} else {
				if (null !== $taken) {
					$from  = (string) ( $taken['from'] ?? '' );
					$until = (string) ( $taken['until'] ?? '' );
				}
				if ('' !== $start && $date >= $start && $date <= $end) {
					$state = 'selected';
				} else {
					$state = null !== $taken ? 'partial' : 'available';
				}
			}
			$status = self::state_text($state, $from, $until);
			$range  = '';
			if ($interactive && 'selected' === $state) {
				$range = ( $date === $start ? 'start' : '' ) . ( $date === $end ? 'end' : '' );
				$range = 'startend' === $range ? 'single' : $range;
			}
			$cells[] = array(
				'range'         => $range,
				'date'          => $date,
				'day'           => $day,
				'state'         => $state,
				'today'         => $date === $today,
				'status'        => $status,
				'label'         => sprintf(
					/* translators: 1: a date, 2: its availability (Available, Booked, Selected, Past, Closed, or "Available from/until" a time). */
					__('%1$s, %2$s', 'mhm-rentiva'),
					wp_date( (string) get_option('date_format'), gmmktime(12, 0, 0, $month, $day, $year), $utc),
					$status
				),
				'partial_from'  => $from,
				'partial_until' => $until,
				'time'          => self::time_text($state, $from, $until, false),
				'time_short'    => self::time_text($state, $from, $until, true),
			);
		}
		$trail = ( 7 - count($cells) % 7 ) % 7;
		$cells = array_merge($cells, array_fill(0, $trail, null));

		return array(
			'id'          => wp_unique_id('rvs-cal-'),
			'year'        => $year,
			'month'       => $month,
			'label'       => (string) wp_date('F Y', $first, $utc),
			'interactive' => $interactive,
			'weekdays'    => $weekdays,
			'weeks'       => array_chunk($cells, 7),
		);
	}

	/**
	 * A list of booked dates (the old input) or the day-state map, as the map.
	 *
	 * @param array<int|string, mixed> $booked
	 * @return array<string, array{state: string, from?: string, until?: string}>
	 */
	private static function normalise_states(array $booked): array
	{
		if (array_is_list($booked)) {
			return array_fill_keys(array_map('strval', $booked), array( 'state' => 'booked' ));
		}
		$states = array();
		foreach ($booked as $date => $info) {
			$state = is_array($info) ? (string) ( $info['state'] ?? 'booked' ) : 'booked';
			if (! in_array($state, array( 'booked', 'closed', 'partial' ), true)) {
				$state = 'booked';
			}
			$entry = array( 'state' => $state );
			if ('partial' === $state) {
				foreach (array( 'from', 'until' ) as $key) {
					$time = is_array($info) ? (string) ( $info[ $key ] ?? '' ) : '';
					if (1 === preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
						$entry[ $key ] = $time;
					}
				}
				if (! isset($entry['from']) && ! isset($entry['until'])) {
					$entry = array( 'state' => 'booked' );
				}
			}
			$states[ (string) $date ] = $entry;
		}

		return $states;
	}

	private static function state_text(string $state, string $from = '', string $until = ''): string
	{
		switch ($state) {
			case 'booked':
				return __('Booked', 'mhm-rentiva');
			case 'closed':
				return __('Closed', 'mhm-rentiva');
			case 'past':
				return __('Past', 'mhm-rentiva');
			case 'selected':
				return __('Selected', 'mhm-rentiva');
			case 'partial':
				if ('' !== $from) {
					/* translators: %s: a time of day, e.g. 11:00 -- the vehicle is free from then on that day. */
					return sprintf(__('Available from %s', 'mhm-rentiva'), self::clock($from));
				}
				/* translators: %s: a time of day, e.g. 09:00 -- the vehicle is free until then on that day. */
				return sprintf(__('Available until %s', 'mhm-rentiva'), self::clock($until));
			default:
				return __('Available', 'mhm-rentiva');
		}
	}

	/**
	 * The visible time in a partial (or selected partial) cell, "11:00 →" /
	 * "→ 09:00"; `$short` drops ":00" for a narrow screen. A closed day shows
	 * its word on wide screens only.
	 */
	private static function time_text(string $state, string $from, string $until, bool $short): string
	{
		if ('closed' === $state) {
			return $short ? '' : __('Closed', 'mhm-rentiva');
		}
		if ('' !== $from) {
			return ( $short ? self::short_clock($from) : self::clock($from) ) . ' →';
		}
		if ('' !== $until) {
			return '→ ' . ( $short ? self::short_clock($until) : self::clock($until) );
		}

		return '';
	}

	/** A `H:i` time in the site's time format. */
	private static function clock(string $time): string
	{
		$utc = new \DateTimeZone('UTC');
		$at  = \DateTimeImmutable::createFromFormat('!H:i', $time, $utc);

		return false === $at ? $time : (string) wp_date( (string) get_option('time_format', 'H:i'), $at->getTimestamp(), $utc);
	}

	/**
	 * "09" for 09:00 on a 24-hour site; a 12-hour site keeps its full time
	 * ("9:00 am"), since a bare "09" says nothing there.
	 */
	private static function short_clock(string $time): string
	{
		$format = (string) get_option('time_format', 'H:i');
		if (1 === preg_match('/[aAgh]/', $format) || ! str_ends_with($time, ':00')) {
			return self::clock($time);
		}

		return substr($time, 0, 2);
	}
}
