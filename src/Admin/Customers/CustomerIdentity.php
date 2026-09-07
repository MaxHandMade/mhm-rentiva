<?php
/**
 * Who counts as a customer of this plugin.
 *
 * @package MHM_Rentiva
 */

declare( strict_types=1 );

namespace MHMRentiva\Admin\Customers;

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * The single place that answers "is this WordPress user a Rentiva customer?".
 *
 * This exists because the plugin did not have an answer. The Customers screen is
 * fed by a query that starts FROM wp_users and LEFT JOINs the bookings, so the
 * joins narrow nothing: its only real filter is `u.ID > 1 AND u.user_login !=
 * 'admin'`. Everything else on the site -- editors, a second administrator whose
 * login happens not to be 'admin' -- is listed there as a customer, and the REST
 * endpoints behind that screen inherited the same reach. WordPress.org's T8
 * review found the consequence: /customers/bulk would delete any account handed
 * to it.
 *
 * A guard built by mirroring that list would therefore have admitted exactly the
 * accounts the review is about, which is why this is a definition rather than a
 * reuse. It is the union of the two ways a customer comes into existence here:
 *
 *   1. A booking points at the account -- either `_mhmrentiva_customer_user_id`
 *      holding its ID, or `_mhmrentiva_customer_email` holding its email.
 *   2. Rentiva created or managed the account and left its own user meta behind:
 *      AddCustomerPage and BookingPortalMetaBox both write `mhmrentiva_phone` /
 *      `mhmrentiva_address`.
 *
 * The second arm is load-bearing, not a convenience. An admin who adds a
 * customer through Rentiva's own "Add Customer" screen creates an account with
 * no bookings yet; a bookings-only guard would make that customer permanently
 * undeletable through the screen that created them.
 *
 * Callers still have to ask WordPress whether the current user may act on the
 * target (`delete_user` / `edit_user`). This answers a different question --
 * whether the target is ours to act on at all -- and neither check substitutes
 * for the other.
 */
final class CustomerIdentity {

	/**
	 * User meta keys this plugin writes on accounts it creates or manages.
	 *
	 * @var string[]
	 */
	private const OWNED_USER_META = array(
		'mhmrentiva_phone',
		'mhmrentiva_address',
	);

	/**
	 * Capabilities that disqualify a role from being handed to a customer.
	 *
	 * A deny list rather than "may hold nothing but read": a site is free to
	 * give its customer role something harmless (an upload, a shop capability
	 * a theme needs) and must keep working. What may not pass is anything that
	 * administers the site, its users, or its content.
	 *
	 * @var string[]
	 */
	private const PRIVILEGED_CAPABILITIES = array(
		'manage_options',
		'promote_users',
		'edit_users',
		'delete_users',
		'create_users',
		'activate_plugins',
		'edit_posts',
	);

	/**
	 * Memo for the current request.
	 *
	 * The bulk-delete route asks once per target in a loop, so without this a
	 * fifty-row bulk action runs fifty queries to answer a question whose answer
	 * cannot change inside one request.
	 *
	 * @var array<int, bool>
	 */
	private static array $memo = array();

	/**
	 * The role this plugin gives the accounts it creates.
	 *
	 * `mhmrentiva_customer_default_role` has no settings screen, no entry in
	 * the defaults map and no sanitiser, so until this method existed nothing
	 * between the stored value and a new account ever inspected it. The two
	 * screens that create accounts checked only that `get_role()` returned
	 * something -- which 'administrator' does.
	 *
	 * Resolving it here rather than in either screen is deliberate: the same
	 * option also answers "is this account a customer?" in this class, once in
	 * PHP and once in SQL. A privileged value there does not merely mint
	 * privileged accounts, it makes the Customers list -- and the delete guard
	 * built on it -- treat every administrator as a customer. One reader for
	 * one invariant.
	 *
	 * @return string A role that exists and holds no privileged capability.
	 */
	public static function default_customer_role(): string {
		$configured = (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
			'mhmrentiva_customer_default_role',
			'customer'
		);

		if ( '' === $configured || 'customer' === $configured ) {
			return 'customer';
		}

		$role = get_role( $configured );
		if ( ! $role instanceof \WP_Role ) {
			return 'customer';
		}

		foreach ( self::PRIVILEGED_CAPABILITIES as $capability ) {
			if ( ! empty( $role->capabilities[ $capability ] ) ) {
				return 'customer';
			}
		}

		return $configured;
	}

	/**
	 * Is this user a customer of this plugin?
	 *
	 * @param int $user_id Candidate user ID.
	 * @return bool
	 */
	public static function is_customer( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( isset( self::$memo[ $user_id ] ) ) {
			return self::$memo[ $user_id ];
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			self::$memo[ $user_id ] = false;

			return false;
		}

		$answer = self::wears_customer_role( $user )
			|| self::has_owned_user_meta( $user_id )
			|| self::has_booking( $user_id, (string) $user->user_email );

		self::$memo[ $user_id ] = $answer;

		return $answer;
	}

