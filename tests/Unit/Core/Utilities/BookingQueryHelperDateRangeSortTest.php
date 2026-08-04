<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;
use WP_UnitTestCase;

/**
 * Görev 14 (T8, SlowDBQuery sweep), row 4: BookingQueryHelper.php:178.
 *
 * findBookingsByDateRange() used to carry BOTH an explicit meta_query BETWEEN
 * clause on '_mhmrentiva_booking_pickup_date' (the actual date-range filter)
 * AND a flat top-level 'meta_key' + 'orderby' => 'meta_value' pair on the SAME
 * key, used only to sort. WP_Query's own WP_Meta_Query::parse_query_vars()
 * turns that flat pair into a second, unnamed clause -- array('key' =>
 * '_mhmrentiva_booking_pickup_date') with no value/compare -- ANDed against
 * the explicit BETWEEN clause. Since every row that satisfies the BETWEEN
 * clause already has that meta key (trivially), the second clause never
 * excludes anything the first didn't already exclude: it was a redundant
 * INNER JOIN, not a second filter.
 *
 * Fix: name the existing BETWEEN clause and point 'orderby' at that name
 * instead of re-deriving a second clause from flat 'meta_key'. Traced through
 * wp-includes/class-wp-meta-query.php: a clause with 'type' => 'DATE' sorts
 * via CAST(alias.meta_value AS DATE) when referenced by name (WP_Query
 * class-wp-query.php:1791-1794), versus a plain string compare on
 * .meta_value when reached through the old unnamed 'meta_value'/primary-key
 * path (class-wp-query.php:1762-1768, since the auto-synthesized clause has
 * no 'type'). For 'Y-m-d'-formatted date strings (the field's documented and
 * only used format) both produce the same relative order -- proven directly
 * below with mixed-boundary, out-of-range, and tie-breaking fixtures, not
 * just asserted.
 *
 * This test was run and PASSED against the pre-change code (git commit prior
 * to this file's own addition) to establish the golden IDs-in/IDs-out
 * baseline, then re-run unchanged after the rewrite -- both runs assert the
 * exact same expected arrays. See task-14-report.md for the before/after
 * transcript.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::findBookingsByDateRange
 */
final class BookingQueryHelperDateRangeSortTest extends WP_UnitTestCase {

	private function make_booking( string $pickup_date, string $status = 'publish' ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => $status,
		) );
		update_post_meta( $id, '_mhmrentiva_booking_pickup_date', $pickup_date );
		return $id;
	}

	/**
	 * Core case: results are filtered to the BETWEEN range (inclusive of both
	 * boundary dates) and returned in ascending pickup-date order -- the same
	 * contract the flat meta_key/orderby=meta_value pair provided.
	 */
	public function test_returns_only_in_range_bookings_sorted_ascending_by_pickup_date(): void {
		$before_range = $this->make_booking( '2026-05-30' );
		$lower_bound  = $this->make_booking( '2026-06-01' );
		$middle       = $this->make_booking( '2026-06-15' );
		$upper_bound  = $this->make_booking( '2026-06-30' );
		$after_range  = $this->make_booking( '2026-07-01' );

		$result = BookingQueryHelper::findBookingsByDateRange( '2026-06-01', '2026-06-30' );

		$this->assertSame(
			array( $lower_bound, $middle, $upper_bound ),
			$result,
			'Must include both BETWEEN boundaries, exclude everything outside, in ascending date order.'
		);
		$this->assertNotContains( $before_range, $result );
		$this->assertNotContains( $after_range, $result );
	}

	/**
	 * Descending insertion order in the fixture must not leak into the
	 * result -- proves the ORDER BY is genuinely driven by the meta value,
	 * not by post ID / insertion order coincidence.
	 */
	public function test_sort_order_is_by_pickup_date_not_by_post_id(): void {
		$last_inserted_earliest_date  = $this->make_booking( '2026-06-05' );
		$first_inserted_latest_date   = $this->make_booking( '2026-06-25' );
		$middle_inserted_middle_date  = $this->make_booking( '2026-06-15' );

		$result = BookingQueryHelper::findBookingsByDateRange( '2026-06-01', '2026-06-30' );

		$this->assertSame(
			array( $last_inserted_earliest_date, $middle_inserted_middle_date, $first_inserted_latest_date ),
			$result
		);
	}

	/**
	 * Statuses filter and the 'ids' fields mode both still work -- proves the
	 * rest of $query_args (untouched by this rewrite) is unaffected.
	 */
	public function test_status_filter_still_applies(): void {
		$published = $this->make_booking( '2026-06-10', 'publish' );
		$draft     = $this->make_booking( '2026-06-11', 'draft' );

		$result = BookingQueryHelper::findBookingsByDateRange( '2026-06-01', '2026-06-30' );

		$this->assertSame( array( $published ), $result );
		$this->assertNotContains( $draft, $result );

		$both = BookingQueryHelper::findBookingsByDateRange( '2026-06-01', '2026-06-30', array( 'publish', 'draft' ) );
		sort( $both );
		$expected = array( $published, $draft );
		sort( $expected );
		$this->assertSame( $expected, $both );
	}

	/**
	 * A booking with NO pickup-date meta at all must never match -- proves
	 * the (now-named) BETWEEN clause is still doing real filtering, not just
	 * establishing an always-true JOIN.
	 */
	public function test_booking_without_pickup_date_meta_is_excluded(): void {
		$no_meta_id = self::factory()->post->create( array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		) );
		$in_range = $this->make_booking( '2026-06-10' );

		$result = BookingQueryHelper::findBookingsByDateRange( '2026-06-01', '2026-06-30' );

		$this->assertSame( array( $in_range ), $result );
		$this->assertNotContains( $no_meta_id, $result );
	}

	/**
	 * Empty start/end date still short-circuits before any query runs.
	 */
	public function test_empty_dates_return_empty_array_without_querying(): void {
		$this->assertSame( array(), BookingQueryHelper::findBookingsByDateRange( '', '2026-06-30' ) );
		$this->assertSame( array(), BookingQueryHelper::findBookingsByDateRange( '2026-06-01', '' ) );
	}
}
