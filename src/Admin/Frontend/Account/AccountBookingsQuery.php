<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the customer's bookings are found by their customer meta; there is no other index.

/**
 * The customer's bookings, by section (PR D, R5 + r2 M1/M8 + r3 Y4).
 *
 * Sections:
 *
 *   cancelled  status cancelled or refunded;
 *   past       not cancelled, and completed, no-show, or its return day is
 *              before today;
 *   upcoming   everything else -- including a booking with no return day.
 *
 * The return day is the first valid `Y-m-d` of RETURN_DAY_CHAIN, then the
 * local day of `_mhmrentiva_end_ts`.
 *
 * One predicate decides the section: AccountBookingsView::segment_of() over
 * return_day(), run in PHP over the customer's booking IDs (one unpaged ID
 * query, metas primed in one go, cached for the request). counts() counts
 * that index and page() pages the same IDs (post__in), so the two always
 * agree. page() also takes its order from the index: Upcoming soonest pickup
 * first, Past and Cancelled latest pickup first, booking ID on a tie, so a row
 * is on exactly one page. An explicit `order` still sorts by created date. The
 * rule is not written as a meta_query: four fallback keys, each
 * "missing or empty", needed ~25 postmeta joins, and WordPress turns every
 * join of a query with a NOT EXISTS clause into a LEFT JOIN whose meta_key
 * test sits in WHERE -- measured 2026-10-27, one such query ran 676 s on a
 * test database before it was killed.
 *
 * This is the one query path of the account bookings list;
 * AccountRenderer::query_user_bookings() is a thin wrapper around query().
 *
 * @since 6.4.0
 */
final class AccountBookingsQuery {

	public const UPCOMING  = 'upcoming';
	public const PAST      = 'past';
	public const CANCELLED = 'cancelled';

	/** @var list<string> */
	public const SEGMENTS = array( self::UPCOMING, self::PAST, self::CANCELLED );

	/** @var list<string> Statuses of the Cancelled section. */
	public const CANCELLED_STATUSES = array( Status::CANCELLED, Status::REFUNDED );

	/** @var list<string> Statuses that are Past whatever the return day. */
	public const PAST_STATUSES = array( Status::COMPLETED, Status::NO_SHOW );

	/**
	 * Date metas that name the return day, in reading order (r2 M1, r3 y7).
	 * After them comes the local day of END_TS_KEY.
	 *
	 * @var list<string>
	 */
	public const RETURN_DAY_CHAIN = array(
		'_mhmrentiva_dropoff_date',
		'_mhmrentiva_booking_dropoff_date',
		'_mhmrentiva_end_date',
	);

	public const END_TS_KEY = '_mhmrentiva_end_ts';

	private const STATUS_KEY   = '_mhmrentiva_status';
	private const CUSTOMER_KEY = '_mhmrentiva_customer_user_id';

	/** @var array<int, array<int, string>> Booking ID => section, per user, this request. */
	private static array $index = array();

	/** @var array<int, array<string, list<int>>> Sorted booking IDs per user and section, this request. */
	private static array $sorted = array();

	/**
	 * Section sizes of the user's bookings (the request's section index).
	 *
	 * @return array{upcoming:int, past:int, cancelled:int}
	 */
	public static function counts(int $user_id): array
	{
		$counts = array(
			self::UPCOMING  => 0,
			self::PAST      => 0,
			self::CANCELLED => 0,
		);
		foreach (self::index($user_id) as $segment) {
			++$counts[ $segment ];
		}

		return $counts;
	}

	/**
	 * The user's booking IDs in a section, in the section's order: Upcoming
	 * soonest pickup first (no recorded pickup last), Past and Cancelled latest
	 * pickup first; booking ID on a tie, so the order never depends on the
	 * database.
	 *
	 * @param string $segment A SEGMENTS value.
	 * @return list<int>
	 */
	public static function segment_ids(int $user_id, string $segment): array
	{
		if (isset(self::$sorted[ $user_id ][ $segment ])) {
			return self::$sorted[ $user_id ][ $segment ];
		}

		$ids     = array_keys(array_filter(self::index($user_id), static fn(string $s): bool => $s === $segment));
		$pickups = array();
		foreach ($ids as $id) {
			$pickups[ $id ] = self::pickup_ts($id);
		}

		$soonest_first = self::UPCOMING === $segment;
		usort(
			$ids,
			static function (int $a, int $b) use ($pickups, $soonest_first): int {
				if ($soonest_first) {
					return array( 0 === $pickups[ $a ], $pickups[ $a ], $a ) <=> array( 0 === $pickups[ $b ], $pickups[ $b ], $b );
				}
				return array( $pickups[ $b ], $b ) <=> array( $pickups[ $a ], $a );
			}
		);

		self::$sorted[ $user_id ][ $segment ] = $ids;
		return $ids;
	}

