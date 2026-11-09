<?php
/**
 * Bounded ID queries over Rentiva's two log post types.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every match is exact and in SQL -- no candidate is fetched and then rejected
 * in PHP (spec r5 #7): a rejected candidate would neither be deleted nor
 * marked, and the next page would fetch it again. A log that carries the
 * person's address as a whole JSON string, or their account ID as a JSON
 * field, holds their data, so deleting it is right.
 */
final class LogQuery {

	/**
	 * Matching log IDs.
	 *
	 * @param string      $post_type  'mhmrentiva_email_log' or 'mhmrentiva_app_log'.
	 * @param string      $email      Requested address.
	 * @param bool|string $queue      false: all; true: not recently marked; 'marked': recently marked.
	 * @param int         $limit      Max rows.
	 * @param int         $offset     Offset.
	 * @return int[]
	 */
	public static function ids( string $post_type, string $email, $queue, int $limit, int $offset = 0 ): array {
		global $wpdb;

		$where = self::where( $post_type, $email );
		if ( null === $where ) {
			return array();
		}
		if ( true === $queue ) {
			$where .= ' AND ' . ErasureMarker::sql_not_recently_marked( 'p' );
		} elseif ( 'marked' === $queue ) {
			$where .= ' AND ' . ErasureMarker::sql_recently_marked( 'p' );
		}

		$sql = $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE {$where} ORDER BY p.ID ASC LIMIT %d OFFSET %d", $limit, $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared in where().

		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- prepared above.
	}

	/**
	 * Prepared WHERE on alias p, or null when no query may run.
	 *
	 * @param string $post_type Log post type.
	 * @param string $email     Requested address.
	 * @return string|null
	 */
	private static function where( string $post_type, string $email ): ?string {
		global $wpdb;

		$email  = EmailMatch::normalise( $email );
		$needle = EmailMatch::json_like_needle( $email );
		if ( '' === $email || '' === $needle ) {
			return null;
		}

		$or   = array();
		$args = array();

		if ( 'mhmrentiva_email_log' === $post_type ) {
			$or[]   = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} lt WHERE lt.post_id = p.ID AND lt.meta_key = '_mhmrentiva_email_to' AND LOWER( TRIM( lt.meta_value ) ) = %s )";
			$args[] = $email;
			$or[]   = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} lc WHERE lc.post_id = p.ID AND lc.meta_key = '_mhmrentiva_email_context' AND LOWER( lc.meta_value ) LIKE %s )";
			$args[] = $needle;
		} else {
			$or[]   = 'LOWER( p.post_content ) LIKE %s';
			$args[] = $needle;

			$account = get_user_by( 'email', $email );
			if ( $account instanceof \WP_User && $account->ID > 0 ) {
				$uid    = (int) $account->ID;
				$or[]   = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} lu WHERE lu.post_id = p.ID AND lu.meta_key IN ( '_mhmrentiva_log_user_id', '_mhmrentiva_log_customer_id' ) AND CAST( lu.meta_value AS SIGNED ) = %d )";
				$args[] = $uid;
				// The context is JSON_PRETTY_PRINT: `"user_id": 17,` mid-object
				// or `"user_id": 17` + newline as the last member. Both
				// terminators keep 170 from matching 17.
				foreach ( array( 'user_id', 'customer_id' ) as $field ) {
					foreach ( array( ',', "\n" ) as $end ) {
						$or[]   = 'p.post_content LIKE %s';
						$args[] = '%' . $wpdb->esc_like( '"' . $field . '": ' . $uid . $end ) . '%';
					}
				}
			}
		}

		return $wpdb->prepare( 'p.post_type = %s AND ( ' . implode( ' OR ', $or ) . ' )', array_merge( array( $post_type ), $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders are in $or.
	}
}
