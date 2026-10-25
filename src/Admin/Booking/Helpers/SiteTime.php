<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Booking time, read in the site's configured timezone (wp_timezone()).
 *
 * A booking is entered as a local date and time; it is stored as the real
 * instant (Unix timestamp) those name. Everything that asks "which day is
 * this?" -- weekend, season, "today", "this month", the day count -- answers
 * in the site's calendar, not UTC's. Calendar arithmetic is done on the
 * calendar (DateTimeImmutable in the site zone), never by adding 86400
 * seconds, so a 23- or 25-hour DST day stays one day.
 */
final class SiteTime {

	/**
	 * Local `Y-m-d` + `H:i` (or `H:i:s`) as a Unix timestamp, or null when the
	 * input is malformed or names an impossible date (it is not normalised
	 * into a neighbouring one).
	 */
	public static function instant(string $date, string $time = '00:00'): ?int
	{
		if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
			return null;
		}

		$format = 5 === strlen($time) ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
		$dt     = \DateTimeImmutable::createFromFormat($format, $date . ' ' . $time, wp_timezone());
		$errors = \DateTimeImmutable::getLastErrors();

		if (false === $dt || ( is_array($errors) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) )) {
			return null;
		}

		return $dt->getTimestamp();
	}

	/**
	 * The local date of an instant.
	 */
	public static function local_date(int $ts): string
	{
		return (string) wp_date('Y-m-d', $ts);
	}

	/**
	 * The local ISO-8601 weekday of an instant: 1 (Monday) to 7 (Sunday).
	 */
	public static function weekday(int $ts): int
	{
		return (int) wp_date('N', $ts);
	}

	/**
	 * $count consecutive local dates, starting with the local date of $start_ts.
	 *
	 * @return list<string>
	 */
	public static function days_from(int $start_ts, int $count): array
	{
		$day   = self::local($start_ts)->setTime(12, 0);
		$dates = array();

		for ($i = 0; $i < $count; $i++) {
			$dates[] = $day->modify("+{$i} day")->format('Y-m-d');
		}

		return $dates;
	}

	/**
	 * Rental days between two instants: whole days of local wall-clock time,
	 * at least 1. 10:00 -> 10:00 three days later is 3 even across a DST
	 * change; 10:00 -> 09:30 is one short, as it always was.
	 */
	public static function rental_days(int $start_ts, int $end_ts): int
	{
		$days = self::local($start_ts)->diff(self::local($end_ts))->days;

		return max(1, false === $days ? 0 : $days);
	}

	/**
	 * The local date $n days before today.
	 *
	 * @param int|null $now Only for tests; production callers omit it.
	 */
	public static function days_ago(int $n, ?int $now = null): string
	{
		return self::local($now ?? time())->setTime(12, 0)->modify("-{$n} day")->format('Y-m-d');
	}

	/**
	 * The first day of the current local month, `Y-m-01`.
	 *
	 * @param int|null $now Only for tests; production callers omit it.
	 */
	public static function month_start(?int $now = null): string
	{
		return self::local($now ?? time())->format('Y-m-01');
	}

	/**
	 * Today's local date.
	 *
	 * @param int|null $now Only for tests; production callers omit it.
	 */
	public static function today(?int $now = null): string
	{
		return self::local($now ?? time())->format('Y-m-d');
	}

	/**
	 * An instant as a GMT MySQL datetime.
	 */
	public static function gmt_mysql(int $ts): string
	{
		return gmdate('Y-m-d H:i:s', $ts);
	}

	/**
	 * The site's UTC offset at $at (default now) as `+HH:MM` / `-HH:MM`.
	 *
	 * Only for the SQL fallback on old rows that carry no timestamp; anything
	 * that can work on instants should.
	 */
	public static function offset_string(?int $at = null): string
	{
		$seconds = wp_timezone()->getOffset(self::local($at ?? time()));
		$abs     = abs($seconds);

		return sprintf('%s%02d:%02d', $seconds < 0 ? '-' : '+', intdiv($abs, 3600), intdiv($abs % 3600, 60));
	}

	/**
	 * An instant in the site's timezone.
	 */
	private static function local(int $ts): \DateTimeImmutable
	{
		return ( new \DateTimeImmutable('@' . $ts) )->setTimezone(wp_timezone());
	}
}
