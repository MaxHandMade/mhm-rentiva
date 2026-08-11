<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Screen-wide occupancy scan; the transient below IS the caching layer.

/**
 * Raw-data fleet occupancy map: one screen-wide query over an arbitrary
 * [start, end] window, no per-row SQL.
 *
 * Generalizes the closed VehicleColumns::get_week_bookings_map() (window
 * was hard-coded to "today .. today+6", single-window memo) so the 7-day
 * strip and a full month matrix can share one service and one cache without
 * poisoning each other's result in the same request.
 *
 * "Counts as occupied" is defined HERE, once, for every consumer:
 *  - painted statuses: pending, confirmed, in_progress, completed
 *    (meta `_mhmrentiva_status`, legacy OR `_mhmrentiva_booking_status`);
 *  - a `pending` booking whose `_mhmrentiva_payment_deadline` has passed is
 *    NOT occupied — the exemption is part of the definition, not a filter
 *    layered on top afterwards. This deliberately CHANGES the old
 *    week-map's behavior, which carried no exemption at all.
 *
 * @since 6.1.0
 */
final class OccupancyMapService {


	/**
	 * Per-request memo, keyed by "$start|$end". The old week-map's single
	 * `?array` memo assumed one window per request; it would poison mixed
	 * windows (the 7-day strip and a month matrix) in the same request.
	 *
	 * @var array<string, array<int, array<string, list<array{booking_id: int, status: string}>>>>
	 */
	private static array $memo = array();

	/**
	 * Precedence for reduce(): strongest status wins when a cell carries
	 * more than one raw entry. Same map the old week-map used inline.
	 */
	private const PRECEDENCE = array(
		'completed'   => 1,
		'pending'     => 2,
		'confirmed'   => 3,
		'in_progress' => 4,
	);

