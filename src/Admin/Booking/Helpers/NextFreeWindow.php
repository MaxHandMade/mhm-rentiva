<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The earliest free window of a booked vehicle -- "Earliest free: %s".
 *
 * The window keeps the shape of the one searched for, and that shape is the
 * vehicle page's: the same number of local days (SiteTime::rental_days) and
 * the return at the pickup's own wall-clock time. So the date and time on the
 * card, opened on the vehicle page, name exactly the window checked here.
 *
 * The scan walks forward from the requested start:
 *
 * - the candidate is rounded up to the next full local hour, inside the loop,
 *   so the rounded window is the one tested;
 * - a booking in the way moves it to that booking's end plus the buffer;
 * - a blocked day in the way moves it to the next day, at the requested
 *   pickup time;
 * - a candidate whose local date is past today + the advance booking days
 *   (the vehicle page's own limit) ends the scan with null.
 *
 * Bookings come from one query for all vehicles (BookingConflicts::
 * booking_intervals) and blocked days from one meta read, so the scan itself
 * asks the database nothing. The answer is then checked with the live rule
 * (BookingConflicts::is_booked), which writes no cache.
 *
 * @since 6.4.0
 */
final class NextFreeWindow {

	/**
	 * A guard, not a limit anyone should meet: every step moves the candidate
	 * forward by at least an hour, and the horizon is at most a year.
	 */
	private const MAX_STEPS = 20000;

	/**
	 * The earliest start whose window is free, per vehicle.
	 *
	 * @param list<int> $vehicle_ids
	 * @return array<int, ?int> Vehicle id => start instant, or null when none within the horizon.
	 */
	public static function earliest(array $vehicle_ids, int $start, int $end): array
	{
		$ids    = array_values(array_unique(array_filter(array_map('intval', $vehicle_ids))));
		$answer = array_fill_keys($ids, null);
		if (array() === $ids || $end <= $start) {
			return $answer;
		}

		$days      = SiteTime::rental_days($start, $end);
		$time      = (string) wp_date('H:i', $start);
		$last_date = self::last_pickup_date();
		$to        = self::local_midnight($last_date, $days + 2);
		if (null === $to || SiteTime::local_date($start) > $last_date) {
			return $answer;
		}

		$intervals = BookingConflicts::booking_intervals($ids, $start, $to);
		$buffer    = BookingConflicts::buffer_seconds();
		update_meta_cache('post', $ids);

		foreach ($ids as $vehicle_id) {
			$found = self::scan($start, $days, $time, $last_date, $intervals[ $vehicle_id ] ?? array(), BookingConflicts::blocked_days($vehicle_id), $buffer);
			if (null === $found) {
				continue;
			}

			$window = self::window($found, $days);
			if (null === $window || BookingConflicts::is_booked($vehicle_id, $window[0], $window[1])) {
				AdvancedLogger::warning(
					'Earliest free window failed its live check; no date shown.',
					array(
						'vehicle_id' => $vehicle_id,
						'start'      => $found,
						'days'       => $days,
					),
					AdvancedLogger::CATEGORY_BOOKING
				);
				continue;
			}

			$answer[ $vehicle_id ] = $found;
		}

		return $answer;
	}

	/**
	 * The vehicle page's window for a start: the return $days local days
	 * later at the same wall-clock time, built as the page builds it
	 * (SiteTime::instant).
	 *
	 * @return array{0: int, 1: int}|null
	 */
	public static function window(int $start, int $days): ?array
	{
		$return = self::add_days(SiteTime::local_date($start), max(1, $days));
		$end    = SiteTime::instant($return, (string) wp_date('H:i', $start));

		return null === $end || $end <= $start ? null : array( $start, $end );
	}

	/**
	 * @param list<array{0: int, 1: int}> $intervals Bookings of this vehicle.
	 * @param list<string>                $blocked   Blocked local days.
	 */
	private static function scan(int $candidate, int $days, string $time, string $last_date, array $intervals, array $blocked, int $buffer): ?int
	{
		for ($step = 0; $step < self::MAX_STEPS; $step++) {
			$at = self::round_up($candidate);
			if (null === $at || SiteTime::local_date($at) > $last_date) {
				return null;
			}

			$window = self::window($at, $days);
			if (null === $window) {
				return null;
			}

			// A booking in the way: the latest one's end plus the buffer.
			$next = null;
			foreach ($intervals as $booking) {
				if ($booking[0] - $buffer < $window[1] && $window[0] < $booking[1] + $buffer) {
					$next = max($next ?? 0, $booking[1] + $buffer);
				}
			}
			if (null !== $next) {
				$candidate = $next;
				continue;
			}

			// A blocked day in the way: the day after the last one, at the requested time.
			$first = SiteTime::local_date($window[0]);
			$last  = SiteTime::local_date($window[1] - 1);
			$hit   = '';
			foreach ($blocked as $day) {
				if ($day >= $first && $day <= $last && $day > $hit) {
					$hit = $day;
				}
			}
			if ('' !== $hit) {
				$next_day = self::wall_instant(self::add_days($hit, 1), $time);
				if (null === $next_day) {
					return null;
				}
				$candidate = $next_day;
				continue;
			}

			return $at;
		}

		return null;
	}

	/**
	 * The first instant at or after $ts that is a full local hour whose wall
	 * time reads back as itself.
	 *
	 * The answer travels as a local date and time (the card, then the vehicle
	 * page's SiteTime::instant), so it must survive that trip. Labels are
	 * stepped on the wall clock and read with wall_instant(), which gives
	 * the reading the page will make: in a repeated autumn hour the label
	 * 01:00 is the first 01:00, so a candidate at the second one gets an
	 * instant before it and moves on to 02:00.
	 */
	private static function round_up(int $ts): ?int
	{
		$local = ( new \DateTimeImmutable('@' . $ts) )->setTimezone(wp_timezone());
		// A wall-clock label, stepped in a zone without DST.
		$label = new \DateTimeImmutable($local->format('Y-m-d H:00:00'), new \DateTimeZone('UTC'));
		if ('00:00' !== $local->format('i:s')) {
			$label = $label->modify('+1 hour');
		}

		for ($i = 0; $i < 4; $i++) {
			$at = self::wall_instant($label->format('Y-m-d'), $label->format('H:i'));
			// A half-hour gap (Lord Howe) can end off the hour: take the next label.
			if (null !== $at && $at >= $ts && '00' === wp_date('i', $at)) {
				return $at;
			}
			$label = $label->modify('+1 hour');
		}

		return null;
	}

	/**
	 * A local date and time as an instant; a time inside a spring-forward gap
	 * is the first instant after the gap (02:30 -> 03:00), not PHP's shifted
	 * reading (03:30). A repeated hour is its first occurrence.
	 */
	private static function wall_instant(string $date, string $time): ?int
	{
		$ts = SiteTime::instant($date, $time);
		if (null === $ts || wp_date('Y-m-d H:i', $ts) === $date . ' ' . $time) {
			return $ts;
		}

		$transitions = wp_timezone()->getTransitions($ts - DAY_IN_SECONDS, $ts);
		if (! is_array($transitions) || count($transitions) < 2) {
			return $ts;
		}

		// Index 0 is the start of the range itself, not a transition.
		$gap_end = end($transitions);

		return (int) $gap_end['ts'];
	}

	/**
	 * The last local date a pickup may fall on: the vehicle page's limit,
	 * today plus the advance booking days, in calendar days.
	 */
	private static function last_pickup_date(): string
	{
		$advance = (int) SettingsCore::get('mhmrentiva_vehicle_advance_booking_days', 365);
		if ($advance <= 0) {
			$advance = 365;
		}

		return SiteTime::days_ahead($advance);
	}

	/**
	 * Local midnight $days after $date.
	 */
	private static function local_midnight(string $date, int $days): ?int
	{
		return SiteTime::instant(self::add_days($date, $days), '00:00');
	}

	/**
	 * A local `Y-m-d` plus $days calendar days.
	 */
	private static function add_days(string $date, int $days): string
	{
		return ( new \DateTimeImmutable($date . ' 12:00:00', new \DateTimeZone('UTC')) )->modify("+{$days} day")->format('Y-m-d');
	}
}