	/**
	 * The one rule: this booking belongs to this account.
	 *
	 * Three surfaces below are this pair with a different right-hand side, so
	 * "belongs to" is defined once. Both meta keys are BOUND rather than
	 * written into the string: an entirely literal fragment would make
	 * $wpdb->prepare() raise _doing_it_wrong ("must have a placeholder"),
	 * which the test suite turns into a failure.
	 *
	 * The user-id branch carries the same style of guard as the email branch
	 * (`%d <> 0` beside `%s <> ''`): `%d` binds a value, it does not change the
	 * comparison's type, so `bmeta.meta_value = 0` against a `longtext` column
	 * casts every non-numeric string in that meta key to 0 and matches it. A
	 * caller that has no user id to bind -- get_recent_bookings() defaults to
	 * 0 -- must not have that default silently match unrelated rows.
	 *
	 * @param bool   $correlated True to compare against the `u` alias, false to bind values.
	 * @param int    $user_id    Bound mode only; 0 drops the user-id branch.
	 * @param string $email      Bound mode only; '' drops the email branch.
	 * @return string SQL predicate over the `bmeta` alias.
	 */
	private static function booking_link_pair( bool $correlated, int $user_id = 0, string $email = '' ): string {
		global $wpdb;

		if ( $correlated ) {
			return $wpdb->prepare(
				"( ( bmeta.meta_key = %s AND bmeta.meta_value = u.ID )
				   OR ( bmeta.meta_key = %s AND u.user_email <> '' AND bmeta.meta_value = u.user_email ) )",
				MetaKeys::BOOKING_CUSTOMER_USER_ID,
				MetaKeys::BOOKING_CUSTOMER_EMAIL
			);
		}