	/**
	 * Raw occupancy map for the closed window [$start, $end] (both
	 * 'Y-m-d'), one query for every vehicle on the screen.
	 *
	 * NO precedence reduction here — a day can hold several bookings with
	 * different statuses, and every one of them must survive; reduce()
	 * collapses a single cell down to one winning status.
	 *
	 * @return array<int, array<string, list<array{booking_id: int, status: string}>>>
	 */
	public static function get_map( string $start, string $end ): array {
		$memo_key = $start . '|' . $end;
		if ( isset( self::$memo[ $memo_key ] ) ) {
			return self::$memo[ $memo_key ];
		}

		global $wpdb;

		// Short-lived transient: the scan below walks the FULL booking
		// history (postmeta values are unindexed), so it must not run on
		// every list/matrix load. The key sits under the same
		// `mhmrentiva_vehicle_stats_` pattern invalidate() deletes, so
		// booking/vehicle saves invalidate it immediately.
		$cache_key = 'mhmrentiva_vehicle_stats_occmap_' . $start . '_' . $end;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			self::$memo[ $memo_key ] = $cached;
			return $cached;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.ID AS booking_id,
                        COALESCE(NULLIF(pm_v1.meta_value, ''), pm_v2.meta_value) AS vehicle_id,
                        COALESCE(NULLIF(pm_p1.meta_value, ''), pm_p2.meta_value) AS pickup_date,
                        COALESCE(NULLIF(pm_d1.meta_value, ''), pm_d2.meta_value, pm_d3.meta_value) AS dropoff_date,
                        COALESCE(NULLIF(pm_s1.meta_value, ''), pm_s2.meta_value) AS status,
                        pm_deadline.meta_value AS deadline
                FROM {$wpdb->posts} b
                LEFT JOIN {$wpdb->postmeta} pm_s1 ON b.ID = pm_s1.post_id AND pm_s1.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_s2 ON b.ID = pm_s2.post_id AND pm_s2.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_v1 ON b.ID = pm_v1.post_id AND pm_v1.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_v2 ON b.ID = pm_v2.post_id AND pm_v2.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_p1 ON b.ID = pm_p1.post_id AND pm_p1.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_p2 ON b.ID = pm_p2.post_id AND pm_p2.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_d1 ON b.ID = pm_d1.post_id AND pm_d1.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_d2 ON b.ID = pm_d2.post_id AND pm_d2.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_d3 ON b.ID = pm_d3.post_id AND pm_d3.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_deadline ON b.ID = pm_deadline.post_id AND pm_deadline.meta_key = %s
                WHERE b.post_type = %s
                AND b.post_status IN ('publish', 'private', 'pending')
                HAVING vehicle_id IS NOT NULL AND pickup_date IS NOT NULL AND dropoff_date IS NOT NULL
                AND pickup_date <= %s AND dropoff_date >= %s
                AND status IN ('pending', 'confirmed', 'in_progress', 'completed')
                AND (
                    status != 'pending' OR
                    deadline IS NULL OR
                    deadline = '' OR
                    deadline > %s
                )",
				'_mhmrentiva_status',
				'_mhmrentiva_booking_status',
				'_mhmrentiva_vehicle_id',
				'_mhmrentiva_booking_vehicle_id',
				'_mhmrentiva_pickup_date',
				'_mhmrentiva_booking_pickup_date',
				'_mhmrentiva_dropoff_date',
				'_mhmrentiva_return_date',
				'_mhmrentiva_end_date',
				'_mhmrentiva_payment_deadline',
				'mhmrentiva_booking',
				$end,
				$start,
				current_time( 'mysql', true )
			)
		);

		$map      = array();
		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );

		foreach ( (array) $rows as $row ) {
			$vehicle_id = (int) $row->vehicle_id;
			$pickup_ts  = strtotime( (string) $row->pickup_date );
			$dropoff_ts = strtotime( (string) $row->dropoff_date );
			if ( $vehicle_id <= 0 || false === $pickup_ts || false === $dropoff_ts ) {
				continue;
			}

			$from = max( $pickup_ts, $start_ts );
			$to   = min( $dropoff_ts, $end_ts );
			for ( $ts = $from; $ts <= $to; $ts += DAY_IN_SECONDS ) {
				$day                          = gmdate( 'Y-m-d', $ts );
				$map[ $vehicle_id ][ $day ][] = array(
					'booking_id' => (int) $row->booking_id,
					'status'     => (string) $row->status,
				);
			}
		}

		set_transient( $cache_key, $map, 5 * MINUTE_IN_SECONDS );

		self::$memo[ $memo_key ] = $map;
		return $map;
	}

	/**
	 * Collapse one cell's raw entry list to the single strongest status,
	 * applying the completed < pending < confirmed < in_progress
	 * precedence. Returns '' for an empty list.
	 *
	 * @param list<array{booking_id: int, status: string}> $cell_entries
	 */
	public static function reduce( array $cell_entries ): string {
		$winner        = '';
		$winner_weight = 0;
		foreach ( $cell_entries as $entry ) {
			$status = (string) ( $entry['status'] ?? '' );
			$weight = self::PRECEDENCE[ $status ] ?? 0;
			if ( '' === $winner || $weight > $winner_weight ) {
				$winner        = $status;
				$winner_weight = $weight;
			}
		}

		return $winner;
	}

	/**
	 * Prefix-DELETE every vehicle-stats transient (occupancy maps included)
	 * from wp_options. Moved here from
	 * VehicleColumns::clear_vehicle_stats_cache(), which now delegates —
	 * other code still calls that method directly.
	 *
	 * Known limit shared with the rest of the codebase: raw option-table
	 * DELETEs do not reach an external object cache backing transients —
	 * the 5-minute TTL bounds the staleness there.
	 */
	public static function invalidate(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_mhmrentiva_vehicle_stats_%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_mhmrentiva_vehicle_stats_%'
			)
		);
	}

	/**
	 * Test API — clears the static per-request memo so a test observes
	 * fresh state regardless of run order.
	 */
	public static function reset_memo(): void {
		self::$memo = array();
	}
}