	/**
	 * One page of a section.
	 *
	 * @param string               $segment  A SEGMENTS value, or '' for no section.
	 * @param array<string, mixed> $legacy   The list's other narrowing: `status`
	 *                                       (the shortcode attribute),
	 *                                       `status_filter`, `search`, `order`.
	 * @return array{posts: list<\WP_Post>, total: int, pages: int}
	 */
	public static function page(int $user_id, string $segment, int $page, int $per_page, array $legacy = array()): array
	{
		$query = self::query(
			$user_id,
			array(
				'segment' => $segment,
				'page'    => $page,
				'limit'   => $per_page,
			) + $legacy
		);

		/** @var list<\WP_Post> $posts */
		$posts = array_values(array_filter($query->posts, static fn($p): bool => $p instanceof \WP_Post));

		return array(
			'posts' => $posts,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * The user's bookings that can be the next rental (r2 M6): every booking in
	 * progress, and every confirmed, pending or awaiting-payment one whose pickup
	 * day is today or later. Unpaged.
	 *
	 * @return list<array{id:int, status:string, pickup_ts:int}>
	 */
	public static function next_rental_candidates(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}

		$today      = SiteTime::today();
		$candidates = array();
		foreach (array_keys(self::index($user_id)) as $id) {
			$status = (string) get_post_meta($id, self::STATUS_KEY, true);
			if (! in_array($status, array( Status::IN_PROGRESS, Status::CONFIRMED, Status::PENDING, Status::PENDING_PAYMENT ), true)) {
				continue;
			}

			$pickup_ts = self::pickup_ts($id);
			if (Status::IN_PROGRESS !== $status && ( 0 === $pickup_ts || SiteTime::local_date($pickup_ts) < $today )) {
				continue;
			}

			$candidates[] = array(
				'id'        => $id,
				'status'    => $status,
				'pickup_ts' => $pickup_ts,
			);
		}

		return $candidates;
	}

	/**
	 * The bookings query (WP_Query) behind page() and
	 * AccountRenderer::query_user_bookings().
	 *
	 * @param array<string, mixed> $args limit, page, orderby, order, status,
	 *                                   status_filter, search, segment. A section
	 *                                   without an `order` keeps its own order
	 *                                   (segment_ids()); otherwise `orderby` in
	 *                                   `order` (newest first when empty), ID on
	 *                                   a tie.
	 */
	public static function query(int $user_id, array $args = array()): \WP_Query
	{
		$args = wp_parse_args(
			$args,
			array(
				'limit'         => 10,
				'status'        => '',
				'orderby'       => 'date',
				'order'         => '',
				'page'          => 1,
				'status_filter' => '',
				'search'        => '',
				'segment'       => '',
			)
		);

		$meta_query = array(
			array(
				'key'     => self::CUSTOMER_KEY,
				'value'   => $user_id,
				'compare' => '=',
			),
		);

		// The shortcode's configured status.
		if (! empty($args['status'])) {
			$meta_query[] = array(
				'key'     => self::STATUS_KEY,
				'value'   => sanitize_text_field( (string) $args['status']),
				'compare' => '=',
			);
		}

		// The list's own (legacy) status filter, on top of the configured one.
		if (! empty($args['status_filter'])) {
			$meta_query[] = array(
				'key'     => self::STATUS_KEY,
				'value'   => sanitize_text_field( (string) $args['status_filter']),
				'compare' => '=',
			);
		}

		$query_args = array(
			'post_type'      => 'mhmrentiva_booking',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $args['limit'],
			'meta_query'     => $meta_query,
			'paged'          => max(1, (int) $args['page']),
		);

		// The section: its IDs from the index (the same predicate as counts()).
		$segment    = (string) $args['segment'];
		$is_section = in_array($segment, self::SEGMENTS, true);
		if ($is_section) {
			$in                     = self::segment_ids($user_id, $segment);
			$query_args['post__in'] = array() !== $in ? $in : array( 0 );
		}

		$order = strtoupper(sanitize_text_field( (string) $args['order']));
		$order = in_array($order, array( 'ASC', 'DESC' ), true) ? $order : '';
		if ($is_section && '' === $order) {
			$query_args['orderby'] = 'post__in';
		} else {
			$order                 = '' !== $order ? $order : 'DESC';
			$query_args['orderby'] = array(
				sanitize_text_field( (string) $args['orderby']) => $order,
				'ID' => $order,
			);
		}

		// Search by reservation number (the legacy filter): a booking whose ID or
		// display number contains the text. The candidates are this customer's
		// already narrowed bookings, so the page count follows the search.
		$search = (string) $args['search'];
		if ('' !== $search) {
			$ids = array_map(
				'intval',
				get_posts(
					array_merge(
						$query_args,
						array(
							'posts_per_page' => -1,
							'paged'          => 1,
							'fields'         => 'ids',
							'no_found_rows'  => true,
						)
					)
				)
			);
			update_meta_cache('post', $ids);
			$matched = array_values(
				array_filter(
					$ids,
					static fn(int $id): bool => false !== strpos( (string) $id, $search)
						|| false !== strpos( (string) mhmrentiva_get_display_id($id), $search)
				)
			);
			// Within the section, when there is one (post__in above).
			$query_args['post__in'] = array() !== $matched ? $matched : array( 0 );
		}

		return new \WP_Query($query_args);
	}

	/**
	 * The booking's return day, local `Y-m-d`, or '' when none is recorded.
	 */
	public static function return_day(int $booking_id): string
	{
		foreach (self::RETURN_DAY_CHAIN as $key) {
			$value = (string) get_post_meta($booking_id, $key, true);
			if (AccountBookingsView::is_day($value)) {
				return substr($value, 0, 10);
			}
		}

		$end_ts = (string) get_post_meta($booking_id, self::END_TS_KEY, true);
		if (1 === preg_match('/^[1-9]\d*$/', $end_ts)) {
			return SiteTime::local_date( (int) $end_ts);
		}

		return '';
	}

	/**
	 * Clears the per-request cache. Tests only.
	 */
	public static function reset(): void
	{
		self::$index  = array();
		self::$sorted = array();
	}

	/**
	 * Booking ID => section for the user; built once per request.
	 *
	 * @return array<int, string>
	 */
	private static function index(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		if (isset(self::$index[ $user_id ])) {
			return self::$index[ $user_id ];
		}

		$today = SiteTime::today();
		$index = array();
		foreach (self::all_ids($user_id) as $id) {
			$index[ $id ] = AccountBookingsView::segment_of(
				(string) get_post_meta($id, self::STATUS_KEY, true),
				self::return_day($id),
				$today
			);
		}

		self::$index[ $user_id ] = $index;
		return $index;
	}

	/**
	 * Every booking ID of the user, metas primed.
	 *
	 * @return list<int>
	 */
	private static function all_ids(int $user_id): array
	{
		$ids = array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'mhmrentiva_booking',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'meta_query'     => array(
						array(
							'key'     => self::CUSTOMER_KEY,
							'value'   => $user_id,
							'compare' => '=',
						),
					),
				)
			)
		);
		update_meta_cache('post', $ids);

		return $ids;
	}

	/**
	 * The pickup instant: `_mhmrentiva_start_ts`, else the pickup date and time.
	 */
	public static function pickup_ts(int $booking_id): int
	{
		$start_ts = (string) get_post_meta($booking_id, '_mhmrentiva_start_ts', true);
		if (1 === preg_match('/^[1-9]\d*$/', $start_ts)) {
			return (int) $start_ts;
		}

		$date = (string) get_post_meta($booking_id, '_mhmrentiva_pickup_date', true);
		if ('' === $date) {
			$date = (string) get_post_meta($booking_id, '_mhmrentiva_booking_pickup_date', true);
		}
		$time = '';
		foreach (array( '_mhmrentiva_start_time', '_mhmrentiva_pickup_time', '_mhmrentiva_booking_pickup_time' ) as $key) {
			$time = (string) get_post_meta($booking_id, $key, true);
			if ('' !== $time) {
				break;
			}
		}

		return (int) ( SiteTime::instant(substr($date, 0, 10), '' !== $time ? $time : '00:00') ?? SiteTime::instant(substr($date, 0, 10)) ?? 0 );
	}
}