		return $wpdb->prepare(
			"( ( bmeta.meta_key = %s AND %d <> 0 AND bmeta.meta_value = %d )
			   OR ( bmeta.meta_key = %s AND %s <> '' AND bmeta.meta_value = %s ) )",
			MetaKeys::BOOKING_CUSTOMER_USER_ID,
			$user_id,
			$user_id,
			MetaKeys::BOOKING_CUSTOMER_EMAIL,
			$email,
			$email
		);
	}

	/**
	 * EXISTS predicate for a JOIN's ON clause, correlated to `u` and `p`.
	 *
	 * EXISTS is a predicate, not a join: a booking carrying BOTH links still
	 * matches once, so SUM() over the joined rows cannot double.
	 *
	 * @return string
	 */
	public static function sql_user_owns_booking(): string {
		global $wpdb;

		return "EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} bmeta
			WHERE bmeta.post_id = p.ID
				AND " . self::booking_link_pair( true ) . '
		)';
	}

	/**
	 * The same predicate with the account's values bound, for statements that
	 * have no `users` table to correlate to.
	 *
	 * @param int    $user_id Account ID, 0 drops the user-id branch.
	 * @param string $email   Account e-mail, '' when it has none.
	 * @return string
	 */
	public static function sql_booking_owned_by( int $user_id, string $email ): string {
		global $wpdb;

		return "EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} bmeta
			WHERE bmeta.post_id = p.ID
				AND " . self::booking_link_pair( false, $user_id, $email ) . '
		)';
	}

	/**
	 * The same rule as a WP_Query meta_query, for callers that filter through
	 * WP_Query rather than composing SQL.
	 *
	 * @param int    $user_id Account ID.
	 * @param string $email   Account e-mail, '' when it has none.
	 * @return array<int|string, array<string, string>|string> 'relation' holds a
	 *         string ('OR'); the numeric keys each hold a {key, value} clause.
	 */
	public static function meta_query_owned_by( int $user_id, string $email ): array {
		$clauses = array(
			'relation' => 'OR',
			array(
				'key'   => MetaKeys::BOOKING_CUSTOMER_USER_ID,
				'value' => (string) $user_id,
			),
		);

		if ( '' !== $email ) {
			$clauses[] = array(
				'key'   => MetaKeys::BOOKING_CUSTOMER_EMAIL,
				'value' => $email,
			);
		}

		return $clauses;
	}

	/**
	 * The same question as is_customer(), expressed as a SQL condition.
	 *
	 * The Customers list needs this in SQL rather than in PHP: it paginates with
	 * LIMIT/OFFSET and reports a total, and filtering rows after the query would
	 * make both wrong. The three criteria are the ones is_customer() applies --
	 * a booking points at the account, this plugin wrote user meta on it, or it
	 * wears the customer role -- kept here beside the PHP version so the two
	 * cannot drift apart unnoticed; CustomersListIsCustomersOnlyTest exercises
	 * this path and CustomerIdentityTest the other.
	 *
	 * Returns a parenthesised boolean expression referring to the users table
	 * under the alias `u`, which the one caller uses. Every dynamic value is a
	 * bound parameter -- the first EXISTS block's predicate comes from
	 * booking_link_pair(), which runs its own prepare() and returns an
	 * already-bound fragment, which is why splicing it in needs a scoped
	 * phpcs suppression (see below) rather than a placeholder.
	 *
	 * @return string
	 */
	public static function sql_is_customer(): string {
		global $wpdb;

		$configured = self::default_customer_role();

		$roles = array_values( array_unique( array_filter( array( $configured, 'customer' ) ) ) );
		if ( array() === $roles ) {
			$roles = array( 'customer' );
		}

		// Two bound slots always. When only one role is configured the same value
		// is bound twice, which is harmless and keeps the statement shape fixed --
		// a variable number of placeholders would mean interpolating SQL.
		$role_like_a = '%' . $wpdb->esc_like( '"' . $roles[0] . '"' ) . '%';
		$role_like_b = '%' . $wpdb->esc_like( '"' . ( $roles[1] ?? $roles[0] ) . '"' ) . '%';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- booking_link_pair() runs its
		// own $wpdb->prepare() and returns a fully bound predicate; WordPress provides no
		// placeholder for splicing a composed SQL fragment into another statement, so the
		// composition itself is what the sniff sees. Scoped to this statement and
		// re-enabled immediately after, same pattern as CustomersOptimizer::get_customers_optimized().
		return $wpdb->prepare(
			"(
			EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} bmeta
				INNER JOIN {$wpdb->posts} bpost
					ON bpost.ID = bmeta.post_id
					AND bpost.post_type = 'mhmrentiva_booking'
					AND bpost.post_status <> 'trash'
				WHERE " . self::booking_link_pair( true ) . "
			)
			OR EXISTS (
				SELECT 1 FROM {$wpdb->usermeta} ometa
				WHERE ometa.user_id = u.ID
					AND ometa.meta_key IN (%s, %s)
					AND ometa.meta_value <> ''
			)
			OR EXISTS (
				SELECT 1 FROM {$wpdb->usermeta} caps
				WHERE caps.user_id = u.ID
					AND caps.meta_key = %s
					AND ( caps.meta_value LIKE %s OR caps.meta_value LIKE %s )
			)
		)",
			self::OWNED_USER_META[0],
			self::OWNED_USER_META[1],
			$wpdb->get_blog_prefix() . 'capabilities',
			$role_like_a,
			$role_like_b
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Forget everything learned this request.
	 *
	 * Only the tests need this: within a request an account does not stop being
	 * a customer, but a test creates several in a row under the same PHP process.
	 *
	 * @return void
	 */
	public static function flush_memo(): void {
		self::$memo = array();
	}

	/**
	 * Does the account wear the role this plugin gives its customers?
	 *
	 * AddCustomerPage assigns `mhmrentiva_customer_default_role` (falling back to
	 * 'customer') to every account it creates, so the role is a marker this
	 * plugin writes, not an assumption about the site's role setup. It is also
	 * the marker three separate test files have encoded since earlier review
	 * rounds -- they all build their target with role 'customer' -- which is what
	 * caught the first version of this guard: built on bookings and user meta
	 * alone, it refused to delete a freshly created customer who had not booked
	 * yet.
	 *
	 * 'subscriber' is deliberately absent even though BookingPortalMetaBox falls
	 * back to it when WooCommerce's 'customer' role is missing: that path always
	 * writes `_mhmrentiva_customer_user_id` on the booking as well, so the first
	 * arm already covers it, and admitting a whole stock WordPress role here
	 * would widen the guard for nothing.
	 *
	 * @param \WP_User $user The account.
	 * @return bool
	 */
	private static function wears_customer_role( \WP_User $user ): bool {
		$configured = self::default_customer_role();

		$customer_roles = array_unique(
			array_filter( array( $configured, 'customer' ) )
		);

		return (bool) array_intersect( $customer_roles, (array) $user->roles );
	}

	/**
	 * Does the account carry user meta this plugin writes?
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function has_owned_user_meta( int $user_id ): bool {
		foreach ( self::OWNED_USER_META as $key ) {
			if ( '' !== (string) get_user_meta( $user_id, $key, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Does any live booking point at this account?
	 *
	 * `post_status => 'any'` is what excludes the trash here: WP_Query's 'any'
	 * drops every status flagged exclude_from_search, which is trash and
	 * auto-draft. A binned booking should not keep an account alive.
	 *
	 * This is a secondary query, so the plugin's own pre_get_posts handlers
	 * (BookingColumns::apply_sorting and its two siblings) leave it alone -- all
	 * three return early on `! $q->is_main_query()`. That was checked rather than
	 * assumed, because a filter widening a security guard's query is exactly the
	 * failure this guard exists to prevent.
	 *
	 * @param int    $user_id User ID.
	 * @param string $email   The account's email, '' if it somehow has none.
	 * @return bool
	 */
	private static function has_booking( int $user_id, string $email ): bool {
		$meta_query = self::meta_query_owned_by( $user_id, $email );

		$found = new \WP_Query(
			array(
				'post_type'              => 'mhmrentiva_booking',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Membership test for a security guard; bounded to one row and memoised per request.
			)
		);

		return ! empty( $found->posts );
	}
}
