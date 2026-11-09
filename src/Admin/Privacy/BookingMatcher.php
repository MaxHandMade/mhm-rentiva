<?php
/**
 * The bookings a privacy request's e-mail address names.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One matcher for the booking exporter and eraser (5a spec T2, T4).
 *
 * A booking belongs to the person when its customer user ID is the account
 * that carries the e-mail (only for a real account: guests store 0, and a
 * "user ID = 0" branch would match every other guest), or when one of its
 * e-mail keys holds the address -- compared lower-cased and trimmed.
 * `post_author` is not used: on a manual booking it is the operator.
 *
 * Every scope is ONE bounded SQL statement: the status filter is in SQL
 * (BookingState::sql_closed), so a long run of active bookings cannot make a
 * queue call read more than its LIMIT.
 */
final class BookingMatcher {

	/**
	 * The e-mail keys a booking may carry: the current one and two legacy
	 * spellings with no writer left but readers still in place.
	 */
	private const EMAIL_KEYS = array(
		'_mhmrentiva_customer_email',
		'_mhmrentiva_booking_customer_email',
		'_mhmrentiva_contact_email',
	);

	private const SCOPES = array( 'all', 'queue', 'active', 'recently_marked' );

	public const SUBJECT_KEY = '_mhmrentiva_privacy_subject';

	public const ERASED_KEY = '_mhmrentiva_personal_data_erased';

	/**
	 * Matching booking IDs, ascending.
	 *
	 * @param string $email  Requested address (raw).
	 * @param string $scope  'all' | 'queue' (closed, not recently marked) | 'active' | 'recently_marked'.
	 * @param int    $limit  Max rows.
	 * @param int    $offset Rows to skip (over the distinct, filtered IDs).
	 * @return int[]
	 */
	public static function ids( string $email, string $scope, int $limit, int $offset = 0 ): array {
		global $wpdb;

		$where = self::where( $email, $scope );
		if ( null === $where ) {
			return array();
		}

		$sql = $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p WHERE {$where} ORDER BY p.ID ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared in where().
			max( 0, $limit ),
			max( 0, $offset )
		);

		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- prepared above; a privacy query must not be served from a cache.
	}

	/**
	 * Number of matching bookings in a scope.
	 *
	 * @param string $email Requested address (raw).
	 * @param string $scope See ids().
	 * @return int
	 */
	public static function count( string $email, string $scope ): int {
		global $wpdb;

		$where = self::where( $email, $scope );
		if ( null === $where ) {
			return 0;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- $where is prepared in where().
	}

	/**
	 * Whether any of the address's bookings is tied to a WooCommerce order
	 * (the booking's own order ID, or an order line carrying its ID).
	 *
	 * @param string $email Requested address (raw).
	 * @return bool
	 */
	public static function any_linked_order( string $email ): bool {
		global $wpdb;

		$where = self::where( $email, 'all' );
		if ( null === $where ) {
			return false;
		}

		$items = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$line  = $items === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $items ) ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			? " OR EXISTS ( SELECT 1 FROM {$items} oi WHERE oi.meta_key = '_mhmrentiva_booking_id' AND oi.meta_value = CAST( p.ID AS CHAR ) )"
			: '';

		// The order-ID keys come from the one resolver, not a private copy.
		$keys = "'" . implode( "', '", array_map( 'esc_sql', \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::wc_order_id_keys() ) ) . "'";
		$sql  = "SELECT 1 FROM {$wpdb->posts} p WHERE {$where} AND ( EXISTS ( SELECT 1 FROM {$wpdb->postmeta} mo WHERE mo.post_id = p.ID AND mo.meta_key IN ( {$keys} ) AND CAST( mo.meta_value AS SIGNED ) > 0 ){$line} ) LIMIT 1";

		return '1' === (string) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- $where is prepared in where(); the rest is constants.
	}

	/**
	 * Whether one booking still belongs to the address.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $email      Requested address (raw).
	 * @return bool
	 */
	public static function matches( int $booking_id, string $email ): bool {
		global $wpdb;

		$where = self::where( $email, 'all' );
		if ( null === $where ) {
			return false;
		}

		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.ID = %d AND {$where}", $booking_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared in where().

		return '1' === (string) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- prepared above.
	}

	/**
	 * Prepared WHERE clause on posts alias `p`, or null when no query may run.
	 *
	 * @param string $email Raw address.
	 * @param string $scope Scope name.
	 * @return string|null
	 */
	private static function where( string $email, string $scope ): ?string {
		global $wpdb;

		$email = EmailMatch::normalise( $email );
		if ( '' === $email || ! in_array( $scope, self::SCOPES, true ) ) {
			return null;
		}

		$owner_sql  = array();
		$owner_args = array();

		$account = get_user_by( 'email', $email );
		if ( $account instanceof \WP_User && $account->ID > 0 ) {
			$owner_sql[]  = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} mu WHERE mu.post_id = p.ID AND mu.meta_key = '_mhmrentiva_customer_user_id' AND CAST( mu.meta_value AS SIGNED ) = %d )";
			$owner_args[] = (int) $account->ID;
		}

		$keys         = "'" . implode( "', '", self::EMAIL_KEYS ) . "'";
		$owner_sql[]  = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} me WHERE me.post_id = p.ID AND me.meta_key IN ( {$keys} ) AND LOWER( TRIM( me.meta_value ) ) = %s )";
		$owner_args[] = $email;

		// A booking whose identity was half-anonymised still carries the
		// request's keyed hash (BookingAnonymizer), so it stays findable.
		$owner_sql[]  = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ms WHERE ms.post_id = p.ID AND ms.meta_key = '" . self::SUBJECT_KEY . "' AND BINARY ms.meta_value = %s )";
		$owner_args[] = EmailMatch::subject_hash( $email );

		$where = $wpdb->prepare(
			"p.post_type = 'mhmrentiva_booking' AND p.post_status NOT IN ( 'trash', 'auto-draft' ) AND ( " . implode( ' OR ', $owner_sql ) . ' )', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders are in $owner_sql.
			$owner_args
		);

		switch ( $scope ) {
			case 'queue':
				// An erased booking leaves the queue by its marker, not by its
				// e-mail changing: core's anonymous address is the fixed
				// 'deleted@site.invalid', so a request for that address would
				// otherwise get the same fifty bookings back on every page.
				$where .= ' AND ' . BookingState::sql_closed( 'p' ) . ' AND ' . ErasureMarker::sql_not_recently_marked( 'p' )
					. " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} mx WHERE mx.post_id = p.ID AND mx.meta_key = '" . self::ERASED_KEY . "' )";
				break;
			case 'active':
				$where .= ' AND NOT ' . BookingState::sql_closed( 'p' );
				break;
			case 'recently_marked':
				$where .= ' AND ' . ErasureMarker::sql_recently_marked( 'p' );
				break;
		}

		return $where;
	}
}
