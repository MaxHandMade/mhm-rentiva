<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Availability is a live read of booking rows; a cached answer is exactly what the conflict check must not use.

/**
 * The one rule for "is this vehicle taken for [start, end)?".
 *
 * A vehicle is booked for a window when either
 *
 * 1. a booking overlaps it: status pending_payment, pending, confirmed or
 *    in_progress -- or completed and not yet returned --, a pending payment
 *    whose deadline has passed not counting, and the booking's interval
 *    widened by the buffer on both sides meeting the window; or
 * 2. one of the window's local days is a blocked day of the vehicle. The
 *    window's days are local_date(start) ... local_date(end - 1); a blocked
 *    day closes local 00:00-24:00 and takes no buffer.
 *
 * Search, the booking paths and the locked count all read the booking half
 * from the SQL below, so they cannot drift apart again: the WHERE clause is
 * written once, in the constants, and every query is built from it.
 *
 * The operator's own paths (manual booking, booking edit) count bookings only
 * (bookings_overlap(), count_booking_overlaps_locked()): an operator may book
 * a closed day and is warned instead of refused.
 *
 * @since 6.4.0
 */
final class BookingConflicts {

	private const BLOCKED_META = '_mhmrentiva_blocked_dates';

	private const DAY_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

	/**
	 * Booking rows and the metas the rule reads. Tables are %i placeholders,
	 * bound by rule_args(): posts, then postmeta nine times.
	 *
	 * Dates are LEFT joins: a row with stored instants needs no date strings,
	 * and a row with neither yields NULL bounds, which match nothing.
	 */
	private const FROM_SQL = "FROM %i p
		INNER JOIN %i pm_vid ON p.ID = pm_vid.post_id AND pm_vid.meta_key = '_mhmrentiva_vehicle_id'
		INNER JOIN %i pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhmrentiva_status'
		LEFT JOIN %i pm_date_s ON p.ID = pm_date_s.post_id AND pm_date_s.meta_key = '_mhmrentiva_pickup_date'
		LEFT JOIN %i pm_date_e ON p.ID = pm_date_e.post_id AND pm_date_e.meta_key = '_mhmrentiva_dropoff_date'
		LEFT JOIN %i pm_time_s ON p.ID = pm_time_s.post_id AND pm_time_s.meta_key IN ('_mhmrentiva_pickup_time', '_mhmrentiva_start_time')
		LEFT JOIN %i pm_time_e ON p.ID = pm_time_e.post_id AND pm_time_e.meta_key IN ('_mhmrentiva_dropoff_time', '_mhmrentiva_end_time')
		LEFT JOIN %i pm_ts_s ON p.ID = pm_ts_s.post_id AND pm_ts_s.meta_key = '_mhmrentiva_start_ts'
		LEFT JOIN %i pm_ts_e ON p.ID = pm_ts_e.post_id AND pm_ts_e.meta_key = '_mhmrentiva_end_ts'
		LEFT JOIN %i pm_deadline ON p.ID = pm_deadline.post_id AND pm_deadline.meta_key = '_mhmrentiva_payment_deadline'";

	/**
	 * A booking's start instant: the stored timestamp, else its local date and
	 * time read in the site's zone. An empty time falls back to the default
	 * pickup time. Binds: default time, site offset.
	 *
	 * The offset goes into CONVERT_TZ() rather than the session: UNIX_TIMESTAMP
	 * of a datetime would read it in whatever zone the connection is in. A
	 * fixed offset ignores DST for those old rows, as it always did.
	 */
	private const START_EXPR = "COALESCE(CAST(NULLIF(pm_ts_s.meta_value, '') AS UNSIGNED), TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', CONVERT_TZ(CONCAT(pm_date_s.meta_value, ' ', COALESCE(NULLIF(pm_time_s.meta_value, ''), %s)), %s, '+00:00')))";

	/**
	 * A booking's end instant, as START_EXPR. Binds: default time, site offset.
	 */
	private const END_EXPR = "COALESCE(CAST(NULLIF(pm_ts_e.meta_value, '') AS UNSIGNED), TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', CONVERT_TZ(CONCAT(pm_date_e.meta_value, ' ', COALESCE(NULLIF(pm_time_e.meta_value, ''), %s)), %s, '+00:00')))";

