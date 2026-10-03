<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\ContactMessages;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only listing over a private post type: the status has to be read as "new|replied, else read" (a CASE) and the search has to span a meta column and post_content at once; WP_Query can express neither. Every value is bound through $wpdb->prepare().

/**
 * Queries behind the Contact Messages screen. Every query is scoped to the
 * contact post type: `_mhmrentiva_contact_email`/`_name` are booking meta
 * keys too.
 */
final class ContactMessageRepository {

	private const TYPE = 'mhmrentiva_contact';

	/**
	 * SQL for the normalised status of alias `st` (ContactStatus::normalize in SQL).
	 * `postmeta.meta_value` collates case-insensitive and PAD SPACE, so a plain
	 * `IN ('new','replied')` would match `'New'`/`'replied '` too and hand the raw
	 * (unnormalised) value back. `CAST(... AS BINARY)` forces byte comparison so only
	 * the exact literals match, mirroring ContactStatus::normalize()'s strict
	 * in_array() -- the bare `BINARY` operator does the same thing but is deprecated
	 * as of MySQL 8.0.27 (warning 1287).
	 */
	private const STATUS_SQL = "CASE WHEN CAST(st.meta_value AS BINARY) = 'new' THEN 'new' WHEN CAST(st.meta_value AS BINARY) = 'replied' THEN 'replied' ELSE 'read' END";

