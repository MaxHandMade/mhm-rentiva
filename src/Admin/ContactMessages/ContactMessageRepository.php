<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\ContactMessages;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;

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
	 * @param array{status?:string,type?:string,period?:string,search?:string,page?:int,per_page?:int} $args
	 * @return array{items:list<int>,total:int}
	 */
	public static function list(array $args): array
	{
		global $wpdb;

		$status   = (string) ( $args['status'] ?? '' );
		$per_page = max(1, min(100, (int) ( $args['per_page'] ?? 20 )));
		$page     = max(1, (int) ( $args['page'] ?? 1 ));

		$where  = array( 'p.post_type = %s', 'p.post_status = %s' );
		$params = array( self::TYPE, 'trash' === $status ? 'trash' : 'private' );

		if (in_array($status, ContactStatus::ALL, true)) {
			$where[]  = self::STATUS_SQL . ' = %s';
			$params[] = $status;
		}

		$type = (string) ( $args['type'] ?? '' );
		if ('general' === $type) {
			// row() labels a missing/unrecognised type meta "General Contact" too, so
			// the `general` filter has to match both, not just an explicit 'general'.
			$known   = array_values(array_diff(ContactMessagePostType::TYPES, array( 'general' )));
			$in_sql  = implode(',', array_fill(0, count($known), '%s'));
			$where[] = "(ty.meta_value IS NULL OR ty.meta_value NOT IN ({$in_sql}))";
			$params  = array_merge($params, $known);
		} elseif ('' !== $type) {
			$where[]  = 'ty.meta_value = %s';
			$params[] = $type;
		}

		list($from, $to) = self::period_bounds( (string) ( $args['period'] ?? '' ));
		if (null !== $from) {
			$where[]  = 'p.post_date >= %s';
			$params[] = $from;
		}
		if (null !== $to) {
			$where[]  = 'p.post_date < %s';
			$params[] = $to;
		}

		$search = trim( (string) ( $args['search'] ?? '' ));
		if ('' !== $search) {
			$like     = '%' . $wpdb->esc_like($search) . '%';
			$where[]  = '(nm.meta_value LIKE %s OR em.meta_value LIKE %s OR p.post_content LIKE %s OR LOWER(em.meta_value) = %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = strtolower($search);
		}

		$joins     = "LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_mhmrentiva_contact_status'
			LEFT JOIN {$wpdb->postmeta} ty ON ty.post_id = p.ID AND ty.meta_key = '_mhmrentiva_contact_type'
			LEFT JOIN {$wpdb->postmeta} nm ON nm.post_id = p.ID AND nm.meta_key = '_mhmrentiva_contact_name'
			LEFT JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_mhmrentiva_contact_email'";
		$where_sql = implode(' AND ', $where);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $joins/$where_sql are built from literals above, values are placeholders; the sniff cannot statically count the %s/%d it assembles, but $params (bound below via prepare()) matches it exactly.
		$total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$joins} WHERE {$where_sql}", $params));

		$params[] = $per_page;
		$params[] = ( $page - 1 ) * $per_page;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- as above; $params carries every bound value including the LIMIT/OFFSET pair appended just above.
		$ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT p.ID FROM {$wpdb->posts} p {$joins} WHERE {$where_sql} ORDER BY p.post_date DESC, p.ID DESC LIMIT %d OFFSET %d", $params));

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
	 * @return array{0:?string,1:?string} Local-time [from, to) for post_date.
	 */
	public static function period_bounds(string $period): array
	{
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