	/**
	 * Statuses that hold the vehicle. A completed booking whose end is still
	 * ahead (cron misfire, early manual completion) keeps holding it until the
	 * real return. Binds: END_EXPR's two, then "now".
	 */
	private const STATUS_SQL = "(pm_status.meta_value IN ('pending_payment', 'pending', 'confirmed', 'in_progress') OR (pm_status.meta_value = 'completed' AND " . self::END_EXPR . ' > %d))';

	/**
	 * A pending payment past its deadline holds nothing. Binds: GMT now.
	 */
	private const PAYMENT_SQL = "(pm_status.meta_value NOT IN ('pending_payment', 'pending') OR pm_deadline.meta_value IS NULL OR pm_deadline.meta_value = '' OR pm_deadline.meta_value > %s)";

	/**
	 * The booking widened by the buffer meets the window:
	 * start - buffer < window end AND window start < end + buffer.
	 * Binds: START_EXPR's two, window end + buffer, END_EXPR's two, window start - buffer.
	 */
	private const OVERLAP_SQL = '(' . self::START_EXPR . ' < %d AND ' . self::END_EXPR . ' > %d)';

	/**
	 * The whole booking rule. Placeholders in rule_args() order.
	 */
	private const RULE_SQL = "p.post_type = 'mhmrentiva_booking' AND p.post_status = 'publish' AND " . self::STATUS_SQL . ' AND ' . self::PAYMENT_SQL . ' AND ' . self::OVERLAP_SQL;

	/**
	 * The vehicle's blocked days, as the site's local `Y-m-d` dates.
	 *
	 * Read defensively: the meta is a JSON string, but an array (a direct
	 * update_post_meta() with one) is taken as it is, broken JSON reads as
	 * nothing blocked, and every item must look like a date.
	 *
	 * @return list<string>
	 */
	public static function blocked_days(int $vehicle_id): array
	{
		return self::filtered_days(get_post_meta($vehicle_id, self::BLOCKED_META, true), $vehicle_id);
	}

	/**
	 * The vehicle's blocked days as stored: the same defensive read as
	 * blocked_days(), without the filter.
	 *
	 * This is the list the blocked-day writers read, change and write back.
	 * The filter's answer is what availability reads; storing it would make
	 * a filter-added day permanent and lose a filter-dropped one on the next
	 * unrelated write.
	 *
	 * @return list<string>
	 */
	public static function stored_days(int $vehicle_id): array
	{
		return self::clean_days(get_post_meta($vehicle_id, self::BLOCKED_META, true));
	}

	/**
	 * The local days a window touches: local_date(start) ... local_date(end - 1).
	 *
	 * @return list<string>
	 */
	public static function window_days(int $start, int $end): array
	{
		if ($end <= $start) {
			return array();
		}

		// Noon, so a 23- or 25-hour DST day still steps one calendar day.
		$last = SiteTime::local_date($end - 1);
		$day  = ( new \DateTimeImmutable('@' . $start) )->setTimezone(wp_timezone())->setTime(12, 0);
		$days = array();

		for ($date = $day->format('Y-m-d'); $date <= $last; $date = $day->format('Y-m-d')) {
			$days[] = $date;
			$day    = $day->modify('+1 day');
		}

		return $days;
	}

	/**
	 * The vehicle's blocked days inside the window.
	 *
	 * @return list<string>
	 */
	public static function blocked_days_in(int $vehicle_id, int $start, int $end): array
	{
		return self::days_in_window(self::blocked_days($vehicle_id), $start, $end);
	}

	public static function hits_blocked_day(int $vehicle_id, int $start, int $end): bool
	{
		return array() !== self::blocked_days_in($vehicle_id, $start, $end);
	}