	/**
	 * The filters below (status/type/period/search) are never conditionally
	 * appended to the query text -- every one of them is always present in
	 * the literal SQL. Status, type and search self-neutralise via `%s = ''`
	 * when the caller didn't ask for them; the period always compares
	 * post_date against real DATETIME bounds (the widest valid range when
	 * unfiltered), because '' against a DATETIME column is an error on
	 * MySQL 8. This is deliberate: an earlier
	 * version built `$where`/`$joins` as PHP arrays/strings and interpolated
	 * them into the SQL text, which is exactly the shape WP.org's Plugin
	 * Check (PluginCheck.Security.DirectDB.UnescapedDBParameter) flags --
	 * correctly, since a tool reading the source alone cannot tell that
	 * `$where_sql` is built only from hardcoded fragments and never from
	 * request input. Writing the whole WHERE clause as one string literal
	 * eliminates the shape itself rather than justifying it away.
	 *
	 * The 'general' branch's NOT IN list is `('booking','support','feedback')`
	 * as a literal, not built from ContactMessagePostType::TYPES at runtime --
	 * see test_non_general_types_match_the_literal_in_list_the_repository_query_hardcodes()
	 * for the guard that fails loudly if TYPES ever changes shape.
	 *
	 * @param array{status?:string,type?:string,period?:string,search?:string,page?:int,per_page?:int} $args
	 * @return array{items:list<int>,total:int}
	 */
	public static function list(array $args): array
	{
		global $wpdb;

		$status_raw = (string) ( $args['status'] ?? '' );
		$per_page   = max(1, min(100, (int) ( $args['per_page'] ?? 20 )));
		$page       = max(1, (int) ( $args['page'] ?? 1 ));

		$post_status = 'trash' === $status_raw ? 'trash' : 'private';
		// Only new|read|replied ever filter rows; anything else (including
		// 'trash', already consumed above) means "no status filter", same as
		// the pre-rewrite `in_array($status, ContactStatus::ALL, true)` gate.
		$status = in_array($status_raw, ContactStatus::ALL, true) ? $status_raw : '';

		$type = (string) ( $args['type'] ?? '' );

		list($from, $to) = self::period_bounds( (string) ( $args['period'] ?? '' ));
		// No period filter -> the widest valid DATETIME range, never ''.
		// MySQL 8 (strict) raises "Incorrect DATETIME value: ''" when an
		// empty string meets post_date, even behind a `'' = '' OR`, and the
		// whole query fails (MariaDB only warns). A real bound also keeps
		// both conditions sargable.
		$from = $from ?? '1000-01-01 00:00:00';
		$to   = $to ?? '9999-12-31 23:59:59';

		$search = trim( (string) ( $args['search'] ?? '' ));
		$like   = '%' . $wpdb->esc_like($search) . '%';

		// Fixed order, one entry per %s/%d in both queries below. A filter
		// that isn't active still contributes its slot(s) -- always '' for
		// its own self-neutralising check(s).
		$params = array(
			self::TYPE,
			$post_status,
			$status,
			$status,
			$type,
			$type,
			$type,
			$type,
			$from,
			$to,
			$search,
			$like,
			$like,
			$like,
			strtolower($search),
		);

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- the sniff counts $params as one replacement because it's a variable, not a literal array() expression; it holds exactly 15, one per %s above, in the fixed order documented on this method's docblock.
		$total = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_mhmrentiva_contact_status'
			LEFT JOIN {$wpdb->postmeta} ty ON ty.post_id = p.ID AND ty.meta_key = '_mhmrentiva_contact_type'
			LEFT JOIN {$wpdb->postmeta} nm ON nm.post_id = p.ID AND nm.meta_key = '_mhmrentiva_contact_name'
			LEFT JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_mhmrentiva_contact_email'
			WHERE p.post_type = %s
			AND p.post_status = %s
			AND (%s = '' OR CASE WHEN CAST(st.meta_value AS BINARY) = 'new' THEN 'new' WHEN CAST(st.meta_value AS BINARY) = 'replied' THEN 'replied' ELSE 'read' END = %s)
			AND (%s = '' OR (%s = 'general' AND (ty.meta_value IS NULL OR ty.meta_value NOT IN ('booking','support','feedback'))) OR (%s <> 'general' AND ty.meta_value = %s))
			AND p.post_date >= %s
			AND p.post_date < %s
			AND (%s = '' OR nm.meta_value LIKE %s OR em.meta_value LIKE %s OR p.post_content LIKE %s OR LOWER(em.meta_value) = %s)",
			$params
		));

		$page_params   = $params;
		$page_params[] = $per_page;
		$page_params[] = ( $page - 1 ) * $per_page;
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- as above; $page_params is $params (15) plus the LIMIT/OFFSET pair appended just above (17 total), one per %s/%d.
		$ids = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_mhmrentiva_contact_status'
			LEFT JOIN {$wpdb->postmeta} ty ON ty.post_id = p.ID AND ty.meta_key = '_mhmrentiva_contact_type'
			LEFT JOIN {$wpdb->postmeta} nm ON nm.post_id = p.ID AND nm.meta_key = '_mhmrentiva_contact_name'
			LEFT JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_mhmrentiva_contact_email'
			WHERE p.post_type = %s
			AND p.post_status = %s
			AND (%s = '' OR CASE WHEN CAST(st.meta_value AS BINARY) = 'new' THEN 'new' WHEN CAST(st.meta_value AS BINARY) = 'replied' THEN 'replied' ELSE 'read' END = %s)
			AND (%s = '' OR (%s = 'general' AND (ty.meta_value IS NULL OR ty.meta_value NOT IN ('booking','support','feedback'))) OR (%s <> 'general' AND ty.meta_value = %s))
			AND p.post_date >= %s
			AND p.post_date < %s
			AND (%s = '' OR nm.meta_value LIKE %s OR em.meta_value LIKE %s OR p.post_content LIKE %s OR LOWER(em.meta_value) = %s)
			ORDER BY p.post_date DESC, p.ID DESC
			LIMIT %d OFFSET %d",
			$page_params
		));

		$ids = array_map('intval', $ids);
		if (array() !== $ids) {
			update_meta_cache('post', $ids);
			_prime_post_caches($ids, false, false);
		}

		return array(
			'items' => $ids,
			'total' => $total,
		);
	}

	/** @return array{all:int,new:int,read:int,replied:int,trash:int} */
	public static function counts(): array
	{
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- self::STATUS_SQL is a fixed CASE expression declared as a private class constant in this file, not user input; it contains no placeholders to bind.
		$rows = $wpdb->get_results($wpdb->prepare(
			'SELECT p.post_status AS ps, ' . self::STATUS_SQL . " AS s, COUNT(DISTINCT p.ID) AS n
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_mhmrentiva_contact_status'
			 WHERE p.post_type = %s AND p.post_status IN ('private','trash')
			 GROUP BY ps, s",
			self::TYPE
		), ARRAY_A);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$out = array(
			'all'     => 0,
			'new'     => 0,
			'read'    => 0,
			'replied' => 0,
			'trash'   => 0,
		);
		foreach ( (array) $rows as $row) {
			$n = (int) $row['n'];
			if ('trash' === $row['ps']) {
				$out['trash'] += $n;
				continue;
			}
			$out['all']       += $n;
			$out[ $row['s'] ] += $n;
		}

		return $out;
	}

	/** @return array{new:int,awaiting:int,last_7_days:int,total:int} */
	public static function stats(): array
	{
		global $wpdb;

		$c    = self::counts();
		$week = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'private' AND post_date >= %s",
			self::TYPE,
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- gmdate() formats this local timestamp to match post_date, which WordPress also stores in local time; both sides of the comparison must use the same clock.
			gmdate('Y-m-d H:i:s', (int) current_time('timestamp') - 7 * DAY_IN_SECONDS)
		));

		return array(
			'new'         => $c['new'],
			'awaiting'    => $c['new'] + $c['read'],
			'last_7_days' => $week,
			'total'       => $c['all'],
		);
	}

	/**
	 * Months that hold at least one live (private) contact message, newest
	 * first -- the option list of the period filter. The post_status
	 * predicate is the same one stats() and other_count() use, so trashed
	 * and draft records never contribute a month. post_date is local time,
	 * like period_bounds().
	 *
	 * @return list<array{value:string,label:string}>
	 */
	public static function months(int $limit = 12): array
	{
		global $wpdb;

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT YEAR(post_date) AS y, MONTH(post_date) AS m FROM {$wpdb->posts}
			 WHERE post_type = %s AND post_status = 'private'
			 GROUP BY YEAR(post_date), MONTH(post_date)
			 ORDER BY y DESC, m DESC
			 LIMIT %d",
			self::TYPE,
			max(1, $limit)
		), ARRAY_A);

		$out = array();
		foreach ((array) $rows as $row) {
			$year  = (int) $row['y'];
			$month = (int) $row['m'];
			if ($year < 1) {
				continue;
			}
			$out[] = array(
				'value' => sprintf('%04d-%02d', $year, $month),
				// Noon UTC on the 1st stays inside the month in every time zone.
				'label' => wp_date('F Y', (int) gmmktime(12, 0, 0, $month, 1, $year)),
			);
		}

		return $out;
	}

	public static function other_count(string $email, int $exclude_id): int
	{
		global $wpdb;

		if ('' === $email) {
			return 0;
		}

		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_mhmrentiva_contact_email'
			 WHERE p.post_type = %s AND p.post_status = 'private' AND p.ID <> %d AND LOWER(em.meta_value) = %s",
			self::TYPE,
			$exclude_id,
			strtolower($email)
		));
	}

	public static function new_count(): int
	{
		$cached = get_transient(ContactStatus::BADGE_TRANSIENT);
		if (false !== $cached) {
			return (int) $cached;
		}
		$n = self::counts()['new'];
		set_transient(ContactStatus::BADGE_TRANSIENT, $n, 5 * MINUTE_IN_SECONDS);

		return $n;
	}

	/**
	 * The one definition of an acceptable `period` (REST validation and
	 * period_bounds() both call it). Years stay inside 1000-9998: DATETIME's
	 * supported range starts at 1000, and December 9999's exclusive upper
	 * bound would be 10000-01-01, which MySQL 8 rejects outright.
	 */
	public static function is_valid_period(string $period): bool
	{
		if (in_array($period, array( '', '7d', '30d' ), true)) {
			return true;
		}
		if (1 !== preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $m)) {
			return false;
		}
		$year = (int) $m[1];

		return $year >= 1000 && $year <= 9998;
	}

	/**
	 * @return array{0:?string,1:?string} Local-time [from, to) for post_date.
	 */
	public static function period_bounds(string $period): array
	{
		if ('' === $period || ! self::is_valid_period($period)) {
			return array( null, null );
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- gmdate() below formats this local timestamp to match post_date, which WordPress also stores in local time; both sides of every comparison must use the same clock.
		$now = (int) current_time('timestamp');
		if ('7d' === $period) {
			return array( gmdate('Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS), null );
		}
		if ('30d' === $period) {
			return array( gmdate('Y-m-d H:i:s', $now - 30 * DAY_IN_SECONDS), null );
		}
		if (1 === preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $m)) {
			$from = sprintf('%s-%s-01 00:00:00', $m[1], $m[2]);
			$to   = gmdate('Y-m-d H:i:s', (int) strtotime($from . ' +1 month'));
			return array( $from, $to );
		}
		return array( null, null );
	}
}