	/**
	 * Vehicles with a blocked day in the window.
	 *
	 * The blocked-day meta of the candidates is read in one query and the
	 * days are filtered in PHP, so the answer is the same function the
	 * booking paths use. Every candidate goes through the filter, meta or
	 * not: a filter may add days.
	 *
	 * @param list<int>|null $among Candidate vehicles; null is every vehicle, an empty list none.
	 * @return list<int>
	 */
	public static function blocked_vehicle_ids(int $start, int $end, ?array $among = null): array
	{
		global $wpdb;

		if ($end <= $start) {
			return array();
		}

		$ids = null === $among ? self::all_vehicle_ids() : array_values(array_unique(array_filter(array_map('intval', $among))));
		if (array() === $ids) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT post_id, meta_value FROM %i WHERE meta_key = %s AND meta_value NOT IN (\'\', \'[]\') AND post_id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')',
				array_merge(array( $wpdb->postmeta, self::BLOCKED_META ), $ids)
			),
			ARRAY_A
		);

		$raw = array();
		foreach ( (array) $rows as $row) {
			$raw[ (int) $row['post_id'] ] = maybe_unserialize($row['meta_value']);
		}

		$hits = array();
		foreach ($ids as $vehicle_id) {
			if (array() !== self::days_in_window(self::filtered_days($raw[ $vehicle_id ] ?? '', $vehicle_id), $start, $end)) {
				$hits[] = $vehicle_id;
			}
		}

		return $hits;
	}

	/**
	 * Does a booking overlap the window? Bookings only, no lock, no cache.
	 *
	 * @param int $exclude A booking to leave out (the one being edited); 0 none.
	 */
	public static function bookings_overlap(int $vehicle_id, int $start, int $end, int $exclude = 0): bool
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- FROM_SQL and RULE_SQL are private class constants of this file whose placeholders rule_args() binds in order (the sniff cannot read a constant); every value goes through prepare().
		$found = $wpdb->get_var($wpdb->prepare('SELECT p.ID ' . self::FROM_SQL . ' WHERE ' . self::RULE_SQL . ' AND p.ID <> %d AND pm_vid.meta_value = %d LIMIT 1', array_merge(self::rule_args($start, $end), array( $exclude, $vehicle_id ))));

		return null !== $found;
	}

	/**
	 * The locked count of bookings overlapping the window, or null when the
	 * query did not produce one. Bookings only.
	 *
	 * Holds a lock only inside a transaction the caller opened
	 * (Locker::withLock()); under autocommit the row lock ends with the
	 * statement.
	 *
	 * A query that never ran is not visible in $wpdb->last_error: a `query`
	 * filter returning an empty string makes wpdb::query() return false before
	 * it flushes or records anything, and get_var() would then read the
	 * previous query's leftover result. So the COUNT is run with query(), its
	 * row count is checked, and the value is read only after a success.
	 *
	 * @param int $exclude A booking to leave out (the one being edited); 0 none.
	 */
	public static function count_booking_overlaps_locked(int $vehicle_id, int $start, int $end, int $exclude = 0): ?int
	{
		global $wpdb;

		// prefix-rename:ignore-start
		// Both the pre- and post-6.0.0 spellings, for the reason Locker::withLock()
		// spells out: a '_mhmrentiva_%' pattern locks nothing on an un-migrated site.
		$wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key LIKE %s
             FOR UPDATE",
				$vehicle_id,
				$wpdb->esc_like('_mhm') . '%'
			)
		);
		// prefix-rename:ignore-end

		$args = array_merge(self::rule_args($start, $end), array( $exclude, $vehicle_id ));

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- FROM_SQL and RULE_SQL are private class constants of this file whose placeholders rule_args() binds in order (the sniff cannot read a constant); every value goes through prepare().
		$ran = $wpdb->query($wpdb->prepare('SELECT COUNT(*) ' . self::FROM_SQL . ' WHERE ' . self::RULE_SQL . ' AND p.ID <> %d AND pm_vid.meta_value = %d', $args));
		// An ungrouped COUNT(*) yields exactly one row, zero matches included. Any
		// other row count (false, or 0 after something reran a query on this
		// connection mid-flight) means the result in hand is not this COUNT's.
		if (1 !== $ran) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no SQL is passed; this reads the result of the prepared COUNT above.
		$count = $wpdb->get_var();

		return null === $count ? null : (int) $count;
	}

	/**
	 * Booking or blocked day. Writes no cache.
	 */
	public static function is_booked(int $vehicle_id, int $start, int $end, int $exclude = 0): bool
	{
		return self::bookings_overlap($vehicle_id, $start, $end, $exclude) || self::hits_blocked_day($vehicle_id, $start, $end);
	}

	/**
	 * Vehicles booked for the window: a booking overlaps it, or it reaches a
	 * blocked day. For the search, computed once before its query so totals
	 * and pages count only what is listed.
	 *
	 * @param list<int>|null $among Candidate vehicles; null is every vehicle, an empty list none.
	 * @return list<int>
	 */
	public static function booked_vehicle_ids(int $start, int $end, ?array $among = null): array
	{
		global $wpdb;

		if ($end <= $start) {
			return array();
		}

		if (null === $among) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- FROM_SQL and RULE_SQL are private class constants of this file whose placeholders rule_args() binds in order (the sniff cannot read a constant); every value goes through prepare().
			$booked = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT pm_vid.meta_value ' . self::FROM_SQL . ' WHERE ' . self::RULE_SQL, self::rule_args($start, $end)));
		} else {
			$ids = array_values(array_unique(array_filter(array_map('intval', $among))));
			if (array() === $ids) {
				return array();
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- FROM_SQL and RULE_SQL are private class constants of this file whose placeholders rule_args() binds in order (the sniff cannot read a constant); every value goes through prepare(); the IN list is one %d per id.
			$booked = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT pm_vid.meta_value ' . self::FROM_SQL . ' WHERE ' . self::RULE_SQL . ' AND pm_vid.meta_value IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')', array_merge(self::rule_args($start, $end), $ids)));
		}

		$all = array_merge(array_map('intval', (array) $booked), self::blocked_vehicle_ids($start, $end, $among));
		$all = array_values(array_unique(array_filter($all)));
		sort($all);

		return $all;
	}

	/**
	 * Values for FROM_SQL + RULE_SQL, in placeholder order.
	 *
	 * @return list<int|string>
	 */
	private static function rule_args(int $start, int $end): array
	{
		global $wpdb;

		$buffer  = max(0, (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_booking_buffer_time', '60')) * MINUTE_IN_SECONDS;
		$offset  = SiteTime::offset_string();
		$pickup  = self::time_or_default( (string) apply_filters('mhmrentiva_default_pickup_time', '10:00'));
		$dropoff = self::time_or_default( (string) apply_filters('mhmrentiva_default_dropoff_time', '10:00'));

		return array(
			// FROM_SQL.
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->postmeta,
			$wpdb->postmeta,
			$wpdb->postmeta,
			$wpdb->postmeta,
			$wpdb->postmeta,
			$wpdb->postmeta,
			$wpdb->postmeta,
			$wpdb->postmeta,
			// STATUS_SQL.
			$dropoff,
			$offset,
			time(),
			// PAYMENT_SQL.
			current_time('mysql', true),
			// OVERLAP_SQL.
			$pickup,
			$offset,
			$end + $buffer,
			$dropoff,
			$offset,
			$start - $buffer,
		);
	}

	private static function time_or_default(string $time): string
	{
		return 1 === preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time) ? $time : '10:00';
	}

	/**
	 * Clean a stored value into dates, pass it through the filter, clean the answer.
	 *
	 * @param mixed $raw The stored meta value.
	 * @return list<string>
	 */
	private static function filtered_days($raw, int $vehicle_id): array
	{
		$days = self::clean_days($raw);

		/**
		 * Filters a vehicle's blocked days before any availability check reads them.
		 *
		 * Search, the booking form, the checkout, REST and the operator's
		 * screens all read the list through this filter. A filter may remove
		 * days (the add-on drops expired penalty days) or add them.
		 *
		 * @since 6.4.0
		 *
		 * @param string[] $days       Blocked days, `Y-m-d` in the site's timezone.
		 * @param int      $vehicle_id Vehicle post ID.
		 */
		return self::clean_days(apply_filters('mhmrentiva_vehicle_blocked_days', $days, $vehicle_id));
	}

	/**
	 * @param mixed $raw
	 * @return list<string>
	 */
	private static function clean_days($raw): array
	{
		if (is_string($raw)) {
			$raw = '' === $raw ? array() : json_decode($raw, true);
		}
		if (! is_array($raw)) {
			return array();
		}

		$days = array();
		foreach ($raw as $day) {
			if (is_string($day) && 1 === preg_match(self::DAY_PATTERN, $day)) {
				$days[] = $day;
			}
		}

		$days = array_values(array_unique($days));
		sort($days);

		return $days;
	}

	/**
	 * @param list<string> $days
	 * @return list<string>
	 */
	private static function days_in_window(array $days, int $start, int $end): array
	{
		if (array() === $days || $end <= $start) {
			return array();
		}

		// `Y-m-d` strings order as the dates do.
		$first = SiteTime::local_date($start);
		$last  = SiteTime::local_date($end - 1);

		return array_values(array_filter($days, static fn(string $day): bool => $day >= $first && $day <= $last));
	}

	/**
	 * @return list<int>
	 */
	private static function all_vehicle_ids(): array
	{
		$ids = get_posts(
			array(
				'post_type'        => 'mhmrentiva_vehicle',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		return array_values(array_map('intval', $ids));
	}
}
